<?php
declare(strict_types=1);

namespace app\admin\service;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use think\facade\Log;

final class ChannelDistributionQueueService
{
    public static function declareQueue(AMQPChannel $channel): string
    {
        $cfg = config('rabbitmq');
        if (!is_array($cfg)) {
            throw new \RuntimeException('rabbitmq config missing');
        }
        $queue = (string)($cfg['channel_distribution_queue'] ?? 'djxs.channel_distribution.publish');
        $enableDlq = (bool)($cfg['channel_distribution_enable_dlq'] ?? true);
        if ($enableDlq) {
            $dlq = (string)($cfg['channel_distribution_dlq'] ?? ($queue . '.dlq'));
            $channel->queue_declare($dlq, false, true, false, false);
            $args = new AMQPTable([
                'x-dead-letter-exchange' => '',
                'x-dead-letter-routing-key' => $dlq,
            ]);
            $channel->queue_declare($queue, false, true, false, false, false, $args);
        } else {
            $channel->queue_declare($queue, false, true, false, false);
        }
        return $queue;
    }

    public static function publish(array $payload, int $delayMs = 0): bool
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
            $queue = self::declareQueue($channel);
            $channel->confirm_select();
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $message = new AMQPMessage($body, [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            ]);
            if ($delayMs > 0) {
                $delayQueue = self::declareDelayQueue($channel, $queue, $delayMs);
                $channel->basic_publish($message, '', $delayQueue);
            } else {
                $channel->basic_publish($message, '', $queue);
            }
            $channel->wait_for_pending_acks(10.0);
            $channel->close();
            $connection->close();
            return true;
        } catch (\Throwable $e) {
            Log::warning('ChannelDistributionQueueService publish failed', [
                'message' => $e->getMessage(),
                'payload' => $payload,
            ]);
            return false;
        }
    }

    private static function declareDelayQueue(AMQPChannel $channel, string $targetQueue, int $delayMs): string
    {
        $delayMs = max(1000, $delayMs);
        $queueName = $targetQueue . '.delay.' . $delayMs;
        $args = new AMQPTable([
            'x-message-ttl' => $delayMs,
            'x-dead-letter-exchange' => '',
            'x-dead-letter-routing-key' => $targetQueue,
        ]);
        $channel->queue_declare($queueName, false, true, false, false, false, $args);
        return $queueName;
    }
}
