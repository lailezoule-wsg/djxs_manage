<?php
declare (strict_types = 1);

namespace app\api\controller;

use app\common\controller\BaseApiController;
use app\api\service\ReadService;
use think\App;

/**
 * 阅读记录控制器
 */
class Read extends BaseApiController
{
    protected $readService;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->readService = new ReadService();
    }

    /**
     * 记录阅读进度
     */
    public function record()
    {
        try {
            $data = $this->request->post();

            $userId = $this->getUserId();
            if ($userId instanceof \think\Response) {
                return $userId;
            }

            $this->readService->record($userId, $data);

            return $this->success([], '记录成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 获取阅读历史
     */
    public function history()
    {
        try {
            $params = $this->request->get();

            $userId = $this->getUserId();
            if ($userId instanceof \think\Response) {
                return $userId;
            }

            $result = $this->readService->history($userId, $params);

            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }
}