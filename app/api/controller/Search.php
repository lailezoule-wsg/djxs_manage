<?php
declare (strict_types = 1);

namespace app\api\controller;

use app\api\service\SearchService;
use app\common\controller\BaseApiController;
use think\App;

/**
 * 搜索控制器
 */
class Search extends BaseApiController
{
    protected SearchService $searchService;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->searchService = new SearchService();
    }

    /**
     * 搜索入口
     */
    public function index()
    {
        try {
            $params = $this->request->get();
            $result = $this->searchService->search($params);

            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }
}
