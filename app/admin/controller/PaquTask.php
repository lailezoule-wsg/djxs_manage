<?php
declare (strict_types = 1);

namespace app\admin\controller;

use app\admin\service\PaquTaskService;
use think\exception\ValidateException;

class PaquTask extends BaseAdminController
{
    protected PaquTaskService $service;

    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $this->service = new PaquTaskService();
    }

    public function list()
    {
        try {
            [$page, $pageSize] = $this->pageParams();
            $result = $this->service->list($this->request->param(), $page, $pageSize);
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function create()
    {
        try {
            $id = $this->service->create($this->request->post());
            return $this->success(['id' => (int)$id], '创建成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 400, self::BIZ_INVALID_PARAMS);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function update(int $id)
    {
        try {
            $this->service->update($id, $this->requestPayload());
            return $this->success([], '更新成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 400, self::BIZ_INVALID_PARAMS);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function delete(int $id)
    {
        try {
            $this->service->delete($id);
            return $this->success([], '删除成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 404, self::BIZ_NOT_FOUND);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function start(int $id)
    {
        try {
            $this->service->start($id);
            return $this->success([], '任务已启动');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 400, self::BIZ_INVALID_PARAMS);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function stop(int $id)
    {
        try {
            $this->service->stop($id);
            return $this->success([], '任务已停止');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 400, self::BIZ_INVALID_PARAMS);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function cancel(int $id)
    {
        try {
            $this->service->cancel($id);
            return $this->success([], '任务已取消');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 400, self::BIZ_INVALID_PARAMS);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function detail(int $id)
    {
        try {
            $result = $this->service->detail($id);
            return $this->success($result, '获取成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 404, self::BIZ_NOT_FOUND);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }
}
