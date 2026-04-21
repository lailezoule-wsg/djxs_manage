<?php

declare(strict_types=1);

namespace app\command;

use app\api\service\ContentStatQueueService;
use app\api\service\ContentStatService;
use app\api\service\ReadService;
use app\api\service\WatchService;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Log;

/**
 * 异步消费：用户阅读/观看进度、小说 read_count、短剧 play_count。
 *
 * 顺序性：basic_qos prefetch=1 + 手动 ack，单进程内按投递顺序处理；勿对同一队列起多个消费进程，
 * 否则 RabbitMQ 轮询分发会破坏全局顺序。多实例需单活跃消费者队列参数或分片队列，见 ContentStatQueueService。
 */
class ContentStatConsume extends Command
{
    protected function configure(): void
    {
        $this->setName('content-stat:consume')
            ->setDescription('Consume RabbitMQ: read/watch progress, novel/drama counters');
    }

    protected function execute(Input $input, Output $output): int
    {
        $cfg = config('rabbitmq');
        if (!is_array($cfg)) {
            $output->writeln('<error>rabbitmq config missing</error>');

            return self::FAILURE;
        }

        $host = (string) ($cfg['host'] ?? '127.0.0.1');
        $port = (int) ($cfg['port'] ?? 5672);
        $user = (string) ($cfg['user'] ?? 'guest');
        $password = (string) ($cfg['password'] ?? 'guest');
        $vhost = (string) ($cfg['vhost'] ?? '/');

        $output->writeln(sprintf('<info>Connecting %s:%d</info>', $host, $port));

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
            10.0,
            130.0,
            null,
            false,
            60
        );
        $channel = $connection->channel();
        $queue = ContentStatQueueService::declareContentStatQueue($channel);
        $output->writeln(sprintf('<info>queue=%s durable + prefetch=1</info>', $queue));
        $channel->basic_qos(null, 1, null);

        $callback = static function (\PhpAmqpLib\Message\AMQPMessage $msg) use ($output): void {
            try {
                $data = json_decode($msg->getBody(), true);
                if (!is_array($data)) {
                    $msg->ack();

                    return;
                }

                $type = (string) ($data['type'] ?? '');
                $actor = (string) ($data['actor'] ?? '');

                if ($type === 'read_record') {
                    ReadService::persistRecord(
                        (int) ($data['user_id'] ?? 0),
                        (int) ($data['novel_id'] ?? 0),
                        (int) ($data['chapter_id'] ?? 0),
                        (int) ($data['progress'] ?? 0)
                    );
                } elseif ($type === 'watch_record') {
                    WatchService::persistWatch(
                        (int) ($data['user_id'] ?? 0),
                        (int) ($data['drama_id'] ?? 0),
                        (int) ($data['episode_id'] ?? 0),
                        (int) ($data['progress'] ?? 0)
                    );
                } elseif ($type === 'novel_read') {
                    ContentStatService::bumpNovelReadCount(
                        (int) ($data['novel_id'] ?? 0),
                        (int) ($data['chapter_id'] ?? 0),
                        $actor
                    );
                } elseif ($type === 'drama_play') {
                    ContentStatService::bumpDramaPlayCount(
                        (int) ($data['drama_id'] ?? 0),
                        (int) ($data['episode_id'] ?? 0),
                        $actor
                    );
                }

                $msg->ack();
            } catch (\Throwable $e) {
                Log::error('content-stat:consume message failed', [
                    'body'    => $msg->getBody(),
                    'message' => $e->getMessage(),
                ]);
                $output->writeln('<error>' . $e->getMessage() . '</error>');
                // 丢弃异常消息，避免毒消息无限重投
                $msg->ack();
            }
        };

        $channel->basic_consume($queue, '', false, false, false, false, $callback);

        while ($channel->is_consuming()) {
            $channel->wait();
        }

        return self::SUCCESS;
    }
}
