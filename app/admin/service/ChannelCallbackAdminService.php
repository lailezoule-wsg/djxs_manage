<?php
declare(strict_types=1);

namespace app\admin\service;

use app\admin\service\channel\ChannelAdapterFactory;
use app\common\exception\BizException;
use think\facade\Cache;
use think\facade\Db;

/**
 * 管理端渠道回调业务服务
 */
class ChannelCallbackAdminService extends BaseAdminService
{
    private array $tableFieldCache = [];
    /**
     * 分页查询回调事件列表
     */
    public function callbackList(array $params, int $page, int $pageSize): array
    {
        $query = Db::name('channel_callback_event')->order('id', 'desc');
        $taskNo = trim((string)($params['task_no'] ?? ''));
        $channelCode = trim((string)($params['channel_code'] ?? ''));
        $eventType = trim((string)($params['event_type'] ?? ''));
        if ($taskNo !== '') {
            $query->where('task_no', $taskNo);
        }
        if ($channelCode !== '') {
            $query->where('channel_code', $channelCode);
        }
        if ($eventType !== '') {
            $query->where('event_type', $eventType);
        }
        return $this->paginateToArray($query, $page, $pageSize);
    }

    /**
     * 获取回调事件详情
     */
    public function callbackDetail(int $id): array
    {
        $row = Db::name('channel_callback_event')->where('id', $id)->find();
        if (!$row) {
            throw new BizException('回调事件不存在', 404, 40401);
        }
        return $row;
    }

    /**
     * 接收并校验渠道回调
     */
    public function receive(string $channelCode, array $headers, string $rawBody): array
    {
        $channelCode = strtolower(trim($channelCode));
        $timestamp = (int)$this->getHeader($headers, 'x-timestamp');
        $nonce = $this->getHeader($headers, 'x-nonce');
        $sign = $this->getHeader($headers, 'x-sign');
        $accountKey = $this->getHeader($headers, 'x-account-key');
        if ($timestamp <= 0 || $nonce === '' || $sign === '') {
            throw new BizException('回调签名参数缺失', 400, 40001);
        }
        $window = max(60, (int)config('app.channel_callback_time_window_seconds', 300));
        if (abs(time() - $timestamp) > $window) {
            throw new BizException('回调请求已过期', 400, 40001);
        }
        $replayKey = 'channel:callback:replay:' . $channelCode . ':' . $timestamp . ':' . md5($nonce);
        if (Cache::has($replayKey)) {
            throw new BizException('回调重复请求', 409, 40901);
        }

        $adapter = ChannelAdapterFactory::make($channelCode);
        $secret = (new ChannelAccountAdminService())->getCallbackSecret($channelCode, $accountKey);
        if (!$adapter->verifyCallbackSign($headers, $rawBody, ['callback_secret' => $secret])) {
            throw new BizException('回调验签失败', 400, 40001);
        }
        Cache::set($replayKey, 1, $window + 60);
        $event = $adapter->parseCallback($headers, $rawBody);
        $eventId = trim((string)($event['event_id'] ?? ''));
        if ($eventId === '') {
            throw new BizException('event_id 缺失', 400, 40001);
        }

        $exists = Db::name('channel_callback_event')
            ->where('event_id', $eventId)
            ->where('channel_code', $channelCode)
            ->count();
        if ($exists > 0) {
            return ['accepted' => true, 'deduplicated' => true];
        }

        $insert = [
            'event_id' => $eventId,
            'channel_code' => $channelCode,
            'event_type' => (string)($event['event_type'] ?? ''),
            'task_no' => (string)($event['task_no'] ?? ''),
            'channel_content_id' => (string)($event['channel_content_id'] ?? ''),
            'raw_payload' => json_encode($event['raw_payload'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'create_time' => date('Y-m-d H:i:s'),
        ];
        if ($this->hasColumn('channel_callback_event', 'nonce')) {
            $insert['nonce'] = $nonce;
        }
        if ($this->hasColumn('channel_callback_event', 'ts')) {
            $insert['ts'] = $timestamp;
        }
        if ($this->hasColumn('channel_callback_event', 'sign')) {
            $insert['sign'] = $sign;
        }
        if ($this->hasColumn('channel_callback_event', 'verify_pass')) {
            $insert['verify_pass'] = 1;
        }
        Db::name('channel_callback_event')->insert($insert);

        $this->syncTaskFromCallback($channelCode, $event);
        return ['accepted' => true, 'deduplicated' => false];
    }

    private function syncTaskFromCallback(string $channelCode, array $event): void
    {
        $taskNo = trim((string)($event['task_no'] ?? ''));
        if ($taskNo === '') {
            return;
        }
        $eventType = strtolower(trim((string)($event['event_type'] ?? '')));
        $rawPayload = $event['raw_payload'] ?? [];
        $success = false;
        if (str_contains($eventType, 'success')) {
            $success = true;
        } elseif (is_array($rawPayload)) {
            $result = strtolower(trim((string)($rawPayload['result'] ?? $rawPayload['status'] ?? '')));
            $success = in_array($result, ['success', 'ok', 'published'], true);
        }
        Db::name('channel_distribution_task')
            ->where('task_no', $taskNo)
            ->where('channel_code', $channelCode)
            ->update([
                'status' => $success ? 'success' : 'failed',
                'channel_content_id' => (string)($event['channel_content_id'] ?? ''),
                'update_time' => date('Y-m-d H:i:s'),
            ]);
    }

    private function getHeader(array $headers, string $name): string
    {
        $target = strtolower($name);
        foreach ($headers as $key => $value) {
            if (strtolower((string)$key) !== $target) {
                continue;
            }
            if (is_array($value)) {
                return trim((string)($value[0] ?? ''));
            }
            return trim((string)$value);
        }
        return '';
    }

    private function hasColumn(string $table, string $column): bool
    {
        if (!isset($this->tableFieldCache[$table])) {
            try {
                $fields = Db::name($table)->getFields();
                $this->tableFieldCache[$table] = is_array($fields) ? $fields : [];
            } catch (\Throwable $e) {
                $this->tableFieldCache[$table] = [];
            }
        }
        return array_key_exists($column, $this->tableFieldCache[$table]);
    }
}
