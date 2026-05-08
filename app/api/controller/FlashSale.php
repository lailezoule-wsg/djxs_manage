<?php
declare (strict_types = 1);

namespace app\api\controller;

use app\api\service\FlashSaleService;
use app\common\service\FlashSaleRealtimeService;
use app\common\controller\BaseApiController;
use think\App;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;

/**
 * 用户端秒杀活动接口
 */
class FlashSale extends BaseApiController
{
    protected FlashSaleService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new FlashSaleService();
    }

    /**
     * 查询可参与秒杀活动列表
     */
    public function list()
    {
        try {
            $result = $this->service->list($this->request->get());
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 获取秒杀活动详情
     */
    public function detail(int $activityId)
    {
        try {
            $result = $this->service->detail($activityId);
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 领取秒杀下单令牌
     */
    public function token()
    {
        try {
            $userId = $this->getUserId();
            if ($userId instanceof \think\Response) {
                return $userId;
            }
            $data = $this->request->post();
            $result = $this->service->issueToken(
                (int)$userId,
                (int)($data['activity_id'] ?? 0),
                (int)($data['item_id'] ?? 0),
                (string)$this->request->ip(),
                trim((string)($this->request->header('x-device-id') ?: $this->request->header('x-device')))
            );
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 创建秒杀订单
     */
    public function createOrder()
    {
        $startTime = microtime(true);
        $status = 'failed';
        $errorType = null;
        $activityId = (int)($this->request->post('activity_id') ?? 0);
        $itemId = (int)($this->request->post('item_id') ?? 0);

        // 初始化 Prometheus 指标
        $registry = new CollectorRegistry(new InMemory());
        $requestCounter = $registry->registerCounter(
            'flash_sale',
            'order_requests_total',
            '秒杀下单请求总数',
            ['activity_id', 'item_id']
        );
        $orderCounter = $registry->registerCounter(
            'flash_sale',
            'order_create_total',
            '秒杀下单总数',
            ['status', 'activity_id', 'item_id']
        );
        $errorCounter = $registry->registerCounter(
            'flash_sale',
            'order_errors_total',
            '秒杀下单错误总数',
            ['error_type', 'activity_id', 'item_id']
        );
        $latencyHistogram = $registry->registerHistogram(
            'flash_sale',
            'order_create_duration_seconds',
            '秒杀下单响应时间',
            ['activity_id', 'item_id'],
            [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5]
        );

        try {
            // 记录请求开始
            $requestCounter->inc([(string)$activityId, (string)$itemId]);

            $userId = $this->getUserId();
            if ($userId instanceof \think\Response) {
                $status = 'auth_failed';
                $errorType = 'authentication_error';
                return $userId;
            }
            $payload = $this->request->post();
            $payload['client_ip'] = (string)$this->request->ip();
            $payload['device_id'] = trim((string)($this->request->header('x-device-id') ?: $this->request->header('x-device')));
            $result = $this->service->createOrder((int)$userId, $payload);

            // 下单成功
            $status = 'success';
            $orderCounter->inc([$status, (string)$activityId, (string)$itemId]);

            return $this->success($result, '下单成功');
        } catch (\Throwable $e) {
            // 记录错误类型
            $errorMessage = $e->getMessage();
            if (str_contains($errorMessage, '库存') || str_contains($errorMessage, 'Stock')) {
                $status = 'stock_out';
                $errorType = 'stock_insufficient';
            } elseif (str_contains($errorMessage, '令牌') || str_contains($errorMessage, 'token')) {
                $status = 'token_failed';
                $errorType = 'token_error';
            } elseif (str_contains($errorMessage, '重复') || str_contains($errorMessage, 'duplicate')) {
                $status = 'duplicate';
                $errorType = 'duplicate_order';
            } else {
                $errorType = 'unknown';
            }

            $orderCounter->inc([$status, (string)$activityId, (string)$itemId]);
            $errorCounter->inc([$errorType, (string)$activityId, (string)$itemId]);

            return $this->failByException($e);
        } finally {
            // 记录响应时间
            $duration = microtime(true) - $startTime;
            $latencyHistogram->observe($duration, [(string)$activityId, (string)$itemId]);
        }
    }

    /**
     * 秒杀下单前置校验
     */
    public function orderPrecheck()
    {
        try {
            $userId = $this->getUserId();
            if ($userId instanceof \think\Response) {
                return $userId;
            }
            $payload = $this->request->post();
            $payload['client_ip'] = (string)$this->request->ip();
            $payload['device_id'] = trim((string)($this->request->header('x-device-id') ?: $this->request->header('x-device')));
            $result = $this->service->precheck((int)$userId, $payload);
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 查询秒杀请求结果
     */
    public function orderResult(string $requestId)
    {
        try {
            $userId = $this->getUserId();
            if ($userId instanceof \think\Response) {
                return $userId;
            }
            $result = $this->service->result((int)$userId, $requestId);
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 拉取秒杀实时事件流（SSE）
     */
    public function stream()
    {
        $timeout = max(5, min(30, (int)$this->request->get('timeout', 25)));
        $queryLastVersion = (int)$this->request->get('last_version', 0);
        $headerLastVersion = (int)$this->request->header('last-event-id', '0');
        $lastVersion = max($queryLastVersion, $headerLastVersion);
        $service = new FlashSaleRealtimeService();
        $startedAt = microtime(true);
        $event = $service->latest();
        while (
            (int)($event['version'] ?? 0) <= $lastVersion
            && (microtime(true) - $startedAt) < $timeout
        ) {
            usleep(500000);
            $event = $service->latest();
        }
        if ((int)($event['version'] ?? 0) <= $lastVersion) {
            $event = [
                'version' => $lastVersion,
                'event' => 'keepalive',
                'payload' => [],
                'server_time' => date('Y-m-d H:i:s'),
                'timestamp' => time(),
            ];
        }
        $eventId = (string)(int)($event['version'] ?? 0);
        $body = "retry: 3000\n";
        $body .= 'id: ' . $eventId . "\n";
        $body .= "event: flash_sale_update\n";
        $body .= 'data: ' . json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        return response($body, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}

