<?php
declare (strict_types = 1);

namespace app\api\service;

use app\api\model\NovelChapter;
use app\api\model\Novel;
use app\api\model\Order;
use think\exception\ValidateException;

/**
 * 小说章节服务层（与短剧剧集接口、解锁口径对齐）
 */
class NovelChapterService
{
    /**
     * 是否需要购买/会员鉴权：整本标价 >0，或任一上架章节标价 >0
     */
    public function novelRequiresAccessControl(Novel $novel): bool
    {
        if ((float)$novel->price > 0) {
            return true;
        }

        return NovelChapter::where('novel_id', $novel->id)
            ->where('status', 1)
            ->where('price', '>', 0)
            ->count() > 0;
    }

    /**
     * 是否已购：整本(20+novel_id)、单章(2+chapter_id)
     */
    private function userHasChapterAccess(int $userId, Novel $novel, NovelChapter $chapter): bool
    {
        if (!$this->novelRequiresAccessControl($novel)) {
            return true;
        }
        if ((float)$chapter->price <= 0) {
            return true;
        }

        $novelId = (int)$novel->id;
        $chapterId = (int)$chapter->id;
        $parts = ['(g.goods_type = ? AND g.goods_id = ?)', '(g.goods_type = ? AND g.goods_id = ?)'];
        $bindings = [20, $novelId, 2, $chapterId];
        $expr = implode(' OR ', $parts);

        return Order::alias('o')
            ->join('djxs_order_goods g', 'o.id = g.order_id')
            ->where('o.user_id', $userId)
            ->where('o.status', 1)
            ->whereRaw($expr, $bindings)
            ->count() > 0;
    }

    /**
     * 获取章节正文（需权限时返回 403，由控制器映射）
     */
    public function getChapter(int $novelId, int $chapterNum, ?int $userId = null)
    {
        $chapter = NovelChapter::where('novel_id', $novelId)
            ->where('chapter_number', $chapterNum)
            ->where('status', 1)
            ->find();

        if (!$chapter) {
            throw new ValidateException('章节不存在');
        }

        $novel = Novel::find($novelId);
        if (!$novel) {
            throw new ValidateException('小说不存在');
        }

        if ($this->novelRequiresAccessControl($novel) && $userId) {
            $memberService = new MemberService();
            $canWatchForFree = $memberService->canWatchForFree($userId);

            if (!$canWatchForFree && !$this->userHasChapterAccess((int)$userId, $novel, $chapter)) {
                throw new ValidateException('请先购买该小说或本章');
            }
        } elseif ($this->novelRequiresAccessControl($novel) && !$userId) {
            throw new ValidateException('请先登录后再阅读');
        }

        $data = $chapter->toArray();

        $nextChapter = NovelChapter::where('novel_id', $novelId)
            ->where('chapter_number', '>', $chapterNum)
            ->where('status', 1)
            ->order('chapter_number', 'asc')
            ->find();

        $data['has_next'] = !empty($nextChapter);
        $data['novel_title'] = $novel->title;

        ContentStatQueueService::publishNovelRead(
            $novelId,
            (int)$chapter->id,
            ContentStatService::actorFromRequest($userId)
        );

        return $data;
    }

    /**
     * 章节列表（含 unlocked；不含正文 content，减轻流量）
     */
    public function getChapterList(int $novelId, ?int $userId = null): array
    {
        $novel = Novel::find($novelId);
        if (!$novel) {
            return [];
        }

        $chapters = NovelChapter::where('novel_id', $novelId)
            ->where('status', 1)
            ->order('chapter_number', 'asc')
            ->withoutField('content')
            ->select();

        if (!$chapters || count($chapters) === 0) {
            return [];
        }

        $memberService = new MemberService();
        $memberFree = $userId && $memberService->canWatchForFree((int)$userId);
        $needsGate = $this->novelRequiresAccessControl($novel);

        $list = [];
        foreach ($chapters as $ch) {
            $row = $ch->toArray();
            if (!$needsGate) {
                $row['unlocked'] = true;
            } elseif (!$userId) {
                $row['unlocked'] = false;
            } elseif ($memberFree) {
                $row['unlocked'] = true;
            } elseif ((float)$ch->price <= 0) {
                $row['unlocked'] = true;
            } else {
                $row['unlocked'] = $this->userHasChapterAccess((int)$userId, $novel, $ch);
            }
            $list[] = $row;
        }

        return $list;
    }

    /**
     * 单章下单前展示（不含正文）
     */
    public function getChapterPurchasePreview(int $chapterId): array
    {
        $chapter = NovelChapter::where('id', $chapterId)->where('status', 1)->find();
        if (!$chapter) {
            throw new ValidateException('章节不存在或已下架');
        }
        $novel = Novel::find($chapter->novel_id);
        if (!$novel) {
            throw new ValidateException('小说不存在');
        }

        return [
            'chapter_id'     => (int)$chapter->id,
            'chapter_number' => (int)$chapter->chapter_number,
            'title'          => (string)$chapter->title,
            'price'          => (float)$chapter->price,
            'novel_id'       => (int)$novel->id,
            'novel_title'    => (string)$novel->title,
            'novel_price'    => (float)$novel->price,
        ];
    }
}
