<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\ChannelAccountAdminService;
use app\admin\service\ChannelCallbackAdminService;
use app\admin\service\ChannelDistributionAdminService;
use app\admin\service\ChannelRegistryAdminService;
use think\App;

/**
 * 管理端渠道分发管理接口
 */
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

    /**
     * 分页查询分发任务列表
     */
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

    /**
     * 创建分发任务
     */
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

    /**
     * 获取分发任务详情
     */
    public function taskDetail(string $taskNo)
    {
        try {
            $result = $this->distributionService->taskDetail($taskNo);
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 重试分发任务
     */
    public function taskRetry(string $taskNo)
    {
        try {
            $result = $this->distributionService->taskRetry($taskNo);
            return $this->success($result, '已入队重试');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 重新提审分发任务
     */
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

    /**
     * 审核分发任务
     */
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

    /**
     * 获取分发任务日志
     */
    public function taskLogs(string $taskNo)
    {
        try {
            $result = $this->distributionService->taskLogs($taskNo);
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 分页查询渠道回调列表
     */
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

    /**
     * 获取渠道回调详情
     */
    public function callbackDetail(int $id)
    {
        try {
            $result = $this->callbackService->callbackDetail($id);
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 分页查询渠道账号列表
     */
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

    /**
     * 新增渠道账号
     */
    public function accountCreate()
    {
        try {
            $id = $this->accountService->accountCreate($this->request->post());
            return $this->success(['id' => $id], '创建成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 更新渠道账号
     */
    public function accountUpdate(int $id)
    {
        try {
            $this->accountService->accountUpdate($id, $this->requestPayload());
            return $this->success([], '更新成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 启停渠道账号
     */
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

    /**
     * 测试渠道账号连通性
     */
    public function accountTest(int $id)
    {
        try {
            $result = $this->accountService->accountTest($id);
            return $this->success($result, '连通性检查通过');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 分页查询渠道配置列表
     */
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

    /**
     * 获取渠道选项列表
     */
    public function channelOptions()
    {
        try {
            $result = $this->channelService->options();
            return $this->success(['list' => $result], '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 新增渠道配置
     */
    public function channelCreate()
    {
        try {
            $id = $this->channelService->create($this->request->post());
            return $this->success(['id' => $id], '创建成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 更新渠道配置
     */
    public function channelUpdate(int $id)
    {
        try {
            $this->channelService->update($id, $this->requestPayload());
            return $this->success([], '更新成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 启停渠道配置
     */
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
