<?php
declare (strict_types = 1);

namespace app\admin\controller;

use app\admin\service\StatisticsAdminService;

class Statistics extends BaseAdminController
{
    protected StatisticsAdminService $service;

    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $this->service = new StatisticsAdminService();
    }

    public function overview()
    {
        try {
            $data = $this->service->overview();
            return $this->success($data, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function user()
    {
        try {
            $data = $this->service->userTrend(7);
            return $this->success($data, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function content()
    {
        try {
            $data = $this->service->content();
            return $this->success($data, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

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
