<?php
declare (strict_types = 1);

namespace app\admin\controller;

use app\admin\service\AdAdminService;
use think\exception\ValidateException;

class Ad extends BaseAdminController
{
    protected AdAdminService $service;

    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $this->service = new AdAdminService();
    }

    public function positionList()
    {
        try {
            [$page, $pageSize] = $this->pageParams();
            $result = $this->service->positionList($this->request->param(), $page, $pageSize);
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function positionCreate()
    {
        try {
            $id = $this->service->positionCreate($this->request->post());
            return $this->success(['id' => $id], '创建成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function positionUpdate(int $id)
    {
        try {
            $this->service->positionUpdate($id, $this->requestPayload());
            return $this->success([], '更新成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 404, self::BIZ_NOT_FOUND);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function positionDelete(int $id)
    {
        try {
            $this->service->positionDelete($id);
            return $this->success([], '删除成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 404, self::BIZ_NOT_FOUND);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
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
            return $this->success(['id' => $id], '创建成功');
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
            return $this->error($e->getMessage(), 404, self::BIZ_NOT_FOUND);
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

    public function statistics(int $id)
    {
        try {
            $result = $this->service->statistics($id);
            return $this->success($result, '获取成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 404, self::BIZ_NOT_FOUND);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }
}
