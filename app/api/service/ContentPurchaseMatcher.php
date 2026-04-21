<?php
declare(strict_types=1);

namespace app\api\service;

use app\api\model\DramaEpisode;
use app\api\model\NovelChapter;

/**
 * 短剧/小说「整内容」与首集/首章订单行统一匹配，供订单购买态与秒杀冲突检测共用。
 */
final class ContentPurchaseMatcher
{
    /**
     * 订单商品行匹配：整剧(10)/整书(20) 与 首集(1)/首章(2) 视为同一内容。
     *
     * @return array<int, array{goods_type:int, goods_id:int}>
     */
    public static function orderGoodsMatchers(int $goodsType, int $goodsId): array
    {
        $goodsType = (int)$goodsType;
        $goodsId = (int)$goodsId;
        $matchers = [
            ['goods_type' => $goodsType, 'goods_id' => $goodsId],
        ];
        if ($goodsType === 10) {
            $episodeId = (int)DramaEpisode::where('drama_id', $goodsId)->order('episode_number', 'asc')->value('id');
            if ($episodeId > 0) {
                $matchers[] = ['goods_type' => 1, 'goods_id' => $episodeId];
            }
        } elseif ($goodsType === 20) {
            $chapterId = (int)NovelChapter::where('novel_id', $goodsId)->order('chapter_number', 'asc')->value('id');
            if ($chapterId > 0) {
                $matchers[] = ['goods_type' => 2, 'goods_id' => $chapterId];
            }
        }
        return $matchers;
    }

    /**
     * 在订单商品表别名下拼接 (type,id) OR (type,id) …
     *
     * @param \think\db\BaseQuery $query where 闭包内的查询构造器
     * @param array<int, array{goods_type:int, goods_id:int}> $matchers
     */
    public static function applyOrderGoodsMatchersWhere($query, string $goodsAlias, array $matchers): void
    {
        foreach ($matchers as $idx => $m) {
            $method = $idx === 0 ? 'where' : 'whereOr';
            $query->{$method}(function ($sub) use ($goodsAlias, $m) {
                $sub->where($goodsAlias . '.goods_type', $m['goods_type'])->where($goodsAlias . '.goods_id', $m['goods_id']);
            });
        }
    }
}
