<?php
declare (strict_types = 1);

namespace app\api\service;

use app\api\model\Ad;
use app\api\model\AdPosition;
use think\exception\ValidateException;

/**
 * 广告服务层
 */
class AdService
{
    /**
     * 获取广告列表
     */
    public function list($params = [])
    {
        $position = $params['position'] ?? '';
        $page = isset($params['page']) ? max(1, (int)$params['page']) : 1;
        $limit = isset($params['limit']) ? max(1, min((int)$params['limit'], 50)) : 20;

        $query = Ad::with(['position']);

        if (!empty($position)) {
            $positionModel = AdPosition::where('position', $position)->find();
            if ($positionModel) {
                $query->where('position_id', $positionModel->id);
            }
        }

        $now = date('Y-m-d H:i:s');
        $query->where('status', 1)
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now);

        $result = $query->order('id', 'desc')
            ->paginate([
                'page' => $page,
                'list_rows' => $limit,
            ]);

        return [
            'list' => $result->items(),
            'total' => $result->total(),
            'page' => $result->currentPage(),
            'limit' => $result->listRows(),
            'has_more' => $result->currentPage() < $result->lastPage()
        ];
    }

    /**
     * 获取广告位列表
     */
    public function positionList()
    {
        return AdPosition::where('status', 1)
            ->order('id', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * 获取指定广告位的广告
     */
    public function getByPosition($position, $limit = 1)
    {
        $positionModel = AdPosition::where('position', $position)->find();
        if (!$positionModel) {
            return [];
        }

        $now = date('Y-m-d H:i:s');
        return Ad::where('position_id', $positionModel->id)
            ->where('status', 1)
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->order('id', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();
    }

    /**
     * 广告点击统计
     */
    public function click($id)
    {
        $ad = Ad::find($id);
        if (!$ad) {
            throw new ValidateException('广告不存在');
        }

        $ad->click_count = $ad->click_count + 1;
        $ad->save();

        return true;
    }
}
