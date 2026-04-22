<?php
declare(strict_types=1);

namespace app\command;

use app\job\ChannelDistributionConsumeJob;
use think\console\Command;
use think\console\Input;
use think\console\Output;

/**
 * 渠道分发队列消费命令
 */
class ChannelDistributionConsume extends Command
{
    /**
     * 注册命令名与描述
     */
    protected function configure(): void
    {
        $this->setName('channel-distribution:consume')
            ->setDescription('Consume RabbitMQ queue and publish content to channels');
    }

    /**
     * 启动渠道分发消费任务
     */
    protected function execute(Input $input, Output $output): int
    {
        $job = new ChannelDistributionConsumeJob();
        $code = $job->run($output);
        return $code === 0 ? self::SUCCESS : self::FAILURE;
    }
}
