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
        ],
    ],
];
