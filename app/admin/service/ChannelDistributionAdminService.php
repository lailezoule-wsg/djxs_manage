<?php
declare(strict_types=1);

namespace app\admin\service;

use app\admin\service\channel\ChannelAdapterFactory;
use app\common\exception\BizException;
use think\facade\Db;

/**
 * 管理端渠道分发任务业务服务
 */
class ChannelDistributionAdminService extends BaseAdminService
{
    private const STATUS_DRAFT = 'draft';
    private const STATUS_QUEUED = 'queued';
    private const STATUS_PROCESSING = 'processing';
    private const STATUS_SUCCESS = 'success';
    private const STATUS_PARTIAL_SUCCESS = 'partial_success';
    private const STATUS_FAILED = 'failed';
    private const AUDIT_PENDING = 'pending';
    private const AUDIT_APPROVED = 'approved';
    private const AUDIT_REJECTED = 'rejected';
    private array $tableFieldCache = [];

    /**
     * 分页查询分发任务
     */
    public function taskList(array $params, int $page, int $pageSize): array
    {
        $query = Db::name('channel_distribution_task');
        $taskNo = trim((string)($params['task_no'] ?? ''));
        $channelCode = trim((string)($params['channel_code'] ?? ''));
        $status = trim((string)($params['status'] ?? ''));
        $auditStatus = trim((string)($params['audit_status'] ?? ''));
        if ($taskNo !== '') {
            $query->where('task_no', $taskNo);
        }
        if ($channelCode !== '') {
            $query->where('channel_code', $channelCode);
        }
        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($auditStatus !== '' && $this->hasColumn('channel_distribution_task', 'audit_status')) {
            $query->where('audit_status', $auditStatus);
        }

        $total = (int)(clone $query)->distinct(true)->count('task_no');
        if ($total <= 0) {
            return [
                'list' => [],
                'total' => 0,
                'page' => $page,
                'page_size' => $pageSize,
            ];
        }

        $taskNoRows = (clone $query)
            ->field('task_no, MAX(id) AS max_id')
            ->group('task_no')
            ->order('max_id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();
        $taskNos = array_values(array_filter(array_unique(array_map(static fn($row) => (string)($row['task_no'] ?? ''), $taskNoRows))));
        if (empty($taskNos)) {
            return [
                'list' => [],
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize,
            ];
        }

        $rows = Db::name('channel_distribution_task')
            ->whereIn('task_no', $taskNos)
            ->order('id', 'asc')
            ->select()
            ->toArray();
        $grouped = [];
        foreach ($rows as $row) {
            $groupTaskNo = (string)($row['task_no'] ?? '');
            if ($groupTaskNo === '') {
                continue;
            }
            if (!isset($grouped[$groupTaskNo])) {
                $grouped[$groupTaskNo] = [
                    'task_no' => $groupTaskNo,
                    'content_type' => (string)($row['content_type'] ?? ''),
                    'content_id' => (int)($row['content_id'] ?? 0),
                    'status' => (string)($row['status'] ?? self::STATUS_DRAFT),
                    'audit_status' => (string)($row['audit_status'] ?? self::AUDIT_PENDING),
                    'retry_count' => (int)($row['retry_count'] ?? 0),
                    'audit_remark' => '',
                    'update_time' => (string)($row['update_time'] ?? ''),
                    'create_time' => (string)($row['create_time'] ?? ''),
                    'channel_codes' => [],
                    '__status_list' => [],
                    '__audit_status_list' => [],
                    '__audit_remark_sort' => '',
                ];
            }
            $channel = (string)($row['channel_code'] ?? '');
            if ($channel !== '') {
                $grouped[$groupTaskNo]['channel_codes'][] = $channel;
            }
            $grouped[$groupTaskNo]['__status_list'][] = (string)($row['status'] ?? self::STATUS_DRAFT);
            $grouped[$groupTaskNo]['__audit_status_list'][] = (string)($row['audit_status'] ?? self::AUDIT_PENDING);
            $auditRemark = trim((string)($row['audit_remark'] ?? ''));
            $auditStatus = trim((string)($row['audit_status'] ?? self::AUDIT_PENDING));
            if ($auditRemark !== '' && $auditStatus === self::AUDIT_REJECTED) {
                $auditSort = trim((string)($row['audit_time'] ?? ''));
                if ($auditSort === '') {
                    $auditSort = trim((string)($row['update_time'] ?? ''));
                }
                if ($auditSort >= (string)$grouped[$groupTaskNo]['__audit_remark_sort']) {
                    $grouped[$groupTaskNo]['audit_remark'] = $auditRemark;
                    $grouped[$groupTaskNo]['__audit_remark_sort'] = $auditSort;
                }
            }
            $grouped[$groupTaskNo]['retry_count'] = max(
                (int)$grouped[$groupTaskNo]['retry_count'],
                (int)($row['retry_count'] ?? 0)
            );
            $grouped[$groupTaskNo]['update_time'] = max(
                (string)$grouped[$groupTaskNo]['update_time'],
                (string)($row['update_time'] ?? '')
            );
            if ((string)$grouped[$groupTaskNo]['create_time'] === '' || (string)($row['create_time'] ?? '') < (string)$grouped[$groupTaskNo]['create_time']) {
                $grouped[$groupTaskNo]['create_time'] = (string)($row['create_time'] ?? '');
            }
        }

        $list = [];
        foreach ($taskNos as $groupTaskNo) {
            if (!isset($grouped[$groupTaskNo])) {
                continue;
            }
            $item = $grouped[$groupTaskNo];
            $channelCodes = array_values(array_unique(array_filter($item['channel_codes'], static fn($v) => $v !== '')));
            sort($channelCodes);
            $item['channel_codes'] = $channelCodes;
            $item['channel_count'] = count($channelCodes);
            $item['status'] = $this->summarizeGroupStatus($item['__status_list']);
            $item['audit_status'] = $this->summarizeGroupAuditStatus($item['__audit_status_list']);
            unset($item['__status_list'], $item['__audit_status_list'], $item['__audit_remark_sort']);
            $list[] = $item;
        }

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 创建分发任务
     */
    public function createTask(int $adminId, array $payload): array
    {
        $contentId = (int)($payload['content_id'] ?? 0);
        $contentType = strtolower(trim((string)($payload['content_type'] ?? '')));
        $channels = $payload['channels'] ?? [];

        if ($contentId <= 0) {
            throw new BizException('content_id 不能为空', 400, 40001);
        }
        if (!in_array($contentType, ['drama', 'novel'], true)) {
            throw new BizException('content_type 仅支持 drama/novel', 400, 40001);
        }
        if (!is_array($channels) || empty($channels)) {
            throw new BizException('channels 不能为空', 400, 40001);
        }

        $version = max(1, (int)($payload['version'] ?? 1));
        $normalizedChannels = array_values(array_unique(array_map(static fn($c) => strtolower(trim((string)$c)), $channels)));
        sort($normalizedChannels);
        $idempotencyBase = trim((string)($payload['idempotency_key'] ?? ''));
        if ($idempotencyBase === '') {
            $idempotencyBase = $this->buildIdempotencyBase($contentId, $contentType, $normalizedChannels, $version);
        }
        $probeKey = $this->buildRowIdempotencyKey($idempotencyBase, (string)($normalizedChannels[0] ?? 'default'));
        if ($this->hasColumn('channel_distribution_task', 'idempotency_key')) {
            $exists = Db::name('channel_distribution_task')
                ->where('idempotency_key', $probeKey)
                ->find();
            if ($exists) {
                return [
                    'task_no' => (string)$exists['task_no'],
                    'status' => (string)$exists['status'],
                    'audit_status' => (string)($exists['audit_status'] ?? self::AUDIT_PENDING),
                    'deduplicated' => true,
                ];
            }
        }

        $taskNo = $this->buildTaskNo();
        $traceId = $this->buildTraceId($taskNo);
        $accountMap = $payload['channel_account_id_map'] ?? [];
        if (!is_array($accountMap)) {
            $accountMap = [];
        }
        $taskRows = [];
        $now = date('Y-m-d H:i:s');
        $unsupportedChannels = [];
        foreach ($channels as $channelCode) {
            $channel = strtolower(trim((string)$channelCode));
            if (!ChannelAdapterFactory::isSupported($channel)) {
                $unsupportedChannels[] = $channel;
                continue;
            }
            $row = [
                'task_no' => $taskNo,
                'channel_code' => $channel,
                'content_id' => $contentId,
                'content_type' => $contentType,
                'status' => self::STATUS_DRAFT,
                'retry_count' => 0,
                'request_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'operator_id' => $adminId,
                'create_time' => $now,
                'update_time' => $now,
            ];
            if ($this->hasColumn('channel_distribution_task', 'version')) {
                $row['version'] = $version;
            }
            if ($this->hasColumn('channel_distribution_task', 'idempotency_key')) {
                $row['idempotency_key'] = $this->buildRowIdempotencyKey($idempotencyBase, $channel);
            }
            if ($this->hasColumn('channel_distribution_task', 'audit_status')) {
                $row['audit_status'] = self::AUDIT_PENDING;
            }
            if ($this->hasColumn('channel_distribution_task', 'trace_id')) {
                $row['trace_id'] = $traceId;
            }
            if ($this->hasColumn('channel_distribution_task', 'channel_account_id')) {
                $row['channel_account_id'] = max(0, (int)($accountMap[$channel] ?? 0));
            }
            $taskRows[] = $row;
        }
        if (!empty($unsupportedChannels)) {
            $unsupportedChannels = array_values(array_unique(array_filter($unsupportedChannels, static fn($v) => $v !== '')));
            throw new BizException('新建任务暂不支持该渠道：' . implode(',', $unsupportedChannels), 400, 40001);
        }

        Db::transaction(function () use ($taskRows) {
            foreach ($taskRows as $row) {
                Db::name('channel_distribution_task')->insert($row);
            }
        });
        $this->logOperation($taskNo, 'create', $adminId, [], [
            'channels' => $channels,
            'idempotency_key' => $idempotencyBase,
            'version' => $version,
        ], '任务创建待审核');

        return [
            'task_no' => $taskNo,
            'status' => self::STATUS_DRAFT,
            'audit_status' => self::AUDIT_PENDING,
            'channel_count' => count($taskRows),
            'deduplicated' => false,
        ];
    }

    /**
     * 获取任务详情
     */
    public function taskDetail(string $taskNo): array
    {
        $taskNo = trim($taskNo);
        if ($taskNo === '') {
            throw new BizException('task_no 不能为空', 400, 40001);
        }
        $rows = Db::name('channel_distribution_task')
            ->where('task_no', $taskNo)
            ->order('id', 'asc')
            ->select()
            ->toArray();
        if (empty($rows)) {
            throw new BizException('任务不存在', 404, 40401);
        }
        return [
            'task_no' => $taskNo,
            'items' => $rows,
        ];
    }

    /**
     * 重试任务
     */
    public function taskRetry(string $taskNo): array
    {
        $taskNo = trim($taskNo);
        if ($taskNo === '') {
            throw new BizException('task_no 不能为空', 400, 40001);
        }
        $row = Db::name('channel_distribution_task')->where('task_no', $taskNo)->find();
        if (!$row) {
            throw new BizException('任务不存在', 404, 40401);
        }
        if ($this->hasColumn('channel_distribution_task', 'audit_status') && (string)$row['audit_status'] !== self::AUDIT_APPROVED) {
            throw new BizException('任务未审核通过，无法重试', 409, 40901);
        }
        $ok = ChannelDistributionQueueService::publish([
            'event' => 'channel_distribution.retry',
            'task_no' => $taskNo,
            'ts' => time(),
        ]);
        if (!$ok) {
            throw new BizException('重试任务投递失败', 500, 50001);
        }
        Db::name('channel_distribution_task')
            ->where('task_no', $taskNo)
            ->whereIn('status', [self::STATUS_FAILED, self::STATUS_PARTIAL_SUCCESS, self::STATUS_DRAFT])
            ->update([
                'status' => self::STATUS_QUEUED,
                'update_time' => date('Y-m-d H:i:s'),
            ]);
        $this->logOperation($taskNo, 'retry', 0, [], ['queue' => true], '任务重试入队');
        return ['task_no' => $taskNo, 'queued' => true];
    }

    /**
     * 重新提审任务
     */
    public function taskResubmit(string $taskNo, int $adminId, array $payload): array
    {
        $taskNo = trim($taskNo);
        $resubmitRemark = trim((string)($payload['resubmit_remark'] ?? ''));
        if ($taskNo === '') {
            throw new BizException('task_no 不能为空', 400, 40001);
        }
        if ($resubmitRemark === '') {
            throw new BizException('提审说明不能为空', 400, 40001);
        }
        $rows = Db::name('channel_distribution_task')->where('task_no', $taskNo)->order('id', 'asc')->select()->toArray();
        if (empty($rows)) {
            throw new BizException('任务不存在', 404, 40401);
        }
        foreach ($rows as $row) {
            $status = (string)($row['status'] ?? '');
            if (in_array($status, [self::STATUS_PROCESSING, self::STATUS_QUEUED], true)) {
                throw new BizException('任务执行中，不能提审', 409, 40901);
            }
            if ($this->hasColumn('channel_distribution_task', 'audit_status')) {
                $auditStatus = (string)($row['audit_status'] ?? self::AUDIT_PENDING);
                if ($auditStatus !== self::AUDIT_REJECTED) {
                    throw new BizException('仅审核驳回任务允许提审', 409, 40901);
                }
            }
        }

        $firstPayload = json_decode((string)($rows[0]['request_payload'] ?? ''), true);
        if (!is_array($firstPayload)) {
            $firstPayload = [];
        }
        $mergedPayload = array_replace_recursive($firstPayload, $payload);
        $contentId = (int)($mergedPayload['content_id'] ?? ($rows[0]['content_id'] ?? 0));
        $contentType = strtolower(trim((string)($mergedPayload['content_type'] ?? ($rows[0]['content_type'] ?? ''))));
        $channels = $mergedPayload['channels'] ?? array_column($rows, 'channel_code');
        if (!is_array($channels) || empty($channels)) {
            throw new BizException('channels 不能为空', 400, 40001);
        }
        if ($contentId <= 0 || !in_array($contentType, ['drama', 'novel'], true)) {
            throw new BizException('提审参数不完整', 400, 40001);
        }
        $channels = array_values(array_unique(array_map(static fn($c) => strtolower(trim((string)$c)), $channels)));
        $channels = array_values(array_filter($channels, static fn($c) => $c !== ''));
        if (empty($channels)) {
            throw new BizException('channels 不能为空', 400, 40001);
        }
        $unsupportedChannels = [];
        foreach ($channels as $channel) {
            if (!ChannelAdapterFactory::isSupported($channel)) {
                $unsupportedChannels[] = $channel;
            }
        }
        if (!empty($unsupportedChannels)) {
            $unsupportedChannels = array_values(array_unique(array_filter($unsupportedChannels, static fn($v) => $v !== '')));
            throw new BizException('提审暂不支持该渠道：' . implode(',', $unsupportedChannels), 400, 40001);
        }

        $existingByChannel = [];
        foreach ($rows as $row) {
            $existingByChannel[(string)$row['channel_code']] = $row;
        }
        $accountMap = $mergedPayload['channel_account_id_map'] ?? [];
        if (!is_array($accountMap)) {
            $accountMap = [];
        }
        $now = date('Y-m-d H:i:s');
        $baseKey = $this->buildIdempotencyBase($contentId, $contentType, $channels, max(1, (int)($mergedPayload['version'] ?? 1)));
        $requestPayloadJson = json_encode($mergedPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        Db::transaction(function () use ($taskNo, $channels, $existingByChannel, $contentId, $contentType, $requestPayloadJson, $accountMap, $baseKey, $now) {
            $toDeleteIds = [];
            foreach ($existingByChannel as $channel => $row) {
                if (!in_array($channel, $channels, true)) {
                    $toDeleteIds[] = (int)$row['id'];
                }
            }
            if (!empty($toDeleteIds)) {
                Db::name('channel_distribution_task')->whereIn('id', $toDeleteIds)->delete();
            }

            foreach ($channels as $channel) {
                $update = [
                    'content_id' => $contentId,
                    'content_type' => $contentType,
                    'status' => self::STATUS_DRAFT,
                    'retry_count' => 0,
                    'error_code' => null,
                    'error_msg' => null,
                    'request_payload' => $requestPayloadJson,
                    'update_time' => $now,
                ];
                if ($this->hasColumn('channel_distribution_task', 'channel_account_id')) {
                    $update['channel_account_id'] = max(0, (int)($accountMap[$channel] ?? 0));
                }
                if ($this->hasColumn('channel_distribution_task', 'audit_status')) {
                    $update['audit_status'] = self::AUDIT_PENDING;
                }
                if ($this->hasColumn('channel_distribution_task', 'audit_by')) {
                    $update['audit_by'] = 0;
                }
                if ($this->hasColumn('channel_distribution_task', 'audit_time')) {
                    $update['audit_time'] = null;
                }
                if ($this->hasColumn('channel_distribution_task', 'audit_remark')) {
                    $update['audit_remark'] = '';
                }
                if ($this->hasColumn('channel_distribution_task', 'idempotency_key')) {
                    $update['idempotency_key'] = $this->buildRowIdempotencyKey($baseKey, $channel);
                }
                if (isset($existingByChannel[$channel])) {
                    Db::name('channel_distribution_task')->where('id', (int)$existingByChannel[$channel]['id'])->update($update);
                    continue;
                }
                $insert = array_merge($update, [
                    'task_no' => $taskNo,
                    'channel_code' => $channel,
                    'operator_id' => 0,
                    'create_time' => $now,
                ]);
                Db::name('channel_distribution_task')->insert($insert);
            }
        });

        $this->logOperation($taskNo, 'resubmit', $adminId, [], [
            'channels' => $channels,
            'content_id' => $contentId,
            'content_type' => $contentType,
            'resubmit_remark' => $resubmitRemark,
        ], $resubmitRemark);
        return [
            'task_no' => $taskNo,
            'audit_status' => self::AUDIT_PENDING,
            'status' => self::STATUS_DRAFT,
            'channels' => $channels,
        ];
    }

    /**
     * 审核任务
     */
    public function taskAudit(string $taskNo, int $adminId, array $payload): array
    {
        $taskNo = trim($taskNo);
        $auditStatus = strtolower(trim((string)($payload['audit_status'] ?? '')));
        $remark = trim((string)($payload['audit_remark'] ?? ''));
        if ($taskNo === '') {
            throw new BizException('task_no 不能为空', 400, 40001);
        }
        if (!in_array($auditStatus, [self::AUDIT_APPROVED, self::AUDIT_REJECTED], true)) {
            throw new BizException('audit_status 仅支持 approved/rejected', 400, 40001);
        }
        $rows = Db::name('channel_distribution_task')->where('task_no', $taskNo)->select()->toArray();
        if (empty($rows)) {
            throw new BizException('任务不存在', 404, 40401);
        }
        $update = [
            'update_time' => date('Y-m-d H:i:s'),
        ];
        if ($this->hasColumn('channel_distribution_task', 'audit_status')) {
            $update['audit_status'] = $auditStatus;
        }
        if ($this->hasColumn('channel_distribution_task', 'audit_by')) {
            $update['audit_by'] = $adminId;
        }
        if ($this->hasColumn('channel_distribution_task', 'audit_time')) {
            $update['audit_time'] = date('Y-m-d H:i:s');
        }
        if ($this->hasColumn('channel_distribution_task', 'audit_remark')) {
            $update['audit_remark'] = $remark;
        }
        $update['status'] = $auditStatus === self::AUDIT_APPROVED ? self::STATUS_QUEUED : self::STATUS_DRAFT;
        Db::name('channel_distribution_task')->where('task_no', $taskNo)->update($update);
        $this->logOperation($taskNo, 'audit', $adminId, [], $update, $remark);

        if ($auditStatus === self::AUDIT_APPROVED) {
            $ok = ChannelDistributionQueueService::publish([
                'event' => 'channel_distribution.publish',
                'task_no' => $taskNo,
                'ts' => time(),
            ]);
            if (!$ok) {
                throw new BizException('审核通过，但队列投递失败', 500, 50001);
            }
        }

        return [
            'task_no' => $taskNo,
            'audit_status' => $auditStatus,
            'queued' => $auditStatus === self::AUDIT_APPROVED,
        ];
    }

    /**
     * 查询任务日志
     */
    public function taskLogs(string $taskNo): array
    {
        if (!$this->hasTable('channel_distribution_op_log')) {
            return ['task_no' => $taskNo, 'list' => []];
        }
        $rows = Db::name('channel_distribution_op_log')
            ->where('task_no', $taskNo)
            ->order('id', 'desc')
            ->select()
            ->toArray();
        return ['task_no' => $taskNo, 'list' => $rows];
    }

    /**
     * 消费分发发布消息
     */
    public function consumePublishMessage(array $payload): void
    {
        $taskNo = trim((string)($payload['task_no'] ?? ''));
        if ($taskNo === '') {
            return;
        }
        $tasks = Db::name('channel_distribution_task')
            ->where('task_no', $taskNo)
            ->whereIn('status', [self::STATUS_QUEUED, self::STATUS_FAILED, self::STATUS_PARTIAL_SUCCESS, self::STATUS_PROCESSING])
            ->order('id', 'asc')
            ->select()
            ->toArray();
        foreach ($tasks as $task) {
            $this->publishSingleTask($task);
        }
    }

    private function publishSingleTask(array $task): void
    {
        $taskId = (int)($task['id'] ?? 0);
        if ($taskId <= 0) {
            return;
        }
        $status = (string)($task['status'] ?? '');
        if ($status === self::STATUS_SUCCESS) {
            return;
        }
        $auditStatus = (string)($task['audit_status'] ?? self::AUDIT_APPROVED);
        if ($this->hasColumn('channel_distribution_task', 'audit_status') && $auditStatus !== self::AUDIT_APPROVED) {
            return;
        }

        Db::name('channel_distribution_task')
            ->where('id', $taskId)
            ->update([
                'status' => self::STATUS_PROCESSING,
                'update_time' => date('Y-m-d H:i:s'),
            ]);

        $channel = (string)($task['channel_code'] ?? '');
        $adapter = ChannelAdapterFactory::make($channel);
        $requestPayload = json_decode((string)($task['request_payload'] ?? ''), true);
        if (!is_array($requestPayload)) {
            $requestPayload = [];
        }
        $accountId = (int)($task['channel_account_id'] ?? 0);
        if ($accountId > 0) {
            $requestPayload['channel_account_id'] = $accountId;
        }

        try {
            $result = $adapter->publish($requestPayload);
            if (!empty($result['success'])) {
                Db::name('channel_distribution_task')->where('id', $taskId)->update([
                    'status' => self::STATUS_SUCCESS,
                    'channel_content_id' => (string)($result['channel_content_id'] ?? ''),
                    'response_payload' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'error_code' => $result['error_code'] ?? null,
                    'error_msg' => $result['error_msg'] ?? null,
                    'update_time' => date('Y-m-d H:i:s'),
                ]);
            } else {
                $this->handleFailureWithRetry($task, (string)($result['error_msg'] ?? '渠道返回失败'), $result['error_code'] ?? null, $result);
            }
            $this->refreshTaskAggregateStatus((string)$task['task_no']);
        } catch (\Throwable $e) {
            $this->handleFailureWithRetry($task, $e->getMessage(), null, []);
            $this->refreshTaskAggregateStatus((string)$task['task_no']);
        }
    }

    private function buildTaskNo(): string
    {
        return 'CD' . date('YmdHis') . random_int(1000, 9999);
    }

    private function buildTraceId(string $taskNo): string
    {
        return 'trace_' . strtolower($taskNo) . '_' . substr(md5($taskNo . microtime(true)), 0, 8);
    }

    private function buildIdempotencyBase(int $contentId, string $contentType, array $channels, int $version): string
    {
        return md5($contentId . '|' . $contentType . '|' . $version . '|' . implode(',', $channels));
    }

    private function buildRowIdempotencyKey(string $base, string $channel): string
    {
        return md5($base . '|' . strtolower(trim($channel)));
    }

    private function handleFailureWithRetry(array $task, string $errorMessage, ?string $errorCode = null, array $result = []): void
    {
        $taskId = (int)($task['id'] ?? 0);
        if ($taskId <= 0) {
            return;
        }
        $currentRetry = (int)($task['retry_count'] ?? 0) + 1;
        $maxRetry = max(0, (int)config('app.channel_distribution_max_retry', 3));
        $canRetry = $currentRetry <= $maxRetry;
        $nextStatus = $canRetry ? self::STATUS_QUEUED : self::STATUS_FAILED;
        $update = [
            'status' => $nextStatus,
            'retry_count' => $currentRetry,
            'error_code' => $errorCode,
            'error_msg' => $errorMessage,
            'response_payload' => !empty($result) ? json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'update_time' => date('Y-m-d H:i:s'),
        ];
        Db::name('channel_distribution_task')->where('id', $taskId)->update($update);
        if (!$canRetry) {
            return;
        }
        $delaySeconds = $this->retryDelaySeconds($currentRetry);
        ChannelDistributionQueueService::publish([
            'event' => 'channel_distribution.retry',
            'task_no' => (string)$task['task_no'],
            'ts' => time(),
            'retry_count' => $currentRetry,
        ], $delaySeconds * 1000);
    }

    private function retryDelaySeconds(int $retryCount): int
    {
        $configured = (string)config('app.channel_distribution_retry_delays', '10,30,60');
        $parts = array_values(array_filter(array_map(static fn($v) => (int)trim($v), explode(',', $configured)), static fn($v) => $v > 0));
        if (empty($parts)) {
            $parts = [10, 30, 60];
        }
        $idx = max(0, min(count($parts) - 1, $retryCount - 1));
        return $parts[$idx];
    }

    private function refreshTaskAggregateStatus(string $taskNo): void
    {
        $rows = Db::name('channel_distribution_task')->where('task_no', $taskNo)->select()->toArray();
        if (empty($rows)) {
            return;
        }
        $total = count($rows);
        $success = 0;
        $failed = 0;
        foreach ($rows as $row) {
            $status = (string)($row['status'] ?? '');
            if ($status === self::STATUS_SUCCESS) {
                $success++;
            } elseif ($status === self::STATUS_FAILED) {
                $failed++;
            }
        }
        $aggregate = self::STATUS_PROCESSING;
        if ($success === $total) {
            $aggregate = self::STATUS_SUCCESS;
        } elseif ($failed === $total) {
            $aggregate = self::STATUS_FAILED;
        } elseif ($success > 0 || $failed > 0) {
            $aggregate = self::STATUS_PARTIAL_SUCCESS;
        }
        if ($aggregate !== self::STATUS_PROCESSING) {
            Db::name('channel_distribution_task')
                ->where('task_no', $taskNo)
                ->whereIn('status', [self::STATUS_QUEUED, self::STATUS_PROCESSING])
                ->update([
                    'status' => $aggregate,
                    'update_time' => date('Y-m-d H:i:s'),
                ]);
        }
    }

    private function logOperation(string $taskNo, string $action, int $operatorId, array $before, array $after, string $remark): void
    {
        if (!$this->hasTable('channel_distribution_op_log')) {
            return;
        }
        Db::name('channel_distribution_op_log')->insert([
            'task_no' => $taskNo,
            'action' => $action,
            'operator_id' => $operatorId,
            'operator_type' => 'admin',
            'before_json' => json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'after_json' => json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'remark' => $remark,
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function hasColumn(string $table, string $column): bool
    {
        $fields = $this->getTableFieldsCached($table);
        return array_key_exists($column, $fields);
    }

    private function hasTable(string $table): bool
    {
        $fields = $this->getTableFieldsCached($table);
        return !empty($fields);
    }

    private function getTableFieldsCached(string $table): array
    {
        if (isset($this->tableFieldCache[$table])) {
            return $this->tableFieldCache[$table];
        }
        try {
            $fields = Db::name($table)->getFields();
            $this->tableFieldCache[$table] = is_array($fields) ? $fields : [];
        } catch (\Throwable $e) {
            $this->tableFieldCache[$table] = [];
        }
        return $this->tableFieldCache[$table];
    }

    private function summarizeGroupStatus(array $statusList): string
    {
        $list = array_values(array_filter(array_map(static fn($v) => trim((string)$v), $statusList), static fn($v) => $v !== ''));
        if (empty($list)) {
            return self::STATUS_DRAFT;
        }
        if (count(array_unique($list)) === 1) {
            return $list[0];
        }
        $count = array_count_values($list);
        $total = count($list);
        if (($count[self::STATUS_SUCCESS] ?? 0) === $total) {
            return self::STATUS_SUCCESS;
        }
        if (($count[self::STATUS_FAILED] ?? 0) === $total) {
            return self::STATUS_FAILED;
        }
        if (($count[self::STATUS_PROCESSING] ?? 0) > 0) {
            return self::STATUS_PROCESSING;
        }
        if (($count[self::STATUS_QUEUED] ?? 0) > 0) {
            return self::STATUS_QUEUED;
        }
        if (($count[self::STATUS_SUCCESS] ?? 0) > 0 || ($count[self::STATUS_FAILED] ?? 0) > 0 || ($count[self::STATUS_PARTIAL_SUCCESS] ?? 0) > 0) {
            return self::STATUS_PARTIAL_SUCCESS;
        }
        return self::STATUS_DRAFT;
    }

    private function summarizeGroupAuditStatus(array $auditStatusList): string
    {
        $list = array_values(array_filter(array_map(static fn($v) => trim((string)$v), $auditStatusList), static fn($v) => $v !== ''));
        if (empty($list)) {
            return self::AUDIT_PENDING;
        }
        if (count(array_unique($list)) === 1) {
            return $list[0];
        }
        return 'mixed';
    }
}
