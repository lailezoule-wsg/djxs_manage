<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\api\service\ContentPurchaseMatcher;

/**
 * Regression checks for matcher rules shared by OrderService::create/checkPurchased.
 */
function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function hasMatcher(array $matchers, int $goodsType, int $goodsId): bool
{
    foreach ($matchers as $matcher) {
        if ((int)($matcher['goods_type'] ?? 0) === $goodsType && (int)($matcher['goods_id'] ?? 0) === $goodsId) {
            return true;
        }
    }
    return false;
}

// Case 1: buying any episode should also match whole-drama order goods.
$episodeId = 888;
$dramaId = 66;
$episodeMatchers = ContentPurchaseMatcher::orderGoodsMatchers(
    1,
    $episodeId,
    static fn(int $id): int => $id === $episodeId ? $dramaId : 0
);
assertTrue(hasMatcher($episodeMatchers, 1, $episodeId), 'Episode matcher missing itself');
assertTrue(hasMatcher($episodeMatchers, 10, $dramaId), 'Episode matcher must include whole drama');

// Case 2: buying any chapter should also match whole-novel order goods.
$chapterId = 999;
$novelId = 77;
$chapterMatchers = ContentPurchaseMatcher::orderGoodsMatchers(
    2,
    $chapterId,
    null,
    static fn(int $id): int => $id === $chapterId ? $novelId : 0
);
assertTrue(hasMatcher($chapterMatchers, 2, $chapterId), 'Chapter matcher missing itself');
assertTrue(hasMatcher($chapterMatchers, 20, $novelId), 'Chapter matcher must include whole novel');

echo "ContentPurchaseMatcher regression tests passed.\n";
