<?php
declare (strict_types = 1);

namespace app\api\service;

use app\api\model\Novel;
use app\api\model\NovelChapter;
use app\api\model\Category;
use app\api\model\Order;
use app\api\model\Member;
use app\api\model\MemberLevel;
use think\exception\ValidateException;

/**
 * 小说服务层
 */
class NovelService
{
    /**
     * 获取小说列表
     */
    public function list($params = [])
    {
        $limit = isset($params['limit']) ? max(1, min((int)$params['limit'], 50)) : 12;

        if (!empty($params['last_id'])) {
            $query = Novel::with(['category', 'tags'])
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

        $query = Novel::with(['category', 'tags']);

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
     * 获取小说详情
     */
    public function detail($id, $userId = null)
    {
        $novel = Novel::with(['category', 'tags'])->find($id);
        if (!$novel) {
            throw new ValidateException('小说不存在');
        }

        $data = $novel->toArray();

        $chapterPriceSum = (float)NovelChapter::where('novel_id', $id)
            ->where('status', 1)
            ->sum('price');
        $data['chapter_price_sum'] = $chapterPriceSum;
        $data['whole_bundle_ratio'] = isset($data['whole_bundle_ratio'])
            ? (float)$data['whole_bundle_ratio']
            : 1.0;

        $hasChapterPricing = NovelChapter::where('novel_id', $id)
            ->where('status', 1)
            ->where('price', '>', 0)
            ->count() > 0;
        $data['access_gated'] = (((float)$novel->price > 0) || $hasChapterPricing) ? 1 : 0;
        $uid = $userId ? (int)$userId : 0;
        $catalogWhole = (float)$novel->price;
        $data['whole_payable_amount'] = ContentPurchasePricing::wholeNovelPayableAmount($catalogWhole, $uid, (int)$id);
        $data['whole_purchase_available'] = ($catalogWhole > 0 && (float)$data['whole_payable_amount'] > 0) ? 1 : 0;
        $data['episode_purchase_available'] = $hasChapterPricing ? 1 : 0;

        $data['is_paid'] = 0;
        $data['member_access'] = false;
        
        if ($userId) {
            $hasPaid = Order::alias('o')
                ->join('djxs_order_goods g', 'o.id = g.order_id')
                ->where('o.user_id', $userId)
                ->where('o.status', 1)
                ->where(function ($query) use ($id) {
                    $query->where(function ($q) use ($id) {
                        $q->where('g.goods_type', 20)->where('g.goods_id', $id);
                    });
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
     * 获取小说分类
     */
    public function category($type = 2)
    {
        return Category::where('type', $type)
            ->where('status', 1)
            ->order('sort', 'desc')
            ->order('id', 'asc')
            ->select()
            ->toArray();
    }
}
