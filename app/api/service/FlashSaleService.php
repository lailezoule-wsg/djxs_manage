<?php
declare (strict_types = 1);

namespace app\api\service;

use app\api\model\Drama;
use app\api\model\FlashSaleActivity;
use app\api\model\FlashSaleItem;
use app\api\model\FlashSaleOrder;
use app\api\model\Novel;
use app\api\model\Order;
use app\api\model\OrderGoods;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;

/**
 * 用户端秒杀业务服务
 */
class FlashSaleService
{
    private const TOKEN_CACHE_PREFIX = 'flash:sale:token:';
    private const TOKEN_BINDING_PREFIX = 'flash:sale:token:binding:';
    private const TOKEN_CONSUMED_PREFIX = 'flash:sale:token:consumed:';
    private const TOKEN_ISSUE_WINDOW_PREFIX = 'flash:sale:token:issue:window:';
    private const RELEASE_ZSET_KEY = 'flash:sale:reserve:release';
    private const STOCK_KEY_PREFIX = 'flash:sale:stock:';
    private const PENDING_KEY_PREFIX = 'flash:sale:pending:';
    private const REQUEST_STATE_PREFIX = 'flash:sale:request:state:';
    private const REQUEST_LOCK_PREFIX = 'flash:sale:request:lock:';
    private const REQUEST_BORN_PREFIX = 'flash:sale:request:born:';
    private const USER_ITEM_LOCK_PREFIX = 'flash:sale:user:item:lock:';
    private const USER_ITEM_PENDING_PREFIX = 'flash:sale:user:item:pending:';
    private const RELEASE_FALLBACK_LOCK_KEY = 'flash:sale:reserve:release:fallback:lock';
    private const TOKEN_TTL_SECONDS = 90;
    private const TOKEN_CONSUME_RETRY_TIMES = 1;
    private const TOKEN_CONSUME_RETRY_SLEEP_MS = 25;
    private const QUEUE_PUBLISH_RETRY_TIMES = 2;
    private const QUEUE_PUBLISH_RETRY_SLEEP_MS = 50;
    private const REQUEST_STATE_TTL = 900;
    private const RELEASE_FALLBACK_THROTTLE_SECONDS = 3;
    private const ORDER_STATUS_QUEUEING = 8;
    private const REQUEST_LOCK_TTL_SECONDS = 120;
    private const USER_ITEM_LOCK_TTL_SECONDS = 60;
    private const MAX_CLIENT_IP_LENGTH = 64;
    private const MAX_DEVICE_ID_LENGTH = 128;
    private const MAX_RISK_EXTRA_JSON_LENGTH = 2000;
    private const RISK_LOG_QUEUE_KEY = 'flash:sale:risk:log:queue';
    private const MSG_PENDING_ORDER_EXISTS = '你已有待支付订单，请先完成支付';
    private const MSG_QUEUEING_ACCEPTED = '抢购请求已受理，正在排队创建订单';
    private const MSG_QUEUE_BUSY_RETRY = '秒杀排队服务繁忙，请稍后重试';
    private const MSG_CREATE_ORDER_FAILED = '秒杀下单失败';
    private const MSG_SYSTEM_BUSY_RETRY = '系统繁忙，请稍后重试';
    private const CREATE_FAIL_REASON_METRIC_PREFIX = 'flash:sale:create:fail:reason:';
    private const TOKEN_ISSUE_FAIL_REASON_METRIC_PREFIX = 'flash:sale:token:issue:fail:reason:';
    private static ?bool $hasReserveExpireField = null;

    /**
     * 用户端活动列表
     */
    /**
     * 获取秒杀活动列表
     */
    public function list(array $params = []): array
    {
        $this->runReleaseFallbackIfDue();
        $page = max(1, (int)($params['page'] ?? 1));
        $limit = max(1, min(50, (int)($params['limit'] ?? 10)));
        $tab = strtolower(trim((string)($params['tab'] ?? 'running')));
        $goodsType = (int)($params['goods_type'] ?? 0);
        $goodsId = (int)($params['goods_id'] ?? 0);
        $now = date('Y-m-d H:i:s');

        $query = Db::name('flash_sale_activity')->alias('a')
            ->join('flash_sale_item i', 'i.activity_id = a.id')
            ->where('i.status', 1)
            ->whereIn('a.status', [1, 2]);

        if (in_array($goodsType, [10, 20], true)) {
            $query->where('i.goods_type', $goodsType);
        }
        if ($goodsId > 0) {
            $query->where('i.goods_id', $goodsId);
        }

        if ($tab === 'upcoming') {
            $query->where('a.start_time', '>', $now);
        } elseif ($tab === 'ended') {
            $query->where('a.end_time', '<', $now);
        } else {
            $query->where('a.start_time', '<=', $now)->where('a.end_time', '>=', $now);
        }

        $result = $query->field([
            'a.id' => 'activity_id',
            'a.name' => 'activity_name',
            'a.status' => 'activity_status',
            'a.start_time',
            'a.end_time',
            'i.id' => 'item_id',
            'i.goods_type',
            'i.goods_id',
            'i.title_snapshot',
            'i.cover_snapshot',
            'i.origin_price',
            'i.seckill_price',
            'i.total_stock',
            'i.sold_stock',
            'i.locked_stock',
            'i.limit_per_user',
        ])
            ->order('a.sort', 'desc')
            ->order('a.id', 'desc')
            ->order('i.id', 'desc')
            ->paginate([
                'page' => $page,
                'list_rows' => $limit,
            ])
            ->toArray();

        $listRows = $this->filterUnavailableContentItems($result['data'] ?? []);
        $list = array_map(function (array $row) use ($now): array {
            $available = max(0, (int)$row['total_stock'] - (int)$row['sold_stock'] - (int)$row['locked_stock']);
            $row['available_stock'] = $available;
            $row['title'] = (string)$row['title_snapshot'];
            $row['cover'] = (string)$row['cover_snapshot'];
            $row['button_status'] = $this->resolveButtonStatus($row, $available, $now);
            return $row;
        }, $listRows);

        return [
            'server_time' => time(),
            'list' => $list,
            'total' => (int)($result['total'] ?? 0),
            'page' => (int)($result['current_page'] ?? $page),
            'limit' => (int)($result['per_page'] ?? $limit),
            'has_more' => (int)($result['current_page'] ?? 0) < (int)($result['last_page'] ?? 0),
        ];
    }

    /**
     * 获取秒杀活动详情
     */
    public function detail(int $activityId): array
    {
        $this->runReleaseFallbackIfDue();
        if ($activityId <= 0) {
            throw new ValidateException('活动不存在');
        }
        $activity = FlashSaleActivity::where('id', $activityId)->find();
        if (!$activity) {
            throw new ValidateException('活动不存在');
        }

        $items = FlashSaleItem::where('activity_id', $activityId)
            ->where('status', 1)
            ->order('id', 'desc')
            ->select()
            ->toArray();
        $items = $this->filterUnavailableContentItems($items);
        $now = date('Y-m-d H:i:s');
        foreach ($items as &$item) {
            $available = max(0, (int)$item['total_stock'] - (int)$item['sold_stock'] - (int)$item['locked_stock']);
            $item['available_stock'] = $available;
            $item['title'] = (string)$item['title_snapshot'];
            $item['cover'] = (string)$item['cover_snapshot'];
            $item['button_status'] = $this->resolveButtonStatus([
                'start_time' => (string)$activity['start_time'],
                'end_time' => (string)$activity['end_time'],
            ], $available, $now);
        }

        return [
            'server_time' => time(),
            'activity' => $activity->toArray(),
            'items' => $items,
        ];
    }

    /**
     * 签发秒杀下单令牌
     */
    public function issueToken(int $userId, int $activityId, int $itemId, string $clientIp = '', string $deviceId = ''): array
    {
        $this->runReleaseFallbackIfDue();
        if ($activityId <= 0 || $itemId <= 0) {
            throw new ValidateException('参数不完整');
        }
        $clientIp = $this->normalizeClientIp($clientIp);
        $deviceId = $this->normalizeDeviceId($deviceId);
        $this->assertTokenRateLimit($userId, $activityId, $itemId, $clientIp, $deviceId);
        $this->assertActivityItemValid($activityId, $itemId);
        $tokenTtlSeconds = $this->getTokenTtlSeconds();
        // P1 门禁：库存快照已售罄时不再签发 token，减少无效 create 请求。
        $stockSnapshot = $this->getRedisAvailableStockSnapshot($itemId);
        if ($stockSnapshot !== null && $stockSnapshot < 1) {
            $this->recordTokenIssueFailureReason('sold_out', $userId, $activityId, $itemId, '库存不足');
            throw new ValidateException('库存不足');
        }
        $quotaReserved = false;
        if ($stockSnapshot !== null) {
            $quotaReserved = $this->tryReserveTokenIssueQuota($activityId, $itemId, $stockSnapshot, $tokenTtlSeconds);
            if (!$quotaReserved) {
                $this->recordTokenIssueFailureReason('token_issue_quota_exceeded', $userId, $activityId, $itemId, '当前抢购人数较多，请稍后重试');
                throw new ValidateException('当前抢购人数较多，请稍后重试');
            }
        }
        try {
            $token = bin2hex(random_bytes(16));
            $cacheKey = $this->buildTokenCacheKey($userId, $activityId, $itemId, $token);
            Cache::set($cacheKey, 1, $tokenTtlSeconds);
            $this->writeTokenBinding($token, $userId, $activityId, $itemId, $tokenTtlSeconds);
        } catch (\Throwable $e) {
            if ($quotaReserved) {
                $this->releaseTokenIssueQuota($activityId, $itemId);
            }
            $this->recordTokenIssueFailureReason('token_issue_cache_write_failed', $userId, $activityId, $itemId, $e->getMessage());
            throw $e;
        }

        return [
            'token' => $token,
            'expire_seconds' => $tokenTtlSeconds,
            'server_time' => time(),
        ];
    }

    /**
     * 秒杀下单前置校验
     */
    public function precheck(int $userId, array $payload): array
    {
        $this->runReleaseFallbackIfDue();
        $activityId = (int)($payload['activity_id'] ?? 0);
        $itemId = (int)($payload['item_id'] ?? 0);
        $buyCount = max(1, (int)($payload['buy_count'] ?? 1));
        // 归一化客户端标识，避免后续风控键异常膨胀。
        $clientIp = $this->normalizeClientIp((string)($payload['client_ip'] ?? ''));
        $deviceId = $this->normalizeDeviceId((string)($payload['device_id'] ?? ''));
        if ($activityId <= 0 || $itemId <= 0) {
            throw new ValidateException('参数不完整');
        }
        $activity = Db::name('flash_sale_activity')->where('id', $activityId)->find();
        $item = Db::name('flash_sale_item')->where('id', $itemId)->find();
        if (!$activity || !$item || (int)$item['activity_id'] !== $activityId) {
            return $this->buildPrecheckResult(false, 'not_found', '活动商品不存在');
        }
        $now = date('Y-m-d H:i:s');
        if ((int)$activity['status'] !== 2 || (int)$item['status'] !== 1 || $now < (string)$activity['start_time'] || $now > (string)$activity['end_time']) {
            return $this->buildPrecheckResult(false, 'activity_not_running', '活动未开始或已结束');
        }
        $available = max(0, (int)$item['total_stock'] - (int)$item['sold_stock'] - (int)$item['locked_stock']);
        if ($available < $buyCount) {
            return $this->buildPrecheckResult(false, 'sold_out', '库存不足', [
                'available_stock' => $available,
            ]);
        }
        $blacklistHit = $this->findCreateBlacklistHit($userId, $clientIp, $deviceId);
        if (!empty($blacklistHit)) {
            return $this->buildPrecheckResult(false, 'blacklist_blocked', '当前账号/设备网络受限，请稍后重试', [
                'blacklist_target_type' => (string)($blacklistHit['target_type'] ?? ''),
                'blacklist_target_value' => (string)($blacklistHit['target_value'] ?? ''),
            ]);
        }
        $rateHit = $this->checkCreateRateLimitPreview($userId, $activityId, $itemId, $clientIp, $deviceId);
        if (!empty($rateHit)) {
            return $this->buildPrecheckResult(false, (string)$rateHit['reason'], (string)$rateHit['message'], [
                'current' => (int)($rateHit['current'] ?? 0),
                'limit' => (int)($rateHit['limit'] ?? 0),
            ]);
        }
        $pendingCount = Db::name('flash_sale_order')
            ->where('user_id', $userId)
            ->where('item_id', $itemId)
            ->where('status', 0)
            ->count();
        if ($pendingCount > 0) {
            return $this->buildPrecheckResult(false, 'pending_order', '你已有待支付订单，请先完成支付');
        }
        if ($this->hasPurchasedConflict($userId, (int)$item['goods_type'], (int)$item['goods_id'])) {
            return $this->buildPrecheckResult(false, 'already_purchased', '你已购买该内容，无需重复抢购');
        }
        $paidCount = Db::name('flash_sale_order')
            ->where('user_id', $userId)
            ->where('item_id', $itemId)
            ->where('status', 1)
            ->count();
        $limitPerUser = max(1, (int)$item['limit_per_user']);
        if ($paidCount >= $limitPerUser) {
            return $this->buildPrecheckResult(false, 'limit_exceeded', '超过限购数量');
        }
        return $this->buildPrecheckResult(true, 'ok', '可参与抢购', [
            'available_stock' => $available,
            'limit_per_user' => $limitPerUser,
            'paid_count' => $paidCount,
        ]);
    }

    /**
     * 创建秒杀订单请求
     */
    public function createOrder(int $userId, array $payload): array
    {
        $activityId = (int)($payload['activity_id'] ?? 0);
        $itemId = (int)($payload['item_id'] ?? 0);
        $buyCount = (int)($payload['buy_count'] ?? 1);
        $requestId = trim((string)($payload['request_id'] ?? ''));
        $token = trim((string)($payload['token'] ?? ''));
        $clientIp = $this->normalizeClientIp((string)($payload['client_ip'] ?? ''));
        $deviceId = $this->normalizeDeviceId((string)($payload['device_id'] ?? ''));
        $payType = strtolower(trim((string)($payload['pay_type'] ?? 'wechat')));
        $payTypeValue = $payType === 'alipay' ? 2 : 1;
        if ($buyCount !== 1) {
            throw new ValidateException('首期仅支持单件购买');
        }
        if ($activityId <= 0 || $itemId <= 0) {
            throw new ValidateException('参数不完整');
        }
        if (!in_array($payType, ['wechat', 'alipay'], true)) {
            throw new ValidateException('pay_type 不合法');
        }
        if (!$this->isValidRequestId($requestId)) {
            throw new ValidateException('request_id 不合法');
        }

        // 先读 request_id 状态缓存，实现幂等快速返回。
        $cachedState = $this->getRequestState($requestId);
        if (!empty($cachedState)) {
            $cachedUserId = (int)($cachedState['user_id'] ?? 0);
            if ($cachedUserId > 0 && $cachedUserId !== $userId) {
                throw new ValidateException('request_id 不合法');
            }
            // 根据缓存状态统一返回（排队/待支付/失败等）。
            return $this->buildResponseByRequestState($cachedState, $requestId);
        }
        $exists = FlashSaleOrder::where('request_id', $requestId)->find();
        if ($exists) {
            if ((int)$exists->user_id !== $userId) {
                throw new ValidateException('request_id 不合法');
            }
            $order = Order::where('id', (int)$exists->order_id)->find();
            $reserveExpireTime = (string)($exists->reserve_expire_time ?? '');
            // 计算剩余支付时长（字段缺失时回退 create_time 推算）。
            $expireSeconds = $this->calcExpireSeconds($reserveExpireTime, $order ? (string)$order->create_time : '');
            return [
                'order_id' => (int)($order->id ?? 0),
                'order_sn' => (string)($order->order_sn ?? ''),
                'pay_amount' => (float)($order->pay_amount ?? 0),
                'expire_seconds' => $expireSeconds,
                'next_action' => 'pay',
                'status' => (int)$exists->status,
                'reserve_expire_time' => $reserveExpireTime,
            ];
        }
        if ($token === '') {
            // 参数错误也写入状态缓存，便于结果查询接口返回一致错误。
            $this->setRequestState($requestId, [
                'status' => 3,
                'user_id' => $userId,
                'message' => 'token 不能为空',
                'reserve_expire_time' => '',
                'order_id' => 0,
            ]);
            $this->recordCreateOrderFailureReason('token_empty', $requestId, $userId, $activityId, $itemId, 'token 不能为空');
            throw new ValidateException('token 不能为空');
        }
        if (!$this->isValidToken($token)) {
            // 参数错误也写入状态缓存，便于结果查询接口返回一致错误。
            $this->setRequestState($requestId, [
                'status' => 3,
                'user_id' => $userId,
                'message' => 'token 不合法',
                'reserve_expire_time' => '',
                'order_id' => 0,
            ]);
            $this->recordCreateOrderFailureReason('token_invalid', $requestId, $userId, $activityId, $itemId, 'token 不合法');
            throw new ValidateException('token 不合法');
        }
        // 创建前先做黑名单与限频拦截。
        $this->assertCreateBlacklist($userId, $activityId, $itemId, $clientIp, $deviceId);
        $this->assertCreateRateLimit($userId, $activityId, $itemId, $clientIp, $deviceId);
        // 校验 request_id 生存窗口，防止旧请求重放。
        $this->assertRequestIdWindow($requestId);
        // P0 快速失败：库存缓存已明确售罄时直接返回，避免进入锁/MQ重链路。
        $stockSnapshot = $this->getRedisAvailableStockSnapshot($itemId);
        if ($stockSnapshot !== null && $stockSnapshot < $buyCount) {
            $this->setRequestState($requestId, [
                'status' => 3,
                'user_id' => $userId,
                'message' => '库存不足',
                'reserve_expire_time' => '',
                'order_id' => 0,
            ]);
            $this->recordCreateOrderFailureReason('stock_not_enough', $requestId, $userId, $activityId, $itemId, '库存不足');
            throw new ValidateException('库存不足');
        }

        // request_id 维度互斥，确保同一请求只会被一个进程处理。
        $requestLockToken = $this->acquireRequestLock($requestId);
        if ($requestLockToken === '') {
            // 锁竞争分支兜底复核 request_id 归属，避免跨用户探测状态。
            $stateOnLockConflict = $this->getRequestState($requestId);
            $stateUserId = (int)($stateOnLockConflict['user_id'] ?? 0);
            if ($stateUserId > 0 && $stateUserId !== $userId) {
                throw new ValidateException('request_id 不合法');
            }
            if ($stateUserId <= 0) {
                $existsOnLockConflict = FlashSaleOrder::where('request_id', $requestId)->find();
                if ($existsOnLockConflict && (int)$existsOnLockConflict->user_id !== $userId) {
                    throw new ValidateException('request_id 不合法');
                }
            }
            // 其他进程已在处理，返回排队中由前端轮询结果接口。
            return $this->buildQueueingResponse($requestId);
        }
        // 用户-商品维度互斥，避免同人同商品并发下单。
        $userItemLockToken = $this->acquireUserItemLock($activityId, $itemId, $userId);
        if ($userItemLockToken === '') {
            $this->releaseRequestLock($requestId, $requestLockToken);
            $this->recordCreateOrderFailureReason('user_item_lock_conflict', $requestId, $userId, $activityId, $itemId, '请求处理中，请勿重复提交');
            throw new ValidateException('请求处理中，请勿重复提交');
        }
        // 统一失败态落缓存，避免重复拼装状态结构。
        $markRequestFailed = function (string $message) use ($requestId, $userId): void {
            $this->setRequestState($requestId, [
                'status' => 3,
                'user_id' => $userId,
                'message' => $message,
                'reserve_expire_time' => '',
                'order_id' => 0,
            ]);
        };
        // 统一释放请求锁与用户商品锁，降低异常路径遗漏风险。
        $releaseCreateLocks = function () use ($activityId, $itemId, $userId, $userItemLockToken, $requestId, $requestLockToken): void {
            $this->releaseUserItemLock($activityId, $itemId, $userId, $userItemLockToken);
            $this->releaseRequestLock($requestId, $requestLockToken);
        };
        // 校验 token 绑定关系，阻止串参/重放。
        $bindingMismatchMessage = $this->resolveTokenBindingMismatchMessage($token, $userId, $activityId, $itemId);
        if ($bindingMismatchMessage !== '') {
            $releaseCreateLocks();
            $markRequestFailed($bindingMismatchMessage);
            $this->recordCreateOrderFailureReason('token_binding_mismatch', $requestId, $userId, $activityId, $itemId, $bindingMismatchMessage);
            // 记录异常行为，供风控与追踪分析。
            $this->recordRiskEvent('token_mismatch', [
                'user_id' => $userId,
                'activity_id' => $activityId,
                'item_id' => $itemId,
                'extra' => [
                    'request_id' => $requestId,
                    'token_prefix' => substr($token, 0, 8),
                ],
            ]);
            throw new ValidateException($bindingMismatchMessage);
        }
        // 计算 token 缓存键并按配置执行消费（带短重试）。
        $cacheKey = $this->buildTokenCacheKey($userId, $activityId, $itemId, $token);
        $tokenTtlSeconds = $this->getTokenTtlSeconds();
        if (!$this->consumeTokenWithRetry(
            $cacheKey,
            $token,
            $tokenTtlSeconds,
            $this->getTokenConsumeRetryTimes(),
            $this->getTokenConsumeRetrySleepMs()
        )) {
            $releaseCreateLocks();
            // 归因 token 失败类型，统一返回用户可理解提示。
            $tokenFailure = $this->resolveTokenConsumeFailure($token, $cacheKey);
            $markRequestFailed((string)$tokenFailure['message']);
            $this->recordCreateOrderFailureReason(
                (string)($tokenFailure['reason'] ?? 'token_consume_failed'),
                $requestId,
                $userId,
                $activityId,
                $itemId,
                (string)$tokenFailure['message']
            );
            $this->recordTokenConsumeFailure($requestId, $userId, $activityId, $itemId, $token, $cacheKey, (array)$tokenFailure);
            throw new ValidateException((string)$tokenFailure['message']);
        }
        // 二次检查用户商品待处理标记，拦截重复待支付单。
        if ($this->hasUserItemPendingMarker($itemId, $userId)) {
            $releaseCreateLocks();
            $this->recordCreateOrderFailureReason('pending_marker_conflict', $requestId, $userId, $activityId, $itemId, self::MSG_PENDING_ORDER_EXISTS);
            throw new ValidateException(self::MSG_PENDING_ORDER_EXISTS);
        }
        $redisReserved = false;
        $lockedReserved = false;
        $queuePayload = [];
        // 长流程心跳续锁，防止锁 TTL 过期导致并发穿透。
        $heartbeatLocks = function () use ($requestId, $requestLockToken, $activityId, $itemId, $userId, $userItemLockToken): void {
            $requestOk = $this->refreshRequestLock($requestId, $requestLockToken);
            $userItemOk = $this->refreshUserItemLock($activityId, $itemId, $userId, $userItemLockToken);
            if (!$requestOk || !$userItemOk) {
                throw new ValidateException('请求处理中，请稍后重试');
            }
        };
        try {
            // 事务内完成库存预占与下单载荷准备；死锁场景按策略重试。
            $queuePayload = $this->executeWithDeadlockRetry(
                function () use ($userId, $activityId, $itemId, $buyCount, $requestId, $payTypeValue, $payType, &$redisReserved, &$lockedReserved, $heartbeatLocks) {
                    $heartbeatLocks();
                    $redisReserved = false;
                    $lockedReserved = false;
                    return Db::transaction(function () use ($userId, $activityId, $itemId, $buyCount, $requestId, $payTypeValue, $payType, &$redisReserved, &$lockedReserved) {
                        // 热点优化：预检改为无锁读取 + 条件更新，减少高并发行锁等待。
                        $activity = [];
                        $item = [];
                        $this->assertActivityItemValid($activityId, $itemId, $activity, $item);
                        // 已购用户不允许再参与同商品秒杀。
                        if ($this->hasPurchasedConflict($userId, (int)$item['goods_type'], (int)$item['goods_id'])) {
                            throw new ValidateException('你已购买该内容，无需重复抢购');
                        }

                        $available = max(0, (int)$item['total_stock'] - (int)$item['sold_stock'] - (int)$item['locked_stock']);
                        if ($available < $buyCount) {
                            throw new ValidateException('库存不足');
                        }

                        // 合并 pending/paid 统计为一次聚合查询，减少热点路径 DB 往返。
                        $orderStats = Db::name('flash_sale_order')
                            ->where('user_id', $userId)
                            ->where('item_id', $itemId)
                            ->fieldRaw('SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) AS pending_count, SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS paid_count')
                            ->find();
                        $pendingCount = (int)($orderStats['pending_count'] ?? 0);
                        if ($pendingCount > 0) {
                            throw new ValidateException(self::MSG_PENDING_ORDER_EXISTS);
                        }
                        $paidCount = (int)($orderStats['paid_count'] ?? 0);
                        if ($paidCount >= max(1, (int)$item['limit_per_user'])) {
                            throw new ValidateException('超过限购数量');
                        }

                        // 先在 Redis 预占，降低热点行锁冲突。
                        $redisReserved = $this->reserveStockWithRedis((int)$item['id'], $userId, $buyCount, $available);
                        $affected = Db::name('flash_sale_item')
                            ->where('id', $itemId)
                            ->where('activity_id', $activityId)
                            ->where('status', 1)
                            ->whereRaw('(total_stock - sold_stock - locked_stock) >= ?', [$buyCount])
                            ->inc('locked_stock', $buyCount)
                            ->update();
                        if ((int)$affected < 1) {
                            throw new ValidateException('库存不足');
                        }
                        $lockedReserved = true;
                        $unitPrice = (float)$item['seckill_price'];
                        $payAmount = round($unitPrice * $buyCount, 2);
                        if ($payAmount <= 0) {
                            throw new ValidateException('秒杀价格异常');
                        }
                        $goodsName = trim((string)$item['title_snapshot']);
                        if ($goodsName === '') {
                            // 快照为空时按商品类型回填标题。
                            $goodsName = $this->resolveGoodsName((int)$item['goods_type'], (int)$item['goods_id']);
                        }
                        // 生成库存预留过期时间，供后续释放与前端倒计时使用。
                        $reserveExpireTime = date('Y-m-d H:i:s', time() + $this->getReserveSeconds());
                        return [
                            'request_id' => $requestId,
                            'user_id' => $userId,
                            'activity_id' => $activityId,
                            'item_id' => $itemId,
                            'buy_count' => $buyCount,
                            'pay_type' => $payType,
                            'pay_type_value' => $payTypeValue,
                            'goods_type' => (int)$item['goods_type'],
                            'goods_id' => (int)$item['goods_id'],
                            'goods_name' => $goodsName,
                            'seckill_price' => $unitPrice,
                            'pay_amount' => $payAmount,
                            'reserve_expire_time' => $reserveExpireTime,
                            'created_at' => date('Y-m-d H:i:s'),
                        ];
                    });
                },
                function () use (&$lockedReserved, &$redisReserved, $itemId, $buyCount, $userId) {
                    // 死锁重试前先回滚库存预占，避免库存虚扣。
                    $this->rollbackCreateOrderReservation($itemId, $userId, $buyCount, $lockedReserved, $redisReserved);
                }
            );
            // 出事务后再续一次锁，避免临近过期导致发布阶段并发问题。
            $heartbeatLocks();
            // 推送到异步队列，失败时按配置重试。
            $published = $this->publishQueueWithRetry($queuePayload);
            if (!$published) {
                $this->logQueuePublishExhaustedRetries($requestId, $userId, $activityId, $itemId);
                throw new ValidateException(self::MSG_QUEUE_BUSY_RETRY);
            }
            return $this->buildCreateOrderQueueingResponse($requestId, $userId, $itemId, $payType, $queuePayload);
        } catch (\Throwable $e) {
            // 异常路径统一回滚库存预占。
            $this->rollbackCreateOrderReservation($itemId, $userId, $buyCount, $lockedReserved, $redisReserved);
            // 失败时清理用户-商品处理中标记。
            $this->clearUserItemPendingMarker($itemId, $userId);
            $this->recordCreateOrderFailureReason(
                $this->resolveCreateOrderFailureReason($e),
                $requestId,
                $userId,
                $activityId,
                $itemId,
                $e instanceof ValidateException ? $e->getMessage() : self::MSG_CREATE_ORDER_FAILED
            );
            // 失败态写入 request_state，前端可通过 result 读到具体失败信息。
            $markRequestFailed($e instanceof ValidateException ? $e->getMessage() : self::MSG_CREATE_ORDER_FAILED);
            throw $e;
        } finally {
            // finally 中统一释放两把互斥锁，避免异常遗漏。
            $releaseCreateLocks();
        }
    }

    /**
     * 消费秒杀下单消息
     */
    public function consumeCreateOrderMessage(array $payload): void
    {
        $requestId = trim((string)($payload['request_id'] ?? ''));
        $userId = (int)($payload['user_id'] ?? 0);
        $activityId = (int)($payload['activity_id'] ?? 0);
        $itemId = (int)($payload['item_id'] ?? 0);
        $buyCount = max(1, (int)($payload['buy_count'] ?? 1));
        if ($requestId === '' || $userId <= 0 || $activityId <= 0 || $itemId <= 0) {
            return;
        }
        $exists = FlashSaleOrder::where('request_id', $requestId)->find();
        if ($exists) {
            $order = Order::where('id', (int)$exists->order_id)->find();
            $reserveExpireTime = (string)($exists->reserve_expire_time ?? '');
            $this->setRequestState($requestId, [
                'status' => (int)$exists->status,
                'user_id' => $userId,
                'message' => (int)$exists->status === 0 ? '订单已创建，请尽快支付' : '订单状态已更新',
                'order_id' => (int)($exists->order_id ?? 0),
                'order_sn' => (string)($order->order_sn ?? ''),
                'pay_amount' => (float)($order->pay_amount ?? 0),
                'reserve_expire_time' => $reserveExpireTime,
            ]);
            return;
        }
        $reserveExpireTime = (string)($payload['reserve_expire_time'] ?? '');
        $reserveExpireTs = $this->parseTimeToTs($reserveExpireTime);
        if ($reserveExpireTs > 0 && $reserveExpireTs <= time()) {
            $this->rollbackQueuedReservation($itemId, $userId, $buyCount);
            $this->clearUserItemPendingMarker($itemId, $userId);
            $this->setRequestState($requestId, [
                'status' => 3,
                'user_id' => $userId,
                'message' => '排队超时，库存已释放，请重新抢购',
                'order_id' => 0,
                'reserve_expire_time' => $reserveExpireTime,
            ]);
            return;
        }
        try {
            $this->executeWithDeadlockRetry(function () use ($payload, $requestId, $userId, $activityId, $itemId, $buyCount, $reserveExpireTime) {
                Db::transaction(function () use ($payload, $requestId, $userId, $activityId, $itemId, $buyCount, $reserveExpireTime) {
                    $item = Db::name('flash_sale_item')->where('id', $itemId)->lock(true)->find();
                    if (!$item || (int)($item['activity_id'] ?? 0) !== $activityId) {
                        throw new ValidateException('活动商品不存在');
                    }
                    if ($this->hasPurchasedConflict($userId, (int)$item['goods_type'], (int)$item['goods_id'])) {
                        throw new ValidateException('你已购买该内容，无需重复抢购');
                    }
                    $pendingCount = Db::name('flash_sale_order')
                        ->where('user_id', $userId)
                        ->where('item_id', $itemId)
                        ->where('status', 0)
                        ->count();
                    if ($pendingCount > 0) {
                        throw new ValidateException('你已有待支付订单，请先完成支付');
                    }
                    $paidCount = Db::name('flash_sale_order')
                        ->where('user_id', $userId)
                        ->where('item_id', $itemId)
                        ->where('status', 1)
                        ->count();
                    if ($paidCount >= max(1, (int)$item['limit_per_user'])) {
                        throw new ValidateException('超过限购数量');
                    }
                    $payTypeValue = (int)($payload['pay_type_value'] ?? 2);
                    $payAmount = (float)($payload['pay_amount'] ?? 0);
                    $seckillPrice = (float)($payload['seckill_price'] ?? 0);
                    if ($payAmount <= 0 || $seckillPrice <= 0) {
                        throw new ValidateException('秒杀价格异常');
                    }
                    $goodsType = (int)($payload['goods_type'] ?? $item['goods_type']);
                    $goodsId = (int)($payload['goods_id'] ?? $item['goods_id']);
                    $order = $this->createPendingOrderWithRetry(
                        $userId,
                        $payAmount,
                        $payTypeValue,
                        $goodsType,
                        $goodsId
                    );
                    OrderGoods::create([
                        'order_id' => (int)$order->id,
                        'goods_type' => $goodsType,
                        'goods_id' => $goodsId,
                        'goods_name' => (string)($payload['goods_name'] ?? $item['title_snapshot']),
                        'price' => $seckillPrice,
                        'quantity' => $buyCount,
                    ]);
                    $flashSaleOrderData = [
                        'order_id' => (int)$order->id,
                        'activity_id' => $activityId,
                        'item_id' => $itemId,
                        'user_id' => $userId,
                        'request_id' => $requestId,
                        'buy_count' => $buyCount,
                        'seckill_price' => $seckillPrice,
                        'status' => 0,
                    ];
                    if ($this->hasReserveExpireField()) {
                        $flashSaleOrderData['reserve_expire_time'] = $reserveExpireTime;
                    }
                    FlashSaleOrder::create($flashSaleOrderData);
                    $this->scheduleReserveRelease((int)$order->id, $reserveExpireTime);
                    $this->setRequestState($requestId, [
                        'status' => 0,
                        'user_id' => $userId,
                        'message' => '订单已创建，请尽快支付',
                        'order_id' => (int)$order->id,
                        'order_sn' => (string)$order->order_sn,
                        'pay_amount' => (float)$order->pay_amount,
                        'expire_seconds' => $this->getReserveSeconds(),
                        'next_action' => 'pay',
                        'reserve_expire_time' => $reserveExpireTime,
                    ]);
                });
                return true;
            });
            return;
        } catch (\Throwable $e) {
            $this->rollbackQueuedReservation($itemId, $userId, $buyCount);
            $this->clearUserItemPendingMarker($itemId, $userId);
            $errorMessage = '订单创建失败，请重新抢购';
            if ($e instanceof ValidateException) {
                $errorMessage = $e->getMessage();
            } elseif ($this->isPendingOrderDuplicateException($e)) {
                $errorMessage = '你已有待支付订单，请先完成支付';
            }
            $this->setRequestState($requestId, [
                'status' => 3,
                'user_id' => $userId,
                'message' => $errorMessage,
                'order_id' => 0,
                'reserve_expire_time' => $reserveExpireTime,
            ]);
        }
    }

    /**
     * 查询秒杀请求处理结果
     */
    public function result(int $userId, string $requestId): array
    {
        $this->runReleaseFallbackIfDue();
        if (!$this->isValidRequestId($requestId)) {
            throw new ValidateException('request_id 不合法');
        }
        $row = FlashSaleOrder::where('user_id', $userId)->where('request_id', $requestId)->find();
        if (!$row) {
            $cachedState = $this->getRequestState($requestId);
            if (empty($cachedState)) {
                return [
                    'request_id' => $requestId,
                    'status' => 3,
                    'order_id' => 0,
                    'order_sn' => '',
                    'message' => '抢购请求不存在或已失效，请重新获取令牌后下单',
                    'reserve_expire_time' => '',
                    'expire_seconds' => 0,
                    'next_action' => 'stop',
                ];
            }
            $cachedUserId = (int)($cachedState['user_id'] ?? 0);
            if ($cachedUserId > 0 && $cachedUserId !== $userId) {
                throw new ValidateException('记录不存在');
            }
            $reserveExpireTime = (string)($cachedState['reserve_expire_time'] ?? '');
            return [
                'request_id' => $requestId,
                'status' => (int)($cachedState['status'] ?? self::ORDER_STATUS_QUEUEING),
                'order_id' => (int)($cachedState['order_id'] ?? 0),
                'order_sn' => (string)($cachedState['order_sn'] ?? ''),
                'message' => (string)($cachedState['message'] ?? ''),
                'reserve_expire_time' => $reserveExpireTime,
                'expire_seconds' => $this->calcExpireSeconds($reserveExpireTime),
            ];
        }
        $reserveExpireTime = (string)($row->reserve_expire_time ?? '');
        $expireSeconds = $this->calcExpireSeconds($reserveExpireTime);
        $status = (int)$row->status;
        $message = '';
        if ($status === 0) {
            $message = '订单已创建，请尽快支付';
        } elseif ($status === 1) {
            $message = '订单已支付';
        } elseif ($status === 2) {
            $message = '订单已取消';
        } elseif ($status === 3) {
            $message = '订单已超时释放';
        }
        return [
            'request_id' => $requestId,
            'status' => $status,
            'order_id' => (int)$row->order_id,
            'reserve_expire_time' => $reserveExpireTime,
            'expire_seconds' => $expireSeconds,
            'message' => $message,
        ];
    }

    /**
     * 处理秒杀订单支付成功
     */
    public function handleOrderPaid(int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }
        Db::transaction(function () use ($orderId) {
            $row = Db::name('flash_sale_order')->where('order_id', $orderId)->lock(true)->find();
            if (!$row) {
                return;
            }
            $status = (int)($row['status'] ?? 0);
            if ($status === 1) {
                return;
            }
            Db::name('flash_sale_order')->where('id', (int)$row['id'])->update([
                'status' => 1,
                'update_time' => date('Y-m-d H:i:s'),
            ]);

            if ($status === 0) {
                // 正常支付：占用库存转已售库存
                Db::name('flash_sale_item')->where('id', (int)$row['item_id'])
                    ->dec('locked_stock', (int)$row['buy_count'])
                    ->inc('sold_stock', (int)$row['buy_count'])
                    ->update();
            } elseif (in_array($status, [2, 3], true)) {
                // 临界补偿：订单已取消/超时释放后晚到支付，仅回补已售库存
                Db::name('flash_sale_item')->where('id', (int)$row['item_id'])
                    ->inc('sold_stock', (int)$row['buy_count'])
                    ->update();
                // 超时释放时 Redis 可售库存已回补，此处需扣回，避免缓存可售偏高
                $this->consumeReservedStockAfterLatePaid((int)$row['item_id'], (int)$row['buy_count']);
            } else {
                return;
            }
            $this->clearPendingByUser((int)$row['item_id'], (int)$row['user_id']);
            $this->clearUserItemPendingMarker((int)$row['item_id'], (int)$row['user_id']);
            $this->removeReserveReleaseSchedule($orderId);
            $this->setRequestState((string)($row['request_id'] ?? ''), [
                'status' => 1,
                'user_id' => (int)($row['user_id'] ?? 0),
                'message' => '订单已支付',
                'order_id' => $orderId,
                'reserve_expire_time' => (string)($row['reserve_expire_time'] ?? ''),
            ]);
        });
    }

    /**
     * 处理秒杀订单取消
     */
    public function handleOrderCanceled(int $orderId, bool $timeout = false): void
    {
        if ($orderId <= 0) {
            return;
        }
        Db::transaction(function () use ($orderId, $timeout) {
            $row = Db::name('flash_sale_order')->where('order_id', $orderId)->lock(true)->find();
            if (!$row) {
                return;
            }
            if ((int)$row['status'] !== 0) {
                return;
            }
            Db::name('flash_sale_order')->where('id', (int)$row['id'])->update([
                'status' => $timeout ? 3 : 2,
                'update_time' => date('Y-m-d H:i:s'),
            ]);
            Db::name('flash_sale_item')->where('id', (int)$row['item_id'])
                ->dec('locked_stock', (int)$row['buy_count'])
                ->update();
            $this->rollbackReservedStock((int)$row['item_id'], (int)$row['user_id'], (int)$row['buy_count']);
            $this->clearUserItemPendingMarker((int)$row['item_id'], (int)$row['user_id']);
            $this->removeReserveReleaseSchedule($orderId);
            $this->setRequestState((string)($row['request_id'] ?? ''), [
                'status' => $timeout ? 3 : 2,
                'user_id' => (int)($row['user_id'] ?? 0),
                'message' => $timeout ? '订单已超时释放' : '订单已取消',
                'order_id' => $orderId,
                'reserve_expire_time' => (string)($row['reserve_expire_time'] ?? ''),
            ]);
        });
    }

    /**
     * 释放超时预留库存
     */
    public function releaseDueReserveOrders(int $limit = 100): int
    {
        $limit = max(1, min(500, $limit));
        $released = 0;
        $nowTs = time();
        $processed = [];
        $redis = $this->getRedisHandler();

        if ($redis && method_exists($redis, 'zRangeByScore')) {
            try {
                $dueOrderIds = $redis->zRangeByScore(self::RELEASE_ZSET_KEY, '-inf', (string)$nowTs, ['limit' => [0, $limit]]);
                if (is_array($dueOrderIds)) {
                    foreach ($dueOrderIds as $orderIdRaw) {
                        $orderId = (int)$orderIdRaw;
                        if ($orderId <= 0) {
                            continue;
                        }
                        $processed[$orderId] = true;
                        if ($this->releaseOrderIfExpired($orderId, $nowTs)) {
                            $released++;
                        }
                        $this->removeReserveReleaseSchedule($orderId);
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        if ($released > 0 || !empty($processed)) {
            return $released;
        }

        // 兜底：Redis不可用或队列为空时，按过期时间小批量扫，避免全表大扫描
        $expireAt = date('Y-m-d H:i:s', $nowTs);
        $query = Db::name('flash_sale_order')
            ->where('status', 0)
            ->order('id', 'asc')
            ->limit($limit);
        if ($this->hasReserveExpireField()) {
            $query->where('reserve_expire_time', '<=', $expireAt);
        } else {
            $query->where('create_time', '<=', date('Y-m-d H:i:s', $nowTs - $this->getReserveSeconds()));
        }
        $rows = $query->column('order_id');
        foreach ($rows as $orderIdValue) {
            $orderId = (int)$orderIdValue;
            if ($orderId <= 0) {
                continue;
            }
            if ($this->releaseOrderIfExpired($orderId, $nowTs)) {
                $released++;
            }
        }

        return $released;
    }

    /**
     * 非核心链路的兜底触发：短间隔内仅允许一次释放检查。
     */
    private function runReleaseFallbackIfDue(): void
    {
        if ((int)env('FLASH_SALE_RELEASE_FALLBACK_ENABLED', 1) !== 1) {
            return;
        }
        $throttleSeconds = max(1, min(30, (int)env('FLASH_SALE_RELEASE_FALLBACK_THROTTLE_SECONDS', self::RELEASE_FALLBACK_THROTTLE_SECONDS)));
        $lockToken = $this->acquireOwnedLock(self::RELEASE_FALLBACK_LOCK_KEY, $throttleSeconds);
        if ($lockToken === '') {
            return;
        }
        $limit = max(1, min(500, (int)env('FLASH_SALE_RELEASE_BATCH', 100)));
        $this->releaseDueReserveOrders($limit);
    }

    /**
     * 秒杀订单与库存对账
     */
    public function reconcile(int $limit = 200): array
    {
        $limit = max(1, min(2000, $limit));
        $released = $this->releaseDueReserveOrders($limit);
        $fixedItems = 0;
        $fixedRedis = 0;
        $items = Db::name('flash_sale_item')->order('id', 'asc')->limit($limit)->select()->toArray();
        $redis = $this->getRedisHandler();
        foreach ($items as $item) {
            $itemId = (int)($item['id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }
            $stats = Db::name('flash_sale_order')
                ->where('item_id', $itemId)
                ->fieldRaw('SUM(CASE WHEN status=0 THEN buy_count ELSE 0 END) AS locked_calc, SUM(CASE WHEN status=1 THEN buy_count ELSE 0 END) AS sold_calc')
                ->find();
            $lockedCalc = (int)($stats['locked_calc'] ?? 0);
            $soldCalc = (int)($stats['sold_calc'] ?? 0);
            if ($lockedCalc !== (int)($item['locked_stock'] ?? 0) || $soldCalc !== (int)($item['sold_stock'] ?? 0)) {
                Db::name('flash_sale_item')->where('id', $itemId)->update([
                    'locked_stock' => $lockedCalc,
                    'sold_stock' => $soldCalc,
                    'update_time' => date('Y-m-d H:i:s'),
                ]);
                $fixedItems++;
            }
            if ($redis && method_exists($redis, 'set')) {
                $available = max(0, (int)$item['total_stock'] - $soldCalc - $lockedCalc);
                try {
                    $redis->set($this->getStockCacheKey($itemId), (string)$available);
                    $fixedRedis++;
                } catch (\Throwable $e) {
                }
            }
        }

        return [
            'released_timeout' => $released,
            'checked_items' => count($items),
            'fixed_items' => $fixedItems,
            'fixed_redis' => $fixedRedis,
            'executed_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * 清理过期库存缓存
     */
    public function cleanupExpiredStockCache(int $graceHours = 24, int $activityLimit = 200, bool $dryRun = false): array
    {
        $graceHours = max(1, min(720, $graceHours));
        $activityLimit = max(1, min(5000, $activityLimit));
        $cutoffTs = time() - ($graceHours * 3600);
        $cutoffTime = date('Y-m-d H:i:s', $cutoffTs);

        $activityIds = Db::name('flash_sale_activity')
            ->where('end_time', '<=', $cutoffTime)
            ->order('id', 'asc')
            ->limit($activityLimit)
            ->column('id');
        $activityIds = array_values(array_unique(array_filter(array_map(static fn($id) => (int)$id, $activityIds), static fn($id) => $id > 0)));
        if (empty($activityIds)) {
            return [
                'grace_hours' => $graceHours,
                'dry_run' => $dryRun ? 1 : 0,
                'checked_activities' => 0,
                'checked_items' => 0,
                'matched_stock_keys' => 0,
                'deleted_stock_keys' => 0,
                'cutoff_time' => $cutoffTime,
                'executed_at' => date('Y-m-d H:i:s'),
            ];
        }

        $itemIds = Db::name('flash_sale_item')
            ->whereIn('activity_id', $activityIds)
            ->column('id');
        $itemIds = array_values(array_unique(array_filter(array_map(static fn($id) => (int)$id, $itemIds), static fn($id) => $id > 0)));

        $matched = 0;
        $deleted = 0;
        $redis = $this->getRedisHandler();
        foreach ($itemIds as $itemId) {
            $stockKey = $this->getStockCacheKey($itemId);
            $exists = false;
            if ($redis && method_exists($redis, 'exists')) {
                try {
                    $exists = (int)$redis->exists($stockKey) > 0;
                } catch (\Throwable $e) {
                    $exists = false;
                }
            } else {
                try {
                    $exists = Cache::has($stockKey);
                } catch (\Throwable $e) {
                    $exists = false;
                }
            }
            if (!$exists) {
                continue;
            }
            $matched++;
            if ($dryRun) {
                continue;
            }
            if ($this->deleteCacheKey($stockKey, $redis)) {
                $deleted++;
            }
        }

        return [
            'grace_hours' => $graceHours,
            'dry_run' => $dryRun ? 1 : 0,
            'checked_activities' => count($activityIds),
            'checked_items' => count($itemIds),
            'matched_stock_keys' => $matched,
            'deleted_stock_keys' => $deleted,
            'cutoff_time' => $cutoffTime,
            'executed_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * 统一返回“排队中”状态，供前端继续轮询结果接口。
     */
    private function buildQueueingResponse(string $requestId): array
    {
        $state = $this->getRequestState($requestId);
        $reserveExpireTime = (string)($state['reserve_expire_time'] ?? '');
        return [
            'request_id' => $requestId,
            'order_id' => (int)($state['order_id'] ?? 0),
            'order_sn' => (string)($state['order_sn'] ?? ''),
            'pay_amount' => (float)($state['pay_amount'] ?? 0),
            'queueing' => 1,
            'message' => (string)($state['message'] ?? '抢购请求已受理，正在排队创建订单'),
            'expire_seconds' => $this->calcExpireSeconds($reserveExpireTime),
            'next_action' => 'query_result',
            'status' => self::ORDER_STATUS_QUEUEING,
            'reserve_expire_time' => $reserveExpireTime,
        ];
    }

    /**
     * 基于 request_state 组装幂等返回，避免重复请求重复下单。
     */
    private function buildResponseByRequestState(array $state, string $requestId): array
    {
        $status = (int)($state['status'] ?? self::ORDER_STATUS_QUEUEING);
        if ($status === 3 || $status === 2) {
            throw new ValidateException((string)($state['message'] ?? '秒杀下单失败'));
        }
        if ($status === self::ORDER_STATUS_QUEUEING) {
            return $this->buildQueueingResponse($requestId);
        }
        if ($status === 0 || $status === 1) {
            $reserveExpireTime = (string)($state['reserve_expire_time'] ?? '');
            return [
                'request_id' => $requestId,
                'order_id' => (int)($state['order_id'] ?? 0),
                'order_sn' => (string)($state['order_sn'] ?? ''),
                'pay_amount' => (float)($state['pay_amount'] ?? 0),
                'expire_seconds' => $this->calcExpireSeconds($reserveExpireTime),
                'next_action' => 'pay',
                'status' => $status,
                'reserve_expire_time' => $reserveExpireTime,
            ];
        }
        return $this->buildQueueingResponse($requestId);
    }

    /**
     * 读取 request_id 对应的短期状态缓存（兼容数组/JSON 两种存储格式）。
     */
    private function getRequestState(string $requestId): array
    {
        if ($requestId === '') {
            return [];
        }
        try {
            $value = Cache::get(self::REQUEST_STATE_PREFIX . $requestId);
            if (is_array($value)) {
                return $value;
            }
            if (is_string($value) && $value !== '') {
                $decoded = json_decode($value, true);
                return is_array($decoded) ? $decoded : [];
            }
        } catch (\Throwable $e) {
        }
        return [];
    }

    /**
     * 写入 request_id 状态缓存，供重试/轮询/幂等返回复用。
     */
    private function setRequestState(string $requestId, array $state): void
    {
        if ($requestId === '') {
            return;
        }
        $state['updated_at'] = date('Y-m-d H:i:s');
        try {
            Cache::set(self::REQUEST_STATE_PREFIX . $requestId, $state, self::REQUEST_STATE_TTL);
        } catch (\Throwable $e) {
        }
    }

    /**
     * 构造普通订单防重键（同用户同商品仅允许一个待支付订单）。
     */
    private function buildOrderPendingLockKey(int $userId, int $goodsType, int $goodsId): string
    {
        return 'u:' . $userId . '|g:' . $goodsType . ':' . $goodsId;
    }

    /**
     * 创建待支付订单并重试处理 order_sn 冲突。
     */
    private function createPendingOrderWithRetry(
        int $userId,
        float $payAmount,
        int $payTypeValue,
        int $goodsType,
        int $goodsId
    ): Order {
        $attempts = 5;
        for ($i = 0; $i < $attempts; $i++) {
            try {
                return Order::create([
                    'order_sn' => $this->generateUniqueOrderSn(),
                    'user_id' => $userId,
                    'total_amount' => $payAmount,
                    'pay_amount' => $payAmount,
                    'pay_type' => $payTypeValue,
                    'status' => 0,
                    'pending_lock_key' => $this->buildOrderPendingLockKey($userId, $goodsType, $goodsId),
                ]);
            } catch (\Throwable $e) {
                if ($this->isOrderSnDuplicateException($e)) {
                    continue;
                }
                throw $e;
            }
        }
        throw new ValidateException('订单创建失败，请稍后重试');
    }

    /**
     * 生成唯一订单号（用于普通订单主表）。
     */
    private function generateUniqueOrderSn(): string
    {
        return 'ORD' . date('YmdHis') . strtoupper(bin2hex(random_bytes(4)));
    }

    /**
     * 判断是否命中“待支付防重键”唯一索引冲突。
     */
    private function isPendingOrderDuplicateException(\Throwable $e): bool
    {
        return $this->isDuplicateForIndexes($e, ['uk_pending_lock_key', 'pending_lock_key']);
    }

    /**
     * 判断是否命中订单号唯一索引冲突。
     */
    private function isOrderSnDuplicateException(\Throwable $e): bool
    {
        return $this->isDuplicateForIndexes($e, ['uk_order_sn', 'order_sn']);
    }

    /**
     * 通用重复键识别：结合 SQLSTATE/驱动码与索引名关键字判断。
     */
    private function isDuplicateForIndexes(\Throwable $e, array $indexNames): bool
    {
        [$sqlState, $driverCode] = $this->extractSqlStateAndDriverCode($e);
        $message = strtolower($e->getMessage());
        $matchedIndex = false;
        foreach ($indexNames as $indexName) {
            $needle = strtolower((string)$indexName);
            if ($needle !== '' && str_contains($message, $needle)) {
                $matchedIndex = true;
                break;
            }
        }
        if (($sqlState === '23000' || $driverCode === 1062) && $matchedIndex) {
            return true;
        }
        return $matchedIndex;
    }

    /**
     * 生成分布式锁持有 token。
     */
    private function createLockToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * 获取 request_id 级别互斥锁，避免同请求并发创建订单。
     */
    private function acquireRequestLock(string $requestId): string
    {
        if ($requestId === '') {
            return '';
        }
        return $this->acquireOwnedLock(
            self::REQUEST_LOCK_PREFIX . $requestId,
            $this->getRequestLockTtlSeconds()
        );
    }

    /**
     * 释放 request_id 锁（仅锁持有者可删除）。
     */
    private function releaseRequestLock(string $requestId, string $lockToken): void
    {
        if ($requestId === '' || $lockToken === '') {
            return;
        }
        $lockKey = self::REQUEST_LOCK_PREFIX . $requestId;
        $redis = $this->getRedisHandler();
        if ($redis && method_exists($redis, 'eval')) {
            try {
                $redis->eval(
                    "if redis.call('GET', KEYS[1]) == ARGV[1] then return redis.call('DEL', KEYS[1]) else return 0 end",
                    [$lockKey, $lockToken],
                    1
                );
                return;
            } catch (\Throwable $e) {
            }
        }
        try {
            $cachedToken = (string)(Cache::get($lockKey) ?? '');
            if ($cachedToken !== '' && hash_equals($cachedToken, $lockToken)) {
                Cache::delete($lockKey);
            }
        } catch (\Throwable $e) {
        }
    }

    /**
     * request_id 锁续期，供长事务/重试阶段保持锁有效。
     */
    private function refreshRequestLock(string $requestId, string $lockToken): bool
    {
        if ($requestId === '' || $lockToken === '') {
            return false;
        }
        return $this->refreshOwnedLock(
            self::REQUEST_LOCK_PREFIX . $requestId,
            $lockToken,
            $this->getRequestLockTtlSeconds()
        );
    }

    /**
     * 回滚“已占库存”状态（数据库 locked_stock + Redis 可售库存）。
     */
    private function rollbackQueuedReservation(int $itemId, int $userId, int $buyCount): void
    {
        if ($itemId > 0 && $buyCount > 0) {
            try {
                Db::name('flash_sale_item')
                    ->where('id', $itemId)
                    ->where('locked_stock', '>=', $buyCount)
                    ->dec('locked_stock', $buyCount)
                    ->update();
            } catch (\Throwable $e) {
            }
        }
        $this->rollbackReservedStock($itemId, $userId, $buyCount);
    }

    /**
     * 校验活动和商品有效性、状态以及时间窗。
     */
    private function assertActivityItemValid(int $activityId, int $itemId, ?array &$activityOut = null, ?array &$itemOut = null): void
    {
        $activity = Db::name('flash_sale_activity')->where('id', $activityId)->find();
        $item = Db::name('flash_sale_item')->where('id', $itemId)->find();
        if (!$activity || !$item || (int)$item['activity_id'] !== $activityId) {
            throw new ValidateException('活动商品不存在');
        }
        if ((int)$activity['status'] !== 2 || (int)$item['status'] !== 1) {
            throw new ValidateException('活动未开始或已结束');
        }
        $now = date('Y-m-d H:i:s');
        if ($now < (string)$activity['start_time'] || $now > (string)$activity['end_time']) {
            throw new ValidateException('活动未开始或已结束');
        }
        if ($activityOut !== null) {
            $activityOut = $activity;
        }
        if ($itemOut !== null) {
            $itemOut = $item;
        }
    }

    /**
     * 构造 token 在缓存中的主键（一次性令牌实体键）。
     */
    private function buildTokenCacheKey(int $userId, int $activityId, int $itemId, string $token): string
    {
        return self::TOKEN_CACHE_PREFIX . $userId . ':' . $activityId . ':' . $itemId . ':' . $token;
    }

    /**
     * 构造 token 绑定关系缓存键（token -> user/activity/item）。
     */
    private function buildTokenBindingCacheKey(string $token): string
    {
        return self::TOKEN_BINDING_PREFIX . $token;
    }

    /**
     * 构造 token 已消费标记缓存键（防止重复使用）。
     */
    private function buildTokenConsumedCacheKey(string $token): string
    {
        return self::TOKEN_CONSUMED_PREFIX . $token;
    }

    /**
     * 构造 token 发放窗口计数键（activity-item 维度）。
     */
    private function buildTokenIssueWindowKey(int $activityId, int $itemId): string
    {
        return self::TOKEN_ISSUE_WINDOW_PREFIX . $activityId . ':' . $itemId;
    }

    /**
     * 申请 token 发放配额，限制“售罄前短时超发”。
     */
    private function tryReserveTokenIssueQuota(int $activityId, int $itemId, int $stockSnapshot, int $tokenTtlSeconds): bool
    {
        $limit = max(1, $stockSnapshot * $this->getTokenIssueStockFactor());
        $ttlSeconds = max(30, $tokenTtlSeconds + 5);
        $redis = $this->getRedisHandler();
        if (!$redis || !method_exists($redis, 'eval')) {
            return true;
        }
        try {
            $ret = (int)$redis->eval(
                "local current = tonumber(redis.call('GET', KEYS[1]) or '0'); local maxv = tonumber(ARGV[1]) or 0; local ttl = tonumber(ARGV[2]) or 30; if maxv <= 0 then return 0 end; if current >= maxv then return 0 end; current = tonumber(redis.call('INCR', KEYS[1]) or '0'); if current == 1 then redis.call('EXPIRE', KEYS[1], ttl) end; if current > maxv then redis.call('DECR', KEYS[1]); return 0 end; return 1",
                [$this->buildTokenIssueWindowKey($activityId, $itemId), (string)$limit, (string)$ttlSeconds],
                1
            );
            return $ret === 1;
        } catch (\Throwable $e) {
        }
        return true;
    }

    /**
     * 回滚 token 发放配额（仅在签发缓存失败时触发）。
     */
    private function releaseTokenIssueQuota(int $activityId, int $itemId): void
    {
        $redis = $this->getRedisHandler();
        if (!$redis || !method_exists($redis, 'decr')) {
            return;
        }
        try {
            $key = $this->buildTokenIssueWindowKey($activityId, $itemId);
            $current = (int)$redis->decr($key);
            if ($current <= 0 && method_exists($redis, 'del')) {
                $redis->del($key);
            }
        } catch (\Throwable $e) {
        }
    }

    /**
     * 记录 token 与请求参数绑定关系，便于识别串参/重放。
     */
    private function writeTokenBinding(string $token, int $userId, int $activityId, int $itemId, int $ttlSeconds): void
    {
        if ($token === '') {
            return;
        }
        try {
            Cache::set(
                $this->buildTokenBindingCacheKey($token),
                [
                    'user_id' => $userId,
                    'activity_id' => $activityId,
                    'item_id' => $itemId,
                ],
                max(30, $ttlSeconds)
            );
        } catch (\Throwable $e) {
        }
    }

    /**
     * 若 token 绑定关系与当前请求不一致，返回可读错误文案。
     */
    private function resolveTokenBindingMismatchMessage(string $token, int $userId, int $activityId, int $itemId): string
    {
        if ($token === '') {
            return '';
        }
        try {
            $value = Cache::get($this->buildTokenBindingCacheKey($token));
            if (!is_array($value) || empty($value)) {
                return '';
            }
            $boundUserId = (int)($value['user_id'] ?? 0);
            $boundActivityId = (int)($value['activity_id'] ?? 0);
            $boundItemId = (int)($value['item_id'] ?? 0);
            if ($boundUserId === $userId && $boundActivityId === $activityId && $boundItemId === $itemId) {
                return '';
            }
            return '抢购令牌与当前请求不匹配，请重新获取令牌';
        } catch (\Throwable $e) {
        }
        return '';
    }

    /**
     * 标记 token 已消费并清理绑定信息。
     */
    private function markTokenConsumed(string $token, int $ttlSeconds): void
    {
        if ($token === '') {
            return;
        }
        try {
            Cache::set($this->buildTokenConsumedCacheKey($token), 1, max(30, $ttlSeconds));
            Cache::delete($this->buildTokenBindingCacheKey($token));
        } catch (\Throwable $e) {
        }
    }

    /**
     * 原子消费 token（删除成功即视为消费成功）。
     */
    private function consumeToken(string $cacheKey, string $token, int $ttlSeconds): bool
    {
        if ($cacheKey === '') {
            return false;
        }
        $redis = $this->getRedisHandler();
        if ($redis && method_exists($redis, 'eval')) {
            try {
                $prefixedCacheKey = $this->buildRedisPrefixedCacheKey($cacheKey);
                $ret = (int)$redis->eval("return redis.call('DEL', KEYS[1])", [$prefixedCacheKey], 1);
                if ($ret === 1) {
                    $this->markTokenConsumed($token, $ttlSeconds);
                    return true;
                }
                // 兼容前缀配置差异：若前缀键未删到，再尝试原始键一次。
                if ($prefixedCacheKey !== $cacheKey) {
                    $retRaw = (int)$redis->eval("return redis.call('DEL', KEYS[1])", [$cacheKey], 1);
                    if ($retRaw === 1) {
                        $this->markTokenConsumed($token, $ttlSeconds);
                        return true;
                    }
                }
                return false;
            } catch (\Throwable $e) {
            }
        }
        try {
            if (!Cache::has($cacheKey)) {
                return false;
            }
            Cache::delete($cacheKey);
            $this->markTokenConsumed($token, $ttlSeconds);
            return true;
        } catch (\Throwable $e) {
        }
        return false;
    }

    /**
     * 适配 Redis 前缀配置，构造带前缀的真实 key。
     */
    private function buildRedisPrefixedCacheKey(string $cacheKey): string
    {
        $prefix = (string)config('cache.stores.redis.prefix', '');
        if ($prefix === '') {
            return $cacheKey;
        }
        return $prefix . $cacheKey;
    }

    /**
     * token 消费短重试，降低极短暂并发冲突带来的失败率。
     */
    private function consumeTokenWithRetry(
        string $cacheKey,
        string $token,
        int $ttlSeconds,
        int $retryTimes = 1,
        int $retrySleepMs = 25
    ): bool {
        if ($this->consumeToken($cacheKey, $token, $ttlSeconds)) {
            return true;
        }
        $retryTimes = max(0, min(3, $retryTimes));
        $retrySleepMs = max(5, min(100, $retrySleepMs));
        for ($i = 0; $i < $retryTimes; $i++) {
            usleep($retrySleepMs * 1000);
            if ($this->consumeToken($cacheKey, $token, $ttlSeconds)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array{reason:string,message:string}
     */
    private function resolveTokenConsumeFailure(string $token, string $cacheKey): array
    {
        $consumed = false;
        $exists = false;
        try {
            $consumed = (bool)Cache::has($this->buildTokenConsumedCacheKey($token));
        } catch (\Throwable $e) {
        }
        try {
            $exists = (bool)Cache::has($cacheKey);
        } catch (\Throwable $e) {
        }
        if ($consumed) {
            return [
                'reason' => 'token_reused',
                'message' => '抢购令牌已被使用，请重新获取令牌',
            ];
        }
        if ($exists) {
            return [
                'reason' => 'token_consume_conflict',
                'message' => '抢购令牌处理中，请稍后重试',
            ];
        }
        return [
            'reason' => 'token_expired_or_missing',
            'message' => '抢购令牌已过期或不存在，请重新获取令牌',
        ];
    }

    /**
     * 计算会场按钮状态（未开始/已结束/售罄/立即抢购）。
     */
    private function resolveButtonStatus(array $row, int $availableStock, string $now): string
    {
        $startTime = (string)($row['start_time'] ?? '');
        $endTime = (string)($row['end_time'] ?? '');
        if ($startTime !== '' && $now < $startTime) {
            return 'not_started';
        }
        if ($endTime !== '' && $now > $endTime) {
            return 'ended';
        }
        if ($availableStock <= 0) {
            return 'sold_out';
        }
        return 'buy_now';
    }

    /**
     * 过滤已下架/不存在的内容，避免前端继续对无效 goods_id 发起购买状态查询。
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function filterUnavailableContentItems(array $rows): array
    {
        if (empty($rows)) {
            return $rows;
        }
        $dramaIds = [];
        $novelIds = [];
        foreach ($rows as $row) {
            $goodsType = (int)($row['goods_type'] ?? 0);
            $goodsId = (int)($row['goods_id'] ?? 0);
            if ($goodsType === 10 && $goodsId > 0) {
                $dramaIds[] = $goodsId;
            } elseif ($goodsType === 20 && $goodsId > 0) {
                $novelIds[] = $goodsId;
            }
        }
        $validDramaMap = [];
        $validNovelMap = [];
        if (!empty($dramaIds)) {
            $ids = array_values(array_unique($dramaIds));
            $validDramaMap = array_flip(array_map(
                'intval',
                Drama::whereIn('id', $ids)->where('status', 1)->column('id')
            ));
        }
        if (!empty($novelIds)) {
            $ids = array_values(array_unique($novelIds));
            $validNovelMap = array_flip(array_map(
                'intval',
                Novel::whereIn('id', $ids)->where('status', 1)->column('id')
            ));
        }
        $filtered = [];
        foreach ($rows as $row) {
            $goodsType = (int)($row['goods_type'] ?? 0);
            $goodsId = (int)($row['goods_id'] ?? 0);
            if ($goodsType === 10) {
                if ($goodsId <= 0 || !isset($validDramaMap[$goodsId])) {
                    continue;
                }
            } elseif ($goodsType === 20) {
                if ($goodsId <= 0 || !isset($validNovelMap[$goodsId])) {
                    continue;
                }
            }
            $filtered[] = $row;
        }
        return $filtered;
    }

    /**
     * 根据商品类型解析快照标题兜底名称。
     */
    private function resolveGoodsName(int $goodsType, int $goodsId): string
    {
        if ($goodsType === 10) {
            return (string)Drama::where('id', $goodsId)->value('title');
        }
        if ($goodsType === 20) {
            return (string)Novel::where('id', $goodsId)->value('title');
        }
        return '秒杀商品';
    }

    /**
     * 检查用户是否已完成购买（防止已购用户重复参与秒杀）。
     */
    private function hasPurchasedConflict(int $userId, int $goodsType, int $goodsId): bool
    {
        if ($userId <= 0 || $goodsId <= 0 || !in_array($goodsType, [10, 20], true)) {
            return false;
        }
        $matchers = ContentPurchaseMatcher::orderGoodsMatchers($goodsType, $goodsId);
        $count = Db::name('order')->alias('o')
            ->join('order_goods g', 'o.id = g.order_id')
            ->where('o.user_id', $userId)
            ->where('o.status', 1)
            ->where(function ($query) use ($matchers) {
                ContentPurchaseMatcher::applyOrderGoodsMatchersWhere($query, 'g', $matchers);
            })
            ->count();
        return $count > 0;
    }

    /**
     * 读取预留库存时长配置（秒）。
     */
    private function getReserveSeconds(): int
    {
        $minutes = (int)env('FLASH_SALE_RESERVE_MINUTES', 5);
        $minutes = max(2, min(5, $minutes));
        return $minutes * 60;
    }

    /**
     * 读取秒杀 token 有效期配置（秒）。
     */
    private function getTokenTtlSeconds(): int
    {
        $ttl = (int)env('FLASH_SALE_TOKEN_TTL_SECONDS', self::TOKEN_TTL_SECONDS);
        return max(30, min(300, $ttl));
    }

    /**
     * token 发放与库存的放大系数（用于短时削峰）。
     */
    private function getTokenIssueStockFactor(): int
    {
        $factor = (int)env('FLASH_SALE_TOKEN_ISSUE_STOCK_FACTOR', 2);
        return max(1, min(10, $factor));
    }

    /**
     * 读取 token 消费重试次数配置。
     */
    private function getTokenConsumeRetryTimes(): int
    {
        $times = (int)env('FLASH_SALE_TOKEN_CONSUME_RETRY_TIMES', self::TOKEN_CONSUME_RETRY_TIMES);
        return max(0, min(3, $times));
    }

    /**
     * 读取 token 消费重试间隔配置（毫秒）。
     */
    private function getTokenConsumeRetrySleepMs(): int
    {
        $sleepMs = (int)env('FLASH_SALE_TOKEN_CONSUME_RETRY_SLEEP_MS', self::TOKEN_CONSUME_RETRY_SLEEP_MS);
        return max(5, min(100, $sleepMs));
    }

    /**
     * 读取队列发布重试次数配置。
     */
    private function getQueuePublishRetryTimes(): int
    {
        $times = (int)env('FLASH_SALE_QUEUE_PUBLISH_RETRY_TIMES', self::QUEUE_PUBLISH_RETRY_TIMES);
        return max(0, min(5, $times));
    }

    /**
     * 读取队列发布重试间隔配置（毫秒）。
     */
    private function getQueuePublishRetrySleepMs(): int
    {
        $sleepMs = (int)env('FLASH_SALE_QUEUE_PUBLISH_RETRY_SLEEP_MS', self::QUEUE_PUBLISH_RETRY_SLEEP_MS);
        return max(10, min(300, $sleepMs));
    }

    /**
     * 发布下单消息并按配置进行短重试。
     */
    private function publishQueueWithRetry(array $payload): bool
    {
        if (FlashSaleOrderQueueService::publish($payload)) {
            return true;
        }
        $retryTimes = $this->getQueuePublishRetryTimes();
        $retrySleepMs = $this->getQueuePublishRetrySleepMs();
        for ($i = 0; $i < $retryTimes; $i++) {
            usleep($retrySleepMs * 1000);
            if (FlashSaleOrderQueueService::publish($payload)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 计算“距离库存预留过期”剩余秒数（支持 create_time 兜底）。
     */
    private function calcExpireSeconds(string $reserveExpireTime, string $fallbackCreateTime = ''): int
    {
        $expireTs = $this->parseTimeToTs($reserveExpireTime);
        if ($expireTs <= 0 && $fallbackCreateTime !== '') {
            $createTs = $this->parseTimeToTs($fallbackCreateTime);
            if ($createTs > 0) {
                $expireTs = $createTs + $this->getReserveSeconds();
            }
        }
        if ($expireTs <= 0) {
            return $this->getReserveSeconds();
        }
        return max(0, $expireTs - time());
    }

    /**
     * 检测 flash_sale_order 是否存在 reserve_expire_time 字段。
     */
    private function hasReserveExpireField(): bool
    {
        if (self::$hasReserveExpireField !== null) {
            return self::$hasReserveExpireField;
        }
        try {
            $fields = Db::name('flash_sale_order')->getFields();
            self::$hasReserveExpireField = array_key_exists('reserve_expire_time', $fields);
        } catch (\Throwable $e) {
            self::$hasReserveExpireField = false;
        }
        return self::$hasReserveExpireField;
    }

    /**
     * 下单接口限频（用户/IP/设备三级窗口）。
     */
    private function assertCreateRateLimit(int $userId, int $activityId, int $itemId, string $clientIp, string $deviceId): void
    {
        $scene = $activityId . ':' . $itemId;
        $userLimit = max(1, (int)env('FLASH_SALE_CREATE_USER_LIMIT_PER_MIN', 20));
        $this->assertRateWindow(
            'flash:sale:create:user:' . $scene . ':' . $userId,
            $userLimit,
            '操作过于频繁，请稍后重试',
            'user_rate_limit',
            ['user_id' => $userId, 'activity_id' => $activityId, 'item_id' => $itemId, 'client_ip' => $clientIp, 'device_id' => $deviceId]
        );

        if ($clientIp !== '') {
            $ipLimit = max(1, (int)env('FLASH_SALE_CREATE_IP_LIMIT_PER_MIN', 60));
            $this->assertRateWindow(
                'flash:sale:create:ip:' . $scene . ':' . $clientIp,
                $ipLimit,
                '请求过于频繁，请稍后重试',
                'ip_rate_limit',
                ['user_id' => $userId, 'activity_id' => $activityId, 'item_id' => $itemId, 'client_ip' => $clientIp, 'device_id' => $deviceId]
            );
        }

        if ($deviceId !== '') {
            $deviceLimit = max(1, (int)env('FLASH_SALE_CREATE_DEVICE_LIMIT_PER_MIN', 40));
            $this->assertRateWindow(
                'flash:sale:create:device:' . $scene . ':' . md5($deviceId),
                $deviceLimit,
                '设备请求过于频繁，请稍后重试',
                'device_rate_limit',
                ['user_id' => $userId, 'activity_id' => $activityId, 'item_id' => $itemId, 'client_ip' => $clientIp, 'device_id' => $deviceId]
            );
        }
    }

    /**
     * 领 token 接口限频（用户/IP/设备三级窗口）。
     */
    private function assertTokenRateLimit(int $userId, int $activityId, int $itemId, string $clientIp, string $deviceId): void
    {
        $scene = $activityId . ':' . $itemId;
        $userLimit = max(1, (int)env('FLASH_SALE_TOKEN_USER_LIMIT_PER_MIN', 30));
        $this->assertRateWindow(
            'flash:sale:token:user:' . $scene . ':' . $userId,
            $userLimit,
            '操作过于频繁，请稍后重试',
            'token_user_rate_limit',
            ['user_id' => $userId, 'activity_id' => $activityId, 'item_id' => $itemId, 'client_ip' => $clientIp, 'device_id' => $deviceId]
        );
        if ($clientIp !== '') {
            $ipLimit = max(1, (int)env('FLASH_SALE_TOKEN_IP_LIMIT_PER_MIN', 120));
            $this->assertRateWindow(
                'flash:sale:token:ip:' . $scene . ':' . $clientIp,
                $ipLimit,
                '请求过于频繁，请稍后重试',
                'token_ip_rate_limit',
                ['user_id' => $userId, 'activity_id' => $activityId, 'item_id' => $itemId, 'client_ip' => $clientIp, 'device_id' => $deviceId]
            );
        }
        if ($deviceId !== '') {
            $deviceLimit = max(1, (int)env('FLASH_SALE_TOKEN_DEVICE_LIMIT_PER_MIN', 80));
            $this->assertRateWindow(
                'flash:sale:token:device:' . $scene . ':' . md5($deviceId),
                $deviceLimit,
                '设备请求过于频繁，请稍后重试',
                'token_device_rate_limit',
                ['user_id' => $userId, 'activity_id' => $activityId, 'item_id' => $itemId, 'client_ip' => $clientIp, 'device_id' => $deviceId]
            );
        }
    }

    /**
     * 通用一分钟滑窗计数校验（超限抛错并记风控日志）。
     */
    private function assertRateWindow(string $key, int $limit, string $errorMessage, string $reason, array $context = []): void
    {
        try {
            $current = 0;
            $redis = $this->getRedisHandler();
            if ($redis && method_exists($redis, 'incr')) {
                $current = (int)$redis->incr($key);
                if (method_exists($redis, 'ttl') && method_exists($redis, 'expire')) {
                    $ttl = (int)$redis->ttl($key);
                    // 兼容历史脏数据：若发现无过期时间，则补齐 65 秒 TTL
                    if ($ttl < 0) {
                        $redis->expire($key, 65);
                    }
                }
            } else {
                $current = (int)Cache::inc($key, 1);
                if ($current <= 1) {
                    Cache::expire($key, 65);
                }
            }
            if ($current > $limit) {
                $this->recordRiskEvent($reason, $context + ['extra' => ['cache_key' => $key, 'limit' => $limit, 'current' => $current]]);
                throw new ValidateException($errorMessage);
            }
        } catch (ValidateException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->recordRiskEvent('rate_limit_guard_error', $context + [
                'extra' => [
                    'cache_key' => $key,
                    'reason' => $reason,
                    'error' => $e->getMessage(),
                ],
            ]);
            // 默认 fail-close；仅显式开启时允许 fail-open。
            $failOpen = (int)env('FLASH_SALE_RATE_LIMIT_FAIL_OPEN', 0) === 1;
            if (!$failOpen) {
                throw new ValidateException(self::MSG_SYSTEM_BUSY_RETRY);
            }
        }
    }

    /**
     * 命中黑名单则阻断下单。
     */
    private function assertCreateBlacklist(int $userId, int $activityId, int $itemId, string $clientIp, string $deviceId): void
    {
        $black = $this->findCreateBlacklistHit($userId, $clientIp, $deviceId);
        if (!empty($black)) {
            $this->recordRiskEvent('blacklist_blocked', [
                'user_id' => $userId,
                'activity_id' => $activityId,
                'item_id' => $itemId,
                'client_ip' => $clientIp,
                'device_id' => $deviceId,
                'target_type' => (string)$black['target_type'],
                'target_value' => (string)$black['target_value'],
                'extra' => ['blacklist_id' => (int)$black['id'], 'note' => (string)($black['note'] ?? '')],
            ]);
            throw new ValidateException('当前账号/设备网络受限，请稍后重试');
        }
    }

    /**
     * 记录风控命中日志（带采样）。
     */
    private function recordRiskEvent(string $reason, array $context = []): void
    {
        if (!$this->shouldRecordRiskEvent($reason, $context)) {
            return;
        }
        try {
            $row = $this->buildRiskLogRow($reason, $context);
            if ($row === []) {
                return;
            }
            if ($this->isRiskLogAsyncEnabled() && $this->enqueueRiskLogRow($row)) {
                return;
            }
            $this->persistRiskLogRow($row);
        } catch (\Throwable $e) {
        }
    }

    /**
     * 组装风控日志落库结构。
     *
     * @return array<string, mixed>
     */
    private function buildRiskLogRow(string $reason, array $context = []): array
    {
        $extra = $context['extra'] ?? [];
        if (!is_array($extra)) {
            $extra = ['raw' => (string)$extra];
        }
        $extraJson = json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($extraJson) || $extraJson === '') {
            $extraJson = '{}';
        }
        if (strlen($extraJson) > self::MAX_RISK_EXTRA_JSON_LENGTH) {
            $extraJson = substr($extraJson, 0, self::MAX_RISK_EXTRA_JSON_LENGTH);
        }
        return [
            'scene' => 'create_order',
            'reason' => substr(trim($reason) === '' ? 'risk_blocked' : $reason, 0, 64),
            'user_id' => (int)($context['user_id'] ?? 0),
            'activity_id' => (int)($context['activity_id'] ?? 0),
            'item_id' => (int)($context['item_id'] ?? 0),
            'client_ip' => $this->normalizeClientIp((string)($context['client_ip'] ?? '')),
            'device_id' => $this->normalizeDeviceId((string)($context['device_id'] ?? '')),
            'extra_json' => $extraJson,
            'create_time' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * 写入风控日志数据库。
     *
     * @param array<string, mixed> $row
     */
    private function persistRiskLogRow(array $row): void
    {
        Db::name('flash_sale_risk_log')->insert($row);
    }

    /**
     * 判断是否开启风控日志异步写入。
     */
    private function isRiskLogAsyncEnabled(): bool
    {
        return (int)env('FLASH_SALE_RISK_LOG_ASYNC', 0) === 1;
    }

    /**
     * 将风控日志推入 Redis 队列，失败时返回 false 供上层回退同步写入。
     *
     * @param array<string, mixed> $row
     */
    private function enqueueRiskLogRow(array $row): bool
    {
        $redis = $this->getRedisHandler();
        if (!$redis || !method_exists($redis, 'lPush')) {
            return false;
        }
        $payload = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($payload) || $payload === '') {
            return false;
        }
        $maxLen = max(1000, min(1000000, (int)env('FLASH_SALE_RISK_LOG_QUEUE_MAX_LEN', 200000)));
        try {
            $redis->lPush(self::RISK_LOG_QUEUE_KEY, $payload);
            if (method_exists($redis, 'lTrim')) {
                $redis->lTrim(self::RISK_LOG_QUEUE_KEY, 0, $maxLen - 1);
            }
            return true;
        } catch (\Throwable $e) {
        }
        return false;
    }

    /**
     * 统一 precheck 返回结构。
     */
    private function buildPrecheckResult(bool $eligible, string $reasonCode, string $message, array $extra = []): array
    {
        return [
            'eligible' => $eligible,
            'reason_code' => $reasonCode,
            'message' => $message,
            'server_time' => time(),
            'extra' => $extra,
        ];
    }

    /**
     * request_id 基础合法性校验（字符集、长度、熵）。
     */
    private function isValidRequestId(string $requestId): bool
    {
        if ($requestId === '') {
            return false;
        }
        if (strlen($requestId) < 8 || strlen($requestId) > 64) {
            return false;
        }
        if (preg_match('/^[A-Za-z0-9_-]+$/', $requestId) !== 1) {
            return false;
        }
        return $this->hasSufficientRequestIdEntropy($requestId);
    }

    /**
     * request_id 熵校验（严格模式可配置）。
     */
    private function hasSufficientRequestIdEntropy(string $requestId): bool
    {
        $strict = (int)env('FLASH_SALE_REQUEST_ID_STRICT', 1) === 1;
        if (!$strict) {
            return true;
        }
        $minLength = max(8, min(64, (int)env('FLASH_SALE_REQUEST_ID_MIN_LENGTH', 12)));
        if (strlen($requestId) < $minLength) {
            return false;
        }
        $classes = 0;
        if (preg_match('/[a-z]/', $requestId) === 1) {
            $classes++;
        }
        if (preg_match('/[A-Z]/', $requestId) === 1) {
            $classes++;
        }
        if (preg_match('/[0-9]/', $requestId) === 1) {
            $classes++;
        }
        if (preg_match('/[_-]/', $requestId) === 1) {
            $classes++;
        }
        if ($classes < 2) {
            return false;
        }
        return count(array_unique(str_split($requestId))) >= 6;
    }

    /**
     * request_id 时效校验，防止旧请求长时间重放。
     */
    private function assertRequestIdWindow(string $requestId): void
    {
        $maxAge = max(60, min(86400, (int)env('FLASH_SALE_REQUEST_ID_MAX_AGE_SECONDS', 1800)));
        $cacheTtl = max($maxAge, 300);
        $cacheKey = self::REQUEST_BORN_PREFIX . $requestId;
        $now = time();
        $redis = $this->getRedisHandler();
        if ($redis && method_exists($redis, 'eval')) {
            try {
                $ret = $redis->eval(
                    "local ok = redis.call('SET', KEYS[1], ARGV[1], 'NX', 'EX', tonumber(ARGV[2])); if ok then return 1 end local born = redis.call('GET', KEYS[1]); if not born then return 0 end return tonumber(born) or 0",
                    [$cacheKey, (string)$now, (string)$cacheTtl],
                    1
                );
                $code = (int)$ret;
                if ($code === 1 || $code === 0) {
                    return;
                }
                $bornTs = $code;
                if ($bornTs > 0 && ($now - $bornTs) > $maxAge) {
                    throw new ValidateException('request_id 已过期，请刷新后重试');
                }
                return;
            } catch (ValidateException $e) {
                throw $e;
            } catch (\Throwable $e) {
            }
        }
        try {
            $born = (int)(Cache::get($cacheKey) ?? 0);
            if ($born <= 0) {
                Cache::set($cacheKey, $now, $cacheTtl);
                return;
            }
            if (($now - $born) > $maxAge) {
                throw new ValidateException('request_id 已过期，请刷新后重试');
            }
        } catch (ValidateException $e) {
            throw $e;
        } catch (\Throwable $e) {
        }
    }

    /**
     * token 格式校验（32位 hex）。
     */
    private function isValidToken(string $token): bool
    {
        return preg_match('/^[a-fA-F0-9]{32}$/', $token) === 1;
    }

    /**
     * 规范化客户端 IP，限制最大长度。
     */
    private function normalizeClientIp(string $clientIp): string
    {
        return substr(trim($clientIp), 0, self::MAX_CLIENT_IP_LENGTH);
    }

    /**
     * 规范化设备标识，限制最大长度。
     */
    private function normalizeDeviceId(string $deviceId): string
    {
        return substr(trim($deviceId), 0, self::MAX_DEVICE_ID_LENGTH);
    }

    /**
     * 判断当前风控事件是否需要落库（支持采样）。
     */
    private function shouldRecordRiskEvent(string $reason, array $context = []): bool
    {
        $reason = trim($reason);
        if ($reason !== '' && str_contains($reason, 'blacklist')) {
            return true;
        }
        $samplePercent = (int)env('FLASH_SALE_RISK_LOG_SAMPLE_PERCENT', 100);
        $samplePercent = max(0, min(100, $samplePercent));
        if ($samplePercent >= 100) {
            return true;
        }
        if ($samplePercent <= 0) {
            return false;
        }
        $seed = implode('|', [
            $reason,
            (string)($context['user_id'] ?? 0),
            (string)($context['activity_id'] ?? 0),
            (string)($context['item_id'] ?? 0),
            $this->normalizeClientIp((string)($context['client_ip'] ?? '')),
            $this->normalizeDeviceId((string)($context['device_id'] ?? '')),
            date('YmdHi'),
        ]);
        $bucket = (int)((int)sprintf('%u', crc32($seed)) % 100);
        return $bucket < $samplePercent;
    }

    /**
     * 查询下单黑名单命中项（user/ip/device）。
     *
     * @return array<string, mixed>
     */
    private function findCreateBlacklistHit(int $userId, string $clientIp, string $deviceId): array
    {
        $targets = [
            ['type' => 'user', 'value' => (string)$userId],
        ];
        if ($clientIp !== '') {
            $targets[] = ['type' => 'ip', 'value' => $clientIp];
        }
        if ($deviceId !== '') {
            $targets[] = ['type' => 'device', 'value' => $deviceId];
        }
        $now = date('Y-m-d H:i:s');
        foreach ($targets as $target) {
            $black = Db::name('flash_sale_risk_blacklist')
                ->where('target_type', $target['type'])
                ->where('target_value', $target['value'])
                ->where('status', 1)
                ->where(function ($query) use ($now) {
                    $query->whereNull('expire_time')->whereOr('expire_time', '>', $now);
                })
                ->where(function ($query) {
                    $query->where('scene', 'all')->whereOr('scene', 'create_order');
                })
                ->order('id', 'desc')
                ->find();
            if ($black) {
                return (array)$black;
            }
        }
        return [];
    }

    /**
     * 仅做限频预览检查（不计数），用于 precheck 返回友好提示。
     *
     * @return array<string, mixed>
     */
    private function checkCreateRateLimitPreview(int $userId, int $activityId, int $itemId, string $clientIp, string $deviceId): array
    {
        $scene = $activityId . ':' . $itemId;
        $rules = [[
            'key' => 'flash:sale:create:user:' . $scene . ':' . $userId,
            'limit' => max(1, (int)env('FLASH_SALE_CREATE_USER_LIMIT_PER_MIN', 20)),
            'reason' => 'user_rate_limit',
            'message' => '操作过于频繁，请稍后重试',
        ]];
        if ($clientIp !== '') {
            $rules[] = [
                'key' => 'flash:sale:create:ip:' . $scene . ':' . $clientIp,
                'limit' => max(1, (int)env('FLASH_SALE_CREATE_IP_LIMIT_PER_MIN', 60)),
                'reason' => 'ip_rate_limit',
                'message' => '请求过于频繁，请稍后重试',
            ];
        }
        if ($deviceId !== '') {
            $rules[] = [
                'key' => 'flash:sale:create:device:' . $scene . ':' . md5($deviceId),
                'limit' => max(1, (int)env('FLASH_SALE_CREATE_DEVICE_LIMIT_PER_MIN', 40)),
                'reason' => 'device_rate_limit',
                'message' => '设备请求过于频繁，请稍后重试',
            ];
        }
        foreach ($rules as $rule) {
            try {
                $current = (int)(Cache::get((string)$rule['key']) ?? 0);
                if ($current >= (int)$rule['limit']) {
                    return [
                        'reason' => (string)$rule['reason'],
                        'message' => (string)$rule['message'],
                        'current' => $current,
                        'limit' => (int)$rule['limit'],
                    ];
                }
            } catch (\Throwable $e) {
            }
        }
        return [];
    }

    /**
     * 将订单过期时间写入 Redis 延迟释放队列（zset）。
     */
    private function scheduleReserveRelease(int $orderId, string $reserveExpireTime): void
    {
        $orderId = (int)$orderId;
        if ($orderId <= 0) {
            return;
        }
        $score = $this->parseTimeToTs($reserveExpireTime);
        if ($score <= 0) {
            $score = time() + $this->getReserveSeconds();
        }
        $redis = $this->getRedisHandler();
        if ($redis && method_exists($redis, 'zAdd')) {
            try {
                $redis->zAdd(self::RELEASE_ZSET_KEY, $score, (string)$orderId);
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * 从 Redis 延迟释放队列移除订单。
     */
    private function removeReserveReleaseSchedule(int $orderId): void
    {
        $orderId = (int)$orderId;
        if ($orderId <= 0) {
            return;
        }
        $redis = $this->getRedisHandler();
        if ($redis && method_exists($redis, 'zRem')) {
            try {
                $redis->zRem(self::RELEASE_ZSET_KEY, (string)$orderId);
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * 在 Redis 原子预占库存，并标记用户待支付占位。
     */
    private function reserveStockWithRedis(int $itemId, int $userId, int $buyCount, int $fallbackStock): bool
    {
        $redis = $this->getRedisHandler();
        if (!$redis || !method_exists($redis, 'eval')) {
            return false;
        }
        $stockKey = $this->getStockCacheKey($itemId);
        $pendingKey = $this->getPendingCacheKey($itemId, $userId);
        try {
            if (method_exists($redis, 'setNx')) {
                $redis->setNx($stockKey, (string)max(0, $fallbackStock));
            }
            $ret = (int)$redis->eval($this->getReserveStockLuaScript(), [$stockKey, $pendingKey, (string)$buyCount, (string)($this->getReserveSeconds() + 30)], 2);
            if ($ret === 1) {
                return true;
            }
            if ($ret === -1) {
                throw new ValidateException('库存不足');
            }
            if ($ret === -2) {
                throw new ValidateException('你已有待支付订单，请先完成支付');
            }
            if ($ret === -3 && method_exists($redis, 'set')) {
                $redis->set($stockKey, (string)max(0, $fallbackStock));
                $retry = (int)$redis->eval($this->getReserveStockLuaScript(), [$stockKey, $pendingKey, (string)$buyCount, (string)($this->getReserveSeconds() + 30)], 2);
                if ($retry === 1) {
                    return true;
                }
                if ($retry === -1) {
                    throw new ValidateException('库存不足');
                }
                if ($retry === -2) {
                    throw new ValidateException('你已有待支付订单，请先完成支付');
                }
            }
        } catch (ValidateException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return false;
        }
        return false;
    }

    /**
     * 回滚 Redis 预占库存并清理 pending 标记。
     */
    private function rollbackReservedStock(int $itemId, int $userId, int $buyCount): void
    {
        $redis = $this->getRedisHandler();
        if (!$redis || !method_exists($redis, 'incrBy')) {
            return;
        }
        try {
            $pendingKey = $this->getPendingCacheKey($itemId, $userId);
            if (method_exists($redis, 'exists') && (int)$redis->exists($pendingKey) < 1) {
                return;
            }
            $redis->incrBy($this->getStockCacheKey($itemId), max(0, $buyCount));
            if (method_exists($redis, 'del')) {
                $redis->del($pendingKey);
            }
        } catch (\Throwable $e) {
        }
    }

    /**
     * 统一回滚 createOrder 阶段的 DB/Redis 库存预占。
     */
    private function rollbackCreateOrderReservation(int $itemId, int $userId, int $buyCount, bool &$lockedReserved, bool &$redisReserved): void
    {
        if ($lockedReserved) {
            Db::name('flash_sale_item')->where('id', $itemId)->dec('locked_stock', $buyCount)->update();
            $lockedReserved = false;
        }
        if ($redisReserved) {
            $this->rollbackReservedStock($itemId, $userId, $buyCount);
            $redisReserved = false;
        }
    }

    /**
     * 构造 createOrder 的排队响应，并写入排队态缓存与 pending 标记。
     */
    private function buildCreateOrderQueueingResponse(string $requestId, int $userId, int $itemId, string $payType, array $queuePayload): array
    {
        // 写入排队态缓存，供前端 result 接口读取。
        $this->setRequestState($requestId, [
            'status' => self::ORDER_STATUS_QUEUEING,
            'user_id' => $userId,
            'message' => self::MSG_QUEUEING_ACCEPTED,
            'reserve_expire_time' => (string)($queuePayload['reserve_expire_time'] ?? ''),
            'order_id' => 0,
        ]);
        // 标记用户-商品处理中，抑制短时间重复点击。
        $this->setUserItemPendingMarker(
            (int)($queuePayload['item_id'] ?? $itemId),
            (int)($queuePayload['user_id'] ?? $userId),
            $this->getReserveSeconds() + 30
        );
        return [
            'request_id' => $requestId,
            'order_id' => 0,
            'order_sn' => '',
            'pay_amount' => (float)($queuePayload['pay_amount'] ?? 0),
            'pay_type' => $payType,
            'queueing' => 1,
            'message' => self::MSG_QUEUEING_ACCEPTED,
            'expire_seconds' => $this->getReserveSeconds(),
            'next_action' => 'query_result',
            'status' => self::ORDER_STATUS_QUEUEING,
            'reserve_expire_time' => (string)($queuePayload['reserve_expire_time'] ?? ''),
        ];
    }

    /**
     * 记录 token 消费失败的风控事件与结构化告警日志。
     */
    private function recordTokenConsumeFailure(string $requestId, int $userId, int $activityId, int $itemId, string $token, string $cacheKey, array $tokenFailure): void
    {
        $reason = (string)($tokenFailure['reason'] ?? '');
        $tokenPrefix = substr($token, 0, 8);
        $this->recordRiskEvent($reason, [
            'user_id' => $userId,
            'activity_id' => $activityId,
            'item_id' => $itemId,
            'extra' => [
                'request_id' => $requestId,
                'token_prefix' => $tokenPrefix,
                'cache_key' => $cacheKey,
            ],
        ]);
        $tokenSummary = sprintf(
            'flash-sale token consume failed | reason=%s request_id=%s user_id=%d activity_id=%d item_id=%d token_prefix=%s',
            $reason,
            $requestId,
            $userId,
            $activityId,
            $itemId,
            $tokenPrefix
        );
        Log::warning('flash-sale token consume failed', [
            'summary' => $tokenSummary,
            'reason' => $reason,
            'request_id' => $requestId,
            'user_id' => $userId,
            'activity_id' => $activityId,
            'item_id' => $itemId,
            'token_prefix' => $tokenPrefix,
        ]);
    }

    /**
     * 记录队列发布重试耗尽的结构化告警日志。
     */
    private function logQueuePublishExhaustedRetries(string $requestId, int $userId, int $activityId, int $itemId): void
    {
        $lastPublishError = FlashSaleOrderQueueService::getLastPublishError();
        $summary = sprintf(
            'flash-sale queue publish exhausted retries | request_id=%s user_id=%d activity_id=%d item_id=%d queue=%s reason=%s message=%s',
            $requestId,
            $userId,
            $activityId,
            $itemId,
            (string)($lastPublishError['queue'] ?? ''),
            (string)($lastPublishError['reason'] ?? ''),
            (string)($lastPublishError['message'] ?? '')
        );
        Log::warning('flash-sale queue publish exhausted retries', [
            'summary' => $summary,
            'request_id' => $requestId,
            'user_id' => $userId,
            'activity_id' => $activityId,
            'item_id' => $itemId,
            'queue' => (string)($lastPublishError['queue'] ?? ''),
            'reason' => (string)($lastPublishError['reason'] ?? ''),
            'message' => (string)($lastPublishError['message'] ?? ''),
        ]);
    }

    /**
     * 统一记录 createOrder 失败原因（结构化日志 + Redis 计数器）。
     */
    private function recordCreateOrderFailureReason(string $reason, string $requestId, int $userId, int $activityId, int $itemId, string $message = ''): void
    {
        $normalizedReason = strtolower(trim($reason));
        if ($normalizedReason === '' || preg_match('/^[a-z0-9_.-]{2,64}$/', $normalizedReason) !== 1) {
            $normalizedReason = 'create_order_failed';
        }
        $logContext = [
            'reason' => $normalizedReason,
            'request_id' => $requestId,
            'user_id' => $userId,
            'activity_id' => $activityId,
            'item_id' => $itemId,
            'message' => $message,
        ];
        if ($normalizedReason === 'stock_not_enough') {
            // 高频场景改为采样日志，避免 warning 日志洪峰影响排障信噪比。
            $sampleRate = $this->getStockNotEnoughLogSampleRate();
            if ($this->hitSampleRate($sampleRate)) {
                $logContext['sample_rate'] = $sampleRate;
                Log::info('flash-sale create-order failed (sampled)', $logContext);
            }
        } else {
            Log::warning('flash-sale create-order failed', $logContext);
        }
        $redis = $this->getRedisHandler();
        if (!$redis || !method_exists($redis, 'incr')) {
            return;
        }
        $metricKey = self::CREATE_FAIL_REASON_METRIC_PREFIX . date('Ymd') . ':' . $normalizedReason;
        try {
            $redis->incr($metricKey);
            if (method_exists($redis, 'expire') && method_exists($redis, 'ttl')) {
                $ttl = (int)$redis->ttl($metricKey);
                if ($ttl < 1) {
                    $redis->expire($metricKey, 7 * 24 * 3600);
                }
            }
        } catch (\Throwable $e) {
        }
    }

    /**
     * 记录 issueToken 失败原因（结构化日志 + Redis 计数器）。
     */
    private function recordTokenIssueFailureReason(string $reason, int $userId, int $activityId, int $itemId, string $message = ''): void
    {
        $normalizedReason = strtolower(trim($reason));
        if ($normalizedReason === '' || preg_match('/^[a-z0-9_.-]{2,64}$/', $normalizedReason) !== 1) {
            $normalizedReason = 'token_issue_failed';
        }
        Log::warning('flash-sale issue-token failed', [
            'reason' => $normalizedReason,
            'user_id' => $userId,
            'activity_id' => $activityId,
            'item_id' => $itemId,
            'message' => $message,
        ]);
        $redis = $this->getRedisHandler();
        if (!$redis || !method_exists($redis, 'incr')) {
            return;
        }
        $metricKey = self::TOKEN_ISSUE_FAIL_REASON_METRIC_PREFIX . date('Ymd') . ':' . $normalizedReason;
        try {
            $redis->incr($metricKey);
            if (method_exists($redis, 'expire') && method_exists($redis, 'ttl')) {
                $ttl = (int)$redis->ttl($metricKey);
                if ($ttl < 1) {
                    $redis->expire($metricKey, 7 * 24 * 3600);
                }
            }
        } catch (\Throwable $e) {
        }
    }

    /**
     * 高频库存不足日志采样率（0-1）。
     */
    private function getStockNotEnoughLogSampleRate(): float
    {
        $rate = (float)env('FLASH_SALE_STOCK_NOT_ENOUGH_LOG_SAMPLE_RATE', 0.02);
        if ($rate < 0) {
            return 0.0;
        }
        if ($rate > 1) {
            return 1.0;
        }
        return $rate;
    }

    /**
     * 按采样率决定是否记录日志。
     */
    private function hitSampleRate(float $rate): bool
    {
        if ($rate <= 0) {
            return false;
        }
        if ($rate >= 1) {
            return true;
        }
        return mt_rand(1, 1000000) <= (int)round($rate * 1000000);
    }

    /**
     * 将 createOrder 异常映射为稳定的失败原因代码。
     */
    private function resolveCreateOrderFailureReason(\Throwable $e): string
    {
        if (!$e instanceof ValidateException) {
            return 'create_order_exception';
        }
        $message = trim($e->getMessage());
        if ($message === self::MSG_QUEUE_BUSY_RETRY) {
            return 'queue_publish_failed';
        }
        if ($message === self::MSG_PENDING_ORDER_EXISTS) {
            return 'pending_order_exists';
        }
        if ($message === '请求处理中，请稍后重试') {
            return 'lock_refresh_failed';
        }
        if ($message === '库存不足') {
            return 'stock_not_enough';
        }
        if ($message === '超过限购数量') {
            return 'purchase_limit_exceeded';
        }
        if ($message === '活动未开始或已结束') {
            return 'activity_inactive';
        }
        if ($message === '活动商品不存在') {
            return 'activity_item_not_found';
        }
        if ($message === '秒杀价格异常') {
            return 'invalid_price';
        }
        if ($message === '你已购买该内容，无需重复抢购') {
            return 'already_owned';
        }
        return 'validate_failed';
    }

    /**
     * 处理“超时后晚到支付”场景：扣回之前回补的可售库存。
     */
    private function consumeReservedStockAfterLatePaid(int $itemId, int $buyCount): void
    {
        if ($itemId <= 0 || $buyCount <= 0) {
            return;
        }
        $redis = $this->getRedisHandler();
        if (!$redis || !method_exists($redis, 'eval')) {
            return;
        }
        $stockKey = $this->getStockCacheKey($itemId);
        try {
            $redis->eval(
                "local stock = tonumber(redis.call('GET', KEYS[1]) or '-1'); if stock < 0 then return 0 end; local qty = tonumber(ARGV[1]) or 0; if qty <= 0 then return 0 end; local next = stock - qty; if next < 0 then next = 0 end; redis.call('SET', KEYS[1], tostring(next)); return 1",
                [$stockKey, (string)$buyCount],
                1
            );
        } catch (\Throwable $e) {
        }
    }

    /**
     * 清理用户在某商品下的 Redis 待支付占位状态。
     */
    private function clearPendingByUser(int $itemId, int $userId): void
    {
        $redis = $this->getRedisHandler();
        if ($redis && method_exists($redis, 'del')) {
            try {
                $redis->del($this->getPendingCacheKey($itemId, $userId));
            } catch (\Throwable $e) {
            }
        }
        $this->clearUserItemPendingMarker($itemId, $userId);
    }

    /**
     * 商品可售库存缓存键。
     */
    private function getStockCacheKey(int $itemId): string
    {
        return self::STOCK_KEY_PREFIX . $itemId;
    }

    /**
     * 读取 Redis 可售库存快照；返回 null 表示无法判定（不中断主流程）。
     */
    private function getRedisAvailableStockSnapshot(int $itemId): ?int
    {
        if ($itemId <= 0) {
            return null;
        }
        $redis = $this->getRedisHandler();
        if (!$redis || !method_exists($redis, 'get')) {
            return null;
        }
        try {
            $raw = $redis->get($this->getStockCacheKey($itemId));
            if ($raw === null || $raw === false || $raw === '') {
                return null;
            }
            if (!is_numeric((string)$raw)) {
                return null;
            }
            return max(0, (int)$raw);
        } catch (\Throwable $e) {
        }
        return null;
    }

    /**
     * 用户-商品待支付占位缓存键。
     */
    private function getPendingCacheKey(int $itemId, int $userId): string
    {
        return self::PENDING_KEY_PREFIX . $itemId . ':' . $userId;
    }

    /**
     * 用户-活动-商品互斥锁 key。
     */
    private function buildUserItemLockKey(int $activityId, int $itemId, int $userId): string
    {
        return self::USER_ITEM_LOCK_PREFIX . $activityId . ':' . $itemId . ':' . $userId;
    }

    /**
     * 用户-商品待处理标记 key（防并发风暴）。
     */
    private function buildUserItemPendingKey(int $itemId, int $userId): string
    {
        return self::USER_ITEM_PENDING_PREFIX . $itemId . ':' . $userId;
    }

    /**
     * 获取用户-商品粒度互斥锁。
     */
    private function acquireUserItemLock(int $activityId, int $itemId, int $userId): string
    {
        return $this->acquireOwnedLock(
            $this->buildUserItemLockKey($activityId, $itemId, $userId),
            $this->getUserItemLockTtlSeconds()
        );
    }

    /**
     * 释放用户-商品粒度互斥锁。
     */
    private function releaseUserItemLock(int $activityId, int $itemId, int $userId, string $lockToken): void
    {
        if ($lockToken === '') {
            return;
        }
        $lockKey = $this->buildUserItemLockKey($activityId, $itemId, $userId);
        $redis = $this->getRedisHandler();
        if ($redis && method_exists($redis, 'eval')) {
            try {
                $redis->eval(
                    "if redis.call('GET', KEYS[1]) == ARGV[1] then return redis.call('DEL', KEYS[1]) else return 0 end",
                    [$lockKey, $lockToken],
                    1
                );
                return;
            } catch (\Throwable $e) {
            }
        }
        try {
            $cachedToken = (string)(Cache::get($lockKey) ?? '');
            if ($cachedToken !== '' && hash_equals($cachedToken, $lockToken)) {
                Cache::delete($lockKey);
            }
        } catch (\Throwable $e) {
        }
    }

    /**
     * 用户-商品锁续期。
     */
    private function refreshUserItemLock(int $activityId, int $itemId, int $userId, string $lockToken): bool
    {
        if ($lockToken === '') {
            return false;
        }
        return $this->refreshOwnedLock(
            $this->buildUserItemLockKey($activityId, $itemId, $userId),
            $lockToken,
            $this->getUserItemLockTtlSeconds()
        );
    }

    /**
     * 通用“持有者锁续期”实现（仅 token 匹配才续期）。
     */
    private function refreshOwnedLock(string $lockKey, string $lockToken, int $ttlSeconds): bool
    {
        if ($lockKey === '' || $lockToken === '' || $ttlSeconds <= 0) {
            return false;
        }
        $redis = $this->getRedisHandler();
        if ($redis && method_exists($redis, 'eval')) {
            try {
                $ret = (int)$redis->eval(
                    "if redis.call('GET', KEYS[1]) == ARGV[1] then return redis.call('EXPIRE', KEYS[1], tonumber(ARGV[2])) else return 0 end",
                    [$lockKey, $lockToken, (string)$ttlSeconds],
                    1
                );
                return $ret === 1;
            } catch (\Throwable $e) {
            }
        }
        try {
            $cachedToken = (string)(Cache::get($lockKey) ?? '');
            if ($cachedToken !== '' && hash_equals($cachedToken, $lockToken)) {
                Cache::set($lockKey, $lockToken, $ttlSeconds);
                return true;
            }
        } catch (\Throwable $e) {
        }
        return false;
    }

    /**
     * 通用“持有者锁获取”实现（NX + EX）。
     */
    private function acquireOwnedLock(string $lockKey, int $ttlSeconds): string
    {
        if ($lockKey === '' || $ttlSeconds <= 0) {
            return '';
        }
        $lockToken = $this->createLockToken();
        $redis = $this->getRedisHandler();
        if ($redis && method_exists($redis, 'eval')) {
            try {
                $ret = $redis->eval(
                    "local ok = redis.call('SET', KEYS[1], ARGV[1], 'NX', 'EX', tonumber(ARGV[2])); if ok then return ARGV[1] else return '' end",
                    [$lockKey, $lockToken, (string)$ttlSeconds],
                    1
                );
                return is_string($ret) ? $ret : '';
            } catch (\Throwable $e) {
            }
        }
        // Redis 不可用时，避免非原子 has+set 造成并发竞态，直接安全失败。
        return '';
    }

    /**
     * request_id 锁 TTL 配置读取。
     */
    private function getRequestLockTtlSeconds(): int
    {
        $ttl = (int)env('FLASH_SALE_REQUEST_LOCK_TTL_SECONDS', self::REQUEST_LOCK_TTL_SECONDS);
        return max(30, min(300, $ttl));
    }

    /**
     * 用户-商品锁 TTL 配置读取。
     */
    private function getUserItemLockTtlSeconds(): int
    {
        $ttl = (int)env('FLASH_SALE_USER_ITEM_LOCK_TTL_SECONDS', self::USER_ITEM_LOCK_TTL_SECONDS);
        return max(20, min(180, $ttl));
    }

    /**
     * 标记“用户在该商品下已有处理中请求”，抑制并发重入。
     */
    private function setUserItemPendingMarker(int $itemId, int $userId, int $ttlSeconds): void
    {
        $pendingKey = $this->buildUserItemPendingKey($itemId, $userId);
        try {
            Cache::set($pendingKey, 1, max(30, $ttlSeconds));
        } catch (\Throwable $e) {
        }
    }

    /**
     * 清理用户-商品处理中标记。
     */
    private function clearUserItemPendingMarker(int $itemId, int $userId): void
    {
        $pendingKey = $this->buildUserItemPendingKey($itemId, $userId);
        try {
            Cache::delete($pendingKey);
        } catch (\Throwable $e) {
        }
    }

    /**
     * 判断用户-商品处理中标记是否存在。
     */
    private function hasUserItemPendingMarker(int $itemId, int $userId): bool
    {
        $pendingKey = $this->buildUserItemPendingKey($itemId, $userId);
        try {
            return (bool)Cache::has($pendingKey);
        } catch (\Throwable $e) {
        }
        return false;
    }

    /**
     * Redis Lua：校验库存与 pending 后原子预扣库存并写 pending。
     */
    private function getReserveStockLuaScript(): string
    {
        return <<<'LUA'
local stockRaw = redis.call('GET', KEYS[1])
if not stockRaw then
  return -3
end
local stock = tonumber(stockRaw)
local qty = tonumber(ARGV[1])
if stock < qty then
  return -1
end
local pending = tonumber(redis.call('GET', KEYS[2]) or '0')
if pending > 0 then
  return -2
end
redis.call('DECRBY', KEYS[1], qty)
redis.call('SET', KEYS[2], qty, 'EX', tonumber(ARGV[2]))
return 1
LUA;
    }

    /**
     * 若订单已过期则释放库存并改写状态。
     */
    private function releaseOrderIfExpired(int $orderId, int $nowTs): bool
    {
        if ($orderId <= 0) {
            return false;
        }

        return (bool)Db::transaction(function () use ($orderId, $nowTs) {
            $row = Db::name('flash_sale_order')->where('order_id', $orderId)->lock(true)->find();
            if (!$row || (int)$row['status'] !== 0) {
                return false;
            }
            $reserveExpireTs = $this->getOrderReserveExpireTs($row);
            if ($reserveExpireTs > $nowTs) {
                return false;
            }
            $affected = (int)Db::name('order')
                ->where('id', $orderId)
                ->where('status', 0)
                ->update([
                    'status' => 2,
                    // 订单离开待支付后必须释放防重键，避免后续下单被唯一键误拦截
                    'pending_lock_key' => null,
                ]);
            if ($affected < 1) {
                return false;
            }

            Db::name('flash_sale_order')->where('id', (int)$row['id'])->update([
                'status' => 3,
                'update_time' => date('Y-m-d H:i:s'),
            ]);
            Db::name('flash_sale_item')->where('id', (int)$row['item_id'])
                ->dec('locked_stock', (int)$row['buy_count'])
                ->update();
            $this->rollbackReservedStock((int)$row['item_id'], (int)$row['user_id'], (int)$row['buy_count']);
            $this->clearUserItemPendingMarker((int)$row['item_id'], (int)$row['user_id']);
            $this->setRequestState((string)($row['request_id'] ?? ''), [
                'status' => 3,
                'user_id' => (int)($row['user_id'] ?? 0),
                'message' => '订单已超时释放',
                'order_id' => $orderId,
                'reserve_expire_time' => (string)($row['reserve_expire_time'] ?? ''),
            ]);
            return true;
        });
    }

    /**
     * 计算订单预留过期时间戳（字段缺失时回退 create_time+reserveSeconds）。
     */
    private function getOrderReserveExpireTs(array $flashSaleOrder): int
    {
        if ($this->hasReserveExpireField()) {
            $reserveExpireTs = $this->parseTimeToTs((string)($flashSaleOrder['reserve_expire_time'] ?? ''));
            if ($reserveExpireTs > 0) {
                return $reserveExpireTs;
            }
        }
        $createTs = $this->parseTimeToTs((string)($flashSaleOrder['create_time'] ?? ''));
        return $createTs > 0 ? $createTs + $this->getReserveSeconds() : time();
    }

    /**
     * 将时间字符串安全转换为时间戳。
     */
    private function parseTimeToTs(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        $ts = strtotime($value);
        return $ts === false ? 0 : (int)$ts;
    }

    /**
     * 获取 Redis 句柄（可用时返回底层客户端）。
     */
    private function getRedisHandler()
    {
        try {
            $cacheStore = Cache::store('redis');
            if (method_exists($cacheStore, 'handler')) {
                return $cacheStore->handler();
            }
        } catch (\Throwable $e) {
        }
        return null;
    }

    /**
     * 删除缓存键，兼容底层 Redis 与 ThinkPHP Cache 门面。
     */
    private function deleteCacheKey(string $key, $redis = null): bool
    {
        if ($key === '') {
            return false;
        }
        if ($redis && method_exists($redis, 'del')) {
            try {
                return (int)$redis->del($key) > 0;
            } catch (\Throwable $e) {
            }
        }
        try {
            $exists = Cache::has($key);
            if ($exists) {
                Cache::delete($key);
            }
            return (bool)$exists;
        } catch (\Throwable $e) {
        }
        return false;
    }

    /**
     * 仅针对数据库死锁/锁等待超时进行重试，避免瞬时并发冲突直接失败。
     *
     * @return mixed
     */
    private function executeWithDeadlockRetry(callable $callback, ?callable $onRetryCleanup = null)
    {
        $attempts = max(1, min(5, (int)env('FLASH_SALE_DEADLOCK_RETRY_TIMES', 3)));
        $baseSleepMs = max(10, min(300, (int)env('FLASH_SALE_DEADLOCK_RETRY_BASE_MS', 30)));
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return $callback();
            } catch (\Throwable $e) {
                if (!$this->isDeadlockException($e) || $attempt >= $attempts) {
                    throw $e;
                }
                if ($onRetryCleanup !== null) {
                    try {
                        $onRetryCleanup();
                    } catch (\Throwable $cleanupError) {
                    }
                }
                usleep($baseSleepMs * $attempt * 1000);
            }
        }
        throw new ValidateException('数据库锁冲突，请稍后重试');
    }

    /**
     * 识别异常是否属于死锁/锁等待超时可重试场景。
     */
    private function isDeadlockException(\Throwable $e): bool
    {
        [$sqlState, $driverCode] = $this->extractSqlStateAndDriverCode($e);
        if ($sqlState === '40001' || $driverCode === 1213 || $driverCode === 1205) {
            return true;
        }
        $message = strtolower($e->getMessage());
        return str_contains($message, 'deadlock found when trying to get lock')
            || str_contains($message, 'sqlstate[40001]')
            || str_contains($message, 'lock wait timeout exceeded');
    }

    /**
     * 提取 SQLSTATE 与驱动错误码（MySQL 常见：23000/1062、40001/1213、1205）。
     *
     * @return array{0:string,1:int}
     */
    private function extractSqlStateAndDriverCode(\Throwable $e): array
    {
        $sqlState = '';
        $driverCode = 0;
        if ($e instanceof \PDOException) {
            $errorInfo = $e->errorInfo;
            if (is_array($errorInfo)) {
                $sqlState = (string)($errorInfo[0] ?? '');
                $driverCode = (int)($errorInfo[1] ?? 0);
            }
            if ($driverCode <= 0) {
                $driverCode = (int)$e->getCode();
            }
            return [$sqlState, $driverCode];
        }
        $message = strtolower($e->getMessage());
        if (preg_match('/sqlstate\\[(\\w+)\\]/', $message, $m) === 1) {
            $sqlState = strtoupper((string)($m[1] ?? ''));
        }
        if (preg_match('/\\b(1062|1213|1205)\\b/', $message, $m) === 1) {
            $driverCode = (int)($m[1] ?? 0);
        }
        return [$sqlState, $driverCode];
    }
}

