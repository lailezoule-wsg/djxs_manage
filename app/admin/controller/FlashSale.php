<?php
declare (strict_types = 1);

namespace app\admin\controller;

use app\admin\service\FlashSaleAdminService;
use think\App;

class FlashSale extends BaseAdminController
{
    protected FlashSaleAdminService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new FlashSaleAdminService();
    }

    public function activityList()
    {
        try {
            [$page, $pageSize] = $this->pageParams();
            $result = $this->service->activityList($this->request->get(), $page, $pageSize);
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function activityCreate()
    {
        try {
            $id = $this->service->activityCreate($this->request->post());
            return $this->success(['id' => $id], '创建成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function activityUpdate(int $id)
    {
        try {
            $this->service->activityUpdate($id, $this->requestPayload());
            return $this->success([], '更新成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function activityPublish(int $id)
    {
        try {
            $this->service->activityPublish($id);
            return $this->success([], '发布成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function activityClose(int $id)
    {
        try {
            $this->service->activityClose($id);
            return $this->success([], '关闭成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function activityCopy(int $id)
    {
        try {
            $adminId = (int)($this->request->user['id'] ?? 0);
            $newId = $this->service->activityCopy($id, $adminId);
            return $this->success(['id' => $newId], '复制成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function activityBatchCopy()
    {
        try {
            $adminId = (int)($this->request->user['id'] ?? 0);
            $newIds = $this->service->activityBatchCopy($this->requestPayload(), $adminId);
            return $this->success(['ids' => $newIds], '批量复制成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function activityBatchStatus()
    {
        try {
            $affected = $this->service->activityBatchStatus($this->requestPayload());
            return $this->success(['affected' => $affected], '批量操作成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function itemList(int $activityId)
    {
        try {
            [$page, $pageSize] = $this->pageParams();
            $result = $this->service->itemList($activityId, $page, $pageSize);
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function itemCreate()
    {
        try {
            $id = $this->service->itemCreate($this->request->post());
            return $this->success(['id' => $id], '创建成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function itemUpdate(int $id)
    {
        try {
            $this->service->itemUpdate($id, $this->requestPayload());
            return $this->success([], '更新成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function itemDelete(int $id)
    {
        try {
            $this->service->itemDelete($id);
            return $this->success([], '删除成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function orderList()
    {
        try {
            [$page, $pageSize] = $this->pageParams();
            $result = $this->service->orderList($this->request->get(), $page, $pageSize);
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function statistics(int $activityId)
    {
        try {
            $result = $this->service->statistics($activityId, $this->request->get());
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function orderExportTaskCreate()
    {
        try {
            $adminId = (int)($this->request->user['id'] ?? 0);
            $result = $this->service->createOrderExportTask($this->request->post(), $adminId);
            return $this->success($result, '创建成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function orderExportTaskStatus(string $taskId)
    {
        try {
            $adminId = (int)($this->request->user['id'] ?? 0);
            $result = $this->service->getOrderExportTaskStatus($taskId, $adminId);
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function orderExportTaskRetry(string $taskId)
    {
        try {
            $adminId = (int)($this->request->user['id'] ?? 0);
            $result = $this->service->retryOrderExportTask($taskId, $adminId);
            return $this->success($result, '重试已开始');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function orderExportTaskList()
    {
        try {
            $adminId = (int)($this->request->user['id'] ?? 0);
            [$page, $pageSize] = $this->pageParams();
            $result = $this->service->listOrderExportTasks($this->request->get(), $adminId, $page, $pageSize);
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function orderExportTaskDelete(string $taskId)
    {
        try {
            $adminId = (int)($this->request->user['id'] ?? 0);
            $this->service->deleteOrderExportTask($taskId, $adminId);
            return $this->success([], '删除成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function riskLogList()
    {
        try {
            [$page, $pageSize] = $this->pageParams();
            $result = $this->service->riskLogList($this->request->get(), $page, $pageSize);
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function riskSummary()
    {
        try {
            $result = $this->service->riskSummary($this->request->get());
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function riskHealthHistory()
    {
        try {
            $result = $this->service->riskHealthHistory($this->request->get());
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function riskHealthThresholdGet()
    {
        try {
            $result = $this->service->riskHealthThresholdGet($this->request->get());
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function riskHealthThresholdUpdate()
    {
        try {
            $result = $this->service->riskHealthThresholdUpdate($this->requestPayload());
            return $this->success($result, '更新成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function blacklistList()
    {
        try {
            [$page, $pageSize] = $this->pageParams();
            $result = $this->service->blacklistList($this->request->get(), $page, $pageSize);
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function blacklistCreate()
    {
        try {
            $adminId = (int)($this->request->user['id'] ?? 0);
            $id = $this->service->blacklistCreate($this->request->post(), $adminId);
            return $this->success(['id' => $id], '创建成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function blacklistUpdate(int $id)
    {
        try {
            $adminId = (int)($this->request->user['id'] ?? 0);
            $this->service->blacklistUpdate($id, $this->requestPayload(), $adminId);
            return $this->success([], '更新成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function blacklistDelete(int $id)
    {
        try {
            $adminId = (int)($this->request->user['id'] ?? 0);
            $this->service->blacklistDelete($id, $adminId);
            return $this->success([], '移除成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }
}

