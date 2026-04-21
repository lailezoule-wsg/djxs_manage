<?php
declare (strict_types = 1);

namespace app\api\controller;

use app\common\controller\BaseApiController;
use app\api\service\NovelService;
use think\App;

/**
 * 小说控制器
 */
class Novel extends BaseApiController
{
    protected $novelService;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->novelService = new NovelService();
    }

    /**
     * 小说列表
     */
    public function list()
    {
        try {
            $params = $this->request->get();

            $result = $this->novelService->list($params);

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
     * 小说详情
     */
    public function detail($id)
    {
        try {
            $userId = $this->getUserId();

            $detail = $this->novelService->detail($id, $userId);

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
     * 小说分类
     */
    public function category()
    {
        try {
            $type = $this->request->get('type', 2);
            
            if (!in_array($type, [1, 2])) {
                return json(['code' => 400, 'msg' => 'type参数错误'], 400);
            }
            
            $category = $this->novelService->category($type);

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
