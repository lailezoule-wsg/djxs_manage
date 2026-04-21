<?php
declare(strict_types=1);

namespace app\api\service;

use think\facade\Db;
use think\facade\Log;
use think\facade\Request;

/**
 * C 端内容曝光统计（播放/阅读），与进度表解耦。
 * 使用表 djxs_content_stat_dedupe + INSERT IGNORE 去重，避免依赖文件缓存（Docker 下 runtime/cache 不可写会导致整接口失败）。
 */
final class ContentStatService
{
    /**
     * 同一 dedupe 键在「自然日」内只计 1 次（按服务器日期；键内已含日期槽）。
     */
    public static function bumpDramaPlayCount(int $dramaId, int $episodeId, string $actorKey): void
    {
        if ($dramaId < 1 || $episodeId < 1 || $actorKey === '') {
            return;
        }
        self::bumpWithDedupe(
            'drama_play:' . $actorKey . ':' . $dramaId . ':' . $episodeId,
            static function () use ($dramaId): void {
                Db::name('drama')->where('id', $dramaId)->setInc('play_count', 1);
            }
        );
    }

    public static function bumpNovelReadCount(int $novelId, int $chapterId, string $actorKey): void
    {
        if ($novelId < 1 || $chapterId < 1 || $actorKey === '') {
            return;
        }
        self::bumpWithDedupe(
            'novel_read:' . $actorKey . ':' . $novelId . ':' . $chapterId,
            static function () use ($novelId): void {
                Db::name('novel')->where('id', $novelId)->setInc('read_count', 1);
            }
        );
    }

    public static function actorFromRequest(?int $userId): string
    {
        if ($userId !== null && $userId > 0) {
            return 'u:' . $userId;
        }
        $ip = (string)Request::ip();

        return 'g:' . md5($ip !== '' ? $ip : '0.0.0.0');
    }

    private static function bumpWithDedupe(string $rawKey, callable $doInc): void
    {
        $day = date('Y-m-d');
        $hash = md5($rawKey . '|' . $day);

        try {
            $table = Db::name('content_stat_dedupe')->getTable();
            $n = Db::execute('INSERT IGNORE INTO `' . $table . '` (`hash`) VALUES (?)', [$hash]);
            if ((int)$n < 1) {
                return;
            }
            $doInc();
        } catch (\Throwable $e) {
            Log::warning('ContentStatService bump failed', [
                'hash'    => $hash,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
