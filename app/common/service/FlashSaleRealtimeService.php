<?php
declare (strict_types = 1);

namespace app\common\service;

use think\facade\Cache;

class FlashSaleRealtimeService
{
    private const VERSION_CACHE_KEY = 'flash:sale:realtime:version';
    private const EVENT_CACHE_KEY = 'flash:sale:realtime:last_event';
    private const EVENT_TTL_SECONDS = 86400;

    public function publish(string $event, array $payload = []): array
    {
        $version = (int)(microtime(true) * 1000);
        $data = [
            'version' => $version,
            'event' => trim($event) !== '' ? trim($event) : 'flash_sale_updated',
            'payload' => $payload,
            'server_time' => date('Y-m-d H:i:s'),
            'timestamp' => time(),
        ];
        Cache::set(self::VERSION_CACHE_KEY, $version, self::EVENT_TTL_SECONDS);
        Cache::set(self::EVENT_CACHE_KEY, $data, self::EVENT_TTL_SECONDS);
        return $data;
    }

    public function latest(): array
    {
        $event = Cache::get(self::EVENT_CACHE_KEY);
        if (is_array($event) && isset($event['version'])) {
            return $event;
        }
        $version = (int)Cache::get(self::VERSION_CACHE_KEY, 0);
        return [
            'version' => $version,
            'event' => 'flash_sale_snapshot',
            'payload' => [],
            'server_time' => date('Y-m-d H:i:s'),
            'timestamp' => time(),
        ];
    }
}

