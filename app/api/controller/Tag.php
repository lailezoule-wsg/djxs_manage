<?php
declare (strict_types = 1);

namespace app\api\controller;

use app\common\controller\BaseApiController;
use think\App;

/**
 * 标签控制器
 */
class Tag extends BaseApiController
{
    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    /**
     * 获取标签列表
     */
    public function list()
    {
        try {
            $type = $this->request->get('type', 1);
            $params = $this->request->get();

            // 分页
            $page = isset($params['page']) ? max(1, min((int)$params['page'], 999999)) : 1;
            $limit = isset($params['limit']) ? max(1, min((int)$params['limit'], 999999)) : 20;

            // 模拟数据
            $data = [
                ['id' => 1, 'name' => '标签1', 'type' => $type],
                ['id' => 2, 'name' => '标签2', 'type' => $type],
                ['id' => 3, 'name' => '标签3', 'type' => $type],
                ['id' => 4, 'name' => '标签4', 'type' => $type],
                ['id' => 5, 'name' => '标签5', 'type' => $type],
            ];

            // 模拟分页
            $start = ($page - 1) * $limit;
            $end = $start + $limit;
            $items = array_slice($data, $start, $limit);

            return json([
                'code' => 200,
                'msg'  => '获取成功',
                'data' => [
                    'total' => count($data),
                    'page' => $page,
                    'limit' => $limit,
                    'list' => $items,
                ],
            ]);
        } catch (\Exception $e) {
            return json(['code' => 500, 'msg' => $e->getMessage()], 500);
        }
    }
}
