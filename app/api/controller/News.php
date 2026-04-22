<?php
declare (strict_types = 1);

namespace app\api\controller;

use app\api\service\NewsService;
use app\common\controller\BaseApiController;
use think\App;

/**
 * 用户端资讯接口
 */
class News extends BaseApiController
{
    protected NewsService $newsService;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->newsService = new NewsService();
    }

    /**
     * 分页查询资讯列表
     */
    public function list()
    {
        try {
            $params = $this->request->get();
            $result = $this->newsService->list($params);

            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 获取资讯详情
     */
    public function detail(int $id)
    {
        try {
            $result = $this->newsService->detail($id);

            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }
}
