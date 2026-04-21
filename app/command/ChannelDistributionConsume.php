<?php
declare(strict_types=1);

namespace app\command;

use app\job\ChannelDistributionConsumeJob;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class ChannelDistributionConsume extends Command
{
    protected function configure(): void
    {
        $this->setName('channel-distribution:consume')
            ->setDescription('Consume RabbitMQ queue and publish content to channels');
    }

    protected function execute(Input $input, Output $output): int
    {
        $job = new ChannelDistributionConsumeJob();
        $code = $job->run($output);
        return $code === 0 ? self::SUCCESS : self::FAILURE;
    }
}
