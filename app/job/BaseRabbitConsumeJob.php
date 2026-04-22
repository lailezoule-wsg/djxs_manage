<?php
declare(strict_types=1);

namespace app\job;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use think\console\Output;

/**
 * RabbitMQ 消费任务基类
 */
abstract class BaseRabbitConsumeJob
{
    /**
     * 建立连接并启动消费循环
     */
    public function run(Output $output): int
    {
        $cfg = config('rabbitmq');
        if (!is_array($cfg)) {
            $output->writeln('<error>rabbitmq config missing</error>');
            return 1;
        }

        $host = (string)($cfg['host'] ?? '127.0.0.1');
        $port = (int)($cfg['port'] ?? 5672);
        $user = (string)($cfg['user'] ?? 'guest');
        $password = (string)($cfg['password'] ?? 'guest');
        $vhost = (string)($cfg['vhost'] ?? '/');

        $output->writeln(sprintf('<info>Connecting %s:%d</info>', $host, $port));
        $connection = new AMQPStreamConnection(
            $host,
            $port,
            $user,
            $password,
            $vhost,
            false,
            'AMQPLAIN',
            null,
            'en_US',
            10.0,
            130.0,
            null,
            false,
            60
        );
        $channel = $connection->channel();
        $this->setupConsumers($channel, $cfg, $output);

        while ($channel->is_consuming()) {
            $channel->wait();
        }
        return 0;
    }

    /**
     * 子类注册具体消费者逻辑
     */
    abstract protected function setupConsumers(AMQPChannel $channel, array $cfg, Output $output): void;
}
