<?php
declare (strict_types = 1);

namespace app\api\controller;

use app\common\controller\BaseApiController;
use app\api\service\ConfigService;

class Config extends BaseApiController
{
    protected ConfigService $service;

    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $this->service = new ConfigService();
    }

    /**
     * 公共配置（白名单）
     */
    public function publicConfig()
    {
        try {
            $data = $this->service->publicConfig();
            return $this->success($data, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }
}
