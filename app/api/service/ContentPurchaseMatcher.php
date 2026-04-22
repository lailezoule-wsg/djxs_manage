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
    public static function orderGoodsMatchers(
        int $goodsType,
        int $goodsId,
        ?callable $episodeToDramaResolver = null,
        ?callable $chapterToNovelResolver = null
    ): array
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
        } elseif ($goodsType === 1) {
            // 任意剧集都应识别整剧(10)购买记录，防止已购整剧后重复购买单集
            $dramaId = 0;
            if ($episodeToDramaResolver !== null) {
                $dramaId = (int)$episodeToDramaResolver($goodsId);
            } else {
                $episode = DramaEpisode::field('id,drama_id')->find($goodsId);
                $dramaId = $episode ? (int)$episode->drama_id : 0;
            }
            if ($dramaId > 0) {
                $matchers[] = ['goods_type' => 10, 'goods_id' => $dramaId];
            }
        } elseif ($goodsType === 2) {
            // 任意章节都应识别整本(20)购买记录，防止已购整本后重复购买单章
            $novelId = 0;
            if ($chapterToNovelResolver !== null) {
                $novelId = (int)$chapterToNovelResolver($goodsId);
            } else {
                $chapter = NovelChapter::field('id,novel_id')->find($goodsId);
                $novelId = $chapter ? (int)$chapter->novel_id : 0;
            }
            if ($novelId > 0) {
                $matchers[] = ['goods_type' => 20, 'goods_id' => $novelId];
            }
        }
        return array_values(array_unique($matchers, SORT_REGULAR));
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
