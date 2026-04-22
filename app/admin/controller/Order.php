<?php
declare (strict_types = 1);

namespace app\admin\controller;

use app\admin\service\OrderAdminService;
use think\exception\ValidateException;

/**
 * 管理端订单管理接口
 */
class Order extends BaseAdminController
{
    protected OrderAdminService $service;

    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $this->service = new OrderAdminService();
    }

    /**
     * 分页查询订单列表
     */
    public function list()
    {
        try {
            [$page, $pageSize] = $this->pageParams();
            $result = $this->service->list($this->request->param(), $page, $pageSize);
            return $this->success($result, '获取成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 400, self::BIZ_INVALID_PARAMS);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 获取订单详情
     */
    public function detail(int $id)
    {
        try {
            $order = $this->service->detail($id);
            return $this->success($order, '获取成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 404, self::BIZ_NOT_FOUND);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 标记订单退款
     */
    public function refund(int $id)
    {
        try {
            $this->service->refund($id);
            return $this->success([], '退款标记成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 400, self::BIZ_INVALID_PARAMS);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 获取订单统计概览
     */
    public function statistics()
    {
        try {
            $data = $this->service->statistics();
            return $this->success($data, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 查询超时关单任务状态
     */
    public function timeoutJobStatus()
    {
        try {
            $result = $this->service->timeoutJobStatus();
            return $this->success($result, '获取成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 403, self::BIZ_UNAUTHORIZED);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }
}
