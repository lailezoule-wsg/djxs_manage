<?php
declare (strict_types = 1);

namespace app\api\controller;

use app\common\controller\BaseApiController;
use app\api\service\DramaService;
use think\App;

/**
 * 短剧控制器
 */
class Drama extends BaseApiController
{
    protected $dramaService;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->dramaService = new DramaService();
    }

    /**
     * 短剧列表
     */
    public function list()
    {
        try {
            $params = $this->request->get();

            $result = $this->dramaService->list($params);

            return json([
                'code' => 200,
                'msg'  => '获取成功',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return json(['code' => 500, 'msg' => $e->getMessage()], 500);
        }
    }

    /**
     * 短剧详情
     */
    public function detail($id)
    {
        try {
            $userId = $this->getUserId();

            $detail = $this->dramaService->detail($id, $userId);

            return json([
                'code' => 200,
                'msg'  => '获取成功',
                'data' => $detail,
            ]);
        } catch (\Exception $e) {
            return json(['code' => 500, 'msg' => $e->getMessage()], 500);
        }
    }

    /**
     * 短剧分类
     */
    public function category()
    {
        try {
            $type = $this->request->get('type', 1);
            
            if (!in_array($type, [1, 2])) {
                return json(['code' => 400, 'msg' => 'type参数错误'], 400);
            }
            
            $category = $this->dramaService->category($type);

            return json([
                'code' => 200,
                'msg'  => '获取成功',
                'data' => $category,
            ]);
        } catch (\Exception $e) {
            return json(['code' => 500, 'msg' => $e->getMessage()], 500);
        }
    }
}
