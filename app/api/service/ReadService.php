<?php
declare (strict_types = 1);

namespace app\api\service;

use app\api\model\Read;
use think\facade\Log;
use think\exception\ValidateException;

/**
 * 阅读记录服务层
 */
class ReadService
{
    /**
     * 记录阅读进度（仅校验并入队，由 RabbitMQ 消费者异步落库）
     */
    public function record($userId, $data)
    {
        Log::info('ReadService::record called', ['userId' => $userId, 'data' => $data]);

        if (!$userId) {
            Log::error('User ID is empty');
            throw new ValidateException('用户未登录');
        }

        $novelId = isset($data['novel_id']) ? (int)$data['novel_id'] : 0;
        $chapterId = isset($data['chapter_id']) ? (int)$data['chapter_id'] : 0;
        $progress = isset($data['progress']) ? (int)$data['progress'] : 0;

        Log::info('Parsed data', ['novelId' => $novelId, 'chapterId' => $chapterId, 'progress' => $progress]);

        if (!$novelId) {
            Log::error('Novel ID is empty');
            throw new ValidateException('小说ID不能为空');
        }

        ContentStatQueueService::publishReadRecord((int)$userId, $novelId, $chapterId, $progress);

        return true;
    }

    /**
     * 消费者调用：写入 djxs_user_read，并累计小说 read_count（与原先同步逻辑一致）。
     */
    public static function persistRecord(int $userId, int $novelId, int $chapterId, int $progress): void
    {
        if ($userId < 1 || $novelId < 1) {
            return;
        }

        $read = Read::where('user_id', $userId)->where('novel_id', $novelId)->find();

        if ($read) {
            $read->chapter_id = $chapterId;
            $read->progress = $progress;
            $result = $read->save();
            Log::info('Read updated (async)', ['read_id' => $read->id, 'result' => $result]);
        } else {
            $read = new Read();
            $read->user_id = $userId;
            $read->novel_id = $novelId;
            $read->chapter_id = $chapterId;
            $read->progress = $progress;
            $result = $read->save();
            Log::info('Read created (async)', ['read_id' => $read->id, 'result' => $result]);
        }

        if ($chapterId > 0) {
            ContentStatService::bumpNovelReadCount(
                $novelId,
                $chapterId,
                ContentStatService::actorFromRequest($userId)
            );
        }
    }

    /**
     * 获取阅读历史
     */
    public function history($userId, $params = [])
    {
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        if ($page < 1) $page = 1;

        $limit = isset($params['limit']) ? (int)$params['limit'] : (isset($params['page_size']) ? (int)$params['page_size'] : 10);
        if ($limit < 1) $limit = 10;
        if ($limit > 100) $limit = 100;

        $offset = ($page - 1) * $limit;

        $list = Read::with(['novel', 'chapter'])
            ->where('user_id', $userId)
            ->order('update_time', 'desc')
            ->limit($offset, $limit)
            ->select();

        $total = Read::where('user_id', $userId)->count();

        $result = [];
        foreach ($list as $read) {
            $item = $read->toArray();
            if ($read->novel) {
                $item['title'] = $read->novel->title;
                $item['cover'] = $read->novel->cover;
            }
            if ($read->chapter) {
                $item['chapter_number'] = $read->chapter->chapter_number;
                $item['chapter_title'] = $read->chapter->title;
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