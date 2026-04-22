<?php
declare (strict_types = 1);

namespace app\admin\controller;

use app\admin\service\NewsAdminService;
use think\exception\ValidateException;

/**
 * 管理端资讯管理接口
 */
class News extends BaseAdminController
{
    protected NewsAdminService $service;

    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $this->service = new NewsAdminService();
    }

    /**
     * 分页查询资讯列表
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
     * 新增资讯
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
     * 更新资讯
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
     * 删除资讯
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
}
