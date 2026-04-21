<?php
declare (strict_types = 1);

namespace app\api\controller;

use app\common\controller\BaseApiController;
use app\api\service\OrderService;
use think\App;

/**
 * 订单控制器
 */
class Order extends BaseApiController
{
    protected $orderService;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->orderService = new OrderService();
    }

    /**
     * 创建订单
     */
    public function create()
    {
        try {
            $data = $this->request->post();
            
            $userId = $this->getUserId();
            if ($userId instanceof \think\Response) {
                return $userId;
            }

            $order = $this->orderService->create($userId, $data);
            
            $payType = strtolower(trim((string)($data['pay_type'] ?? 'wechat')));
            if (!in_array($payType, ['wechat', 'alipay'], true)) {
                $payType = 'wechat';
            }
            $payTypeValue = $payType === 'alipay' ? 2 : 1;
            $order->pay_type = $payTypeValue;
            $order->save();

            return $this->success([
                'order_id'   => $order->id,
                'order_sn'   => $order->order_sn,
                'pay_amount' => $order->pay_amount,
                'pay_type'   => $payType,
            ], '订单创建成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 订单列表
     */
    public function list()
    {
        try {
            $params = $this->request->get();
            
            $userId = $this->getUserId();
            if ($userId instanceof \think\Response) {
                return $userId;
            }

            $result = $this->orderService->list($userId, $params);

            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function checkMemberAccess()
    {
        try {
            $params = $this->request->get();
            
            $userId = $this->getUserId();
            if ($userId instanceof \think\Response) {
                return $userId;
            }

            if (!isset($params['goods_type']) || empty($params['goods_type'])) {
                return $this->error('商品类型不能为空', 400, self::BIZ_INVALID_PARAMS);
            }

            if (!isset($params['goods_id']) || empty($params['goods_id'])) {
                return $this->error('商品ID不能为空', 400, self::BIZ_INVALID_PARAMS);
            }

            $result = $this->orderService->checkMemberAccess(
                $userId, 
                $params['goods_type'], 
                $params['goods_id']
            );

            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 订单详情
     */
    public function detail($id)
    {
        try {
            $userId = $this->getUserId();
            if ($userId instanceof \think\Response) {
                return $userId;
            }

            $detail = $this->orderService->detail($userId, $id);

            return $this->success($detail, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 支付回调
     */
    public function notify()
    {
        try {
            $data = $this->request->post();
            $result = $this->orderService->processNotify($data, (string)$this->request->ip());
            if (!$result['success']) {
                // 支付宝异步通知要求返回非 success 时可重试
                return 'fail';
            }
            // 支付宝异步通知要求返回纯文本 success
            return 'success';
        } catch (\Throwable $e) {
            return 'fail';
        }
    }

    /**
     * 支付宝同步回跳确认（兜底）
     */
    public function alipayReturn()
    {
        try {
            $data = $this->request->get();
            $result = $this->orderService->processNotify($data, (string)$this->request->ip());
            if (!$result['success']) {
                return $this->error($result['msg'], 400, self::BIZ_INVALID_PARAMS);
            }
            return $this->success([], '支付确认成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 支付订单
     */
    public function pay()
    {
        try {
            $data = $this->request->post();
            
            $userId = $this->getUserId();
            if ($userId instanceof \think\Response) {
                return $userId;
            }

            if (!isset($data['id']) || empty($data['id'])) {
                return $this->error('订单ID不能为空', 400, self::BIZ_INVALID_PARAMS);
            }

            $payType = isset($data['pay_type']) ? strtolower(trim((string)$data['pay_type'])) : null;
            if ($payType !== null && !in_array($payType, ['wechat', 'alipay'], true)) {
                return $this->error('支付方式不支持', 400, self::BIZ_INVALID_PARAMS);
            }
            $result = $this->orderService->pay($userId, $data['id'], $payType);

            return $this->success($result, '获取支付参数成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 取消订单
     */
    public function cancel()
    {
        try {
            $data = $this->request->post();
            
            $userId = $this->getUserId();
            if ($userId instanceof \think\Response) {
                return $userId;
            }

            if (!isset($data['id']) || empty($data['id'])) {
                return $this->error('订单ID不能为空', 400, self::BIZ_INVALID_PARAMS);
            }

            $this->orderService->cancel($userId, $data['id']);

            return $this->success([], '订单已取消');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 检查购买状态
     */
    public function checkPurchased()
    {
        try {
            $params = $this->request->get();
            
            $userId = $this->getUserId();
            if ($userId instanceof \think\Response) {
                return $userId;
            }

            if (!isset($params['goods_type']) || empty($params['goods_type'])) {
                return $this->error('商品类型不能为空', 400, self::BIZ_INVALID_PARAMS);
            }

            if (!isset($params['goods_id']) || empty($params['goods_id'])) {
                return $this->error('商品ID不能为空', 400, self::BIZ_INVALID_PARAMS);
            }

            $result = $this->orderService->checkPurchased(
                $userId, 
                $params['goods_type'], 
                $params['goods_id']
            );

            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }
}
