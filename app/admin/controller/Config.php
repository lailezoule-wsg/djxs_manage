<?php
declare (strict_types = 1);

namespace app\admin\controller;

use app\admin\service\ConfigAdminService;
use think\exception\ValidateException;

/**
 * 管理端系统配置接口
 */
class Config extends BaseAdminController
{
    protected ConfigAdminService $service;

    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $this->service = new ConfigAdminService();
    }

    /**
     * 获取配置项列表
     */
    public function list()
    {
        try {
            return $this->success($this->service->list(), '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 批量更新配置项
     */
    public function update()
    {
        try {
            $payload = $this->requestPayload();
            $this->validateOrFail(\app\admin\validate\ConfigUpdateValidate::class, $payload);
            $items = (array)($payload['items'] ?? []);
            $this->service->update($items);
            return $this->success([], '更新成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 400, self::BIZ_INVALID_PARAMS);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }
}
