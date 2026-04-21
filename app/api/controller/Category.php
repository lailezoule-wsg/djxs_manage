<?php
declare (strict_types = 1);

namespace app\api\controller;

use app\common\controller\BaseApiController;
use app\api\model\Category as CategoryModel;
use think\App;

/**
 * 分类控制器
 */
class Category extends BaseApiController
{
    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    /**
     * 获取分类列表
     */
    public function list()
    {
        try {
            $type = $this->request->get('type', 1);
            
            if (!in_array($type, [1, 2])) {
                return json(['code' => 400, 'msg' => 'type参数错误'], 400);
            }
            
            $params = $this->request->get();
            $page = isset($params['page']) ? max(1, (int)$params['page']) : 1;
            $limit = isset($params['limit']) ? max(1, min((int)$params['limit'], 50)) : 20;

            $query = CategoryModel::where('type', $type)->where('status', 1);

            $result = $query->order('sort', 'desc')
                ->order('id', 'asc')
                ->paginate([
                    'page' => $page,
                    'list_rows' => $limit,
                ]);

            return json([
                'code' => 200,
                'msg'  => '获取成功',
                'data' => [
                    'total' => $result->total(),
                    'page' => $result->currentPage(),
                    'limit' => $result->listRows(),
                    'list' => $result->items(),
                ],
            ]);
        } catch (\Exception $e) {
            return json(['code' => 500, 'msg' => $e->getMessage()], 500);
        }
    }
}
