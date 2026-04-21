<?php
declare (strict_types = 1);

namespace app\api\controller;

use app\common\controller\BaseApiController;
use app\api\service\AdService;
use think\App;

/**
 * 广告控制器
 */
class Ad extends BaseApiController
{
    protected $adService;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->adService = new AdService();
    }

    /**
     * 获取广告列表
     */
    public function list()
    {
        try {
            $params = $this->request->get();

            $ads = $this->adService->list($params);

            return $this->success($ads, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 广告点击统计
     */
    public function click()
    {
        try {
            $data = $this->request->post();
            $id = (int)($data['ad_id'] ?? ($data['id'] ?? 0));

            $this->adService->click($id);

            return $this->success([], '统计成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }
}
