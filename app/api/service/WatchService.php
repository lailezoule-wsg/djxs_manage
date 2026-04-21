<?php
declare (strict_types = 1);

namespace app\api\service;

use app\api\model\Watch;
use think\facade\Log;
use think\exception\ValidateException;

/**
 * 观看记录服务层
 */
class WatchService
{
    /**
     * 记录观看进度（仅校验并入队，由 RabbitMQ 消费者异步落库）
     */
    public function record($userId, $data)
    {
        Log::info('WatchService::record called', ['userId' => $userId, 'data' => $data]);

        if (!$userId) {
            Log::error('User ID is empty');
            throw new ValidateException('用户未登录');
        }

        $dramaId = isset($data['drama_id']) ? (int)$data['drama_id'] : 0;
        $episodeId = isset($data['episode_id']) ? (int)$data['episode_id'] : 0;
        $progress = isset($data['progress']) ? (int)$data['progress'] : 0;

        Log::info('Parsed data', ['dramaId' => $dramaId, 'episodeId' => $episodeId, 'progress' => $progress]);

        if (!$dramaId) {
            Log::error('Drama ID is empty');
            throw new ValidateException('短剧ID不能为空');
        }

        ContentStatQueueService::publishWatchRecord((int)$userId, $dramaId, $episodeId, $progress);

        return true;
    }

    /**
     * 消费者调用：写入 djxs_user_watch，并在满足条件时累计 drama play_count。
     */
    public static function persistWatch(int $userId, int $dramaId, int $episodeId, int $progress): void
    {
        if ($userId < 1 || $dramaId < 1) {
            return;
        }

        $watch = Watch::where('user_id', $userId)
            ->where('drama_id', $dramaId)
            ->find();

        if ($watch) {
            $watch->episode_id = $episodeId;
            $watch->progress = $progress;
            $result = $watch->save();
            Log::info('Watch updated (async)', ['watch_id' => $watch->id, 'result' => $result]);
        } else {
            $watch = new Watch();
            $watch->user_id = $userId;
            $watch->drama_id = $dramaId;
            $watch->episode_id = $episodeId;
            $watch->progress = $progress;
            $result = $watch->save();
            Log::info('Watch created (async)', ['watch_id' => $watch->id, 'result' => $result]);
        }

        if ($episodeId > 0 && $progress > 0) {
            ContentStatService::bumpDramaPlayCount(
                $dramaId,
                $episodeId,
                ContentStatService::actorFromRequest($userId)
            );
        }
    }

    /**
     * 获取观看历史
     */
    public function history($userId, $params = [])
    {
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        if ($page < 1) $page = 1;

        $limit = isset($params['limit']) ? (int)$params['limit'] : (isset($params['page_size']) ? (int)$params['page_size'] : 10);
        if ($limit < 1) $limit = 10;
        if ($limit > 100) $limit = 100;

        $offset = ($page - 1) * $limit;

        $list = Watch::with(['drama', 'episode'])
            ->where('user_id', $userId)
            ->order('update_time', 'desc')
            ->limit($offset, $limit)
            ->select();

        $total = Watch::where('user_id', $userId)->count();

        $result = [];
        foreach ($list as $watch) {
            $item = $watch->toArray();
            if ($watch->drama) {
                $item['title'] = $watch->drama->title;
                $item['cover'] = $watch->drama->cover;
            }
            if ($watch->episode) {
                $item['episode_number'] = $watch->episode->episode_number;
                $item['episode_title'] = $watch->episode->title;
                $item['duration'] = $watch->episode->duration;
            }
            $result[] = $item;
        }

        return [
            'list' => $result,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }
}