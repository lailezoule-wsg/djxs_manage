<?php
declare (strict_types = 1);

namespace app\command;

use app\admin\service\FlashSaleAdminService;
use think\console\Command;
use think\console\Input;
use think\console\Output;

/**
 * 清理秒杀风控历史日志
 */
class FlashSaleCleanupRisk extends Command
{
    protected function configure()
    {
        $this->setName('flash-sale:cleanup-risk')
            ->setDescription('清理超出保留期的秒杀风控日志');
    }

    protected function execute(Input $input, Output $output)
    {
        $service = new FlashSaleAdminService();
        $deleted = $service->cleanupRiskLogs();
        $output->writeln(sprintf(
            '[flash-sale:cleanup-risk] deleted=%d executed_at=%s',
            $deleted,
            date('Y-m-d H:i:s')
        ));
        return 0;
    }
}
