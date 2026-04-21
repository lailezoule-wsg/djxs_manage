<?php
declare (strict_types = 1);

namespace app\api\service;

use app\api\model\Drama;
use app\api\model\DramaEpisode;
use app\api\model\Order;
use think\exception\ValidateException;

/**
 * 短剧剧集服务
 */
class DramaEpisodeService
{
    /**
     * 是否需要购买/会员鉴权：整剧标价 >0，或任一上架单集标价 >0（避免后台整剧为 0、单集有价却全免费播放）
     */
    public function dramaRequiresAccessControl(Drama $drama): bool
    {
        if ((float)$drama->price > 0) {
            return true;
        }

        return DramaEpisode::where('drama_id', $drama->id)
            ->where('status', 1)
            ->where('price', '>', 0)
            ->count() > 0;
    }

    /**
     * 获取剧集信息
     */
    public function getEpisode(int $dramaId, int $episodeNum, ?int $userId = null)
    {
        $episode = DramaEpisode::where('drama_id', $dramaId)
            ->where('episode_number', $episodeNum)
            ->where('status', 1)
            ->find();

        if (!$episode) {
            throw new \Exception('剧集不存在或已下架');
        }

        $drama = Drama::where('id', $dramaId)->find();
        if (!$drama) {
            throw new \Exception('短剧不存在');
        }

        if ($this->dramaRequiresAccessControl($drama) && $userId) {
            $memberService = new MemberService();
            $canWatchForFree = $memberService->canWatchForFree($userId);

            if (!$canWatchForFree && !$this->userHasEpisodeAccess((int)$userId, $drama, $episode)) {
                throw new ValidateException('请先购买该短剧或本集');
            }
        } elseif ($this->dramaRequiresAccessControl($drama) && !$userId) {
            throw new ValidateException('请先登录后再观看');
        }

        $data = $episode->toArray();
        $data['drama_title'] = $drama->title;

        $nextEpisode = DramaEpisode::where('drama_id', $dramaId)
            ->where('episode_number', $episodeNum + 1)
            ->where('status', 1)
            ->find();

        $data['has_next'] = (bool)$nextEpisode;

        ContentStatQueueService::publishDramaPlay(
            $dramaId,
            (int)$episode->id,
            ContentStatService::actorFromRequest($userId)
        );

        return $data;
    }

    /**
     * 获取剧集列表（含 unlocked，用于 C 端选集/单集购买）
     */
    public function getEpisodeList(int $dramaId, ?int $userId = null): array
    {
        $drama = Drama::find($dramaId);
        if (!$drama) {
            return [];
        }

        $episodes = DramaEpisode::where('drama_id', $dramaId)
            ->where('status', 1)
            ->order('episode_number', 'asc')
            ->select();

        if (!$episodes || count($episodes) === 0) {
            return [];
        }

        $memberService = new MemberService();
        $memberFree = $userId && $memberService->canWatchForFree((int)$userId);
        $needsGate = $this->dramaRequiresAccessControl($drama);

        $list = [];
        foreach ($episodes as $ep) {
            $row = $ep->toArray();
            if (!$needsGate) {
                $row['unlocked'] = true;
            } elseif (!$userId) {
                $row['unlocked'] = false;
            } elseif ($memberFree) {
                $row['unlocked'] = true;
            } elseif ((float)$ep->price <= 0) {
                $row['unlocked'] = true;
            } else {
                $row['unlocked'] = $this->userHasEpisodeAccess((int)$userId, $drama, $ep);
            }
            $list[] = $row;
        }

        return $list;
    }

    /**
     * 单集下单前展示（不含视频地址）
     */
    public function getEpisodePurchasePreview(int $episodeId): array
    {
        $episode = DramaEpisode::where('id', $episodeId)->where('status', 1)->find();
        if (!$episode) {
            throw new ValidateException('剧集不存在或已下架');
        }
        $drama = Drama::find($episode->drama_id);
        if (!$drama) {
            throw new ValidateException('短剧不存在');
        }

        return [
            'episode_id'       => (int)$episode->id,
            'episode_number'   => (int)$episode->episode_number,
            'title'            => (string)$episode->title,
            'price'            => (float)$episode->price,
            'drama_id'         => (int)$drama->id,
            'drama_title'      => (string)$drama->title,
            'drama_price'      => (float)$drama->price,
        ];
    }

    /**
     * 是否已购：整剧(10+drama_id)、单集(1+episode_id)、历史整剧(1+首集 id)
     */
    private function userHasEpisodeAccess(int $userId, Drama $drama, DramaEpisode $episode): bool
    {
        if (!$this->dramaRequiresAccessControl($drama)) {
            return true;
        }
        if ((float)$episode->price <= 0) {
            return true;
        }

        $dramaId = (int)$drama->id;
        $episodeId = (int)$episode->id;
        $firstEpId = (int)DramaEpisode::where('drama_id', $dramaId)->order('episode_number', 'asc')->value('id');

        // 使用显式 OR 条件，避免嵌套 where/whereOr 在部分环境下生成歧义 SQL，导致列表已解锁但播放页校验失败
        $parts = ['(g.goods_type = ? AND g.goods_id = ?)', '(g.goods_type = ? AND g.goods_id = ?)'];
        $bindings = [10, $dramaId, 1, $episodeId];
        if ($firstEpId > 0) {
            $parts[] = '(g.goods_type = ? AND g.goods_id = ?)';
            $bindings[] = 1;
            $bindings[] = $firstEpId;
        }
        $expr = implode(' OR ', $parts);

        $count = Order::alias('o')
            ->join('djxs_order_goods g', 'o.id = g.order_id')
            ->where('o.user_id', $userId)
            ->where('o.status', 1)
            ->whereRaw($expr, $bindings)
            ->count();

        return $count > 0;
    }
}
