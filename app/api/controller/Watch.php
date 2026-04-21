<?php
declare (strict_types = 1);

namespace app\api\controller;

use app\common\controller\BaseApiController;
use app\api\service\WatchService;
use think\App;

/**
 * 观看记录控制器
 */
class Watch extends BaseApiController
{
    protected $watchService;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->watchService = new WatchService();
    }

    /**
     * 记录观看进度
     */
    public function record()
    {
        try {
            $data = $this->request->post();

            $userId = $this->getUserId();
            if ($userId instanceof \think\Response) {
                return $userId;
            }

            $result = $this->watchService->record($userId, $data);

            return $this->success($result, '记录成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 获取观看历史
     */
    public function history()
    {
        try {
            $params = $this->request->get();

            $userId = $this->getUserId();
            if ($userId instanceof \think\Response) {
                return $userId;
            }

            $result = $this->watchService->history($userId, $params);

            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }
}
