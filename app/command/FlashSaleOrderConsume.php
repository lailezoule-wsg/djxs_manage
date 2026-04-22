<?php

declare(strict_types=1);

namespace app\command;

use app\job\FlashSaleOrderConsumeJob;
use think\console\Command;
use think\console\Input;
use think\console\Output;

/**
 * 秒杀下单队列消费命令
 */
class FlashSaleOrderConsume extends Command
{
    /**
     * 注册命令名与描述
     */
    protected function configure(): void
    {
        $this->setName('flash-sale:order-consume')
            ->setDescription('Consume RabbitMQ queue and create flash-sale orders asynchronously');
    }

    /**
     * 启动秒杀下单消费任务
     */
    protected function execute(Input $input, Output $output): int
    {
        $job = new FlashSaleOrderConsumeJob();
        $code = $job->run($output);
        return $code === 0 ? self::SUCCESS : self::FAILURE;
    }
}

