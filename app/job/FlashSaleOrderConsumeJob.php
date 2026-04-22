<?php
declare(strict_types=1);

namespace app\job;

use app\api\service\FlashSaleOrderQueueService;
use app\api\service\FlashSaleService;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use think\console\Output;
use think\facade\Cache;
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
        $retryLimit = max(1, min(10, (int)($cfg['flash_sale_order_consume_retry_limit'] ?? 5)));
        $retryTtl = max(60, min(3600, (int)($cfg['flash_sale_order_consume_retry_ttl'] ?? 600)));
        $output->writeln(sprintf(
            '<info>queues=%s durable + prefetch=%d + retry_limit=%d</info>',
            implode(',', $consumeQueues),
            $prefetch,
            $retryLimit
        ));
        $channel->basic_qos(null, $prefetch, null);

        $service = new FlashSaleService();
        $callback = function (AMQPMessage $msg) use ($service, $output, $retryLimit, $retryTtl): void {
            $body = (string)$msg->getBody();
            try {
                $payload = json_decode($body, true);
                if (!is_array($payload)) {
                    $this->clearConsumeRetryCounter($body);
                    $msg->ack();
                    return;
                }
                $service->consumeCreateOrderMessage($payload);
                $this->clearConsumeRetryCounter($body);
                $msg->ack();
            } catch (\Throwable $e) {
                Log::error('flash-sale:order-consume message failed', [
                    'body' => $body,
                    'message' => $e->getMessage(),
                ]);
                $output->writeln('<error>' . $e->getMessage() . '</error>');
                $retryable = $this->isRetryableConsumeException($e);
                $attempt = $this->markConsumeRetryAttempt($body, $retryTtl);
                if ($retryable && $attempt < $retryLimit) {
                    $output->writeln(sprintf('<comment>retrying message attempt=%d/%d</comment>', $attempt, $retryLimit));
                    $msg->nack(false, true);
                    return;
                }
                $this->clearConsumeRetryCounter($body);
                if ($retryable) {
                    // 超出重试上限：拒绝并不重回队列，交由 DLQ（如已配置）或丢弃
                    $msg->reject(false);
                    return;
                }
                // 非可重试错误直接确认，避免毒消息堵塞消费
                if (method_exists($msg, 'ack')) {
                    $msg->ack();
                }
            }
        };

        foreach ($consumeQueues as $queue) {
            $channel->basic_consume($queue, '', false, false, false, false, $callback);
        }
    }

    private function buildConsumeRetryKey(string $body): string
    {
        return 'flash:sale:consume:retry:' . sha1($body);
    }

    private function markConsumeRetryAttempt(string $body, int $ttl): int
    {
        $key = $this->buildConsumeRetryKey($body);
        try {
            $attempt = (int)Cache::inc($key, 1);
            if ($attempt <= 1) {
                Cache::expire($key, $ttl);
            }
            return max(1, $attempt);
        } catch (\Throwable $e) {
        }
        return 1;
    }

    private function clearConsumeRetryCounter(string $body): void
    {
        try {
            Cache::delete($this->buildConsumeRetryKey($body));
        } catch (\Throwable $e) {
        }
    }

    private function isRetryableConsumeException(\Throwable $e): bool
    {
        if ($e instanceof \PDOException) {
            $errorInfo = $e->errorInfo;
            if (is_array($errorInfo)) {
                $sqlState = (string)($errorInfo[0] ?? '');
                $driverCode = (int)($errorInfo[1] ?? 0);
                if ($sqlState === '40001' || $driverCode === 1213 || $driverCode === 1205) {
                    return true;
                }
            }
        }
        $message = strtolower($e->getMessage());
        return str_contains($message, 'deadlock')
            || str_contains($message, 'lock wait timeout')
            || str_contains($message, 'sqlstate[40001]')
            || str_contains($message, 'server has gone away')
            || str_contains($message, 'connection refused')
            || str_contains($message, 'connection reset')
            || str_contains($message, 'broken pipe')
            || str_contains($message, 'timed out')
            || str_contains($message, 'temporarily unavailable');
    }
}
