<?php
declare (strict_types = 1);

namespace app\admin\controller;

use app\admin\service\MemberAdminService;
use think\exception\ValidateException;

/**
 * 管理端会员管理接口
 */
class Member extends BaseAdminController
{
    protected MemberAdminService $service;

    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $this->service = new MemberAdminService();
    }

    /**
     * 分页查询会员等级列表
     */
    public function levelList()
    {
        try {
            [$page, $pageSize] = $this->pageParams();
            $result = $this->service->levelList($page, $pageSize);
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 新增会员等级
     */
    public function levelCreate()
    {
        try {
            $id = $this->service->levelCreate($this->request->post());
            return $this->success(['id' => (int)$id], '创建成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 更新会员等级
     */
    public function levelUpdate(int $id)
    {
        try {
            $this->service->levelUpdate($id, $this->requestPayload());
            return $this->success([], '更新成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 404, self::BIZ_NOT_FOUND);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 删除会员等级
     */
    public function levelDelete(int $id)
    {
        try {
            $this->service->levelDelete($id);
            return $this->success([], '删除成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 404, self::BIZ_NOT_FOUND);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 分页查询会员用户列表
     */
    public function userList()
    {
        try {
            [$page, $pageSize] = $this->pageParams();
            $result = $this->service->userList($this->request->param(), $page, $pageSize);
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }
}
