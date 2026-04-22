<?php

declare(strict_types=1);

return [
    'host'               => env('RABBITMQ_HOST', '127.0.0.1'),
    'port'               => (int) env('RABBITMQ_PORT', 5672),
    'user'               => env('RABBITMQ_USER', 'guest'),
    'password'           => env('RABBITMQ_PASSWORD', 'guest'),
    'vhost'              => env('RABBITMQ_VHOST', '/'),
    'content_stat_queue' => env('RABBITMQ_QUEUE_CONTENT_STAT', 'djxs.content_stat'),
    'flash_sale_order_create_queue' => env('RABBITMQ_QUEUE_FLASH_SALE_ORDER_CREATE', 'djxs.flash_sale.order_create'),
    // 秒杀下单队列分片数（>1 时按 activity_id 分片，消费者订阅全部分片队列）。
    'flash_sale_order_create_queue_shards' => max(1, (int) env('RABBITMQ_QUEUE_FLASH_SALE_ORDER_CREATE_SHARDS', 1)),
    // 秒杀下单队列是否开启 DLQ（变更后可能需重建队列）。
    'flash_sale_order_enable_dlq' => filter_var(env('RABBITMQ_FLASH_SALE_ORDER_ENABLE_DLQ', true), FILTER_VALIDATE_BOOLEAN),
    // 秒杀下单 DLQ 队列后缀（最终队列名 = 原队列名 + 后缀）。
    'flash_sale_order_create_dlq_suffix' => env('RABBITMQ_QUEUE_FLASH_SALE_ORDER_CREATE_DLQ_SUFFIX', '.dlq'),
    // 秒杀订单消费者 prefetch（单消费者未 ack 消息上限）。
    'flash_sale_order_consume_prefetch' => max(1, (int) env('FLASH_SALE_ORDER_CONSUME_PREFETCH', 20)),
    // 秒杀订单消费者最大重试次数（超过后 reject 到 DLQ/丢弃）。
    'flash_sale_order_consume_retry_limit' => max(1, (int) env('FLASH_SALE_ORDER_CONSUME_RETRY_LIMIT', 5)),
    // 秒杀订单消费者重试计数缓存 TTL（秒）。
    'flash_sale_order_consume_retry_ttl' => max(60, (int) env('FLASH_SALE_ORDER_CONSUME_RETRY_TTL', 600)),
    // 多渠道分发队列（任务创建后异步分发）。
    'channel_distribution_queue' => env('RABBITMQ_QUEUE_CHANNEL_DISTRIBUTION', 'djxs.channel_distribution.publish'),
    'channel_distribution_dlq' => env('RABBITMQ_QUEUE_CHANNEL_DISTRIBUTION_DLQ', 'djxs.channel_distribution.publish.dlq'),
    // 多渠道分发消费者 prefetch。
    'channel_distribution_consume_prefetch' => max(1, (int) env('CHANNEL_DISTRIBUTION_CONSUME_PREFETCH', 10)),
    // 多渠道分发是否启用死信队列参数（切换后可能需重建队列）。
    'channel_distribution_enable_dlq' => filter_var(
        env('RABBITMQ_CHANNEL_DISTRIBUTION_ENABLE_DLQ', true),
        FILTER_VALIDATE_BOOLEAN
    ),
    /**
     * 单活跃消费者：多实例连接同一队列时仅一个实例投递消息，有利于顺序处理。
     * 启用后队列参数会变化，需在 RabbitMQ 中删除旧队列再启动（或建新队列名）。
     */
    'content_stat_queue_single_active_consumer' => filter_var(
        env('RABBITMQ_CONTENT_STAT_SINGLE_ACTIVE_CONSUMER', false),
        FILTER_VALIDATE_BOOLEAN
    ),
];
