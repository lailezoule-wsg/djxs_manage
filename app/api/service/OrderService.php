<?php
declare (strict_types = 1);

namespace app\api\service;

use app\api\service\AlipayService;
use app\api\model\Order;
use app\api\model\OrderGoods;
use app\api\model\DramaEpisode;
use app\api\model\Drama;
use app\api\model\Novel;
use app\api\model\NovelChapter;
use app\api\model\MemberLevel;
use app\api\model\Member;
use app\api\model\User;
use app\api\validate\Order as OrderValidate;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Db;

/**
 * 订单服务层
 */
class OrderService
{
    private const TIMEOUT_JOB_STATUS_CACHE_KEY = 'order:timeout:last_result';
    private const PAY_NOTIFY_REPLAY_CACHE_PREFIX = 'pay:notify:replay:';
    private static ?bool $hasFlashReserveExpireField = null;
    private static ?bool $hasOrderUpdateTimeField = null;

    protected AlipayService $alipayService;
    protected FlashSaleService $flashSaleService;

    /**
     * 初始化支付与秒杀依赖服务
     */
    public function __construct()
    {
        $this->alipayService = new AlipayService();
        $this->flashSaleService = new FlashSaleService();
    }

    /**
     * 创建订单
     */
    public function create($userId, $data)
    {
        $validate = new OrderValidate();
        if (!$validate->scene('create')->check($data)) {
            throw new ValidateException($validate->getError());
        }

        $goodsType = (int)$data['goods_type'];
        $goodsId = (int)$data['goods_id'];

        $goodsName = '';
        $price = 0;

        $realGoodsType = $goodsType;
        $realGoodsId = $goodsId;

        if ($goodsType == 10) {
            $drama = Drama::where('id', $goodsId)->where('status', 1)->find();
            if (!$drama) {
                throw new ValidateException('短剧不存在或已下架');
            }
            $availableEpisode = DramaEpisode::where('drama_id', $goodsId)
                ->where('status', 1)
                ->order('episode_number', 'asc')
                ->order('id', 'asc')
                ->find();
            if (!$availableEpisode) {
                throw new ValidateException('该短剧没有可购买的剧集');
            }
            $legacyFirstEpisodeId = (int)DramaEpisode::where('drama_id', $goodsId)
                ->order('episode_number', 'asc')
                ->order('id', 'asc')
                ->value('id');
            // 订单行记录整剧：goods_type=10 + drama_id，避免与单集 goods_type=1 + episode_id 混淆
            $realGoodsType = 10;
            $realGoodsId = $goodsId;
            $goodsName = $drama->title;
            $price = ContentPurchasePricing::wholeDramaPayableAmount((float)$drama->price, $userId, $goodsId);
            if ($price <= 0) {
                throw new ValidateException('您已购买的单集已覆盖当前整剧价，无需再购买整剧');
            }

            $hasPaid = Order::alias('o')
                ->join('djxs_order_goods g', 'o.id = g.order_id')
                ->where('o.user_id', $userId)
                ->where('o.status', 1)
                ->where(function($query) use ($goodsType, $goodsId, $realGoodsType, $realGoodsId, $legacyFirstEpisodeId) {
                    $query->where(function($q) use ($goodsType, $goodsId) {
                        $q->where('g.goods_type', $goodsType)->where('g.goods_id', $goodsId);
                    })->whereOr(function($q) use ($realGoodsType, $realGoodsId) {
                        $q->where('g.goods_type', $realGoodsType)->where('g.goods_id', $realGoodsId);
                    });
                    if ($legacyFirstEpisodeId > 0) {
                        $query->whereOr(function($q) use ($legacyFirstEpisodeId) {
                            // 历史订单：整剧曾记为第一集 id + goods_type=1
                            $q->where('g.goods_type', 1)->where('g.goods_id', $legacyFirstEpisodeId);
                        });
                    }
                })
                ->count();

            if ($hasPaid > 0) {
                throw new ValidateException('您已购买过该短剧，无需重复购买');
            }

            $pendingOrder = Order::alias('o')
                ->join('djxs_order_goods g', 'o.id = g.order_id')
                ->where('o.user_id', $userId)
                ->where('o.status', 0)
                ->where(function($query) use ($goodsType, $goodsId, $realGoodsType, $realGoodsId, $legacyFirstEpisodeId) {
                    $query->where(function($q) use ($goodsType, $goodsId) {
                        $q->where('g.goods_type', $goodsType)->where('g.goods_id', $goodsId);
                    })->whereOr(function($q) use ($realGoodsType, $realGoodsId) {
                        $q->where('g.goods_type', $realGoodsType)->where('g.goods_id', $realGoodsId);
                    });
                    if ($legacyFirstEpisodeId > 0) {
                        $query->whereOr(function($q) use ($legacyFirstEpisodeId) {
                            $q->where('g.goods_type', 1)->where('g.goods_id', $legacyFirstEpisodeId);
                        });
                    }
                })
                ->count();

            if ($pendingOrder > 0) {
                throw new ValidateException('您已存在该短剧的待支付订单，请先完成支付');
            }
        } elseif ($goodsType == 20) {
            $novel = Novel::where('id', $goodsId)->where('status', 1)->find();
            if (!$novel) {
                throw new ValidateException('小说不存在或已下架');
            }
            $availableChapter = NovelChapter::where('novel_id', $goodsId)
                ->where('status', 1)
                ->order('chapter_number', 'asc')
                ->order('id', 'asc')
                ->find();
            if (!$availableChapter) {
                throw new ValidateException('该小说没有可购买的章节');
            }
            $realGoodsType = 20;
            $realGoodsId = $goodsId;
            $goodsName = $novel->title;
            $price = ContentPurchasePricing::wholeNovelPayableAmount((float)$novel->price, $userId, $goodsId);
            if ($price <= 0) {
                throw new ValidateException('您已购买的章节已覆盖当前整本价，无需再购买整本');
            }

            $hasPaid = Order::alias('o')
                ->join('djxs_order_goods g', 'o.id = g.order_id')
                ->where('o.user_id', $userId)
                ->where('o.status', 1)
                ->where(function($query) use ($goodsType, $goodsId, $realGoodsType, $realGoodsId) {
                    $query->where(function($q) use ($goodsType, $goodsId) {
                        $q->where('g.goods_type', $goodsType)->where('g.goods_id', $goodsId);
                    })->whereOr(function($q) use ($realGoodsType, $realGoodsId) {
                        $q->where('g.goods_type', $realGoodsType)->where('g.goods_id', $realGoodsId);
                    });
                })
                ->count();

            if ($hasPaid > 0) {
                throw new ValidateException('您已购买过该小说，无需重复购买');
            }

            $pendingOrder = Order::alias('o')
                ->join('djxs_order_goods g', 'o.id = g.order_id')
                ->where('o.user_id', $userId)
                ->where('o.status', 0)
                ->where(function($query) use ($goodsType, $goodsId, $realGoodsType, $realGoodsId) {
                    $query->where(function($q) use ($goodsType, $goodsId) {
                        $q->where('g.goods_type', $goodsType)->where('g.goods_id', $goodsId);
                    })->whereOr(function($q) use ($realGoodsType, $realGoodsId) {
                        $q->where('g.goods_type', $realGoodsType)->where('g.goods_id', $realGoodsId);
                    });
                })
                ->count();

            if ($pendingOrder > 0) {
                throw new ValidateException('您已存在该小说的待支付订单，请先完成支付');
            }
        } else {
            switch ($goodsType) {
                case 1:
                    $episode = DramaEpisode::where('id', $goodsId)->where('status', 1)->find();
                    if (!$episode) {
                        throw new ValidateException('短剧剧集不存在或已下架');
                    }
                    $drama = Drama::where('id', (int)$episode->drama_id)->where('status', 1)->find();
                    if (!$drama) {
                        throw new ValidateException('短剧不存在或已下架');
                    }
                    $goodsName = $episode->title;
                    $price = $episode->price;
                    break;
                case 2:
                    $chapter = NovelChapter::where('id', $goodsId)->where('status', 1)->find();
                    if (!$chapter) {
                        throw new ValidateException('小说章节不存在或已下架');
                    }
                    $novel = Novel::where('id', (int)$chapter->novel_id)->where('status', 1)->find();
                    if (!$novel) {
                        throw new ValidateException('小说不存在或已下架');
                    }
                    $goodsName = $chapter->title;
                    $price = $chapter->price;
                    break;
                case 3:
                    $level = MemberLevel::where('id', $goodsId)->where('status', 1)->find();
                    if (!$level) {
                        throw new ValidateException('会员等级不存在或已停用');
                    }
                    $goodsName = $level->name . '会员';
                    $price = $level->price;
                    break;
                default:
                    throw new ValidateException('无效的商品类型');
            }

            $pendingOrder = Order::alias('o')
                ->join('djxs_order_goods g', 'o.id = g.order_id')
                ->where('o.user_id', $userId)
                ->where('o.status', 0)
                ->where('g.goods_type', $goodsType)
                ->where('g.goods_id', $goodsId)
                ->count();

            if ($pendingOrder > 0) {
                throw new ValidateException('您已存在该商品的待支付订单，请先完成支付');
            }

            $hasPaid = Order::alias('o')
                ->join('djxs_order_goods g', 'o.id = g.order_id')
                ->where('o.user_id', $userId)
                ->where('o.status', 1)
                ->where('g.goods_type', $goodsType)
                ->where('g.goods_id', $goodsId)
                ->count();

            if ($hasPaid > 0) {
                throw new ValidateException('您已购买过该商品，无需重复购买');
            }
        }

        if ($price <= 0) {
            throw new ValidateException('商品价格不能为0');
        }
        $matchers = ContentPurchaseMatcher::orderGoodsMatchers((int)$realGoodsType, (int)$realGoodsId);
        return Db::transaction(function () use (
            $userId,
            $price,
            $goodsName,
            $realGoodsType,
            $realGoodsId,
            $matchers
        ) {
            // 串行化同一用户下单，避免并发请求绕过去重校验创建重复待支付单
            $lockedUser = User::where('id', (int)$userId)->lock(true)->find();
            if (!$lockedUser) {
                throw new ValidateException('用户不存在');
            }

            if ($this->hasOrderByStatusWithMatchers((int)$userId, 0, $matchers)) {
                throw new ValidateException('您已存在该商品的待支付订单，请先完成支付');
            }
            if ($this->hasOrderByStatusWithMatchers((int)$userId, 1, $matchers)) {
                throw new ValidateException('您已购买过该商品，无需重复购买');
            }

            $order = Order::create([
                'order_sn'     => $this->generateUniqueOrderSn(),
                'user_id'      => (int)$userId,
                'total_amount' => $price,
                'pay_amount'   => $price,
                'status'       => 0,
                'pending_lock_key' => $this->buildPendingLockKey((int)$userId, (int)$realGoodsType, (int)$realGoodsId),
            ]);

            OrderGoods::create([
                'order_id'   => $order->id,
                'goods_type' => $realGoodsType,
                'goods_id'   => $realGoodsId,
                'goods_name' => $goodsName,
                'price'      => $price,
                'quantity'   => 1,
            ]);

            return $order;
        });
    }

    /**
     * 检查给定商品匹配条件下是否存在指定状态订单
     *
     * @param array<int, array{goods_type:int, goods_id:int}> $matchers
     */
    private function hasOrderByStatusWithMatchers(int $userId, int $status, array $matchers): bool
    {
        if ($userId <= 0 || empty($matchers)) {
            return false;
        }

        $count = Order::alias('o')
            ->join('djxs_order_goods g', 'o.id = g.order_id')
            ->where('o.user_id', $userId)
            ->where('o.status', $status)
            ->where(function ($query) use ($matchers) {
                ContentPurchaseMatcher::applyOrderGoodsMatchersWhere($query, 'g', $matchers);
            })
            ->lock(true)
            ->count();

        return $count > 0;
    }

    /**
     * 生成高熵且不重复的订单号
     */
    private function generateUniqueOrderSn(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $suffix = strtoupper(bin2hex(random_bytes(4)));
            $orderSn = 'ORD' . date('YmdHis') . $suffix;
            $exists = Order::where('order_sn', $orderSn)->lock(true)->count() > 0;
            if (!$exists) {
                return $orderSn;
            }
        }

        throw new ValidateException('订单创建失败，请稍后重试');
    }

    /**
     * 构造“同用户同商品待支付防重键”
     */
    private function buildPendingLockKey(int $userId, int $goodsType, int $goodsId): string
    {
        return 'u:' . $userId . '|g:' . $goodsType . ':' . $goodsId;
    }

    /**
     * 获取订单列表
     */
    /**
     * 分页查询用户订单列表
     */
    public function list($userId, $params = [])
    {
        $query = Order::where('user_id', $userId);

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', $params['status']);
        }

        $page = isset($params['page']) ? max(1, (int)$params['page']) : 1;
        $limit = isset($params['limit']) ? max(1, min((int)$params['limit'], 50)) : 20;

        $result = $query->order('id', 'desc')
            ->paginate([
                'page' => $page,
                'list_rows' => $limit,
            ]);

        $items = [];
        foreach ($result->items() as $item) {
            $row = method_exists($item, 'toArray') ? $item->toArray() : (array)$item;
            $items[] = $this->buildOrderResponseRow($row);
        }

        return [
            'list'      => $items,
            'total'     => $result->total(),
            'page'      => $result->currentPage(),
            'limit'     => $result->listRows(),
            'has_more'  => $result->currentPage() < $result->lastPage()
        ];
    }

    /**
     * 获取订单详情
     */
    /**
     * 获取用户订单详情
     */
    public function detail($userId, $id)
    {
        $order = Order::where('user_id', $userId)->where('id', $id)->find();
        if (!$order) {
            throw new ValidateException('订单不存在');
        }
        return $this->buildOrderResponseRow($order->toArray());
    }

    /**
     * 支付成功回调
     */
    /**
     * 处理支付成功通知（兼容旧入口）
     */
    public function notify($orderSn, $payType)
    {
        $order = Order::where('order_sn', $orderSn)->find();
        if (!$order) {
            return ['success' => false, 'msg' => '订单不存在'];
        }

        if ($order->status != 0) {
            return ['success' => true, 'msg' => '订单已处理'];
        }

        $this->updateOrderById((int)$order->id, [
            'status' => 1,
            'pay_type' => $payType,
            'pay_time' => date('Y-m-d H:i:s'),
        ]);

        return ['success' => true, 'msg' => '处理成功'];
    }

    /**
     * 检查并取消超时订单
     */
    /**
     * 执行超时未支付订单取消
     */
    public function cancelTimeoutOrders(int $timeout = 30, int $graceSeconds = 120): array
    {
        $releasedFlashSaleCount = $this->flashSaleService->releaseDueReserveOrders((int)env('FLASH_SALE_RELEASE_BATCH', 200));
        // 临界点保护：增加缓冲秒，避免用户支付中的订单被提前取消
        $timeoutTime = date('Y-m-d H:i:s', time() - $timeout * 60 - $graceSeconds);
        $pendingFlashSaleOrderIds = Db::name('flash_sale_order')->where('status', 0)->column('order_id');

        $timeoutOrderQuery = Order::where('status', 0)
            ->where('create_time', '<', $timeoutTime);
        if (!empty($pendingFlashSaleOrderIds)) {
            $timeoutOrderQuery->whereNotIn('id', $pendingFlashSaleOrderIds);
        }
        $timeoutOrders = $timeoutOrderQuery->column('id');
        $count = 0;
        if (!empty($timeoutOrders)) {
            $count = $this->updateOrdersByIds(array_map('intval', $timeoutOrders), ['status' => 2]);
            foreach ($timeoutOrders as $orderId) {
                $this->flashSaleService->handleOrderCanceled((int)$orderId, true);
            }
        }

        $result = [
            'timeout_minutes' => $timeout,
            'grace_seconds' => $graceSeconds,
            'canceled_count' => (int)$count,
            'released_flash_sale_count' => $releasedFlashSaleCount,
            'threshold_time' => $timeoutTime,
            'executed_at' => date('Y-m-d H:i:s'),
        ];

        Cache::set(self::TIMEOUT_JOB_STATUS_CACHE_KEY, $result, 7 * 24 * 3600);
        $statusFile = rtrim(app()->getRuntimePath(), '/') . '/order-timeout-status.json';
        @file_put_contents($statusFile, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $result;
    }

    private function appendFlashSaleReserveMeta(array $order): array
    {
        $orderId = (int)($order['id'] ?? 0);
        if ($orderId <= 0) {
            return $order;
        }

        $flashOrderQuery = Db::name('flash_sale_order')
            ->where('order_id', $orderId)
            ->field('status,create_time');
        if ($this->hasFlashReserveExpireField()) {
            $flashOrderQuery->field('status,reserve_expire_time,create_time');
        }
        $flashOrder = $flashOrderQuery->find();
        if (!$flashOrder) {
            $order['is_flash_sale_order'] = false;
            return $order;
        }

        $order['is_flash_sale_order'] = true;
        $order['flash_sale_status'] = (int)($flashOrder['status'] ?? 0);
        $reserveExpireTime = trim((string)($flashOrder['reserve_expire_time'] ?? ''));
        if ($reserveExpireTime === '') {
            $createTs = strtotime((string)($flashOrder['create_time'] ?? ''));
            if ($createTs !== false) {
                $reserveExpireTime = date('Y-m-d H:i:s', $createTs + max(120, min(300, (int)env('FLASH_SALE_RESERVE_MINUTES', 5) * 60)));
            }
        }
        $order['reserve_expire_time'] = $reserveExpireTime;
        return $order;
    }

    private function buildOrderResponseRow(array $order): array
    {
        $orderId = (int)($order['id'] ?? 0);
        $goods = $orderId > 0 ? OrderGoods::where('order_id', $orderId)->find() : null;
        $order['goods'] = $this->decorateOrderGoods($goods ? $goods->toArray() : null);
        $order['goods_display_name'] = $order['goods']['display_name'] ?? '';
        $order['pay_type_text'] = ((int)($order['pay_type'] ?? 0) === 2) ? '支付宝' : '微信';
        return $this->appendFlashSaleReserveMeta($order);
    }

    private function hasFlashReserveExpireField(): bool
    {
        if (self::$hasFlashReserveExpireField !== null) {
            return self::$hasFlashReserveExpireField;
        }
        try {
            $fields = Db::name('flash_sale_order')->getFields();
            self::$hasFlashReserveExpireField = array_key_exists('reserve_expire_time', $fields);
        } catch (\Throwable $e) {
            self::$hasFlashReserveExpireField = false;
        }
        return self::$hasFlashReserveExpireField;
    }

    private function hasOrderUpdateTimeField(): bool
    {
        if (self::$hasOrderUpdateTimeField !== null) {
            return self::$hasOrderUpdateTimeField;
        }
        try {
            $fields = Db::name('order')->getFields();
            self::$hasOrderUpdateTimeField = array_key_exists('update_time', $fields);
        } catch (\Throwable $e) {
            self::$hasOrderUpdateTimeField = false;
        }
        return self::$hasOrderUpdateTimeField;
    }

    private function buildOrderUpdateData(array $data): array
    {
        if (array_key_exists('status', $data) && (int)$data['status'] !== 0) {
            // 订单离开待支付状态后释放防重键，允许后续重新下单
            $data['pending_lock_key'] = null;
        }
        if ($this->hasOrderUpdateTimeField() && !array_key_exists('update_time', $data)) {
            $data['update_time'] = date('Y-m-d H:i:s');
        }
        return $data;
    }

    private function updateOrderById(int $orderId, array $data): int
    {
        if ($orderId <= 0) {
            return 0;
        }
        return (int)Db::name('order')
            ->where('id', $orderId)
            ->update($this->buildOrderUpdateData($data));
    }

    private function updateOrdersByIds(array $orderIds, array $data): int
    {
        $orderIds = array_values(array_filter(array_map('intval', $orderIds), static fn(int $id): bool => $id > 0));
        if (empty($orderIds)) {
            return 0;
        }
        return (int)Db::name('order')
            ->whereIn('id', $orderIds)
            ->update($this->buildOrderUpdateData($data));
    }

    /**
     * 支付订单
     */
    /**
     * 获取订单支付参数
     */
    public function pay($userId, $id, $payType = null)
    {
        $order = Order::where('user_id', $userId)->where('id', $id)->find();
        if (!$order) {
            throw new ValidateException('订单不存在');
        }

        if ($order->status != 0) {
            throw new ValidateException('订单状态不正确，无法支付');
        }

        if ($payType === null) {
            $payType = $order->pay_type == 2 ? 'alipay' : 'wechat';
        }
        
        $payParams = $this->getPayParams($order, $payType);

        return [
            'order_id'    => $order->id,
            'order_sn'   => $order->order_sn,
            'pay_amount' => $order->pay_amount,
            'pay_type'   => $payParams['pay_type'],
            'pay_url'    => $payParams['pay_url'],
        ];
    }

    /**
     * 获取订单商品名称
     */
    private function getOrderGoodsName($orderId)
    {
        $goods = \app\api\model\OrderGoods::where('order_id', $orderId)->find();
        return $goods ? $goods->goods_name : '商品购买';
    }

    /**
     * 获取支付参数或支付跳转链接
     */
    /**
     * 构建第三方支付参数
     */
    public function getPayParams($order, $payType = 'wechat')
    {
        $orderSn = $order->order_sn;
        $payAmount = $order->pay_amount;
        $goodsName = $this->getOrderGoodsName($order->id);
        
        if ($payType === 'alipay') {
            $alipayUrl = $this->getAlipayUrl($orderSn, $payAmount, $goodsName);
            return [
                'pay_type' => 'alipay',
                'pay_url' => $alipayUrl,
                'order_sn' => $orderSn,
            ];
        }
        
        $wechatPayUrl = $this->getWechatPayUrl($orderSn, $payAmount, $goodsName);
        return [
            'pay_type' => 'wechat',
            'pay_url' => $wechatPayUrl,
            'order_sn' => $orderSn,
        ];
    }

    /**
     * 生成支付宝支付链接
     */
    private function getAlipayUrl($orderSn, $amount, $subject)
    {
        try {
            return $this->alipayService->buildPagePayUrl($orderSn, (float)$amount, (string)$subject);
        } catch (\Throwable $e) {
            throw new ValidateException('支付宝配置未完成，请补充应用私钥与支付宝公钥');
        }
    }

    /**
     * 生成微信支付链接
     */
    private function getWechatPayUrl($orderSn, $amount, $subject)
    {
        $appId = config('wechat.app_id', '');
        
        if (empty($appId)) {
            return 'weixin://wxpay/bizpayurl?pr=' . $orderSn;
        }
        
        return 'https://wx.tenpay.com/cgi-bin/mmpayweb-bin/checkmweb?prepay_id=' . $orderSn . '&mch_id=' . config('wechat.mch_id', '');
    }

    /**
     * 取消订单
     */
    /**
     * 用户主动取消订单
     */
    public function cancel($userId, $id)
    {
        $order = Order::where('user_id', $userId)->where('id', $id)->find();
        if (!$order) {
            throw new ValidateException('订单不存在');
        }

        if ($order->status != 0) {
            throw new ValidateException('只有待支付的订单可以取消');
        }

        $this->updateOrderById((int)$order->id, ['status' => 2]);
        $this->flashSaleService->handleOrderCanceled((int)$order->id);

        return true;
    }

    /**
     * 检查商品购买状态
     * @param int $userId 用户ID
     * @param int $goodsType 商品类型
     * @param int $goodsId 商品ID
     * @return array
     */
    /**
     * 检查内容购买状态
     */
    public function checkPurchased($userId, $goodsType, $goodsId)
    {
        $goodsType = (int)$goodsType;
        $goodsId = (int)$goodsId;

        if ($goodsType == 10) {
            $drama = Drama::where('id', $goodsId)->where('status', 1)->find();
            if (!$drama) {
                throw new ValidateException('短剧不存在或已下架');
            }
            $availableEpisode = DramaEpisode::where('drama_id', $goodsId)
                ->where('status', 1)
                ->order('episode_number', 'asc')
                ->order('id', 'asc')
                ->find();
            if (!$availableEpisode) {
                throw new ValidateException('该短剧没有可购买的剧集');
            }
        } elseif ($goodsType == 20) {
            $novel = Novel::where('id', $goodsId)->where('status', 1)->find();
            if (!$novel) {
                throw new ValidateException('小说不存在或已下架');
            }
            $availableChapter = NovelChapter::where('novel_id', $goodsId)
                ->where('status', 1)
                ->order('chapter_number', 'asc')
                ->order('id', 'asc')
                ->find();
            if (!$availableChapter) {
                throw new ValidateException('该小说没有可购买的章节');
            }
        }

        $matchers = ContentPurchaseMatcher::orderGoodsMatchers($goodsType, $goodsId);

        $hasPaid = Order::alias('o')
            ->join('djxs_order_goods g', 'o.id = g.order_id')
            ->where('o.user_id', $userId)
            ->where('o.status', 1)
            ->where(function ($query) use ($matchers) {
                ContentPurchaseMatcher::applyOrderGoodsMatchersWhere($query, 'g', $matchers);
            })
            ->count();

        if ($hasPaid > 0) {
            return [
                'purchased' => true,
                'pending_order_id' => null,
                'status' => 1
            ];
        }

        $pendingOrder = Order::alias('o')
            ->join('djxs_order_goods g', 'o.id = g.order_id')
            ->where('o.user_id', $userId)
            ->where('o.status', 0)
            ->where(function ($query) use ($matchers) {
                ContentPurchaseMatcher::applyOrderGoodsMatchersWhere($query, 'g', $matchers);
            })
            ->find();

        if ($pendingOrder) {
            return [
                'purchased' => false,
                'pending_order_id' => $pendingOrder->id,
                'status' => 0
            ];
        }

        return [
            'purchased' => false,
            'pending_order_id' => null,
            'status' => 0
        ];
    }

    /**
     * 补全订单商品展示信息，避免仅显示章节/剧集名。
     */
    private function decorateOrderGoods(?array $goods): ?array
    {
        if (!$goods) {
            return null;
        }

        $goodsType = (int)($goods['goods_type'] ?? 0);
        $goodsId = (int)($goods['goods_id'] ?? 0);
        $goodsName = trim((string)($goods['goods_name'] ?? ''));
        $parentTitle = '';
        $childTitle = '';
        $displayName = $goodsName;

        if ($goodsType === 1) {
            $episode = DramaEpisode::field('id,drama_id,title')->find($goodsId);
            if ($episode) {
                $childTitle = (string)$episode->title;
                $parentTitle = (string)Drama::where('id', (int)$episode->drama_id)->value('title');
            }
            $displayName = $parentTitle !== ''
                ? ($parentTitle . ($childTitle !== '' ? (' / ' . $childTitle) : ''))
                : ($childTitle !== '' ? $childTitle : $goodsName);
        } elseif ($goodsType === 2) {
            $chapter = NovelChapter::field('id,novel_id,title')->find($goodsId);
            if ($chapter) {
                $childTitle = (string)$chapter->title;
                $parentTitle = (string)Novel::where('id', (int)$chapter->novel_id)->value('title');
            }
            $displayName = $parentTitle !== ''
                ? ($parentTitle . ($childTitle !== '' ? (' / ' . $childTitle) : ''))
                : ($childTitle !== '' ? $childTitle : $goodsName);
        } elseif ($goodsType === 10) {
            $parentTitle = (string)Drama::where('id', $goodsId)->value('title');
            $displayName = $parentTitle !== '' ? $parentTitle : $goodsName;
        } elseif ($goodsType === 20) {
            $parentTitle = (string)Novel::where('id', $goodsId)->value('title');
            $displayName = $parentTitle !== '' ? $parentTitle : $goodsName;
        }

        if ($displayName === '') {
            $displayName = '商品#' . $goodsId;
        }
        if ($goodsName === '') {
            $goods['goods_name'] = $displayName;
        }

        $goods['parent_title'] = $parentTitle;
        $goods['child_title'] = $childTitle;
        $goods['display_name'] = $displayName;

        return $goods;
    }

    /**
     * 检查会员访问权限
     */
    public function checkMemberAccess($userId, $goodsType, $goodsId)
    {
        $member = Member::where('user_id', $userId)
            ->where('status', 1)
            ->where('end_time', '>', date('Y-m-d H:i:s'))
            ->find();

        if (!$member) {
            return [
                'can_access' => false,
                'member_level' => 0,
                'reason' => '非会员'
            ];
        }

        $levelId = $member->member_level_id;
        $level = MemberLevel::find($levelId);

        if ($goodsType == 10 || $goodsType == 1) {
            $drama = Drama::find($goodsId);
            if (!$drama) {
                return ['can_access' => false, 'member_level' => 0, 'reason' => '短剧不存在'];
            }
            if ($levelId == 1) {
                return ['can_access' => false, 'member_level' => $levelId, 'reason' => '青铜会员仅可免费观看部分内容'];
            }
            if ($levelId == 2) {
                return ['can_access' => true, 'member_level' => $levelId, 'reason' => '白银会员可免费观看所有短剧'];
            }
            if ($levelId >= 3) {
                return ['can_access' => true, 'member_level' => $levelId, 'reason' => '会员可免费观看'];
            }
        }

        if ($goodsType == 20 || $goodsType == 2) {
            $novel = Novel::find($goodsId);
            if (!$novel) {
                return ['can_access' => false, 'member_level' => 0, 'reason' => '小说不存在'];
            }
            if ($levelId == 1) {
                return ['can_access' => false, 'member_level' => $levelId, 'reason' => '青铜会员仅可免费观看部分内容'];
            }
            if ($levelId == 2) {
                return ['can_access' => true, 'member_level' => $levelId, 'reason' => '白银会员可免费观看所有小说章节'];
            }
            if ($levelId >= 3) {
                return ['can_access' => true, 'member_level' => $levelId, 'reason' => '会员可免费观看'];
            }
        }

        return ['can_access' => false, 'member_level' => 0, 'reason' => '无法确定访问权限'];
    }

    /**
     * 处理支付回调（幂等 + 金额校验 + 状态机迁移）
     */
    /**
     * 统一处理支付回调
     */
    public function processNotify(array $data, string $clientIp = ''): array
    {
        $orderSn = (string)($data['order_sn'] ?? $data['out_trade_no'] ?? '');
        $status = strtoupper((string)($data['status'] ?? $data['trade_status'] ?? ''));
        $payTypeRaw = strtolower((string)($data['pay_type'] ?? 'wechat'));
        $isAlipayNotify = isset($data['trade_no']) || isset($data['out_trade_no']);
        $isAlipayReturn = (($data['method'] ?? '') === 'alipay.trade.page.pay.return');
        $amount = (float)($data['total_amount'] ?? $data['amount'] ?? 0);
        $nonce = (string)($data['nonce'] ?? '');
        $timestampRaw = $data['ts'] ?? ($data['timestamp'] ?? null);

        // 支付宝同步回跳通常无 trade_status，验签通过后可按成功处理
        if ($status === '' && (($data['method'] ?? '') === 'alipay.trade.page.pay.return')) {
            $status = 'TRADE_SUCCESS';
        }

        if ($orderSn === '' || $amount <= 0 || $status === '') {
            return ['success' => false, 'msg' => '回调参数不完整'];
        }

        // 支付宝同步回跳来自用户浏览器出口 IP，不应按服务器异步通知白名单拦截
        if (!$isAlipayReturn && !$this->checkNotifyClientIp($clientIp)) {
            return ['success' => false, 'msg' => '回调来源IP不在白名单'];
        }

        if ($isAlipayNotify) {
            if (!$this->alipayService->verifyNotify($data)) {
                return ['success' => false, 'msg' => '支付宝回调验签失败'];
            }
            if (!$this->checkAlipayNotifyBusiness($data)) {
                return ['success' => false, 'msg' => '支付宝回调商户参数不匹配'];
            }
            $payTypeRaw = 'alipay';
        } else {
            $sign = (string)($data['sign'] ?? '');
            if ($this->isNotifyStrictModeEnabled() && !$this->hasRequiredNotifyFields($timestampRaw, $nonce, $sign)) {
                return ['success' => false, 'msg' => '严格模式下回调缺少 timestamp/nonce/sign'];
            }
            if (!$this->verifyNotifySign($orderSn, $amount, $status, $sign, $timestampRaw, $nonce)) {
                return ['success' => false, 'msg' => '签名验证失败'];
            }
        }

        $payType = $payTypeRaw === 'alipay' ? 2 : 1;
        $isPaid = in_array($status, ['SUCCESS', 'PAID', 'TRADE_SUCCESS', 'TRADE_FINISHED'], true);
        if (!$isPaid) {
            return ['success' => false, 'msg' => '支付未成功'];
        }

        $requireTimestamp = (!$isAlipayNotify && $this->isNotifyStrictModeEnabled());
        if (!$this->checkNotifyTimestamp($data, $timestampRaw, $requireTimestamp)) {
            return ['success' => false, 'msg' => '回调时间戳超出允许窗口'];
        }

        $replayKey = $this->buildNotifyReplayKey($data, $orderSn, $status, $amount);
        if ($this->isNotifyReplay($replayKey)) {
            // 重放/重复通知直接视为成功，避免第三方重试风暴
            return ['success' => true, 'msg' => '重复回调已忽略'];
        }

        $result = Db::transaction(function () use ($orderSn, $amount, $payType) {
            $order = Order::where('order_sn', $orderSn)->lock(true)->find();
            if (!$order) {
                return ['success' => false, 'msg' => '订单不存在'];
            }

            if ((int)$order->status === 1) {
                $this->flashSaleService->handleOrderPaid((int)$order->id);
                return ['success' => true, 'msg' => '订单已处理'];
            }

            // 临界点保护：订单已被超时取消，但第三方已扣款，仍以支付成功落库避免用户损失
            if ((int)$order->status === 2) {
                if (abs((float)$order->pay_amount - $amount) > 0.01) {
                    return ['success' => false, 'msg' => '回调金额与订单金额不一致'];
                }
                $payTime = date('Y-m-d H:i:s');
                $this->updateOrderById((int)$order->id, [
                    'status' => 1,
                    'pay_type' => $payType,
                    'pay_time' => $payTime,
                ]);
                $order->status = 1;
                $order->pay_type = $payType;
                $order->pay_time = $payTime;
                $this->grantOrderBenefits($order);
                $this->flashSaleService->handleOrderPaid((int)$order->id);
                $this->settleDistributionCommission((int)$order->id);
                return ['success' => true, 'msg' => '订单已取消但支付成功，已补记为已支付'];
            }

            if ((int)$order->status !== 0) {
                return ['success' => false, 'msg' => '订单状态异常'];
            }

            if (abs((float)$order->pay_amount - $amount) > 0.01) {
                return ['success' => false, 'msg' => '回调金额与订单金额不一致'];
            }

            $payTime = date('Y-m-d H:i:s');
            $this->updateOrderById((int)$order->id, [
                'status' => 1,
                'pay_type' => $payType,
                'pay_time' => $payTime,
            ]);
            $order->status = 1;
            $order->pay_type = $payType;
            $order->pay_time = $payTime;
            $this->grantOrderBenefits($order);
            $this->flashSaleService->handleOrderPaid((int)$order->id);
            $this->settleDistributionCommission((int)$order->id);

            return ['success' => true, 'msg' => '处理成功'];
        });

        if (($result['success'] ?? false) === true) {
            $this->markNotifyReplay($replayKey);
        }
        return $result;
    }

    /**
     * 发放订单权益（目前重点：会员订单支付后开通/续期会员）
     */
    private function grantOrderBenefits(Order $order): void
    {
        $goods = OrderGoods::where('order_id', (int)$order->id)->lock(true)->find();
        if (!$goods) {
            return;
        }
        if ((int)$goods->goods_type !== 3) {
            return;
        }

        $level = MemberLevel::find((int)$goods->goods_id);
        if (!$level || (int)$level->status !== 1) {
            return;
        }

        $durationDays = (int)$level->duration;
        if ($durationDays <= 0) {
            $durationDays = 36500;
        }
        $now = date('Y-m-d H:i:s');
        $member = Member::where('user_id', (int)$order->user_id)->lock(true)->find();

        if ($member && strtotime((string)$member->end_time) > time()) {
            $baseTs = strtotime((string)$member->end_time);
            $endTs = $baseTs + ($durationDays * 86400);
            $member->member_level_id = (int)$level->id;
            $member->start_time = $now;
            $member->end_time = date('Y-m-d H:i:s', $endTs);
            $member->status = 1;
            $member->save();
            return;
        }

        $endTs = time() + ($durationDays * 86400);
        if ($member) {
            $member->member_level_id = (int)$level->id;
            $member->start_time = $now;
            $member->end_time = date('Y-m-d H:i:s', $endTs);
            $member->status = 1;
            $member->save();
            return;
        }

        Member::create([
            'user_id' => (int)$order->user_id,
            'member_level_id' => (int)$level->id,
            'start_time' => $now,
            'end_time' => date('Y-m-d H:i:s', $endTs),
            'status' => 1,
        ]);
    }

    /**
     * 订单支付后结算分销佣金（单层上级，幂等）
     */
    private function settleDistributionCommission(int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }

        $order = Db::name('order')->alias('o')->where('o.id', $orderId)->find();
        if (!$order || (int)($order['status'] ?? 0) !== 1) {
            return;
        }

        $buyerId = (int)($order['user_id'] ?? 0);
        if ($buyerId <= 0) {
            return;
        }

        $buyerDistribution = Db::name('distribution')->alias('d')->where('d.user_id', $buyerId)->find();
        if (!$buyerDistribution) {
            $this->ensureDistributionRow($buyerId, 0);
            $buyerDistribution = Db::name('distribution')->alias('d')->where('d.user_id', $buyerId)->find();
        }
        $parentUserId = (int)($buyerDistribution['parent_id'] ?? 0);
        if ($parentUserId <= 0 || $parentUserId === $buyerId) {
            return;
        }

        $ratePercent = $this->getDistributionRatePercent();
        if ($ratePercent <= 0) {
            return;
        }

        $orderAmount = round((float)($order['pay_amount'] ?? 0), 2);
        if ($orderAmount <= 0) {
            return;
        }
        $commission = round($orderAmount * $ratePercent / 100, 2);
        if ($commission <= 0) {
            return;
        }

        $exists = Db::name('commission_record')->alias('c')
            ->where('c.order_id', $orderId)
            ->where('c.user_id', $parentUserId)
            ->where('c.type', 1)
            ->lock(true)
            ->find();
        if ($exists) {
            return;
        }

        $this->ensureDistributionRow($parentUserId, 0);
        Db::name('distribution')->alias('d')
            ->where('d.user_id', $parentUserId)
            ->inc('total_commission', $commission)
            ->inc('available_commission', $commission)
            ->update();

        Db::name('commission_record')->insert([
            'user_id' => $parentUserId,
            'order_id' => $orderId,
            'amount' => $commission,
            'type' => 1,
            'status' => 1,
            'create_time' => date('Y-m-d H:i:s'),
            'process_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function getDistributionRatePercent(): float
    {
        $row = Db::name('system_config')->whereRaw('`key` = ?', ['distribution_config'])->find();
        if (!$row || !isset($row['value'])) {
            return 0.0;
        }
        $decoded = json_decode((string)$row['value'], true);
        if (!is_array($decoded)) {
            return 0.0;
        }
        $rate = round((float)($decoded['rate'] ?? 0), 2);
        return $rate > 0 ? $rate : 0.0;
    }

    private function ensureDistributionRow(int $userId, int $parentUserId = 0): void
    {
        if ($userId <= 0) {
            return;
        }
        $exists = Db::name('distribution')->alias('d')->where('d.user_id', $userId)->find();
        if ($exists) {
            return;
        }
        $parent = ($parentUserId > 0 && $parentUserId !== $userId) ? $parentUserId : 0;
        Db::name('distribution')->insert([
            'user_id' => $userId,
            'parent_id' => $parent,
            'promotion_code' => $this->generateDistributionCode(),
            'total_commission' => 0,
            'available_commission' => 0,
            'status' => 1,
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function generateDistributionCode(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $max = strlen($chars) - 1;
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $chars[random_int(0, $max)];
            }
            $used = Db::name('distribution')->where('promotion_code', $code)->count() > 0;
        } while ($used);

        return $code;
    }

    /**
     * 回调签名验证（演示版）
     */
    private function verifyNotifySign(string $orderSn, float $amount, string $status, string $sign, $timestampRaw = null, string $nonce = ''): bool
    {
        if ($sign === '') {
            return false;
        }
        $secret = (string)config('app.pay_notify_secret', '');
        if ($secret === '') {
            return false;
        }
        $timestamp = $timestampRaw === null ? '' : trim((string)$timestampRaw);
        $plain = $orderSn . '|' . number_format($amount, 2, '.', '') . '|' . $status;
        if ($timestamp !== '' || $nonce !== '') {
            $plain .= '|' . $timestamp . '|' . trim($nonce);
        }
        $expected = hash_hmac('sha256', $plain, $secret);
        return hash_equals(strtolower($expected), strtolower($sign));
    }

    /**
     * 校验支付宝回调业务参数（app_id/seller_id）
     */
    private function checkAlipayNotifyBusiness(array $data): bool
    {
        $expectedAppId = trim((string)config('alipay.app_id', ''));
        $notifyAppId = trim((string)($data['app_id'] ?? ''));
        if ($expectedAppId !== '') {
            if ($notifyAppId === '' || $notifyAppId !== $expectedAppId) {
                return false;
            }
        }

        $expectedSellerId = trim((string)config('alipay.seller_id', ''));
        if ($expectedSellerId === '') {
            return true;
        }
        $notifySellerId = trim((string)($data['seller_id'] ?? ''));
        if ($notifySellerId === '') {
            return false;
        }

        return $notifySellerId === $expectedSellerId;
    }

    private function getNotifyReplayTtlSeconds(): int
    {
        $ttl = (int)env('PAY_NOTIFY_REPLAY_TTL_SECONDS', 86400);
        return max(60, $ttl);
    }

    private function buildNotifyReplayKey(array $data, string $orderSn, string $status, float $amount): string
    {
        $core = [
            'order_sn' => $orderSn,
            'status' => $status,
            'amount' => number_format($amount, 2, '.', ''),
            'trade_no' => (string)($data['trade_no'] ?? ''),
            'sign' => (string)($data['sign'] ?? ''),
            'nonce' => (string)($data['nonce'] ?? ''),
            'ts' => (string)($data['ts'] ?? ($data['timestamp'] ?? ($data['notify_time'] ?? ''))),
        ];
        return self::PAY_NOTIFY_REPLAY_CACHE_PREFIX . sha1(json_encode($core, JSON_UNESCAPED_UNICODE));
    }

    private function isNotifyReplay(string $key): bool
    {
        return Cache::has($key);
    }

    private function markNotifyReplay(string $key): void
    {
        Cache::set($key, 1, $this->getNotifyReplayTtlSeconds());
    }

    private function checkNotifyTimestamp(array $data, $timestampRaw = null, bool $requireTimestamp = false): bool
    {
        $window = (int)env('PAY_NOTIFY_TIMESTAMP_WINDOW_SECONDS', 1800);
        if ($window <= 0) {
            return true;
        }

        $candidates = [];
        if ($timestampRaw !== null && $timestampRaw !== '') {
            $candidates[] = $timestampRaw;
        }
        $candidates[] = $data['timestamp'] ?? null;
        $candidates[] = $data['ts'] ?? null;
        $candidates[] = $data['notify_time'] ?? null;
        $candidates[] = $data['gmt_payment'] ?? null;

        $ts = null;
        foreach ($candidates as $candidate) {
            if ($candidate === null || $candidate === '') {
                continue;
            }
            if (is_numeric($candidate)) {
                $value = (int)$candidate;
                // 兼容毫秒时间戳
                if ($value > 1000000000000) {
                    $value = (int)floor($value / 1000);
                }
                if ($value > 0) {
                    $ts = $value;
                    break;
                }
            }
            $parsed = strtotime((string)$candidate);
            if ($parsed !== false && $parsed > 0) {
                $ts = $parsed;
                break;
            }
        }

        if ($ts === null) {
            return !$requireTimestamp;
        }

        return abs(time() - $ts) <= $window;
    }

    private function isNotifyStrictModeEnabled(): bool
    {
        return $this->getEnvBool('PAY_NOTIFY_STRICT_MODE', false);
    }

    private function hasRequiredNotifyFields($timestampRaw, string $nonce, string $sign): bool
    {
        $timestamp = trim((string)$timestampRaw);
        $sign = trim($sign);
        $nonce = trim($nonce);
        if ($timestamp === '' || $sign === '' || $nonce === '') {
            return false;
        }
        if (!preg_match('/^[a-zA-Z0-9_-]{8,128}$/', $nonce)) {
            return false;
        }
        return true;
    }

    private function getEnvBool(string $key, bool $default = false): bool
    {
        $value = env($key, $default ? '1' : '0');
        if (is_bool($value)) {
            return $value;
        }
        $normalized = strtolower(trim((string)$value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    private function checkNotifyClientIp(string $clientIp): bool
    {
        $raw = env('PAY_NOTIFY_TRUSTED_IPS', '');
        $trusted = is_array($raw) ? $raw : explode(',', (string)$raw);
        $trusted = array_values(array_filter(array_map(static fn($v) => trim((string)$v), $trusted)));
        if (empty($trusted)) {
            return true;
        }
        if ($clientIp === '') {
            return false;
        }
        foreach ($trusted as $rule) {
            if ($this->ipMatchesRule($clientIp, $rule)) {
                return true;
            }
        }
        return false;
    }

    private function ipMatchesRule(string $ip, string $rule): bool
    {
        if ($rule === '*') {
            return true;
        }
        if (strpos($rule, '/') === false) {
            return $ip === $rule;
        }
        [$subnet, $maskBitsRaw] = explode('/', $rule, 2);
        $maskBits = (int)$maskBitsRaw;
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false || $maskBits < 0 || $maskBits > 32) {
            return false;
        }
        if ($maskBits === 0) {
            return true;
        }
        $mask = -1 << (32 - $maskBits);
        return (($ipLong & $mask) === ($subnetLong & $mask));
    }

    /**
     * 获取订单超时任务最近执行状态
     */
    /**
     * 获取超时任务最近运行状态
     */
    public function getTimeoutJobStatus(): array
    {
        $result = [];
        $statusFile = rtrim(app()->getRuntimePath(), '/') . '/order-timeout-status.json';
        if (is_file($statusFile)) {
            $content = (string)file_get_contents($statusFile);
            $decoded = json_decode($content, true);
            if (is_array($decoded) && !empty($decoded)) {
                $result = $decoded;
            }
        }

        if (!is_array($result) || empty($result)) {
            $result = Cache::get(self::TIMEOUT_JOB_STATUS_CACHE_KEY, []);
        }
        if (!is_array($result) || empty($result)) {
            return [
                'executed_at' => null,
                'timeout_minutes' => null,
                'grace_seconds' => null,
                'canceled_count' => 0,
                'threshold_time' => null,
            ];
        }
        return $result;
    }
}
