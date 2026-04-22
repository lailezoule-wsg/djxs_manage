<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        \app\command\CancelTimeoutOrders::class,
        \app\command\OrderContractCheck::class,
        \app\command\FlashSaleCleanupRisk::class,
        \app\command\FlashSaleCleanupCache::class,
        \app\command\FlashSaleAuditItems::class,
        \app\command\FlashSaleReleaseReserve::class,
        \app\command\FlashSaleReconcile::class,
        \app\command\ContentRebuildStats::class,
        \app\command\ContentStatConsume::class,
        \app\command\FlashSaleOrderConsume::class,
        \app\command\ChannelDistributionConsume::class,
    ],
];
