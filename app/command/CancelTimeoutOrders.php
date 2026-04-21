<?php
declare (strict_types = 1);

namespace app\command;

use app\api\service\OrderService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/**
 * 取消超时未支付订单
 */
class CancelTimeoutOrders extends Command
{
    protected function configure()
    {
        $this->setName('order:cancel-timeout')
            ->setDescription('取消超时未支付订单（含临界点缓冲）')
            ->addOption('timeout', 't', Option::VALUE_OPTIONAL, '超时分钟数', (string)env('ORDER_TIMEOUT_MINUTES', '30'))
            ->addOption('grace', 'g', Option::VALUE_OPTIONAL, '临界点缓冲秒数', (string)env('ORDER_TIMEOUT_GRACE_SECONDS', '120'));
    }

    protected function execute(Input $input, Output $output)
    {
        $timeout = max(1, (int)$input->getOption('timeout'));
        $grace = max(0, (int)$input->getOption('grace'));

        $service = new OrderService();
        $result = $service->cancelTimeoutOrders($timeout, $grace);

        $output->writeln(sprintf(
            '[order:cancel-timeout] timeout=%dmin grace=%ds canceled=%d threshold=%s',
            $result['timeout_minutes'],
            $result['grace_seconds'],
            $result['canceled_count'],
            $result['threshold_time']
        ));

        return 0;
    }
}

