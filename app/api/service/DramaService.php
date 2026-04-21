<?php
declare (strict_types = 1);

namespace app\api\service;

use app\api\model\Drama;
use app\api\model\DramaEpisode;
use app\api\model\Category;
use app\api\model\Order;
use app\api\model\Member;
use app\api\model\MemberLevel;
use think\exception\ValidateException;

/**
 * 短剧服务层
 */
class DramaService
{
    /**
     * 获取短剧列表
     */
    public function list($params = [])
    {
        $limit = isset($params['limit']) ? max(1, min((int)$params['limit'], 50)) : 12;

        if (!empty($params['last_id'])) {
            $query = Drama::with(['category', 'tags'])
                ->where('id', '<', (int)$params['last_id']);

            if (!empty($params['category_id'])) {
                $query->where('category_id', $params['category_id']);
            }

            if (!empty($params['keyword'])) {
                $query->where('title', 'like', '%' . $params['keyword'] . '%');
            }

            $list = $query->order('id', 'asc')
                ->limit($limit)
                ->select()
                ->toArray();

            return [
                'list' => $list,
                'total' => count($list),
                'has_more' => count($list) === $limit
            ];
        }

        $query = Drama::with(['category', 'tags']);

        if (!empty($params['category_id'])) {
            $query->where('category_id', $params['category_id']);
        }

        if (!empty($params['keyword'])) {
            $query->where('title', 'like', '%' . $params['keyword'] . '%');
        }

        $page = isset($params['page']) ? max(1, (int)$params['page']) : 1;

        $result = $query->order('sort', 'asc')->order('id', 'asc')
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
     * 获取短剧详情
     */
    public function detail($id, $userId = null)
    {
        $drama = Drama::with(['category', 'tags'])->find($id);
        if (!$drama) {
            throw new ValidateException('短剧不存在');
        }

        $data = $drama->toArray();

        $episodePriceSum = (float)DramaEpisode::where('drama_id', $id)
            ->where('status', 1)
            ->sum('price');
        $data['episode_price_sum'] = $episodePriceSum;
        $data['whole_bundle_ratio'] = isset($data['whole_bundle_ratio'])
            ? (float)$data['whole_bundle_ratio']
            : 1.0;

        $hasEpisodePricing = DramaEpisode::where('drama_id', $id)
            ->where('status', 1)
            ->where('price', '>', 0)
            ->count() > 0;
        // 与剧集接口一致：整剧价为 0 但单集有价时仍视为需鉴权/购买
        $data['access_gated'] = (((float)$drama->price > 0) || $hasEpisodePricing) ? 1 : 0;
        // 与后台定价规则一致：整剧价>0 且补差价后仍>0 才开放整剧 SKU；任一有价单集则开放单集 SKU
        $uid = $userId ? (int)$userId : 0;
        $catalogWhole = (float)$drama->price;
        $data['whole_payable_amount'] = ContentPurchasePricing::wholeDramaPayableAmount($catalogWhole, $uid, (int)$id);
        $data['whole_purchase_available'] = ($catalogWhole > 0 && (float)$data['whole_payable_amount'] > 0) ? 1 : 0;
        $data['episode_purchase_available'] = $hasEpisodePricing ? 1 : 0;

        $data['is_paid'] = 0;
        $data['member_access'] = false;
        
        if ($userId) {
            $firstEpId = (int)DramaEpisode::where('drama_id', $id)->order('episode_number', 'asc')->value('id');
            $hasPaid = Order::alias('o')
                ->join('djxs_order_goods g', 'o.id = g.order_id')
                ->where('o.user_id', $userId)
                ->where('o.status', 1)
                ->where(function ($query) use ($id, $firstEpId) {
                    $query->where(function ($q) use ($id) {
                        $q->where('g.goods_type', 10)->where('g.goods_id', $id);
                    });
                    if ($firstEpId > 0) {
                        $query->whereOr(function ($q) use ($firstEpId) {
                            // 历史订单：整剧记为 goods_type=1 + 首集 id
                            $q->where('g.goods_type', 1)->where('g.goods_id', $firstEpId);
                        });
                    }
                })
                ->count();
            $data['is_paid'] = $hasPaid > 0 ? 1 : 0;
            
            $member = Member::where('user_id', $userId)
                ->where('status', 1)
                ->where('end_time', '>', date('Y-m-d H:i:s'))
                ->find();
            
            if ($member) {
                $levelId = $member->member_level_id;
                if ($levelId >= 2) {
                    $data['member_access'] = true;
                    $data['is_paid'] = 1;
                }
            }
        }

        return $data;
    }

    /**
     * 获取短剧分类
     */
    public function category($type = 1)
    {
        return Category::where('type', $type)
            ->where('status', 1)
            ->order('sort', 'desc')
            ->order('id', 'asc')
            ->select()
            ->toArray();
    }
}
