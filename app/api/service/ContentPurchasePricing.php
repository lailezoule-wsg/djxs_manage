<?php
declare (strict_types = 1);

namespace app\api\service;

use app\api\model\DramaEpisode;
use app\api\model\NovelChapter;
use app\api\model\Order;

/**
 * 整本/整剧「补差价」定价：已单买（章）节的标价可从整包价中抵扣，避免重复付费。
 */
class ContentPurchasePricing
{
    public static function userHasPaidGoods(int $userId, int $goodsType, int $goodsId): bool
    {
        if ($userId <= 0 || $goodsId <= 0) {
            return false;
        }

        return Order::alias('o')
            ->join('djxs_order_goods g', 'o.id = g.order_id')
            ->where('o.user_id', $userId)
            ->where('o.status', 1)
            ->where('g.goods_type', $goodsType)
            ->where('g.goods_id', $goodsId)
            ->count() > 0;
    }

    /** 当前上架单集中，用户已通过 goods_type=1 单集订单买下的标价合计（用当前单集价口径，与 drama.price 一致） */
    public static function sumPurchasedDramaEpisodeCatalog(int $userId, int $dramaId): float
    {
        $sum = 0.0;
        $list = DramaEpisode::where('drama_id', $dramaId)->where('status', 1)->select();
        foreach ($list as $ep) {
            $p = (float)$ep->price;
            if ($p <= 0) {
                continue;
            }
            if (self::userHasPaidGoods($userId, 1, (int)$ep->id)) {
                $sum += $p;
            }
        }

        return round($sum, 2);
    }

    /** 整剧应付 = 整剧标价（含比例）− 已买单集标价合计，不低于 0 */
    public static function wholeDramaPayableAmount(float $dramaCatalogWhole, int $userId, int $dramaId): float
    {
        $credited = self::sumPurchasedDramaEpisodeCatalog($userId, $dramaId);
        $pay = round($dramaCatalogWhole - $credited, 2);

        return $pay > 0 ? $pay : 0.0;
    }

    public static function sumPurchasedNovelChapterCatalog(int $userId, int $novelId): float
    {
        $sum = 0.0;
        $list = NovelChapter::where('novel_id', $novelId)->where('status', 1)->select();
        foreach ($list as $ch) {
            $p = (float)$ch->price;
            if ($p <= 0) {
                continue;
            }
            if (self::userHasPaidGoods($userId, 2, (int)$ch->id)) {
                $sum += $p;
            }
        }

        return round($sum, 2);
    }

    public static function wholeNovelPayableAmount(float $novelCatalogWhole, int $userId, int $novelId): float
    {
        $credited = self::sumPurchasedNovelChapterCatalog($userId, $novelId);
        $pay = round($novelCatalogWhole - $credited, 2);

        return $pay > 0 ? $pay : 0.0;
    }
}
