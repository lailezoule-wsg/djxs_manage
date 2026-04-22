<?php
declare(strict_types=1);

namespace app\job;

use app\api\service\FlashSaleOrderQueueService;
use app\api\service\FlashSaleService;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use think\console\Output;
use think\facade\Log;

/**
 * 秒杀下单消息消费任务
 */
class FlashSaleOrderConsumeJob extends BaseRabbitConsumeJob
{
    /**
     * 声明队列并注册消费回调
     */
    protected function setupConsumers(AMQPChannel $channel, array $cfg, Output $output): void
    {
        $consumeQueues = FlashSaleOrderQueueService::resolveConsumeQueues();
        foreach ($consumeQueues as $queue) {
            FlashSaleOrderQueueService::declareQueue($channel, $queue);
        }
        $prefetch = max(1, (int)($cfg['flash_sale_order_consume_prefetch'] ?? 20));
        $output->writeln(sprintf(
            '<info>queues=%s durable + prefetch=%d</info>',
            implode(',', $consumeQueues),
            $prefetch
        ));
        $channel->basic_qos(null, $prefetch, null);

        $service = new FlashSaleService();
        $callback = static function (AMQPMessage $msg) use ($service, $output): void {
            try {
                $payload = json_decode($msg->getBody(), true);
                if (!is_array($payload)) {
                    $msg->ack();
                    return;
                }
                $service->consumeCreateOrderMessage($payload);
                $msg->ack();
            } catch (\Throwable $e) {
                Log::error('flash-sale:order-consume message failed', [
                    'body' => $msg->getBody(),
                    'message' => $e->getMessage(),
                ]);
                $output->writeln('<error>' . $e->getMessage() . '</error>');
                // 毒消息直接确认，避免无限重投阻塞队列
                $msg->ack();
            }
        };

        foreach ($consumeQueues as $queue) {
            $channel->basic_consume($queue, '', false, false, false, false, $callback);
        }
    }
}
