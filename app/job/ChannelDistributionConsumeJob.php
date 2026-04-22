<?php
declare(strict_types=1);

namespace app\job;

use app\admin\service\ChannelDistributionAdminService;
use app\admin\service\ChannelDistributionQueueService;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use think\console\Output;
use think\facade\Log;

/**
 * 渠道分发消息消费任务
 */
class ChannelDistributionConsumeJob extends BaseRabbitConsumeJob
{
    /**
     * 声明队列并注册消费回调
     */
    protected function setupConsumers(AMQPChannel $channel, array $cfg, Output $output): void
    {
        $prefetch = max(1, (int)($cfg['channel_distribution_consume_prefetch'] ?? 10));
        $queue = ChannelDistributionQueueService::declareQueue($channel);
        $output->writeln(sprintf('<info>queue=%s durable + prefetch=%d</info>', $queue, $prefetch));
        $channel->basic_qos(null, $prefetch, null);

        $service = new ChannelDistributionAdminService();
        $callback = static function (AMQPMessage $msg) use ($service, $output): void {
            try {
                $payload = json_decode($msg->getBody(), true);
                if (!is_array($payload)) {
                    $msg->ack();
                    return;
                }
                $service->consumePublishMessage($payload);
                $msg->ack();
            } catch (\Throwable $e) {
                Log::error('channel-distribution:consume message failed', [
                    'body' => $msg->getBody(),
                    'message' => $e->getMessage(),
                ]);
                $output->writeln('<error>' . $e->getMessage() . '</error>');
                $msg->nack(false, false);
            }
        };

        $channel->basic_consume($queue, '', false, false, false, false, $callback);
    }
}
