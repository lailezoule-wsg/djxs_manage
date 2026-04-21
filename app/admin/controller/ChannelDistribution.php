<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\ChannelAccountAdminService;
use app\admin\service\ChannelCallbackAdminService;
use app\admin\service\ChannelDistributionAdminService;
use app\admin\service\ChannelRegistryAdminService;
use think\App;

class ChannelDistribution extends BaseAdminController
{
    protected ChannelDistributionAdminService $distributionService;
    protected ChannelCallbackAdminService $callbackService;
    protected ChannelAccountAdminService $accountService;
    protected ChannelRegistryAdminService $channelService;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->distributionService = new ChannelDistributionAdminService();
        $this->callbackService = new ChannelCallbackAdminService();
        $this->accountService = new ChannelAccountAdminService();
        $this->channelService = new ChannelRegistryAdminService();
    }

    public function taskList()
    {
        try {
            [$page, $pageSize] = $this->pageParams();
            $result = $this->distributionService->taskList($this->request->get(), $page, $pageSize);
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function taskCreate()
    {
        try {
            $adminId = (int)($this->request->user['id'] ?? 0);
            $result = $this->distributionService->createTask($adminId, $this->request->post());
            return $this->success($result, '创建成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function taskDetail(string $taskNo)
    {
        try {
            $result = $this->distributionService->taskDetail($taskNo);
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function taskRetry(string $taskNo)
    {
        try {
            $result = $this->distributionService->taskRetry($taskNo);
            return $this->success($result, '已入队重试');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function taskResubmit(string $taskNo)
    {
        try {
            $adminId = (int)($this->request->user['id'] ?? 0);
            $result = $this->distributionService->taskResubmit($taskNo, $adminId, $this->request->post());
            return $this->success($result, '提审成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function taskAudit(string $taskNo)
    {
        try {
            $adminId = (int)($this->request->user['id'] ?? 0);
            $result = $this->distributionService->taskAudit($taskNo, $adminId, $this->request->post());
            return $this->success($result, '审核完成');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function taskLogs(string $taskNo)
    {
        try {
            $result = $this->distributionService->taskLogs($taskNo);
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function callbackList()
    {
        try {
            [$page, $pageSize] = $this->pageParams();
            $result = $this->callbackService->callbackList($this->request->get(), $page, $pageSize);
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function callbackDetail(int $id)
    {
        try {
            $result = $this->callbackService->callbackDetail($id);
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function accountList()
    {
        try {
            [$page, $pageSize] = $this->pageParams();
            $result = $this->accountService->accountList($this->request->get(), $page, $pageSize);
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function accountCreate()
    {
        try {
            $id = $this->accountService->accountCreate($this->request->post());
            return $this->success(['id' => $id], '创建成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function accountUpdate(int $id)
    {
        try {
            $this->accountService->accountUpdate($id, $this->requestPayload());
            return $this->success([], '更新成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function accountToggle(int $id)
    {
        try {
            $status = (int)$this->request->post('status', -1);
            $this->accountService->accountToggle($id, $status);
            return $this->success([], '状态更新成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function accountTest(int $id)
    {
        try {
            $result = $this->accountService->accountTest($id);
            return $this->success($result, '连通性检查通过');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function channelList()
    {
        try {
            [$page, $pageSize] = $this->pageParams();
            $result = $this->channelService->list($this->request->get(), $page, $pageSize);
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function channelOptions()
    {
        try {
            $result = $this->channelService->options();
            return $this->success(['list' => $result], '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function channelCreate()
    {
        try {
            $id = $this->channelService->create($this->request->post());
            return $this->success(['id' => $id], '创建成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function channelUpdate(int $id)
    {
        try {
            $this->channelService->update($id, $this->requestPayload());
            return $this->success([], '更新成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function channelToggle(int $id)
    {
        try {
            $status = (int)$this->request->post('status', -1);
            $this->channelService->toggle($id, $status);
            return $this->success([], '状态更新成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }
}
