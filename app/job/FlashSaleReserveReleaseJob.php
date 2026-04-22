<?php
declare(strict_types=1);

namespace app\job;

use app\api\service\FlashSaleService;

/**
 * 秒杀超时预留库存释放任务
 */
class FlashSaleReserveReleaseJob
{
    /**
     * 执行一次超时预留库存释放
     */
    public function run(int $limit): int
    {
        $limit = max(1, min(500, $limit));
        $service = new FlashSaleService();
        return $service->releaseDueReserveOrders($limit);
    }
}

