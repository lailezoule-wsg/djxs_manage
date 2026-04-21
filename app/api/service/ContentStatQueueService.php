<?php

declare(strict_types=1);

namespace app\api\service;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use think\facade\Log;

/**
 * 将阅读/播放量统计、用户阅读进度投递到 RabbitMQ，由 content-stat:consume 异步落库。
 *
 * 持久化：durable 队列 + delivery_mode=persistent + publisher confirm（broker 确认后再返回）。
 * 顺序性：单队列 + 消费者 basic_qos prefetch=1 + 单消费进程；多实例横向扩展会破坏全局 FIFO，
 * 可设 content_stat_queue_single_active_consumer 并重建队列，使多连接下仅一个活跃消费者。
 */
final class ContentStatQueueService
{
    private const TYPE_NOVEL = 'novel_read';

    private const TYPE_DRAMA = 'drama_play';

    /** 用户阅读进度（djxs_user_read + 小说 read_count） */
    private const TYPE_READ_RECORD = 'read_record';

    /** 用户观看进度（djxs_user_watch + drama play_count） */
    private const TYPE_WATCH_RECORD = 'watch_record';

    public static function publishNovelRead(int $novelId, int $chapterId, string $actorKey): void
    {
        if ($novelId < 1 || $chapterId < 1 || $actorKey === '') {
            return;
        }
        self::publish([
            'type'        => self::TYPE_NOVEL,
            'novel_id'    => $novelId,
            'chapter_id'  => $chapterId,
            'actor'       => $actorKey,
            'ts'          => time(),
        ]);
    }

    public static function publishDramaPlay(int $dramaId, int $episodeId, string $actorKey): void
    {
        if ($dramaId < 1 || $episodeId < 1 || $actorKey === '') {
            return;
        }
        self::publish([
            'type'        => self::TYPE_DRAMA,
            'drama_id'    => $dramaId,
            'episode_id'  => $episodeId,
            'actor'       => $actorKey,
            'ts'          => time(),
        ]);
    }

    /**
     * 异步写入用户阅读进度（原 ReadService::record 中的 DB 逻辑在消费者中执行）。
     */
    public static function publishReadRecord(int $userId, int $novelId, int $chapterId, int $progress): void
    {
        if ($userId < 1 || $novelId < 1) {
            return;
        }
        self::publish([
            'type'        => self::TYPE_READ_RECORD,
            'user_id'     => $userId,
            'novel_id'    => $novelId,
            'chapter_id'  => $chapterId,
            'progress'    => $progress,
            'ts'          => time(),
        ]);
    }

    /**
     * 异步写入用户观看进度（原 WatchService::record 中的 DB 逻辑在消费者中执行）。
     */
    public static function publishWatchRecord(int $userId, int $dramaId, int $episodeId, int $progress): void
    {
        if ($userId < 1 || $dramaId < 1) {
            return;
        }
        self::publish([
            'type'        => self::TYPE_WATCH_RECORD,
            'user_id'     => $userId,
            'drama_id'    => $dramaId,
            'episode_id'  => $episodeId,
            'progress'    => $progress,
            'ts'          => time(),
        ]);
    }

    /**
     * 声明内容统计队列（与发布端一致，避免参数漂移）。
     */
    public static function declareContentStatQueue(AMQPChannel $channel): string
    {
        $cfg = config('rabbitmq');
        if (!is_array($cfg)) {
            throw new \RuntimeException('rabbitmq config missing');
        }
        $queue = (string) ($cfg['content_stat_queue'] ?? 'djxs.content_stat');
        $singleActive = (bool) ($cfg['content_stat_queue_single_active_consumer'] ?? false);
        $arguments = $singleActive
            ? new AMQPTable(['x-single-active-consumer' => true])
            : [];
        $channel->queue_declare($queue, false, true, false, false, false, $arguments);

        return $queue;
    }

    private static function publish(array $payload): void
    {
        $cfg = config('rabbitmq');
        if (!is_array($cfg)) {
            return;
        }

        $host = (string) ($cfg['host'] ?? '127.0.0.1');
        $port = (int) ($cfg['port'] ?? 5672);
        $user = (string) ($cfg['user'] ?? 'guest');
        $password = (string) ($cfg['password'] ?? 'guest');
        $vhost = (string) ($cfg['vhost'] ?? '/');

        try {
            $connection = new AMQPStreamConnection(
                $host,
                $port,
                $user,
                $password,
                $vhost,
                false,
                'AMQPLAIN',
                null,
                'en_US',
                3.0,
                3.0,
                null,
                false,
                30
            );
            $channel = $connection->channel();
            $queue = self::declareContentStatQueue($channel);
            $channel->confirm_select();

            $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $message = new AMQPMessage($body, [
                'content_type'  => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            ]);
            $channel->basic_publish($message, '', $queue);
            $channel->wait_for_pending_acks(10.0);

            $channel->close();
            $connection->close();
        } catch (\Throwable $e) {
            Log::warning('ContentStatQueueService publish failed', [
                'message' => $e->getMessage(),
                'payload' => $payload,
            ]);
        }
    }
}
