<?php
declare (strict_types = 1);

namespace app\admin\controller;

use app\admin\service\UserAdminService;
use think\exception\ValidateException;

/**
 * 管理端用户管理接口
 */
class User extends BaseAdminController
{
    protected UserAdminService $service;

    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $this->service = new UserAdminService();
    }

    /**
     * 分页查询用户列表
     */
    public function list()
    {
        try {
            [$page, $pageSize] = $this->pageParams();
            $result = $this->service->list($this->request->param(), $page, $pageSize);
            return $this->success($result, '获取成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 400, self::BIZ_INVALID_PARAMS);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 获取用户详情
     */
    public function detail(int $id)
    {
        try {
            $row = $this->service->detail($id);
            return $this->success($row, '获取成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 404, self::BIZ_NOT_FOUND);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 更新用户状态
     */
    public function updateStatus(int $id)
    {
        try {
            $payload = $this->requestPayload();
            $this->validateOrFail(\app\admin\validate\UserStatusValidate::class, $payload);
            $status = (int)$payload['status'];
            $this->service->updateStatus($id, $status);
            return $this->success([], '更新成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 400, self::BIZ_INVALID_PARAMS);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 分页查询用户设备列表
     */
    public function deviceList()
    {
        try {
            [$page, $pageSize] = $this->pageParams();
            $result = $this->service->deviceList($this->request->param(), $page, $pageSize);
            return $this->success($result, '获取成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 400, self::BIZ_INVALID_PARAMS);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 删除设备记录
     */
    public function deviceDelete(int $id)
    {
        try {
            $this->service->deviceDelete($id);
            return $this->success([], '删除成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 404, self::BIZ_NOT_FOUND);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }
}
