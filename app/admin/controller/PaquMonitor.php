<?php
declare (strict_types = 1);

namespace app\admin\controller;

use app\admin\service\PaquMonitorService;

class PaquMonitor extends BaseAdminController
{
    protected PaquMonitorService $service;

    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $this->service = new PaquMonitorService();
    }

    public function tasks()
    {
        try {
            $result = $this->service->tasks();
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function logs(int $task_id)
    {
        try {
            [$page, $pageSize] = $this->pageParams();
            $result = $this->service->logs($task_id, $page, $pageSize);
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function progress(int $task_id)
    {
        try {
            $result = $this->service->progress($task_id);
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function errors()
    {
        try {
            $result = $this->service->errors($this->request->param());
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function stats()
    {
        try {
            $result = $this->service->stats();
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }
}
