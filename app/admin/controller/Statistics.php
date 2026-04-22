<?php
declare (strict_types = 1);

namespace app\admin\controller;

use app\admin\service\StatisticsAdminService;

/**
 * 管理端统计分析接口
 */
class Statistics extends BaseAdminController
{
    protected StatisticsAdminService $service;

    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $this->service = new StatisticsAdminService();
    }

    /**
     * 获取整体经营概览
     */
    public function overview()
    {
        try {
            $data = $this->service->overview();
            return $this->success($data, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 获取用户增长趋势
     */
    public function user()
    {
        try {
            $data = $this->service->userTrend(7);
            return $this->success($data, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 获取内容规模统计
     */
    public function content()
    {
        try {
            $data = $this->service->content();
            return $this->success($data, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 获取支付趋势统计
     */
    public function payment()
    {
        try {
            $data = $this->service->paymentTrend(7);
            return $this->success($data, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }
}
