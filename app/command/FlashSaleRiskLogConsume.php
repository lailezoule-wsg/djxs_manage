<?php

declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;

/**
 * 秒杀风控日志异步消费命令（Redis List -> MySQL）。
 */
class FlashSaleRiskLogConsume extends Command
{
    /**
     * 风控日志异步队列键。
     */
    private const RISK_LOG_QUEUE_KEY = 'flash:sale:risk:log:queue';

    /**
     * 注册命令名与描述。
     */
    protected function configure(): void
    {
        $this->setName('flash-sale:risk-log-consume')
            ->setDescription('Consume async flash-sale risk logs from Redis queue');
    }

    /**
     * 启动异步风控日志消费循环。
     */
    protected function execute(Input $input, Output $output): int
    {
        $batchSize = max(1, min(500, (int)env('FLASH_SALE_RISK_LOG_CONSUME_BATCH_SIZE', 100)));
        $idleSleepMs = max(20, min(2000, (int)env('FLASH_SALE_RISK_LOG_CONSUME_IDLE_SLEEP_MS', 200)));
        $output->writeln(sprintf(
            '[flash-sale:risk-log-consume] queue=%s batch_size=%d idle_sleep_ms=%d',
            self::RISK_LOG_QUEUE_KEY,
            $batchSize,
            $idleSleepMs
        ));

        while (true) {
            $redis = $this->getRedisHandler();
            if (!$redis) {
                $output->writeln('[flash-sale:risk-log-consume] redis unavailable, retrying...');
                usleep($idleSleepMs * 1000);
                continue;
            }
            $batchRows = $this->dequeueBatchRows($redis, $batchSize);
            if ($batchRows === []) {
                usleep($idleSleepMs * 1000);
                continue;
            }
            try {
                Db::name('flash_sale_risk_log')->insertAll($batchRows);
            } catch (\Throwable $e) {
                Log::warning('flash-sale risk-log consume insert batch failed', [
                    'size' => count($batchRows),
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * 从 Redis 批量拉取风控日志行。
     *
     * @param mixed $redis
     * @return array<int, array<string, mixed>>
     */
    private function dequeueBatchRows($redis, int $batchSize): array
    {
        $rows = [];
        $firstPayload = $this->dequeuePayload($redis, true);
        if ($firstPayload !== '') {
            $firstRow = $this->decodeRiskRow($firstPayload);
            if ($firstRow !== []) {
                $rows[] = $firstRow;
            }
        }
        while (count($rows) < $batchSize) {
            $payload = $this->dequeuePayload($redis, false);
            if ($payload === '') {
                break;
            }
            $row = $this->decodeRiskRow($payload);
            if ($row === []) {
                continue;
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * 拉取单条队列消息，支持阻塞/非阻塞两种模式。
     *
     * @param mixed $redis
     */
    private function dequeuePayload($redis, bool $blocking): string
    {
        try {
            if ($blocking && method_exists($redis, 'brPop')) {
                $result = $redis->brPop([self::RISK_LOG_QUEUE_KEY], 1);
                if (is_array($result) && isset($result[1]) && is_string($result[1])) {
                    return $result[1];
                }
                return '';
            }
            if (method_exists($redis, 'rPop')) {
                $raw = $redis->rPop(self::RISK_LOG_QUEUE_KEY);
                return is_string($raw) ? $raw : '';
            }
        } catch (\Throwable $e) {
        }
        return '';
    }

    /**
     * 解码并兜底清洗队列消息。
     *
     * @return array<string, mixed>
     */
    private function decodeRiskRow(string $payload): array
    {
        $payload = trim($payload);
        if ($payload === '') {
            return [];
        }
        try {
            $row = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return [];
        }
        if (!is_array($row)) {
            return [];
        }
        return [
            'scene' => substr((string)($row['scene'] ?? 'create_order'), 0, 32),
            'reason' => substr((string)($row['reason'] ?? 'risk_blocked'), 0, 64),
            'user_id' => (int)($row['user_id'] ?? 0),
            'activity_id' => (int)($row['activity_id'] ?? 0),
            'item_id' => (int)($row['item_id'] ?? 0),
            'client_ip' => substr((string)($row['client_ip'] ?? ''), 0, 64),
            'device_id' => substr((string)($row['device_id'] ?? ''), 0, 128),
            'extra_json' => (string)($row['extra_json'] ?? '{}'),
            'create_time' => (string)($row['create_time'] ?? date('Y-m-d H:i:s')),
        ];
    }

    /**
     * 获取 Redis 底层句柄。
     *
     * @return mixed|null
     */
    private function getRedisHandler()
    {
        try {
            $cacheStore = Cache::store('redis');
            if (method_exists($cacheStore, 'handler')) {
                return $cacheStore->handler();
            }
        } catch (\Throwable $e) {
        }
        return null;
    }
}

