<?php

declare(strict_types=1);

namespace app\command;

use app\job\FlashSaleOrderConsumeJob;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class FlashSaleOrderConsume extends Command
{
    protected function configure(): void
    {
        $this->setName('flash-sale:order-consume')
            ->setDescription('Consume RabbitMQ queue and create flash-sale orders asynchronously');
    }

    protected function execute(Input $input, Output $output): int
    {
        $job = new FlashSaleOrderConsumeJob();
        $code = $job->run($output);
        return $code === 0 ? self::SUCCESS : self::FAILURE;
    }
}

