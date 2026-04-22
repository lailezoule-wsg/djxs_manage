<?php
declare (strict_types = 1);

namespace app\admin\service;

use app\common\service\FlashSaleRealtimeService;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 管理端秒杀业务服务
 */
class FlashSaleAdminService extends BaseAdminService
{
    private const EXPORT_TASK_DIR = 'flash_sale_export_tasks';
    private const EXPORT_PAGE_SIZE = 300;
    private const EXPORT_CSV_RELATIVE_DIR = 'storage/exports/flash-sale';
    private const RISK_SCENES = ['all', 'create_order'];
    private const RISK_TARGET_TYPES = ['user', 'ip', 'device'];
    private static array $tableExistsCache = [];
    private static ?array $riskThresholdCache = null;

    /**
     * 分页查询活动
     */
    public function activityList(array $params, int $page, int $pageSize): array
    {
        $query = Db::name('flash_sale_activity');
        $keyword = trim((string)($params['keyword'] ?? ''));
        $status = $params['status'] ?? '';
        $withRisk = (int)($params['with_risk'] ?? 1) === 1;
        $riskMinutes = (int)($params['risk_minutes'] ?? 60);
        $riskMinutes = max(5, min(1440, $riskMinutes));
        if ($keyword !== '') {
            $query->whereLike('name', '%' . $keyword . '%');
        }
        if ($status !== '' && is_numeric($status)) {
            $query->where('status', (int)$status);
        }
        $query->order('sort', 'desc')->order('id', 'desc');
        $result = $this->paginateToArray($query, $page, $pageSize);
        if (!empty($result['list'])) {
            $result['list'] = $this->appendActivityTradeMetrics((array)$result['list']);
        }
        if (
            !$withRisk
            || empty($result['list'])
            || !$this->tableExists('flash_sale_risk_log')
            || !$this->tableExists('flash_sale_order')
        ) {
            return $result;
        }
        $result['list'] = $this->appendActivityRiskMetrics((array)$result['list'], $riskMinutes);
        return $result;
    }

    /**
     * 创建活动
     */
    public function activityCreate(array $payload): int
    {
        $data = $this->normalizeActivity($payload, false);
        $data = $this->ensureCreateTimeForInsert('flash_sale_activity', $data);
        $id = (int)Db::name('flash_sale_activity')->insertGetId($data);
        $this->publishRealtimeEvent('activity_created', ['activity_id' => $id]);
        return $id;
    }

    /**
     * 更新活动
     */
    public function activityUpdate(int $id, array $payload): void
    {
        $this->assertExists('flash_sale_activity', $id, '活动不存在');
        $data = $this->normalizeActivity($payload, true);
        if (!empty($data)) {
            Db::name('flash_sale_activity')->where('id', $id)->update($data);
            $this->publishRealtimeEvent('activity_updated', ['activity_id' => $id]);
        }
    }

    /**
     * 发布活动
     */
    public function activityPublish(int $id): void
    {
        $this->assertExists('flash_sale_activity', $id, '活动不存在');
        Db::name('flash_sale_activity')->where('id', $id)->update([
            'status' => 2,
            'update_time' => date('Y-m-d H:i:s'),
        ]);
        $this->publishRealtimeEvent('activity_published', ['activity_id' => $id]);
    }

    /**
     * 关闭活动
     */
    public function activityClose(int $id): void
    {
        $this->assertExists('flash_sale_activity', $id, '活动不存在');
        Db::name('flash_sale_activity')->where('id', $id)->update([
            'status' => 4,
            'update_time' => date('Y-m-d H:i:s'),
        ]);
        $this->publishRealtimeEvent('activity_closed', ['activity_id' => $id]);
    }

    /**
     * 批量更新活动状态
     */
    public function activityBatchStatus(array $payload): int
    {
        $idsRaw = $payload['ids'] ?? [];
        if (!is_array($idsRaw)) {
            throw new ValidateException('活动ID参数格式错误');
        }
        $ids = array_values(array_unique(array_filter(array_map(static fn($id) => (int)$id, $idsRaw), static fn($id) => $id > 0)));
        if (empty($ids)) {
            throw new ValidateException('请选择活动');
        }
        $action = strtolower(trim((string)($payload['action'] ?? '')));
        if (!in_array($action, ['publish', 'close'], true)) {
            throw new ValidateException('批量操作类型不支持');
        }
        $status = $action === 'publish' ? 2 : 4;
        $affected = (int)Db::name('flash_sale_activity')
            ->whereIn('id', $ids)
            ->update([
                'status' => $status,
                'update_time' => date('Y-m-d H:i:s'),
            ]);
        if ($affected > 0) {
            $this->publishRealtimeEvent('activity_batch_status', [
                'activity_ids' => $ids,
                'action' => $action,
                'affected' => $affected,
            ]);
        }
        return $affected;
    }

    /**
     * 复制活动
     */
    public function activityCopy(int $id, int $adminId): int
    {
        $source = Db::name('flash_sale_activity')->where('id', $id)->find();
        if (!$source) {
            throw new ValidateException('活动不存在');
        }
        $newId = (int)Db::transaction(function () use ($source, $id, $adminId) {
            $now = date('Y-m-d H:i:s');
            $newActivity = [
                'name' => (string)$source['name'] . '（复制）',
                'cover' => (string)($source['cover'] ?? ''),
                'start_time' => (string)$source['start_time'],
                'end_time' => (string)$source['end_time'],
                'preheat_time' => (string)($source['preheat_time'] ?? $source['start_time']),
                'status' => 0,
                'sort' => (int)($source['sort'] ?? 0),
                'create_time' => $now,
                'update_time' => $now,
            ];
            if ($this->tableHasColumn('flash_sale_activity', 'creator_id')) {
                $newActivity['creator_id'] = $adminId;
            }
            $newActivityId = (int)Db::name('flash_sale_activity')->insertGetId($newActivity);
            $items = Db::name('flash_sale_item')->where('activity_id', $id)->select()->toArray();
            foreach ($items as $item) {
                Db::name('flash_sale_item')->insert([
                    'activity_id' => $newActivityId,
                    'goods_type' => (int)$item['goods_type'],
                    'goods_id' => (int)$item['goods_id'],
                    'title_snapshot' => (string)($item['title_snapshot'] ?? ''),
                    'cover_snapshot' => (string)($item['cover_snapshot'] ?? ''),
                    'origin_price' => (float)($item['origin_price'] ?? 0),
                    'seckill_price' => (float)($item['seckill_price'] ?? 0),
                    'total_stock' => (int)($item['total_stock'] ?? 0),
                    'sold_stock' => 0,
                    'locked_stock' => 0,
                    'limit_per_user' => max(1, (int)($item['limit_per_user'] ?? 1)),
                    'status' => (int)($item['status'] ?? 1),
                    'create_time' => $now,
                    'update_time' => $now,
                ]);
            }
            return $newActivityId;
        });
        $this->publishRealtimeEvent('activity_copied', ['source_activity_id' => $id, 'activity_id' => $newId]);
        return $newId;
    }

    /**
     * 批量复制活动
     */
    public function activityBatchCopy(array $payload, int $adminId): array
    {
        $idsRaw = $payload['ids'] ?? [];
        if (!is_array($idsRaw)) {
            throw new ValidateException('活动ID参数格式错误');
        }
        $ids = array_values(array_unique(array_filter(array_map(static fn($id) => (int)$id, $idsRaw), static fn($id) => $id > 0)));
        if (empty($ids)) {
            throw new ValidateException('请选择活动');
        }
        if (count($ids) > 50) {
            throw new ValidateException('单次最多复制50个活动');
        }
        $newIds = [];
        foreach ($ids as $id) {
            $newIds[] = $this->activityCopy($id, $adminId);
        }
        if (!empty($newIds)) {
            $this->publishRealtimeEvent('activity_batch_copied', [
                'source_activity_ids' => $ids,
                'activity_ids' => $newIds,
            ]);
        }
        return $newIds;
    }

    /**
     * 分页查询活动下商品列表。
     */
    public function itemList(int $activityId, int $page, int $pageSize): array
    {
        if ($activityId <= 0) {
            throw new ValidateException('活动ID不合法');
        }
        $this->assertExists('flash_sale_activity', $activityId, '活动不存在');
        $query = Db::name('flash_sale_item')->where('activity_id', $activityId)->order('id', 'desc');

        return $this->paginateToArray($query, $page, $pageSize);
    }

    /**
     * 创建活动商品。
     */
    public function itemCreate(array $payload): int
    {
        $data = $this->normalizeItem($payload, false);
        $data = $this->ensureCreateTimeForInsert('flash_sale_item', $data);
        $id = (int)Db::name('flash_sale_item')->insertGetId($data);
        $this->publishRealtimeEvent('item_created', [
            'activity_id' => (int)($data['activity_id'] ?? 0),
            'item_id' => $id,
        ]);
        return $id;
    }

    /**
     * 更新活动商品。
     */
    public function itemUpdate(int $id, array $payload): void
    {
        $item = Db::name('flash_sale_item')->where('id', $id)->find();
        if (!$item) {
            throw new ValidateException('活动商品不存在');
        }
        $data = $this->normalizeItem($payload, true);
        if (!empty($data)) {
            Db::name('flash_sale_item')->where('id', $id)->update($data);
            $this->publishRealtimeEvent('item_updated', [
                'activity_id' => (int)($data['activity_id'] ?? ($item['activity_id'] ?? 0)),
                'item_id' => $id,
            ]);
        }
    }

    /**
     * 删除活动商品。
     */
    public function itemDelete(int $id): void
    {
        $item = Db::name('flash_sale_item')->where('id', $id)->find();
        if (!$item) {
            throw new ValidateException('活动商品不存在');
        }
        Db::name('flash_sale_item')->where('id', $id)->delete();
        $this->publishRealtimeEvent('item_deleted', [
            'activity_id' => (int)($item['activity_id'] ?? 0),
            'item_id' => $id,
        ]);
    }

    /**
     * 分页查询秒杀订单（支持活动/用户/状态/时间区间筛选）。
     */
    public function orderList(array $params, int $page, int $pageSize): array
    {
        $query = Db::name('flash_sale_order')->alias('f')
            ->leftJoin('order o', 'o.id = f.order_id')
            ->leftJoin('flash_sale_item i', 'i.id = f.item_id')
            ->leftJoin('flash_sale_activity a', 'a.id = f.activity_id')
            ->field([
                'f.id',
                'f.order_id',
                'f.activity_id',
                'f.item_id',
                'f.user_id',
                'f.buy_count',
                'f.seckill_price',
                'f.status',
                'f.create_time',
                'o.order_sn',
                'o.pay_amount',
                'o.pay_type',
                'o.status' => 'order_status',
                'a.name' => 'activity_name',
                'i.title_snapshot' => 'item_title',
            ]);

        $activityId = (int)($params['activity_id'] ?? 0);
        $userId = (int)($params['user_id'] ?? 0);
        $itemId = (int)($params['item_id'] ?? 0);
        $status = $params['status'] ?? '';
        $orderStatus = $params['order_status'] ?? '';
        $payType = $params['pay_type'] ?? '';
        $orderSn = trim((string)($params['order_sn'] ?? ''));
        $itemKeyword = trim((string)($params['item_keyword'] ?? ''));
        $startTime = trim((string)($params['start_time'] ?? ''));
        $endTime = trim((string)($params['end_time'] ?? ''));
        if ($activityId > 0) {
            $query->where('f.activity_id', $activityId);
        }
        if ($userId > 0) {
            $query->where('f.user_id', $userId);
        }
        if ($itemId > 0) {
            $query->where('f.item_id', $itemId);
        }
        if ($status !== '' && is_numeric($status)) {
            $query->where('f.status', (int)$status);
        }
        if ($orderStatus !== '' && is_numeric((string)$orderStatus)) {
            $query->where('o.status', (int)$orderStatus);
        }
        if ($payType !== '' && is_numeric((string)$payType)) {
            $query->where('o.pay_type', (int)$payType);
        }
        if ($orderSn !== '') {
            $query->whereLike('o.order_sn', '%' . $orderSn . '%');
        }
        if ($itemKeyword !== '') {
            $query->whereLike('i.title_snapshot', '%' . $itemKeyword . '%');
        }
        if ($startTime !== '' && strtotime($startTime) !== false) {
            $query->where('f.create_time', '>=', $startTime);
        }
        if ($endTime !== '' && strtotime($endTime) !== false) {
            $query->where('f.create_time', '<=', $endTime);
        }
        $query->order('f.id', 'desc');
        return $this->paginateToArray($query, $page, $pageSize);
    }

    /**
     * 创建异步导出任务（先初始化任务元数据与 CSV 头）。
     */
    public function createOrderExportTask(array $params, int $adminId): array
    {
        if ($adminId <= 0) {
            throw new ValidateException('管理员信息无效');
        }
        $this->cleanupOldExportFiles();
        $normalizedParams = $this->normalizeOrderFilterParams($params);
        $taskId = date('YmdHis') . substr(md5(uniqid((string)$adminId, true)), 0, 16);
        $now = date('Y-m-d H:i:s');
        $task = [
            'task_id' => $taskId,
            'admin_id' => $adminId,
            'status' => 'pending',
            'params' => $normalizedParams,
            'page_size' => self::EXPORT_PAGE_SIZE,
            'next_page' => 1,
            'total' => 0,
            'exported' => 0,
            'error_message' => '',
            'failed_page' => 0,
            'download_url' => '/storage/exports/flash-sale/orders-' . $taskId . '.csv',
            'created_at' => $now,
            'updated_at' => $now,
            'finished_at' => '',
        ];
        $this->ensureExportTaskDirectory();
        $this->writeExportCsvHeader($taskId);
        $this->saveExportTask($taskId, $task);
        return $task;
    }

    /**
     * 获取导出任务状态（按 chunk 推进任务）。
     */
    public function getOrderExportTaskStatus(string $taskId, int $adminId): array
    {
        $this->cleanupOldExportFiles();
        $task = $this->loadExportTask($taskId);
        $this->assertExportTaskOwner($task, $adminId);
        if (in_array($task['status'], ['pending', 'running'], true)) {
            $task = $this->processExportTaskChunk($taskId, $task);
        }
        return $this->formatExportTaskStatus($task);
    }

    /**
     * 重试失败导出任务。
     */
    public function retryOrderExportTask(string $taskId, int $adminId): array
    {
        $this->cleanupOldExportFiles();
        $task = $this->loadExportTask($taskId);
        $this->assertExportTaskOwner($task, $adminId);
        if ($task['status'] !== 'failed') {
            throw new ValidateException('当前任务无需重试');
        }
        $task['status'] = 'running';
        $task['error_message'] = '';
        $task['updated_at'] = date('Y-m-d H:i:s');
        $task['failed_page'] = 0;
        $this->saveExportTask($taskId, $task);
        return $this->processExportTaskChunk($taskId, $task);
    }

    /**
     * 分页查询当前管理员的导出任务列表。
     */
    public function listOrderExportTasks(array $params, int $adminId, int $page, int $pageSize): array
    {
        if ($adminId <= 0) {
            throw new ValidateException('管理员信息无效');
        }
        $this->cleanupOldExportFiles();
        $statusFilter = trim((string)($params['status'] ?? ''));
        if ($statusFilter !== '' && !in_array($statusFilter, ['pending', 'running', 'done', 'failed'], true)) {
            $statusFilter = '';
        }
        $startTime = trim((string)($params['start_time'] ?? ''));
        $endTime = trim((string)($params['end_time'] ?? ''));
        $startTs = ($startTime !== '' && strtotime($startTime) !== false) ? (int)strtotime($startTime) : 0;
        $endTs = ($endTime !== '' && strtotime($endTime) !== false) ? (int)strtotime($endTime) : 0;

        $taskDir = $this->getExportTaskDirPath();
        $rows = [];
        if (is_dir($taskDir)) {
            $taskFiles = glob($taskDir . DIRECTORY_SEPARATOR . '*.json');
            if (is_array($taskFiles)) {
                foreach ($taskFiles as $taskFile) {
                    if (!is_file($taskFile)) {
                        continue;
                    }
                    $content = file_get_contents($taskFile);
                    $task = json_decode((string)$content, true);
                    if (!is_array($task)) {
                        continue;
                    }
                    if ((int)($task['admin_id'] ?? 0) !== $adminId) {
                        continue;
                    }
                    $row = $this->formatExportTaskStatus($task);
                    if ($statusFilter !== '' && (string)($row['status'] ?? '') !== $statusFilter) {
                        continue;
                    }
                    $createdTs = strtotime((string)($row['created_at'] ?? '')) ?: 0;
                    if ($startTs > 0 && $createdTs > 0 && $createdTs < $startTs) {
                        continue;
                    }
                    if ($endTs > 0 && $createdTs > 0 && $createdTs > $endTs) {
                        continue;
                    }
                    $rows[] = $row;
                }
            }
        }

        usort($rows, static function (array $a, array $b): int {
            $ta = strtotime((string)($a['updated_at'] ?? '')) ?: 0;
            $tb = strtotime((string)($b['updated_at'] ?? '')) ?: 0;
            if ($ta === $tb) {
                return strcmp((string)($b['task_id'] ?? ''), (string)($a['task_id'] ?? ''));
            }
            return $tb <=> $ta;
        });

        $total = count($rows);
        $offset = max(0, ($page - 1) * $pageSize);
        $list = array_slice($rows, $offset, $pageSize);
        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 删除已结束导出任务及对应 CSV 文件。
     */
    public function deleteOrderExportTask(string $taskId, int $adminId): void
    {
        $task = $this->loadExportTask($taskId);
        $this->assertExportTaskOwner($task, $adminId);
        $status = (string)($task['status'] ?? '');
        if (in_array($status, ['pending', 'running'], true)) {
            throw new ValidateException('任务进行中，暂不支持删除');
        }
        $taskFile = $this->getExportTaskFilePath($taskId);
        if (is_file($taskFile)) {
            @unlink($taskFile);
        }
        $csvPath = $this->getExportCsvPath($taskId);
        if (is_file($csvPath)) {
            @unlink($csvPath);
        }
    }

    /**
     * 活动统计概览（GMV、支付率、售罄数、峰值与趋势）。
     */
    public function statistics(int $activityId, array $params = []): array
    {
        if ($activityId <= 0) {
            throw new ValidateException('活动ID不合法');
        }
        $this->assertExists('flash_sale_activity', $activityId, '活动不存在');

        $startTime = trim((string)($params['start_time'] ?? ''));
        $endTime = trim((string)($params['end_time'] ?? ''));
        $granularity = trim((string)($params['granularity'] ?? 'minute'));
        if (!in_array($granularity, ['minute', 'hour', 'day'], true)) {
            $granularity = 'minute';
        }
        $bucketFormat = '%Y-%m-%d %H:%i:00';
        if ($granularity === 'hour') {
            $bucketFormat = '%Y-%m-%d %H:00:00';
        } elseif ($granularity === 'day') {
            $bucketFormat = '%Y-%m-%d 00:00:00';
        }

        $orderQuery = Db::name('flash_sale_order')->where('activity_id', $activityId);
        if ($startTime !== '' && strtotime($startTime) !== false) {
            $orderQuery->where('create_time', '>=', $startTime);
        }
        if ($endTime !== '' && strtotime($endTime) !== false) {
            $orderQuery->where('create_time', '<=', $endTime);
        }

        $orders = $orderQuery->select()->toArray();
        $orderCount = count($orders);
        $payOrders = array_filter($orders, static fn($o) => (int)$o['status'] === 1);
        $payOrderCount = count($payOrders);
        $gmv = 0.0;
        foreach ($payOrders as $o) {
            $gmv += ((float)$o['seckill_price'] * (int)$o['buy_count']);
        }
        $items = Db::name('flash_sale_item')->where('activity_id', $activityId)->select()->toArray();
        $soldOutCount = 0;
        foreach ($items as $item) {
            $available = (int)$item['total_stock'] - (int)$item['sold_stock'] - (int)$item['locked_stock'];
            if ($available <= 0) {
                $soldOutCount++;
            }
        }

        $timelineQuery = Db::name('flash_sale_order')->where('activity_id', $activityId);
        if ($startTime !== '' && strtotime($startTime) !== false) {
            $timelineQuery->where('create_time', '>=', $startTime);
        }
        if ($endTime !== '' && strtotime($endTime) !== false) {
            $timelineQuery->where('create_time', '<=', $endTime);
        }
        $timelineRows = $timelineQuery
            ->fieldRaw("DATE_FORMAT(create_time, '{$bucketFormat}') AS time_bucket, COUNT(*) AS order_count, SUM(CASE WHEN status=1 THEN 1 ELSE 0 END) AS paid_count")
            ->group('time_bucket')
            ->order('time_bucket', 'asc')
            ->select()
            ->toArray();
        $peakQps = 0;
        foreach ($timelineRows as $row) {
            $peakQps = max($peakQps, (int)$row['order_count']);
        }

        return [
            'gmv' => round($gmv, 2),
            'order_count' => $orderCount,
            'pay_order_count' => $payOrderCount,
            'pay_rate' => $orderCount > 0 ? round($payOrderCount / $orderCount, 4) : 0,
            'sold_out_count' => $soldOutCount,
            'peak_qps' => $peakQps,
            'granularity' => $granularity,
            'timeline' => $timelineRows,
        ];
    }

    /**
     * 分页查询风控命中日志。
     */
    public function riskLogList(array $params, int $page, int $pageSize): array
    {
        if (!$this->tableExists('flash_sale_risk_log')) {
            return [
                'list' => [],
                'total' => 0,
                'page' => $page,
                'page_size' => $pageSize,
            ];
        }
        $this->cleanupOldRiskLogs();
        $query = Db::name('flash_sale_risk_log')->alias('l')
            ->leftJoin('flash_sale_activity a', 'a.id = l.activity_id')
            ->leftJoin('flash_sale_item i', 'i.id = l.item_id')
            ->field([
                'l.id',
                'l.scene',
                'l.reason',
                'l.user_id',
                'l.activity_id',
                'l.item_id',
                'l.client_ip',
                'l.device_id',
                'l.extra_json',
                'l.create_time',
                'a.name' => 'activity_name',
                'i.title_snapshot' => 'item_title',
            ]);
        $reason = trim((string)($params['reason'] ?? ''));
        $targetType = trim((string)($params['target_type'] ?? ''));
        $targetValue = trim((string)($params['target_value'] ?? ''));
        $startTime = trim((string)($params['start_time'] ?? ''));
        $endTime = trim((string)($params['end_time'] ?? ''));
        if ($reason !== '') {
            $query->where('l.reason', $reason);
        }
        if ($targetType !== '' && in_array($targetType, self::RISK_TARGET_TYPES, true) && $targetValue !== '') {
            if ($targetType === 'user') {
                $query->where('l.user_id', (int)$targetValue);
            } elseif ($targetType === 'ip') {
                $query->where('l.client_ip', $targetValue);
            } elseif ($targetType === 'device') {
                $query->where('l.device_id', $targetValue);
            }
        }
        if ($startTime !== '' && strtotime($startTime) !== false) {
            $query->where('l.create_time', '>=', $startTime);
        }
        if ($endTime !== '' && strtotime($endTime) !== false) {
            $query->where('l.create_time', '<=', $endTime);
        }
        $query->order('l.id', 'desc');
        return $this->paginateToArray($query, $page, $pageSize);
    }

    /**
     * 风控汇总看板（原因、Top IP/设备/用户、趋势）。
     */
    public function riskSummary(array $params = []): array
    {
        if (!$this->tableExists('flash_sale_risk_log')) {
            return [
                'minutes' => 60,
                'activity_id' => 0,
                'granularity' => 'minute',
                'since_time' => date('Y-m-d H:i:s'),
                'total_blocked' => 0,
                'latest_block_time' => '',
                'by_reason' => [],
                'top_ips' => [],
                'top_devices' => [],
                'top_users' => [],
                'trend' => [],
            ];
        }
        $this->cleanupOldRiskLogs();
        $minutes = (int)($params['minutes'] ?? 60);
        $minutes = max(5, min(1440, $minutes));
        $activityId = (int)($params['activity_id'] ?? 0);
        $granularity = trim((string)($params['granularity'] ?? 'minute'));
        if (!in_array($granularity, ['minute', 'hour', 'day'], true)) {
            $granularity = 'minute';
        }
        $bucketFormat = '%Y-%m-%d %H:%i:00';
        if ($granularity === 'hour') {
            $bucketFormat = '%Y-%m-%d %H:00:00';
        } elseif ($granularity === 'day') {
            $bucketFormat = '%Y-%m-%d 00:00:00';
        }
        $since = date('Y-m-d H:i:s', time() - $minutes * 60);

        $base = Db::name('flash_sale_risk_log')->where('create_time', '>=', $since);
        if ($activityId > 0) {
            $base->where('activity_id', $activityId);
        }
        $totalBlocked = (int)(clone $base)->count();
        $latestBlockTime = (string)((clone $base)->max('create_time') ?: '');

        $byReasonRows = (clone $base)
            ->field('reason, COUNT(*) AS cnt')
            ->group('reason')
            ->order('cnt', 'desc')
            ->limit(10)
            ->select()
            ->toArray();
        $byReason = array_map(static function (array $row): array {
            return [
                'reason' => (string)($row['reason'] ?? ''),
                'count' => (int)($row['cnt'] ?? 0),
            ];
        }, $byReasonRows);

        $topIpRows = (clone $base)
            ->where('client_ip', '<>', '')
            ->field('client_ip, COUNT(*) AS cnt')
            ->group('client_ip')
            ->order('cnt', 'desc')
            ->limit(5)
            ->select()
            ->toArray();
        $topIps = array_map(static function (array $row): array {
            return [
                'client_ip' => (string)($row['client_ip'] ?? ''),
                'count' => (int)($row['cnt'] ?? 0),
            ];
        }, $topIpRows);

        $topDeviceRows = (clone $base)
            ->where('device_id', '<>', '')
            ->field('device_id, COUNT(*) AS cnt')
            ->group('device_id')
            ->order('cnt', 'desc')
            ->limit(5)
            ->select()
            ->toArray();
        $topDevices = array_map(static function (array $row): array {
            return [
                'device_id' => (string)($row['device_id'] ?? ''),
                'count' => (int)($row['cnt'] ?? 0),
            ];
        }, $topDeviceRows);

        $trendRows = (clone $base)
            ->fieldRaw("DATE_FORMAT(create_time, '{$bucketFormat}') AS time_bucket, COUNT(*) AS cnt")
            ->group('time_bucket')
            ->order('time_bucket', 'asc')
            ->select()
            ->toArray();
        $trend = array_map(static function (array $row): array {
            return [
                'time_bucket' => (string)($row['time_bucket'] ?? ''),
                'count' => (int)($row['cnt'] ?? 0),
            ];
        }, $trendRows);

        $topUserRows = (clone $base)
            ->where('user_id', '>', 0)
            ->field('user_id, COUNT(*) AS cnt')
            ->group('user_id')
            ->order('cnt', 'desc')
            ->limit(10)
            ->select()
            ->toArray();
        $topUsers = array_map(static function (array $row): array {
            return [
                'user_id' => (int)($row['user_id'] ?? 0),
                'count' => (int)($row['cnt'] ?? 0),
            ];
        }, $topUserRows);

        return [
            'minutes' => $minutes,
            'activity_id' => $activityId,
            'granularity' => $granularity,
            'since_time' => $since,
            'total_blocked' => $totalBlocked,
            'latest_block_time' => $latestBlockTime,
            'by_reason' => $byReason,
            'top_ips' => $topIps,
            'top_devices' => $topDevices,
            'top_users' => $topUsers,
            'trend' => $trend,
        ];
    }

    /**
     * 活动健康分历史趋势（按分钟/小时/天聚合）。
     */
    public function riskHealthHistory(array $params = []): array
    {
        if (!$this->tableExists('flash_sale_risk_log')) {
            return [
                'activity_id' => 0,
                'minutes' => 60,
                'granularity' => 'minute',
                'since_time' => date('Y-m-d H:i:s'),
                'latest_health' => 100,
                'latest_level' => 'safe',
                'trend' => [],
            ];
        }
        $activityId = (int)($params['activity_id'] ?? 0);
        if ($activityId <= 0) {
            throw new ValidateException('活动ID不合法');
        }
        $minutes = (int)($params['minutes'] ?? 60);
        $minutes = max(5, min(10080, $minutes));
        $granularity = trim((string)($params['granularity'] ?? 'minute'));
        if (!in_array($granularity, ['minute', 'hour', 'day'], true)) {
            $granularity = 'minute';
        }
        $bucketFormat = '%Y-%m-%d %H:%i:00';
        if ($granularity === 'hour') {
            $bucketFormat = '%Y-%m-%d %H:00:00';
        } elseif ($granularity === 'day') {
            $bucketFormat = '%Y-%m-%d 00:00:00';
        }
        $since = date('Y-m-d H:i:s', time() - $minutes * 60);
        $riskRows = Db::name('flash_sale_risk_log')
            ->where('activity_id', $activityId)
            ->where('create_time', '>=', $since)
            ->fieldRaw("DATE_FORMAT(create_time, '{$bucketFormat}') AS time_bucket, COUNT(*) AS risk_count")
            ->group('time_bucket')
            ->order('time_bucket', 'asc')
            ->select()
            ->toArray();
        $riskMap = [];
        foreach ($riskRows as $row) {
            $riskMap[(string)$row['time_bucket']] = (int)($row['risk_count'] ?? 0);
        }
        $orderMap = [];
        if ($this->tableExists('flash_sale_order')) {
            $orderRows = Db::name('flash_sale_order')
                ->where('activity_id', $activityId)
                ->where('create_time', '>=', $since)
                ->fieldRaw("DATE_FORMAT(create_time, '{$bucketFormat}') AS time_bucket, COUNT(*) AS order_count")
                ->group('time_bucket')
                ->order('time_bucket', 'asc')
                ->select()
                ->toArray();
            foreach ($orderRows as $row) {
                $orderMap[(string)$row['time_bucket']] = (int)($row['order_count'] ?? 0);
            }
        }
        $topActorRows = Db::name('flash_sale_risk_log')
            ->where('activity_id', $activityId)
            ->where('create_time', '>=', $since)
            ->fieldRaw("DATE_FORMAT(create_time, '{$bucketFormat}') AS time_bucket, CONCAT(IFNULL(client_ip, ''), '#', IFNULL(device_id, ''), '#', IFNULL(user_id, 0)) AS actor_key, COUNT(*) AS cnt")
            ->group('time_bucket, actor_key')
            ->select()
            ->toArray();
        $topActorMap = [];
        foreach ($topActorRows as $row) {
            $bucket = (string)($row['time_bucket'] ?? '');
            $cnt = (int)($row['cnt'] ?? 0);
            if (!isset($topActorMap[$bucket]) || $cnt > $topActorMap[$bucket]) {
                $topActorMap[$bucket] = $cnt;
            }
        }
        $bucketKeys = array_values(array_unique(array_merge(array_keys($riskMap), array_keys($orderMap))));
        sort($bucketKeys);
        $trend = [];
        foreach ($bucketKeys as $bucket) {
            $riskCount = (int)($riskMap[$bucket] ?? 0);
            $orderCount = (int)($orderMap[$bucket] ?? 0);
            $topActorCount = (int)($topActorMap[$bucket] ?? 0);
            $health = $this->calcActivityRiskHealth($riskCount, $orderCount, $topActorCount);
            $trend[] = [
                'time_bucket' => $bucket,
                'risk_count' => $riskCount,
                'order_count' => $orderCount,
                'top_actor_count' => $topActorCount,
                'health' => $health,
                'risk_level' => $this->resolveRiskLevel($health, $activityId),
            ];
        }
        $latest = !empty($trend) ? $trend[count($trend) - 1] : ['health' => 100, 'risk_level' => 'safe'];
        return [
            'activity_id' => $activityId,
            'minutes' => $minutes,
            'granularity' => $granularity,
            'since_time' => $since,
            'thresholds' => $this->getRiskThresholds($activityId),
            'latest_health' => (int)($latest['health'] ?? 100),
            'latest_level' => (string)($latest['risk_level'] ?? 'safe'),
            'trend' => $trend,
        ];
    }

    /**
     * 获取风控健康分阈值（全局/活动覆盖）。
     */
    public function riskHealthThresholdGet(array $params = []): array
    {
        $activityId = (int)($params['activity_id'] ?? 0);
        if ($activityId > 0) {
            $this->assertExists('flash_sale_activity', $activityId, '活动不存在');
        }
        $thresholds = $this->getRiskThresholds($activityId);
        $thresholds['activity_id'] = $activityId;
        $thresholds['scope'] = $activityId > 0 ? 'activity' : 'global';
        $thresholds['activity_overridden'] = $activityId > 0 ? (int)$this->hasActivityRiskThresholdOverride($activityId) : 0;
        return $thresholds;
    }

    /**
     * 更新风控健康分阈值，支持活动级覆盖与恢复全局。
     */
    public function riskHealthThresholdUpdate(array $payload): array
    {
        $activityId = (int)($payload['activity_id'] ?? 0);
        if ($activityId > 0) {
            $this->assertExists('flash_sale_activity', $activityId, '活动不存在');
        }
        $resetToGlobal = (int)($payload['reset_to_global'] ?? 0) === 1;
        if ($resetToGlobal && $activityId <= 0) {
            throw new ValidateException('仅活动维度支持恢复全局阈值');
        }
        if ($resetToGlobal) {
            $prefix = 'flash_sale_risk_threshold_activity_' . $activityId . '_';
            $this->deleteSystemConfigKeys([
                $prefix . 'safe',
                $prefix . 'attention',
                $prefix . 'warning',
            ]);
            self::$riskThresholdCache = null;
            $thresholds = $this->getRiskThresholds($activityId);
            $thresholds['activity_id'] = $activityId;
            $thresholds['scope'] = 'activity';
            $thresholds['activity_overridden'] = 0;
            return $thresholds;
        }
        $safe = (int)($payload['safe_min'] ?? 85);
        $attention = (int)($payload['attention_min'] ?? 60);
        $warning = (int)($payload['warning_min'] ?? 40);
        $safe = max(1, min(100, $safe));
        $attention = max(1, min(99, $attention));
        $warning = max(0, min(98, $warning));
        if (!($safe > $attention && $attention > $warning)) {
            throw new ValidateException('阈值需满足 safe > attention > warning');
        }
        $prefix = $activityId > 0 ? ('flash_sale_risk_threshold_activity_' . $activityId . '_') : 'flash_sale_risk_threshold_';
        $this->saveSystemConfigValues([
            $prefix . 'safe' => (string)$safe,
            $prefix . 'attention' => (string)$attention,
            $prefix . 'warning' => (string)$warning,
        ]);
        self::$riskThresholdCache = null;
        $thresholds = $this->getRiskThresholds($activityId);
        $thresholds['activity_id'] = $activityId;
        $thresholds['scope'] = $activityId > 0 ? 'activity' : 'global';
        $thresholds['activity_overridden'] = $activityId > 0 ? 1 : 0;
        return $thresholds;
    }

    /**
     * 分页查询风控黑名单。
     */
    public function blacklistList(array $params, int $page, int $pageSize): array
    {
        if (!$this->tableExists('flash_sale_risk_blacklist')) {
            return [
                'list' => [],
                'total' => 0,
                'page' => $page,
                'page_size' => $pageSize,
            ];
        }
        $query = Db::name('flash_sale_risk_blacklist');
        $targetType = trim((string)($params['target_type'] ?? ''));
        $targetValue = trim((string)($params['target_value'] ?? ''));
        $status = $params['status'] ?? '';
        $scene = trim((string)($params['scene'] ?? ''));
        if ($targetType !== '' && in_array($targetType, self::RISK_TARGET_TYPES, true)) {
            $query->where('target_type', $targetType);
        }
        if ($targetValue !== '') {
            $query->whereLike('target_value', '%' . $targetValue . '%');
        }
        if ($status !== '' && is_numeric((string)$status)) {
            $query->where('status', (int)$status);
        }
        if ($scene !== '' && in_array($scene, self::RISK_SCENES, true)) {
            $query->where('scene', $scene);
        }
        $query->order('id', 'desc');
        return $this->paginateToArray($query, $page, $pageSize);
    }

    /**
     * 手动触发风控日志历史清理。
     */
    public function cleanupRiskLogs(): int
    {
        return $this->cleanupOldRiskLogs();
    }

    /**
     * 创建黑名单记录。
     */
    public function blacklistCreate(array $payload, int $adminId): int
    {
        if (!$this->tableExists('flash_sale_risk_blacklist')) {
            throw new ValidateException('风控黑名单表不存在，请先执行最新迁移');
        }
        if ($adminId <= 0) {
            throw new ValidateException('管理员信息无效');
        }
        $data = $this->normalizeBlacklist($payload, false);
        $data['created_by'] = $adminId;
        $data = $this->ensureCreateTimeForInsert('flash_sale_risk_blacklist', $data);
        return (int)Db::name('flash_sale_risk_blacklist')->insertGetId($data);
    }

    /**
     * 更新黑名单记录。
     */
    public function blacklistUpdate(int $id, array $payload, int $adminId): void
    {
        if (!$this->tableExists('flash_sale_risk_blacklist')) {
            throw new ValidateException('风控黑名单表不存在，请先执行最新迁移');
        }
        if ($adminId <= 0) {
            throw new ValidateException('管理员信息无效');
        }
        $this->assertExists('flash_sale_risk_blacklist', $id, '黑名单记录不存在');
        $data = $this->normalizeBlacklist($payload, true);
        if (empty($data)) {
            return;
        }
        $data['updated_by'] = $adminId;
        Db::name('flash_sale_risk_blacklist')->where('id', $id)->update($data);
    }

    /**
     * 软删除黑名单（置 status=0）。
     */
    public function blacklistDelete(int $id, int $adminId): void
    {
        if (!$this->tableExists('flash_sale_risk_blacklist')) {
            throw new ValidateException('风控黑名单表不存在，请先执行最新迁移');
        }
        if ($adminId <= 0) {
            throw new ValidateException('管理员信息无效');
        }
        $this->assertExists('flash_sale_risk_blacklist', $id, '黑名单记录不存在');
        Db::name('flash_sale_risk_blacklist')->where('id', $id)->update([
            'status' => 0,
            'updated_by' => $adminId,
            'update_time' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 规范化订单筛选参数（导出与列表复用）。
     */
    private function normalizeOrderFilterParams(array $params): array
    {
        $normalized = [
            'activity_id' => (int)($params['activity_id'] ?? 0),
            'user_id' => (int)($params['user_id'] ?? 0),
            'item_id' => (int)($params['item_id'] ?? 0),
            'status' => '',
            'order_status' => '',
            'pay_type' => '',
            'start_time' => '',
            'end_time' => '',
        ];
        if (isset($params['status']) && $params['status'] !== '' && is_numeric((string)$params['status'])) {
            $normalized['status'] = (int)$params['status'];
        }
        if (isset($params['order_status']) && $params['order_status'] !== '' && is_numeric((string)$params['order_status'])) {
            $normalized['order_status'] = (int)$params['order_status'];
        }
        if (isset($params['pay_type']) && $params['pay_type'] !== '' && is_numeric((string)$params['pay_type'])) {
            $normalized['pay_type'] = (int)$params['pay_type'];
        }
        $startTime = trim((string)($params['start_time'] ?? ''));
        $endTime = trim((string)($params['end_time'] ?? ''));
        if ($startTime !== '' && strtotime($startTime) !== false) {
            $normalized['start_time'] = $startTime;
        }
        if ($endTime !== '' && strtotime($endTime) !== false) {
            $normalized['end_time'] = $endTime;
        }
        return $normalized;
    }

    /**
     * 检测指定表是否包含某字段（兼容灰度结构）。
     */
    private function tableHasColumn(string $table, string $column): bool
    {
        try {
            $fields = Db::name($table)->getFields();
            return array_key_exists($column, $fields);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 确保导出任务目录与公开 CSV 目录存在。
     */
    private function ensureExportTaskDirectory(): void
    {
        $taskDir = $this->getExportTaskDirPath();
        if (!is_dir($taskDir)) {
            mkdir($taskDir, 0775, true);
        }
        $publicDir = $this->getExportCsvDirPath();
        if (!is_dir($publicDir)) {
            mkdir($publicDir, 0775, true);
        }
    }

    /**
     * 导出任务元数据目录（runtime 下）。
     */
    private function getExportTaskDirPath(): string
    {
        return rtrim(app()->getRuntimePath(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::EXPORT_TASK_DIR;
    }

    /**
     * 导出任务元数据文件路径（JSON）。
     */
    private function getExportTaskFilePath(string $taskId): string
    {
        return $this->getExportTaskDirPath() . DIRECTORY_SEPARATOR . $taskId . '.json';
    }

    /**
     * 导出 CSV 绝对路径。
     */
    private function getExportCsvPath(string $taskId): string
    {
        return rtrim(app()->getRootPath(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'public'
            . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'exports'
            . DIRECTORY_SEPARATOR . 'flash-sale'
            . DIRECTORY_SEPARATOR . 'orders-' . $taskId . '.csv';
    }

    /**
     * 导出 CSV 所在目录绝对路径。
     */
    private function getExportCsvDirPath(): string
    {
        return rtrim(app()->getRootPath(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'public'
            . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::EXPORT_CSV_RELATIVE_DIR);
    }

    /**
     * 清理超过保留期的导出任务文件与 CSV 文件。
     */
    private function cleanupOldExportFiles(): void
    {
        $retentionDays = (int)config('app.flash_sale_export_retention_days', 7);
        if ($retentionDays <= 0) {
            return;
        }
        $expireBefore = time() - ($retentionDays * 86400);

        $taskDir = $this->getExportTaskDirPath();
        if (is_dir($taskDir)) {
            $taskFiles = glob($taskDir . DIRECTORY_SEPARATOR . '*.json');
            if (is_array($taskFiles)) {
                foreach ($taskFiles as $taskFile) {
                    if (!is_file($taskFile)) {
                        continue;
                    }
                    $taskContent = file_get_contents($taskFile);
                    $task = json_decode((string)$taskContent, true);
                    $taskTime = filemtime($taskFile) ?: time();
                    if (is_array($task)) {
                        $refTime = strtotime((string)($task['finished_at'] ?? '')) ?: strtotime((string)($task['updated_at'] ?? ''));
                        if ($refTime !== false) {
                            $taskTime = $refTime;
                        }
                    }
                    if ($taskTime >= $expireBefore) {
                        continue;
                    }
                    if (is_array($task) && !empty($task['task_id'])) {
                        $csvPath = $this->getExportCsvPath((string)$task['task_id']);
                        if (is_file($csvPath)) {
                            @unlink($csvPath);
                        }
                    }
                    @unlink($taskFile);
                }
            }
        }

        $csvDir = $this->getExportCsvDirPath();
        if (is_dir($csvDir)) {
            $csvFiles = glob($csvDir . DIRECTORY_SEPARATOR . '*.csv');
            if (is_array($csvFiles)) {
                foreach ($csvFiles as $csvFile) {
                    if (!is_file($csvFile)) {
                        continue;
                    }
                    $mtime = filemtime($csvFile) ?: time();
                    if ($mtime < $expireBefore) {
                        @unlink($csvFile);
                    }
                }
            }
        }
    }

    /**
     * 清理超过保留期的风控日志。
     */
    private function cleanupOldRiskLogs(): int
    {
        if (!$this->tableExists('flash_sale_risk_log')) {
            return 0;
        }
        $retentionDays = (int)config('app.flash_sale_risk_log_retention_days', 30);
        if ($retentionDays <= 0) {
            return 0;
        }
        $expireBefore = date('Y-m-d H:i:s', time() - ($retentionDays * 86400));
        return (int)Db::name('flash_sale_risk_log')
            ->where('create_time', '<', $expireBefore)
            ->delete();
    }

    /**
     * 为活动列表补充交易指标（商品数、销量、GMV）。
     */
    private function appendActivityTradeMetrics(array $activities): array
    {
        if (empty($activities)) {
            return $activities;
        }
        $activityIds = array_values(array_filter(array_map(static fn ($row) => (int)($row['id'] ?? 0), $activities)));
        if (empty($activityIds)) {
            return $activities;
        }

        $itemStatsMap = [];
        if ($this->tableExists('flash_sale_item')) {
            $itemStats = Db::name('flash_sale_item')
                ->whereIn('activity_id', $activityIds)
                ->fieldRaw('activity_id, COUNT(*) AS item_count, SUM(sold_stock) AS sold_count')
                ->group('activity_id')
                ->select()
                ->toArray();
            foreach ($itemStats as $row) {
                $itemStatsMap[(int)$row['activity_id']] = [
                    'item_count' => (int)($row['item_count'] ?? 0),
                    'sold_count' => (int)($row['sold_count'] ?? 0),
                ];
            }
        }

        $gmvMap = [];
        if ($this->tableExists('flash_sale_order')) {
            $gmvRows = Db::name('flash_sale_order')
                ->whereIn('activity_id', $activityIds)
                ->where('status', 1)
                ->fieldRaw('activity_id, SUM(buy_count * seckill_price) AS gmv')
                ->group('activity_id')
                ->select()
                ->toArray();
            foreach ($gmvRows as $row) {
                $gmvMap[(int)$row['activity_id']] = round((float)($row['gmv'] ?? 0), 2);
            }
        }

        return array_map(function (array $row) use ($itemStatsMap, $gmvMap): array {
            $activityId = (int)($row['id'] ?? 0);
            $row['item_count'] = (int)($itemStatsMap[$activityId]['item_count'] ?? 0);
            $row['sold_count'] = (int)($itemStatsMap[$activityId]['sold_count'] ?? 0);
            $row['gmv'] = (float)($gmvMap[$activityId] ?? 0);
            $row['creator_name'] = (string)($row['creator_name'] ?? '-');
            return $row;
        }, $activities);
    }

    /**
     * 检测表是否存在（带进程内缓存）。
     */
    private function tableExists(string $table): bool
    {
        if (isset(self::$tableExistsCache[$table])) {
            return self::$tableExistsCache[$table];
        }
        try {
            $fields = Db::name($table)->getFields();
            self::$tableExistsCache[$table] = is_array($fields) && !empty($fields);
        } catch (\Throwable $e) {
            self::$tableExistsCache[$table] = false;
        }
        return self::$tableExistsCache[$table];
    }

    /**
     * 写入 CSV 表头并添加 UTF-8 BOM。
     */
    private function writeExportCsvHeader(string $taskId): void
    {
        $csvPath = $this->getExportCsvPath($taskId);
        $dir = dirname($csvPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $fp = fopen($csvPath, 'wb');
        if ($fp === false) {
            throw new ValidateException('导出文件创建失败');
        }
        fwrite($fp, "\xEF\xBB\xBF");
        fputcsv($fp, ['ID', '活动', '商品', '订单号', '用户ID', '数量', '单价', '秒杀状态', '订单状态', '创建时间']);
        fclose($fp);
    }

    /**
     * 追加一批导出数据行。
     */
    private function appendExportRows(string $taskId, array $rows): void
    {
        if (empty($rows)) {
            return;
        }
        $csvPath = $this->getExportCsvPath($taskId);
        $fp = fopen($csvPath, 'ab');
        if ($fp === false) {
            throw new ValidateException('导出文件写入失败');
        }
        foreach ($rows as $row) {
            fputcsv($fp, [
                (int)($row['id'] ?? 0),
                (string)($row['activity_name'] ?? ''),
                (string)($row['item_title'] ?? ''),
                (string)($row['order_sn'] ?? ''),
                (int)($row['user_id'] ?? 0),
                (int)($row['buy_count'] ?? 0),
                (string)($row['seckill_price'] ?? ''),
                $this->flashOrderStatusText((int)($row['status'] ?? 0)),
                $this->orderStatusText((int)($row['order_status'] ?? 0)),
                (string)($row['create_time'] ?? ''),
            ]);
        }
        fclose($fp);
    }

    /**
     * 秒杀订单状态文案映射。
     */
    private function flashOrderStatusText(int $status): string
    {
        return [
            0 => '待支付',
            1 => '已支付',
            2 => '已取消',
            3 => '已超时',
        ][$status] ?? '-';
    }

    /**
     * 普通订单状态文案映射。
     */
    private function orderStatusText(int $status): string
    {
        return [
            0 => '待支付',
            1 => '已支付',
            2 => '已取消',
            3 => '已退款',
        ][$status] ?? '-';
    }

    /**
     * 读取导出任务元数据。
     */
    private function loadExportTask(string $taskId): array
    {
        $file = $this->getExportTaskFilePath($taskId);
        if (!is_file($file)) {
            throw new ValidateException('导出任务不存在');
        }
        $content = file_get_contents($file);
        $task = json_decode((string)$content, true);
        if (!is_array($task)) {
            throw new ValidateException('导出任务数据异常');
        }
        return $task;
    }

    /**
     * 持久化导出任务元数据。
     */
    private function saveExportTask(string $taskId, array $task): void
    {
        $file = $this->getExportTaskFilePath($taskId);
        file_put_contents($file, json_encode($task, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * 校验导出任务所属管理员。
     */
    private function assertExportTaskOwner(array $task, int $adminId): void
    {
        if ((int)($task['admin_id'] ?? 0) !== $adminId) {
            throw new ValidateException('无权访问该导出任务');
        }
    }

    /**
     * 处理一段导出任务（单页查询 + 追加 CSV + 状态推进）。
     */
    private function processExportTaskChunk(string $taskId, array $task): array
    {
        $task['status'] = 'running';
        $task['updated_at'] = date('Y-m-d H:i:s');
        $this->saveExportTask($taskId, $task);

        try {
            $result = $this->orderList(
                (array)($task['params'] ?? []),
                (int)($task['next_page'] ?? 1),
                (int)($task['page_size'] ?? self::EXPORT_PAGE_SIZE)
            );
            $rows = (array)($result['list'] ?? []);
            $total = (int)($result['total'] ?? 0);

            $this->appendExportRows($taskId, $rows);

            $task['total'] = $total;
            $task['exported'] = (int)($task['exported'] ?? 0) + count($rows);
            $task['next_page'] = (int)($task['next_page'] ?? 1) + 1;
            $task['updated_at'] = date('Y-m-d H:i:s');

            if (empty($rows) || $task['exported'] >= $total) {
                $task['status'] = 'done';
                $task['finished_at'] = date('Y-m-d H:i:s');
                $task['exported'] = min($task['exported'], $total);
            }
        } catch (\Throwable $e) {
            $task['status'] = 'failed';
            $task['error_message'] = $e->getMessage();
            $task['failed_page'] = (int)($task['next_page'] ?? 1);
            $task['updated_at'] = date('Y-m-d H:i:s');
        }

        $this->saveExportTask($taskId, $task);
        return $task;
    }

    /**
     * 格式化导出任务状态响应（含进度）。
     */
    private function formatExportTaskStatus(array $task): array
    {
        $total = (int)($task['total'] ?? 0);
        $exported = (int)($task['exported'] ?? 0);
        $progress = $total > 0 ? (int)min(100, round($exported / $total * 100)) : 0;
        if (($task['status'] ?? '') === 'done') {
            $progress = 100;
        }
        return [
            'task_id' => (string)($task['task_id'] ?? ''),
            'status' => (string)($task['status'] ?? 'pending'),
            'total' => $total,
            'exported' => $exported,
            'progress' => $progress,
            'next_page' => (int)($task['next_page'] ?? 1),
            'failed_page' => (int)($task['failed_page'] ?? 0),
            'error_message' => (string)($task['error_message'] ?? ''),
            'download_url' => (string)($task['download_url'] ?? ''),
            'created_at' => (string)($task['created_at'] ?? ''),
            'updated_at' => (string)($task['updated_at'] ?? ''),
            'finished_at' => (string)($task['finished_at'] ?? ''),
        ];
    }

    /**
     * 为活动列表补充风控健康指标。
     */
    private function appendActivityRiskMetrics(array $activities, int $minutes): array
    {
        if (empty($activities)) {
            return $activities;
        }
        $activityIds = array_values(array_unique(array_filter(array_map(static function (array $row): int {
            return (int)($row['id'] ?? 0);
        }, $activities))));
        if (empty($activityIds)) {
            return $activities;
        }

        $since = date('Y-m-d H:i:s', time() - $minutes * 60);
        $riskRows = Db::name('flash_sale_risk_log')
            ->whereIn('activity_id', $activityIds)
            ->where('create_time', '>=', $since)
            ->field('activity_id, COUNT(*) AS risk_count')
            ->group('activity_id')
            ->select()
            ->toArray();
        $riskMap = [];
        foreach ($riskRows as $row) {
            $riskMap[(int)($row['activity_id'] ?? 0)] = (int)($row['risk_count'] ?? 0);
        }

        $orderRows = Db::name('flash_sale_order')
            ->whereIn('activity_id', $activityIds)
            ->where('create_time', '>=', $since)
            ->field('activity_id, COUNT(*) AS order_count')
            ->group('activity_id')
            ->select()
            ->toArray();
        $orderMap = [];
        foreach ($orderRows as $row) {
            $orderMap[(int)($row['activity_id'] ?? 0)] = (int)($row['order_count'] ?? 0);
        }

        $topActorRows = Db::name('flash_sale_risk_log')
            ->whereIn('activity_id', $activityIds)
            ->where('create_time', '>=', $since)
            ->fieldRaw("activity_id, CONCAT(IFNULL(client_ip, ''), '#', IFNULL(device_id, ''), '#', IFNULL(user_id, 0)) AS actor_key, COUNT(*) AS cnt")
            ->group('activity_id, actor_key')
            ->select()
            ->toArray();
        $topActorMap = [];
        foreach ($topActorRows as $row) {
            $aid = (int)($row['activity_id'] ?? 0);
            $cnt = (int)($row['cnt'] ?? 0);
            if (!isset($topActorMap[$aid]) || $cnt > $topActorMap[$aid]) {
                $topActorMap[$aid] = $cnt;
            }
        }

        foreach ($activities as &$activity) {
            $activityId = (int)($activity['id'] ?? 0);
            $riskCount = (int)($riskMap[$activityId] ?? 0);
            $orderCount = (int)($orderMap[$activityId] ?? 0);
            $topActorCount = (int)($topActorMap[$activityId] ?? 0);
            $activity['risk_health'] = $this->calcActivityRiskHealth($riskCount, $orderCount, $topActorCount);
            $activity['risk_level'] = $this->resolveRiskLevel((int)$activity['risk_health'], $activityId);
            $activity['risk_count_window'] = $riskCount;
            $activity['risk_order_count_window'] = $orderCount;
            $activity['risk_window_minutes'] = $minutes;
        }
        unset($activity);
        return $activities;
    }

    /**
     * 计算活动风控健康分（0-100）。
     */
    private function calcActivityRiskHealth(int $riskCount, int $orderCount, int $topActorCount): int
    {
        if ($riskCount <= 0) {
            return 100;
        }
        $denominator = max(1, $riskCount + $orderCount);
        $blockRate = $riskCount / $denominator;
        $concentration = $topActorCount / max(1, $riskCount);
        $volumePenalty = min(1, $riskCount / 100);
        $riskScore = ($blockRate * 0.5 + $concentration * 0.35 + $volumePenalty * 0.15) * 100;
        $health = 100 - (int)round($riskScore);
        return max(0, min(100, $health));
    }

    /**
     * 根据健康分映射风险级别（safe/attention/warning/critical）。
     */
    private function resolveRiskLevel(int $health, int $activityId = 0): string
    {
        $thresholds = $this->getRiskThresholds($activityId);
        if ($health >= (int)$thresholds['safe_min']) {
            return 'safe';
        }
        if ($health >= (int)$thresholds['attention_min']) {
            return 'attention';
        }
        if ($health >= (int)$thresholds['warning_min']) {
            return 'warning';
        }
        return 'critical';
    }

    /**
     * 读取风险阈值（活动覆盖优先，否则回退全局）。
     */
    private function getRiskThresholds(int $activityId = 0): array
    {
        $cacheKey = (string)$activityId;
        if (self::$riskThresholdCache === null) {
            self::$riskThresholdCache = [];
        }
        if (self::$riskThresholdCache !== null && array_key_exists($cacheKey, self::$riskThresholdCache)) {
            return (array)self::$riskThresholdCache[$cacheKey];
        }
        $defaults = [
            'safe_min' => 85,
            'attention_min' => 60,
            'warning_min' => 35,
        ];
        if (!$this->tableExists('system_config')) {
            self::$riskThresholdCache[$cacheKey] = $defaults;
            return $defaults;
        }
        $globalRows = Db::name('system_config')
            ->whereIn('key', [
                'flash_sale_risk_threshold_safe',
                'flash_sale_risk_threshold_attention',
                'flash_sale_risk_threshold_warning',
            ])
            ->column('value', 'key');
        $safe = (int)($globalRows['flash_sale_risk_threshold_safe'] ?? $defaults['safe_min']);
        $attention = (int)($globalRows['flash_sale_risk_threshold_attention'] ?? $defaults['attention_min']);
        $warning = (int)($globalRows['flash_sale_risk_threshold_warning'] ?? $defaults['warning_min']);
        if ($activityId > 0) {
            $activityRows = Db::name('system_config')
                ->whereIn('key', [
                    'flash_sale_risk_threshold_activity_' . $activityId . '_safe',
                    'flash_sale_risk_threshold_activity_' . $activityId . '_attention',
                    'flash_sale_risk_threshold_activity_' . $activityId . '_warning',
                ])
                ->column('value', 'key');
            $safe = (int)($activityRows['flash_sale_risk_threshold_activity_' . $activityId . '_safe'] ?? $safe);
            $attention = (int)($activityRows['flash_sale_risk_threshold_activity_' . $activityId . '_attention'] ?? $attention);
            $warning = (int)($activityRows['flash_sale_risk_threshold_activity_' . $activityId . '_warning'] ?? $warning);
        }
        $safe = max(1, min(100, $safe));
        $attention = max(1, min(99, $attention));
        $warning = max(0, min(98, $warning));
        if (!($safe > $attention && $attention > $warning)) {
            $safe = $defaults['safe_min'];
            $attention = $defaults['attention_min'];
            $warning = $defaults['warning_min'];
        }
        $result = [
            'safe_min' => $safe,
            'attention_min' => $attention,
            'warning_min' => $warning,
        ];
        self::$riskThresholdCache[$cacheKey] = $result;
        return $result;
    }

    /**
     * 判断活动是否配置了独立阈值覆盖。
     */
    private function hasActivityRiskThresholdOverride(int $activityId): bool
    {
        if ($activityId <= 0 || !$this->tableExists('system_config')) {
            return false;
        }
        $keys = [
            'flash_sale_risk_threshold_activity_' . $activityId . '_safe',
            'flash_sale_risk_threshold_activity_' . $activityId . '_attention',
            'flash_sale_risk_threshold_activity_' . $activityId . '_warning',
        ];
        $count = Db::name('system_config')->whereIn('key', $keys)->count();
        return (int)$count > 0;
    }

    /**
     * 批量写入 system_config 键值。
     */
    private function saveSystemConfigValues(array $dict): void
    {
        if (!$this->tableExists('system_config')) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        foreach ($dict as $key => $value) {
            $exists = Db::name('system_config')->whereRaw('`key` = ?', [$key])->find();
            if ($exists) {
                Db::name('system_config')->whereRaw('`key` = ?', [$key])->update([
                    'value' => (string)$value,
                    'update_time' => $now,
                ]);
                continue;
            }
            Db::name('system_config')->insert([
                'key' => (string)$key,
                'value' => (string)$value,
                'description' => '',
                'update_time' => $now,
            ]);
        }
    }

    /**
     * 批量删除 system_config 键。
     */
    private function deleteSystemConfigKeys(array $keys): void
    {
        if (!$this->tableExists('system_config')) {
            return;
        }
        $cleanKeys = array_values(array_filter(array_map(static function ($key): string {
            return trim((string)$key);
        }, $keys), static function (string $key): bool {
            return $key !== '';
        }));
        if (empty($cleanKeys)) {
            return;
        }
        Db::name('system_config')->whereIn('key', $cleanKeys)->delete();
    }

    /**
     * 发布秒杀实时事件（失败不影响主链路）。
     */
    private function publishRealtimeEvent(string $event, array $payload = []): void
    {
        try {
            (new FlashSaleRealtimeService())->publish($event, $payload);
        } catch (\Throwable $e) {
            // SSE 通知失败不影响主业务链路
        }
    }

    /**
     * 规范化活动入参并做字段级校验。
     */
    private function normalizeActivity(array $payload, bool $isUpdate): array
    {
        $name = trim((string)($payload['name'] ?? ''));
        $cover = trim((string)($payload['cover'] ?? ''));
        $status = (int)($payload['status'] ?? 0);
        $startTime = trim((string)($payload['start_time'] ?? ''));
        $endTime = trim((string)($payload['end_time'] ?? ''));
        $preheatTime = trim((string)($payload['preheat_time'] ?? ''));
        $sort = (int)($payload['sort'] ?? 0);

        if (!$isUpdate || array_key_exists('name', $payload)) {
            if ($name === '') {
                throw new ValidateException('活动名称不能为空');
            }
        }
        if (!$isUpdate || array_key_exists('status', $payload)) {
            if (!in_array($status, [0, 1, 2, 3, 4], true)) {
                throw new ValidateException('活动状态不合法');
            }
        }
        if (!$isUpdate || array_key_exists('start_time', $payload)) {
            if ($startTime === '' || strtotime($startTime) === false) {
                throw new ValidateException('开始时间格式不正确');
            }
        }
        if (!$isUpdate || array_key_exists('end_time', $payload)) {
            if ($endTime === '' || strtotime($endTime) === false) {
                throw new ValidateException('结束时间格式不正确');
            }
        }
        if ($startTime !== '' && $endTime !== '' && strtotime($startTime) >= strtotime($endTime)) {
            throw new ValidateException('结束时间必须晚于开始时间');
        }
        if ($preheatTime !== '' && strtotime($preheatTime) === false) {
            throw new ValidateException('预热时间格式不正确');
        }

        $data = [];
        foreach (['name', 'cover', 'status', 'start_time', 'end_time', 'sort'] as $field) {
            if (!$isUpdate || array_key_exists($field, $payload)) {
                $data[$field] = ${str_replace(' ', '', lcfirst(str_replace('_', ' ', ucwords($field, '_'))))};
            }
        }
        if (!$isUpdate || array_key_exists('preheat_time', $payload)) {
            $data['preheat_time'] = $preheatTime === '' ? null : $preheatTime;
        }
        $data['update_time'] = date('Y-m-d H:i:s');
        return $data;
    }

    /**
     * 规范化黑名单入参并做字段级校验。
     */
    private function normalizeBlacklist(array $payload, bool $isUpdate): array
    {
        $scene = trim((string)($payload['scene'] ?? 'create_order'));
        $targetType = trim((string)($payload['target_type'] ?? ''));
        $targetValue = trim((string)($payload['target_value'] ?? ''));
        $status = (int)($payload['status'] ?? 1);
        $expireTime = trim((string)($payload['expire_time'] ?? ''));
        $note = trim((string)($payload['note'] ?? ''));

        if (!$isUpdate || array_key_exists('scene', $payload)) {
            if (!in_array($scene, self::RISK_SCENES, true)) {
                throw new ValidateException('场景不合法');
            }
        }
        if (!$isUpdate || array_key_exists('target_type', $payload)) {
            if (!in_array($targetType, self::RISK_TARGET_TYPES, true)) {
                throw new ValidateException('对象类型不合法');
            }
        }
        if (!$isUpdate || array_key_exists('target_value', $payload)) {
            if ($targetValue === '') {
                throw new ValidateException('对象值不能为空');
            }
        }
        if ($targetType === 'user' && $targetValue !== '' && !preg_match('/^\d+$/', $targetValue)) {
            throw new ValidateException('用户ID格式不正确');
        }
        if (!$isUpdate || array_key_exists('status', $payload)) {
            if (!in_array($status, [0, 1], true)) {
                throw new ValidateException('状态不合法');
            }
        }
        if ($expireTime !== '' && strtotime($expireTime) === false) {
            throw new ValidateException('过期时间格式不正确');
        }

        $data = [];
        $map = [
            'scene' => $scene,
            'target_type' => $targetType,
            'target_value' => $targetValue,
            'status' => $status,
            'note' => $note,
        ];
        foreach ($map as $field => $value) {
            if (!$isUpdate || array_key_exists($field, $payload)) {
                $data[$field] = $value;
            }
        }
        if (!$isUpdate || array_key_exists('expire_time', $payload)) {
            $data['expire_time'] = $expireTime === '' ? null : $expireTime;
        }
        $data['update_time'] = date('Y-m-d H:i:s');
        return $data;
    }

    /**
     * 规范化活动商品入参并做字段级校验。
     */
    private function normalizeItem(array $payload, bool $isUpdate): array
    {
        $activityId = (int)($payload['activity_id'] ?? 0);
        $goodsType = (int)($payload['goods_type'] ?? 0);
        $goodsId = (int)($payload['goods_id'] ?? 0);
        $titleSnapshot = trim((string)($payload['title_snapshot'] ?? ''));
        $coverSnapshot = trim((string)($payload['cover_snapshot'] ?? ''));
        $originPrice = round((float)($payload['origin_price'] ?? 0), 2);
        $seckillPrice = round((float)($payload['seckill_price'] ?? 0), 2);
        $totalStock = (int)($payload['total_stock'] ?? 0);
        $limitPerUser = (int)($payload['limit_per_user'] ?? 1);
        $status = (int)($payload['status'] ?? 1);

        if (!$isUpdate || array_key_exists('activity_id', $payload)) {
            if ($activityId <= 0) {
                throw new ValidateException('活动ID不合法');
            }
            $this->assertExists('flash_sale_activity', $activityId, '活动不存在');
        }
        if (!$isUpdate || array_key_exists('goods_type', $payload)) {
            if (!in_array($goodsType, [10, 20], true)) {
                throw new ValidateException('仅支持整剧(10)/整本(20)');
            }
        }
        if (!$isUpdate || array_key_exists('goods_id', $payload)) {
            if ($goodsId <= 0) {
                throw new ValidateException('商品ID不合法');
            }
        }
        if (!$isUpdate || array_key_exists('seckill_price', $payload)) {
            if ($seckillPrice <= 0) {
                throw new ValidateException('秒杀价必须大于0');
            }
        }
        if (!$isUpdate || array_key_exists('origin_price', $payload)) {
            if ($originPrice <= 0) {
                throw new ValidateException('原价必须大于0');
            }
        }
        if ($originPrice > 0 && $seckillPrice > 0 && $seckillPrice >= $originPrice) {
            throw new ValidateException('秒杀价必须小于原价');
        }
        if (!$isUpdate || array_key_exists('total_stock', $payload)) {
            if ($totalStock <= 0) {
                throw new ValidateException('库存必须大于0');
            }
        }
        if (!$isUpdate || array_key_exists('limit_per_user', $payload)) {
            if ($limitPerUser <= 0) {
                throw new ValidateException('限购必须大于0');
            }
        }
        if (!$isUpdate || array_key_exists('status', $payload)) {
            if (!in_array($status, [0, 1], true)) {
                throw new ValidateException('商品状态不合法');
            }
        }

        if ($titleSnapshot === '' && $goodsType > 0 && $goodsId > 0) {
            if ($goodsType === 10) {
                $titleSnapshot = (string)Db::name('drama')->where('id', $goodsId)->value('title');
                $coverSnapshot = $coverSnapshot !== '' ? $coverSnapshot : (string)Db::name('drama')->where('id', $goodsId)->value('cover');
            } elseif ($goodsType === 20) {
                $titleSnapshot = (string)Db::name('novel')->where('id', $goodsId)->value('title');
                $coverSnapshot = $coverSnapshot !== '' ? $coverSnapshot : (string)Db::name('novel')->where('id', $goodsId)->value('cover');
            }
        }

        $data = [];
        $map = [
            'activity_id' => $activityId,
            'goods_type' => $goodsType,
            'goods_id' => $goodsId,
            'title_snapshot' => $titleSnapshot,
            'cover_snapshot' => $coverSnapshot,
            'origin_price' => $originPrice,
            'seckill_price' => $seckillPrice,
            'total_stock' => $totalStock,
            'limit_per_user' => $limitPerUser,
            'status' => $status,
        ];
        foreach ($map as $field => $value) {
            if (!$isUpdate || array_key_exists($field, $payload)) {
                $data[$field] = $value;
            }
        }

        if (!$isUpdate) {
            $data['sold_stock'] = 0;
            $data['locked_stock'] = 0;
        }
        $data['update_time'] = date('Y-m-d H:i:s');
        return $data;
    }
}

