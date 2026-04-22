<?php
declare (strict_types = 1);

namespace app\admin\controller;

use app\admin\service\AdAdminService;
use think\exception\ValidateException;

/**
 * 管理端广告管理接口
 */
class Ad extends BaseAdminController
{
    protected AdAdminService $service;

    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $this->service = new AdAdminService();
    }

    /**
     * 分页查询广告位列表
     */
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

    /**
     * 新增广告位
     */
    public function positionCreate()
    {
        try {
            $id = $this->service->positionCreate($this->request->post());
            return $this->success(['id' => $id], '创建成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 更新广告位
     */
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

    /**
     * 删除广告位
     */
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

    /**
     * 分页查询广告列表
     */
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

    /**
     * 新增广告
     */
    public function create()
    {
        try {
            $id = $this->service->create($this->request->post());
            return $this->success(['id' => $id], '创建成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 更新广告
     */
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

    /**
     * 删除广告
     */
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

    /**
     * 获取广告投放统计
     */
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
