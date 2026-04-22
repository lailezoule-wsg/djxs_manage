<?php
declare (strict_types = 1);

namespace app\command;

use app\api\service\FlashSaleService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/**
 * 清理已结束活动的秒杀库存缓存
 */
class FlashSaleCleanupCache extends Command
{
    /**
     * 注册命令名、描述与参数
     */
    protected function configure()
    {
        $this->setName('flash-sale:cleanup-cache')
            ->setDescription('清理已结束活动（超过宽限期）的秒杀库存缓存 key')
            ->addOption('grace-hours', 'g', Option::VALUE_OPTIONAL, '活动结束后保留小时数', '24')
            ->addOption('limit', 'l', Option::VALUE_OPTIONAL, '单次最多扫描活动数', '200')
            ->addOption('dry-run', null, Option::VALUE_NONE, '仅统计不删除');
    }

    /**
     * 执行秒杀缓存清理任务
     */
    protected function execute(Input $input, Output $output)
    {
        $graceHours = max(1, min(720, (int)$input->getOption('grace-hours')));
        $limit = max(1, min(5000, (int)$input->getOption('limit')));
        $dryRun = (bool)$input->getOption('dry-run');

        $service = new FlashSaleService();
        $result = $service->cleanupExpiredStockCache($graceHours, $limit, $dryRun);
        $output->writeln(sprintf(
            '[flash-sale:cleanup-cache] dry_run=%d grace_hours=%d checked_activities=%d checked_items=%d matched_stock_keys=%d deleted_stock_keys=%d cutoff_time=%s executed_at=%s',
            (int)($result['dry_run'] ?? 0),
            (int)($result['grace_hours'] ?? $graceHours),
            (int)($result['checked_activities'] ?? 0),
            (int)($result['checked_items'] ?? 0),
            (int)($result['matched_stock_keys'] ?? 0),
            (int)($result['deleted_stock_keys'] ?? 0),
            (string)($result['cutoff_time'] ?? ''),
            (string)($result['executed_at'] ?? date('Y-m-d H:i:s'))
        ));
        return 0;
    }
}
