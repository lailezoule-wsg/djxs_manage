<?php

declare(strict_types=1);

namespace app\api\service;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use think\facade\Log;

/**
 * 秒杀订单异步创建队列。
 */
final class FlashSaleOrderQueueService
{
    /**
     * 声明秒杀下单队列
     */
    public static function declareQueue(AMQPChannel $channel, ?string $queueName = null): string
    {
        $queue = $queueName ?: self::resolveQueueName();
        $cfg = config('rabbitmq');
        $enableDlq = is_array($cfg)
            ? filter_var($cfg['flash_sale_order_enable_dlq'] ?? false, FILTER_VALIDATE_BOOLEAN)
            : false;
        if (!$enableDlq) {
            $channel->queue_declare($queue, false, true, false, false);
            return $queue;
        }

        $dlqQueue = self::resolveDlqQueueName($queue);
        $channel->queue_declare($dlqQueue, false, true, false, false);
        $args = new AMQPTable([
            'x-dead-letter-exchange' => '',
            'x-dead-letter-routing-key' => $dlqQueue,
        ]);
        $channel->queue_declare($queue, false, true, false, false, false, $args);
        return $queue;
    }

    /**
     * @return array<int, string>
     */
    /**
     * 解析消费端应监听的队列列表
     *
     * @return array<int, string>
     */
    public static function resolveConsumeQueues(): array
    {
        $cfg = config('rabbitmq');
        if (!is_array($cfg)) {
            throw new \RuntimeException('rabbitmq config missing');
        }
        $baseQueue = (string)($cfg['flash_sale_order_create_queue'] ?? 'djxs.flash_sale.order_create');
        $shards = max(1, (int)($cfg['flash_sale_order_create_queue_shards'] ?? 1));
        if ($shards <= 1) {
            return [$baseQueue];
        }
        $queues = [];
        for ($i = 0; $i < $shards; $i++) {
            $queues[] = $baseQueue . '.s' . $i;
        }
        return $queues;
    }

    /**
     * 根据分片规则解析发布目标队列
     */
    public static function resolveQueueName(array $payload = []): string
    {
        $cfg = config('rabbitmq');
        if (!is_array($cfg)) {
            throw new \RuntimeException('rabbitmq config missing');
        }
        $baseQueue = (string)($cfg['flash_sale_order_create_queue'] ?? 'djxs.flash_sale.order_create');
        $shards = max(1, (int)($cfg['flash_sale_order_create_queue_shards'] ?? 1));
        if ($shards <= 1) {
            return $baseQueue;
        }
        $activityId = max(0, (int)($payload['activity_id'] ?? 0));
        $shard = $activityId % $shards;
        return $baseQueue . '.s' . $shard;
    }

    /**
     * 解析秒杀下单队列对应 DLQ 名称
     */
    public static function resolveDlqQueueName(string $queueName): string
    {
        $queueName = trim($queueName);
        if ($queueName === '') {
            $queueName = self::resolveQueueName();
        }
        $cfg = config('rabbitmq');
        $suffix = is_array($cfg) ? trim((string)($cfg['flash_sale_order_create_dlq_suffix'] ?? '.dlq')) : '.dlq';
        if ($suffix === '') {
            $suffix = '.dlq';
        }
        return $queueName . $suffix;
    }

    /**
     * 发布秒杀下单消息
     */
    public static function publish(array $payload): bool
    {
        $cfg = config('rabbitmq');
        if (!is_array($cfg)) {
            return false;
        }
        $host = (string)($cfg['host'] ?? '127.0.0.1');
        $port = (int)($cfg['port'] ?? 5672);
        $user = (string)($cfg['user'] ?? 'guest');
        $password = (string)($cfg['password'] ?? 'guest');
        $vhost = (string)($cfg['vhost'] ?? '/');

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
            $queue = self::resolveQueueName($payload);
            self::declareQueue($channel, $queue);
            $channel->confirm_select();
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $message = new AMQPMessage($body, [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            ]);
            $channel->basic_publish($message, '', $queue);
            $channel->wait_for_pending_acks(10.0);
            $channel->close();
            $connection->close();
            return true;
        } catch (\Throwable $e) {
            Log::warning('FlashSaleOrderQueueService publish failed', [
                'message' => $e->getMessage(),
                'payload' => $payload,
            ]);
            return false;
        }
    }
}

