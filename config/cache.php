<?php

// +----------------------------------------------------------------------
// | 缓存设置
// +----------------------------------------------------------------------
// 默认使用 Redis（Docker 中服务名为 redis）。无 Redis 时可设 CACHE_DRIVER=file

return [
    'default' => env('CACHE_DRIVER', 'redis'),

    'stores'  => [
        'file' => [
            'type'       => 'File',
            'path'       => '',
            'prefix'     => '',
            'expire'     => 0,
            'tag_prefix' => 'tag:',
            'serialize'  => [],
        ],
        'redis' => [
            'type'       => 'redis',
            'host'       => env('REDIS_HOST', '127.0.0.1'),
            'port'       => (int) env('REDIS_PORT', 6379),
            'password'   => env('REDIS_PASSWORD', ''),
            'select'     => (int) env('REDIS_DB', 0),
            'timeout'    => (float) env('REDIS_TIMEOUT', 0),
            'expire'     => 0,
            'persistent' => (bool) env('REDIS_PERSISTENT', false),
            'prefix'     => env('REDIS_PREFIX', 'djxs_manage:'),
            'tag_prefix' => 'tag:',
            'serialize'  => [],
            // 连接池配置 - 高并发场景下提高性能
            'pool'       => [
                'min'          => (int) env('REDIS_POOL_MIN', 10),      // 最小连接数
                'max'          => (int) env('REDIS_POOL_MAX', 100),     // 最大连接数
                'wait_timeout' => (float) env('REDIS_POOL_WAIT_TIMEOUT', 3.0), // 等待超时时间(秒)
                'expire'       => (int) env('REDIS_POOL_EXPIRE', 3600), // 连接过期时间(秒)
            ],
        ],
    ],
];
