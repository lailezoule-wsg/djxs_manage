<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\ChannelCallbackAdminService;
use think\App;

class ChannelDistributionCallback extends BaseAdminController
{
    protected ChannelCallbackAdminService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new ChannelCallbackAdminService();
    }

    public function receive(string $channel)
    {
        try {
            $headers = $this->request->header();
            $rawBody = (string)$this->request->getInput();
            $result = $this->service->receive($channel, is_array($headers) ? $headers : [], $rawBody);
            return $this->success($result, '回调接收成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }
}
