<?php
declare (strict_types = 1);

namespace app\command;

use app\api\service\FlashSaleService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/**
 * 秒杀库存与订单状态对账修复
 */
class FlashSaleReconcile extends Command
{
    protected function configure()
    {
        $this->setName('flash-sale:reconcile')
            ->setDescription('秒杀库存与订单状态对账修复')
            ->addOption('limit', 'l', Option::VALUE_OPTIONAL, '单次处理商品数', '200');
    }

    protected function execute(Input $input, Output $output)
    {
        $limit = max(1, min(2000, (int)$input->getOption('limit')));
        $service = new FlashSaleService();
        $result = $service->reconcile($limit);
        $output->writeln(sprintf(
            '[flash-sale:reconcile] released_timeout=%d checked_items=%d fixed_items=%d fixed_redis=%d executed_at=%s',
            (int)($result['released_timeout'] ?? 0),
            (int)($result['checked_items'] ?? 0),
            (int)($result['fixed_items'] ?? 0),
            (int)($result['fixed_redis'] ?? 0),
            (string)($result['executed_at'] ?? date('Y-m-d H:i:s'))
        ));
        return 0;
    }
}
