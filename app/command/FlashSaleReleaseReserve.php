<?php
declare (strict_types = 1);

namespace app\command;

use app\job\FlashSaleReserveReleaseJob;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/**
 * 释放秒杀超时占用库存
 */
class FlashSaleReleaseReserve extends Command
{
    /**
     * 注册命令名、描述与参数
     */
    protected function configure()
    {
        $this->setName('flash-sale:release-reserve')
            ->setDescription('释放秒杀超时未支付订单占用库存')
            ->addOption('limit', 'l', Option::VALUE_OPTIONAL, '单次处理条数', (string)env('FLASH_SALE_RELEASE_BATCH', '200'));
    }

    /**
     * 执行超时占用库存释放
     */
    protected function execute(Input $input, Output $output)
    {
        $limit = max(1, min(500, (int)$input->getOption('limit')));
        $job = new FlashSaleReserveReleaseJob();
        $released = $job->run($limit);
        $output->writeln(sprintf(
            '[flash-sale:release-reserve] limit=%d released=%d executed_at=%s',
            $limit,
            $released,
            date('Y-m-d H:i:s')
        ));
        return 0;
    }
}
