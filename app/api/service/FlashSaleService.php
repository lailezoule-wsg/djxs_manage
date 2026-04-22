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

/**
 * 用户端秒杀业务服务
 */
class FlashSaleService
{
    private const TOKEN_CACHE_PREFIX = 'flash:sale:token:';
    private const RELEASE_ZSET_KEY = 'flash:sale:reserve:release';
    private const STOCK_KEY_PREFIX = 'flash:sale:stock:';
    private const PENDING_KEY_PREFIX = 'flash:sale:pending:';
    private const REQUEST_STATE_PREFIX = 'flash:sale:request:state:';
    private const REQUEST_LOCK_PREFIX = 'flash:sale:request:lock:';
    private const REQUEST_BORN_PREFIX = 'flash:sale:request:born:';
    private const USER_ITEM_LOCK_PREFIX = 'flash:sale:user:item:lock:';
    private const USER_ITEM_PENDING_PREFIX = 'flash:sale:user:item:pending:';
    private const REQUEST_STATE_TTL = 900;
    private const ORDER_STATUS_QUEUEING = 8;
    private const REQUEST_LOCK_TTL_SECONDS = 120;
    private const USER_ITEM_LOCK_TTL_SECONDS = 60;
    private const MAX_CLIENT_IP_LENGTH = 64;
    private const MAX_DEVICE_ID_LENGTH = 128;
    private const MAX_RISK_EXTRA_JSON_LENGTH = 2000;
    private static ?bool $hasReserveExpireField = null;

    /**
     * 用户端活动列表
     */
    /**
     * 获取秒杀活动列表
     */
    public function list(array $params = []): array
    {
        $this->releaseDueReserveOrders((int)env('FLASH_SALE_RELEASE_BATCH', 100));
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

        $list = array_map(function (array $row) use ($now): array {
            $available = max(0, (int)$row['total_stock'] - (int)$row['sold_stock'] - (int)$row['locked_stock']);
            $row['available_stock'] = $available;
            $row['title'] = (string)$row['title_snapshot'];
            $row['cover'] = (string)$row['cover_snapshot'];
            $row['button_status'] = $this->resolveButtonStatus($row, $available, $now);
            return $row;
        }, $result['data'] ?? []);

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
        $this->releaseDueReserveOrders((int)env('FLASH_SALE_RELEASE_BATCH', 100));
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
        $this->releaseDueReserveOrders((int)env('FLASH_SALE_RELEASE_BATCH', 100));
        if ($activityId <= 0 || $itemId <= 0) {
            throw new ValidateException('参数不完整');
        }
        $clientIp = $this->normalizeClientIp($clientIp);
        $deviceId = $this->normalizeDeviceId($deviceId);
        $this->assertTokenRateLimit($userId, $activityId, $itemId, $clientIp, $deviceId);
        $this->assertActivityItemValid($activityId, $itemId);
        $token = bin2hex(random_bytes(16));
        $cacheKey = $this->buildTokenCacheKey($userId, $activityId, $itemId, $token);
        Cache::set($cacheKey, 1, 90);

        return [
            'token' => $token,
            'expire_seconds' => 90,
            'server_time' => time(),
        ];
    }

    /**
     * 秒杀下单前置校验
     */
    public function precheck(int $userId, array $payload): array
    {
        $this->releaseDueReserveOrders((int)env('FLASH_SALE_RELEASE_BATCH', 100));
        $activityId = (int)($payload['activity_id'] ?? 0);
        $itemId = (int)($payload['item_id'] ?? 0);
        $buyCount = max(1, (int)($payload['buy_count'] ?? 1));
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
        $this->releaseDueReserveOrders((int)env('FLASH_SALE_RELEASE_BATCH', 100));
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

        $cachedState = $this->getRequestState($requestId);
        if (!empty($cachedState)) {
            $cachedUserId = (int)($cachedState['user_id'] ?? 0);
            if ($cachedUserId > 0 && $cachedUserId !== $userId) {
                throw new ValidateException('request_id 不合法');
            }
            return $this->buildResponseByRequestState($cachedState, $requestId);
        }
        $exists = FlashSaleOrder::where('request_id', $requestId)->find();
        if ($exists) {
            if ((int)$exists->user_id !== $userId) {
                throw new ValidateException('request_id 不合法');
            }
            $order = Order::where('id', (int)$exists->order_id)->find();
            $reserveExpireTime = (string)($exists->reserve_expire_time ?? '');
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
            throw new ValidateException('token 不能为空');
        }
        if (!$this->isValidToken($token)) {
            throw new ValidateException('token 不合法');
        }
        $this->assertCreateBlacklist($userId, $activityId, $itemId, $clientIp, $deviceId);
        $this->assertCreateRateLimit($userId, $activityId, $itemId, $clientIp, $deviceId);
        $cacheKey = $this->buildTokenCacheKey($userId, $activityId, $itemId, $token);
        if (!$this->consumeToken($cacheKey)) {
            throw new ValidateException('抢购令牌无效或已过期');
        }
        if ($this->hasUserItemPendingMarker($itemId, $userId)) {
            throw new ValidateException('你已有待支付订单，请先完成支付');
        }
        $this->assertRequestIdWindow($requestId);

        $requestLockToken = $this->acquireRequestLock($requestId);
        if ($requestLockToken === '') {
            return $this->buildQueueingResponse($requestId);
        }
        $userItemLockToken = $this->acquireUserItemLock($activityId, $itemId, $userId);
        if ($userItemLockToken === '') {
            $this->releaseRequestLock($requestId, $requestLockToken);
            throw new ValidateException('请求处理中，请勿重复提交');
        }
        $redisReserved = false;
        $lockedReserved = false;
        $queuePayload = [];
        $heartbeatLocks = function () use ($requestId, $requestLockToken, $activityId, $itemId, $userId, $userItemLockToken): void {
            $requestOk = $this->refreshRequestLock($requestId, $requestLockToken);
            $userItemOk = $this->refreshUserItemLock($activityId, $itemId, $userId, $userItemLockToken);
            if (!$requestOk || !$userItemOk) {
                throw new ValidateException('请求处理中，请稍后重试');
            }
        };
        try {
            $queuePayload = $this->executeWithDeadlockRetry(
                function () use ($userId, $activityId, $itemId, $buyCount, $requestId, $payTypeValue, $payType, &$redisReserved, &$lockedReserved, $heartbeatLocks) {
                    $heartbeatLocks();
                    $redisReserved = false;
                    $lockedReserved = false;
                    return Db::transaction(function () use ($userId, $activityId, $itemId, $buyCount, $requestId, $payTypeValue, $payType, &$redisReserved, &$lockedReserved) {
                        // 热点优化：预检改为无锁读取 + 条件更新，减少高并发行锁等待。
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
                        if ($this->hasPurchasedConflict($userId, (int)$item['goods_type'], (int)$item['goods_id'])) {
                            throw new ValidateException('你已购买该内容，无需重复抢购');
                        }

                        $available = max(0, (int)$item['total_stock'] - (int)$item['sold_stock'] - (int)$item['locked_stock']);
                        if ($available < $buyCount) {
                            throw new ValidateException('库存不足');
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

                        $redisReserved = $this->reserveStockWithRedis((int)$item['id'], $userId, $buyCount, $available);
                        $affected = Db::name('flash_sale_item')
                            ->where('id', $itemId)
                            ->where('activity_id', $activityId)
                            ->where('status', 1)
                            ->whereRaw('(total_stock - sold_stock - locked_stock) >= ' . $buyCount)
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
                            $goodsName = $this->resolveGoodsName((int)$item['goods_type'], (int)$item['goods_id']);
                        }
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
                    if ($lockedReserved) {
                        Db::name('flash_sale_item')->where('id', $itemId)->dec('locked_stock', $buyCount)->update();
                        $lockedReserved = false;
                    }
                    if ($redisReserved) {
                        $this->rollbackReservedStock($itemId, $userId, $buyCount);
                        $redisReserved = false;
                    }
                }
            );
            $heartbeatLocks();
            $published = FlashSaleOrderQueueService::publish($queuePayload);
            if (!$published) {
                throw new ValidateException('系统繁忙，请稍后重试');
            }
            $this->setRequestState($requestId, [
                'status' => self::ORDER_STATUS_QUEUEING,
                'user_id' => $userId,
                'message' => '抢购请求已受理，正在排队创建订单',
                'reserve_expire_time' => (string)($queuePayload['reserve_expire_time'] ?? ''),
                'order_id' => 0,
            ]);
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
                'message' => '抢购请求已受理，正在排队创建订单',
                'expire_seconds' => $this->getReserveSeconds(),
                'next_action' => 'query_result',
                'status' => self::ORDER_STATUS_QUEUEING,
                'reserve_expire_time' => (string)($queuePayload['reserve_expire_time'] ?? ''),
            ];
        } catch (\Throwable $e) {
            if ($lockedReserved) {
                Db::name('flash_sale_item')->where('id', $itemId)->dec('locked_stock', $buyCount)->update();
            }
            if ($redisReserved) {
                $this->rollbackReservedStock($itemId, $userId, $buyCount);
            }
            $this->clearUserItemPendingMarker($itemId, $userId);
            $this->setRequestState($requestId, [
                'status' => 3,
                'user_id' => $userId,
                'message' => $e instanceof ValidateException ? $e->getMessage() : '秒杀下单失败',
                'reserve_expire_time' => '',
                'order_id' => 0,
            ]);
            throw $e;
        } finally {
            $this->releaseUserItemLock($activityId, $itemId, $userId, $userItemLockToken);
            $this->releaseRequestLock($requestId, $requestLockToken);
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
        $this->releaseDueReserveOrders((int)env('FLASH_SALE_RELEASE_BATCH', 100));
        if (!$this->isValidRequestId($requestId)) {
            throw new ValidateException('request_id 不合法');
        }
        $row = FlashSaleOrder::where('user_id', $userId)->where('request_id', $requestId)->find();
        if (!$row) {
            $cachedState = $this->getRequestState($requestId);
            if (empty($cachedState)) {
                throw new ValidateException('记录不存在');
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

    private function buildOrderPendingLockKey(int $userId, int $goodsType, int $goodsId): string
    {
        return 'u:' . $userId . '|g:' . $goodsType . ':' . $goodsId;
    }

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

    private function generateUniqueOrderSn(): string
    {
        return 'ORD' . date('YmdHis') . strtoupper(bin2hex(random_bytes(4)));
    }

    private function isPendingOrderDuplicateException(\Throwable $e): bool
    {
        return $this->isDuplicateForIndexes($e, ['uk_pending_lock_key', 'pending_lock_key']);
    }

    private function isOrderSnDuplicateException(\Throwable $e): bool
    {
        return $this->isDuplicateForIndexes($e, ['uk_order_sn', 'order_sn']);
    }

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

    private function createLockToken(): string
    {
        return bin2hex(random_bytes(16));
    }

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

    private function assertActivityItemValid(int $activityId, int $itemId): void
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
    }

    private function buildTokenCacheKey(int $userId, int $activityId, int $itemId, string $token): string
    {
        return self::TOKEN_CACHE_PREFIX . $userId . ':' . $activityId . ':' . $itemId . ':' . $token;
    }

    private function consumeToken(string $cacheKey): bool
    {
        if ($cacheKey === '') {
            return false;
        }
        $redis = $this->getRedisHandler();
        if ($redis && method_exists($redis, 'eval')) {
            try {
                $ret = (int)$redis->eval("return redis.call('DEL', KEYS[1])", [$cacheKey], 1);
                return $ret === 1;
            } catch (\Throwable $e) {
            }
        }
        try {
            if (!Cache::has($cacheKey)) {
                return false;
            }
            Cache::delete($cacheKey);
            return true;
        } catch (\Throwable $e) {
        }
        return false;
    }

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

    private function getReserveSeconds(): int
    {
        $minutes = (int)env('FLASH_SALE_RESERVE_MINUTES', 5);
        $minutes = max(2, min(5, $minutes));
        return $minutes * 60;
    }

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
        }
    }

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

    private function recordRiskEvent(string $reason, array $context = []): void
    {
        if (!$this->shouldRecordRiskEvent($reason, $context)) {
            return;
        }
        try {
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
            Db::name('flash_sale_risk_log')->insert([
                'scene' => 'create_order',
                'reason' => substr(trim($reason) === '' ? 'risk_blocked' : $reason, 0, 64),
                'user_id' => (int)($context['user_id'] ?? 0),
                'activity_id' => (int)($context['activity_id'] ?? 0),
                'item_id' => (int)($context['item_id'] ?? 0),
                'client_ip' => $this->normalizeClientIp((string)($context['client_ip'] ?? '')),
                'device_id' => $this->normalizeDeviceId((string)($context['device_id'] ?? '')),
                'extra_json' => $extraJson,
                'create_time' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
        }
    }

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

    private function isValidToken(string $token): bool
    {
        return preg_match('/^[a-fA-F0-9]{32}$/', $token) === 1;
    }

    private function normalizeClientIp(string $clientIp): string
    {
        return substr(trim($clientIp), 0, self::MAX_CLIENT_IP_LENGTH);
    }

    private function normalizeDeviceId(string $deviceId): string
    {
        return substr(trim($deviceId), 0, self::MAX_DEVICE_ID_LENGTH);
    }

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

    private function getStockCacheKey(int $itemId): string
    {
        return self::STOCK_KEY_PREFIX . $itemId;
    }

    private function getPendingCacheKey(int $itemId, int $userId): string
    {
        return self::PENDING_KEY_PREFIX . $itemId . ':' . $userId;
    }

    private function buildUserItemLockKey(int $activityId, int $itemId, int $userId): string
    {
        return self::USER_ITEM_LOCK_PREFIX . $activityId . ':' . $itemId . ':' . $userId;
    }

    private function buildUserItemPendingKey(int $itemId, int $userId): string
    {
        return self::USER_ITEM_PENDING_PREFIX . $itemId . ':' . $userId;
    }

    private function acquireUserItemLock(int $activityId, int $itemId, int $userId): string
    {
        return $this->acquireOwnedLock(
            $this->buildUserItemLockKey($activityId, $itemId, $userId),
            $this->getUserItemLockTtlSeconds()
        );
    }

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
        try {
            if (Cache::has($lockKey)) {
                return '';
            }
            Cache::set($lockKey, $lockToken, $ttlSeconds);
            return $lockToken;
        } catch (\Throwable $e) {
        }
        return '';
    }

    private function getRequestLockTtlSeconds(): int
    {
        $ttl = (int)env('FLASH_SALE_REQUEST_LOCK_TTL_SECONDS', self::REQUEST_LOCK_TTL_SECONDS);
        return max(30, min(300, $ttl));
    }

    private function getUserItemLockTtlSeconds(): int
    {
        $ttl = (int)env('FLASH_SALE_USER_ITEM_LOCK_TTL_SECONDS', self::USER_ITEM_LOCK_TTL_SECONDS);
        return max(20, min(180, $ttl));
    }

    private function setUserItemPendingMarker(int $itemId, int $userId, int $ttlSeconds): void
    {
        $pendingKey = $this->buildUserItemPendingKey($itemId, $userId);
        try {
            Cache::set($pendingKey, 1, max(30, $ttlSeconds));
        } catch (\Throwable $e) {
        }
    }

    private function clearUserItemPendingMarker(int $itemId, int $userId): void
    {
        $pendingKey = $this->buildUserItemPendingKey($itemId, $userId);
        try {
            Cache::delete($pendingKey);
        } catch (\Throwable $e) {
        }
    }

    private function hasUserItemPendingMarker(int $itemId, int $userId): bool
    {
        $pendingKey = $this->buildUserItemPendingKey($itemId, $userId);
        try {
            return (bool)Cache::has($pendingKey);
        } catch (\Throwable $e) {
        }
        return false;
    }

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

    private function parseTimeToTs(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        $ts = strtotime($value);
        return $ts === false ? 0 : (int)$ts;
    }

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
        throw new ValidateException('系统繁忙，请稍后重试');
    }

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

