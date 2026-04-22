<?php
declare (strict_types = 1);

namespace app\admin\service;

use app\api\service\OrderService;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 管理端订单业务服务
 */
class OrderAdminService extends BaseAdminService
{
    /**
     * 分页查询订单列表
     */
    public function list(array $params, int $page, int $pageSize): array
    {
        $status = $params['status'] ?? '';
        $orderSn = trim((string)($params['order_sn'] ?? ''));
        $mobile = trim((string)($params['mobile'] ?? ''));
        $goodsName = trim((string)($params['goods_name'] ?? ''));
        $userId = (int)($params['user_id'] ?? 0);

        $sortDir = strtolower(trim((string)($params['sort_dir'] ?? 'desc')));
        if (!in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'desc';
        }

        $query = Db::name('order')->alias('o')
            ->leftJoin('djxs_user u', 'u.id=o.user_id')
            ->field('o.*,u.mobile,u.nickname');
        if ($status !== '' && $status !== null && $status !== 'all') {
            $query->where('o.status', (int)$status);
        }
        if ($orderSn !== '') {
            $query->whereLike('o.order_sn', '%' . $orderSn . '%');
        }
        if ($mobile !== '') {
            $query->whereLike('u.mobile', '%' . $mobile . '%');
        }
        if ($userId > 0) {
            $query->where('o.user_id', $userId);
        }
        if ($goodsName !== '') {
            $query->whereExists(function ($sub) use ($goodsName) {
                $sub->name('order_goods')->alias('g')
                    ->fieldRaw('1')
                    ->whereColumn('g.order_id', 'o.id')
                    ->whereLike('g.goods_name', '%' . $goodsName . '%');
            });
        }

        $query->order('o.create_time', $sortDir)->order('o.id', $sortDir);

        $result = $this->paginateToArray($query, $page, $pageSize);
        $this->attachOrderGoodsNamesToList($result['list']);

        return $result;
    }

    /**
     * 列表展示用：批量拼接订单商品名称（避免 JOIN 导致分页重复行）
     *
     * @param array<int, array<string, mixed>> $list
     */
    private function attachOrderGoodsNamesToList(array &$list): void
    {
        if ($list === []) {
            return;
        }
        $orderIds = [];
        foreach ($list as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id > 0) {
                $orderIds[$id] = true;
            }
        }
        $orderIds = array_keys($orderIds);
        if ($orderIds === []) {
            return;
        }

        $rows = Db::name('order_goods')
            ->whereIn('order_id', $orderIds)
            ->order('order_id', 'asc')
            ->order('id', 'asc')
            ->field('order_id,goods_name')
            ->select()
            ->toArray();

        $byOrder = [];
        foreach ($rows as $r) {
            $oid = (int)($r['order_id'] ?? 0);
            $name = trim((string)($r['goods_name'] ?? ''));
            if ($oid <= 0) {
                continue;
            }
            if (!isset($byOrder[$oid])) {
                $byOrder[$oid] = [];
            }
            if ($name !== '') {
                $byOrder[$oid][] = $name;
            }
        }

        foreach ($list as &$row) {
            $oid = (int)($row['id'] ?? 0);
            $parts = $byOrder[$oid] ?? [];
            $row['goods_names'] = $parts !== [] ? implode('；', $parts) : '';
        }
        unset($row);
    }

    /**
     * 获取订单详情（含商品明细）
     */
    public function detail(int $id): array
    {
        $order = Db::name('order')->alias('o')
            ->leftJoin('djxs_user u', 'u.id=o.user_id')
            ->field('o.*,u.mobile,u.nickname')
            ->where('o.id', $id)
            ->find();
        if (!$order) {
            throw new ValidateException('订单不存在');
        }
        $payType = (int)($order['pay_type'] ?? 0);
        $order['pay_type_text'] = $payType === 2 ? '支付宝' : ($payType === 1 ? '微信' : '-');
        $order['status_text'] = $this->orderStatusText((int)($order['status'] ?? 0));
        $order['goods'] = Db::name('order_goods')->where('order_id', $id)->order('id', 'asc')->select()->toArray();
        foreach ($order['goods'] as &$g) {
            $g['goods_type_text'] = $this->orderGoodsTypeText((int)($g['goods_type'] ?? 0));
        }
        unset($g);

        return $order;
    }

    private function orderStatusText(int $status): string
    {
        return match ($status) {
            0 => '待支付',
            1 => '已支付',
            2 => '已取消',
            3 => '已退款',
            default => '未知',
        };
    }

    private function orderGoodsTypeText(int $type): string
    {
        return match ($type) {
            1 => '短剧剧集',
            2 => '小说章节',
            3 => '会员',
            10 => '短剧(整剧)',
            20 => '小说(整本)',
            default => '商品',
        };
    }

    /**
     * 标记订单退款状态
     */
    public function refund(int $id): void
    {
        $order = Db::name('order')->where('id', $id)->find();
        if (!$order) {
            throw new ValidateException('订单不存在');
        }
        if ((int)$order['status'] !== 1) {
            throw new ValidateException('仅已支付订单可退款');
        }
        $update = [
            'status' => 3,
            // 订单离开待支付状态后释放防重键，避免后续同商品下单被误拦
            'pending_lock_key' => null,
        ];
        if (isset($order['refund_time'])) {
            $update['refund_time'] = date('Y-m-d H:i:s');
        }
        Db::name('order')->where('id', $id)->update($update);
    }

    /**
     * 获取订单统计概览
     */
    public function statistics(): array
    {
        return [
            'total_count' => (int)Db::name('order')->count(),
            'paid_count' => (int)Db::name('order')->where('status', 1)->count(),
            'pending_count' => (int)Db::name('order')->where('status', 0)->count(),
            'cancel_count' => (int)Db::name('order')->where('status', 2)->count(),
            'refund_count' => (int)Db::name('order')->where('status', 3)->count(),
            'paid_amount' => (float)Db::name('order')->where('status', 1)->sum('pay_amount'),
        ];
    }

    /**
     * 查询超时关单任务状态
     */
    public function timeoutJobStatus(): array
    {
        $service = new OrderService();
        return $service->getTimeoutJobStatus();
    }
}
