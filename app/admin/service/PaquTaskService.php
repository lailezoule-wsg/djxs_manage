<?php
declare (strict_types = 1);

namespace app\admin\service;

use app\admin\model\PaquTask;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 爬虫任务服务
 */
class PaquTaskService extends BaseAdminService
{
    public function list(array $params, int $page, int $pageSize): array
    {
        $query = PaquTask::where('status', '<>', PaquTask::STATUS_DELETED);
        
        if (!empty($params['name'])) {
            $query->whereLike('name', '%' . $params['name'] . '%');
        }
        if (isset($params['type']) && in_array($params['type'], [PaquTask::TYPE_NOVEL, PaquTask::TYPE_DRAMA])) {
            $query->where('type', (int)$params['type']);
        }
        if (isset($params['status']) && in_array($params['status'], [PaquTask::STATUS_PENDING, PaquTask::STATUS_RUNNING, PaquTask::STATUS_COMPLETED, PaquTask::STATUS_PAUSED, PaquTask::STATUS_CANCELLED])) {
            $query->where('status', (int)$params['status']);
        }
        if (isset($params['source_id']) && (int)$params['source_id'] > 0) {
            $query->where('source_id', (int)$params['source_id']);
        }
        
        return $this->paginateToArray($query, $page, $pageSize);
    }

    public function create(array $payload): int
    {
        $this->validatePayload($payload);
        
        $data = [
            'name' => $payload['name'],
            'source_id' => (int)$payload['source_id'],
            'type' => (int)$payload['type'],
            'status' => PaquTask::STATUS_PENDING,
            'total_count' => 0,
            'success_count' => 0,
            'failed_count' => 0,
            'config' => isset($payload['config']) ? json_encode($payload['config'], JSON_UNESCAPED_UNICODE) : '{}',
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
        ];
        
        return (int)Db::name('paqu_task')->insertGetId($data);
    }

    public function update(int $id, array $payload): void
    {
        $this->assertExists('paqu_task', $id);
        
        $task = PaquTask::find($id);
        if ($task->status === PaquTask::STATUS_RUNNING) {
            throw new ValidateException('运行中的任务不允许修改');
        }
        
        $data = [];
        if (isset($payload['name'])) {
            $data['name'] = $payload['name'];
        }
        if (isset($payload['source_id']) && (int)$payload['source_id'] > 0) {
            $data['source_id'] = (int)$payload['source_id'];
        }
        if (isset($payload['type']) && in_array($payload['type'], [PaquTask::TYPE_NOVEL, PaquTask::TYPE_DRAMA])) {
            $data['type'] = (int)$payload['type'];
        }
        if (isset($payload['config'])) {
            $data['config'] = json_encode($payload['config'], JSON_UNESCAPED_UNICODE);
        }
        $data['update_time'] = date('Y-m-d H:i:s');
        
        Db::name('paqu_task')->where('id', $id)->update($data);
    }

    public function delete(int $id): void
    {
        $this->assertExists('paqu_task', $id);
        
        $task = PaquTask::find($id);
        if ($task->status === PaquTask::STATUS_RUNNING) {
            throw new ValidateException('运行中的任务不允许删除');
        }
        
        Db::name('paqu_task')->where('id', $id)->update([
            'status' => PaquTask::STATUS_DELETED,
            'update_time' => date('Y-m-d H:i:s'),
        ]);
    }

    public function start(int $id): void
    {
        $task = PaquTask::find($id);
        if (!$task) {
            throw new ValidateException('任务不存在');
        }
        if (!in_array($task->status, [PaquTask::STATUS_PENDING, PaquTask::STATUS_PAUSED])) {
            throw new ValidateException('任务状态不允许启动');
        }
        
        $task->status = PaquTask::STATUS_RUNNING;
        $task->start_time = date('Y-m-d H:i:s');
        $task->update_time = date('Y-m-d H:i:s');
        $task->save();
        
        $this->dispatchSpiderTask($id);
    }

    public function stop(int $id): void
    {
        $task = PaquTask::find($id);
        if (!$task) {
            throw new ValidateException('任务不存在');
        }
        if ($task->status !== PaquTask::STATUS_RUNNING) {
            throw new ValidateException('任务未在运行中');
        }
        
        $this->stopSpiderTask($id);
        
        $task->status = PaquTask::STATUS_PAUSED;
        $task->update_time = date('Y-m-d H:i:s');
        $task->save();
    }

    public function complete(int $id): void
    {
        $task = PaquTask::find($id);
        if (!$task) {
            throw new ValidateException('任务不存在');
        }
        
        $task->status = PaquTask::STATUS_COMPLETED;
        $task->end_time = date('Y-m-d H:i:s');
        $task->update_time = date('Y-m-d H:i:s');
        $task->save();
    }

    public function cancel(int $id): void
    {
        $task = PaquTask::find($id);
        if (!$task) {
            throw new ValidateException('任务不存在');
        }
        if ($task->status === PaquTask::STATUS_COMPLETED) {
            throw new ValidateException('已完成的任务不允许取消');
        }
        
        if ($task->status === PaquTask::STATUS_RUNNING) {
            $this->stopSpiderTask($id);
        }
        
        $task->status = PaquTask::STATUS_CANCELLED;
        $task->end_time = date('Y-m-d H:i:s');
        $task->update_time = date('Y-m-d H:i:s');
        $task->save();
    }

    public function detail(int $id): array
    {
        $task = PaquTask::with('source')->find($id);
        if (!$task) {
            throw new ValidateException('任务不存在');
        }
        return $task->toArray();
    }

    private function validatePayload(array $payload): void
    {
        $name = trim((string)($payload['name'] ?? ''));
        if ($name === '') {
            throw new ValidateException('任务名称不能为空');
        }
        
        $sourceId = (int)($payload['source_id'] ?? 0);
        if ($sourceId <= 0) {
            throw new ValidateException('请选择数据源');
        }
        
        $type = (int)($payload['type'] ?? 0);
        if (!in_array($type, [PaquTask::TYPE_NOVEL, PaquTask::TYPE_DRAMA])) {
            throw new ValidateException('请选择正确的类型');
        }
    }

    private function dispatchSpiderTask(int $taskId): void
    {
        $task = PaquTask::find($taskId);
        if (!$task) {
            return;
        }
        
        $spiderType = $task->type === PaquTask::TYPE_NOVEL ? 'novel' : 'drama';
        $taskName = $spiderType === 'novel' ? 'crawler.tasks.start_novel_spider' : 'crawler.tasks.start_drama_spider';
        
        $body = json_encode([
            'task' => $taskName,
            'id' => uniqid('celery-'),
            'args' => [(string)$taskId],
            'kwargs' => [],
            'retries' => 0,
            'eta' => null,
            'expires' => null,
            'queue' => 'celery',
            'exchange' => '',
            'exchange_type' => 'direct',
            'routing_key' => 'celery',
            'priority' => 0,
            'delivery_mode' => 2,
            'delivery_info' => [],
            'hostname' => null,
            'timestamp' => time(),
            'acknowledged' => false,
            'properties' => [],
            'headers' => [],
        ], JSON_UNESCAPED_UNICODE);
        
        $this->publishToQueue($body, 'celery');
    }
    
    private function publishToQueue(string $body, string $queue): void
    {
        $cfg = config('rabbitmq');
        if (!is_array($cfg)) {
            return;
        }
        $host = (string)($cfg['host'] ?? '127.0.0.1');
        $port = (int)($cfg['port'] ?? 5672);
        $user = (string)($cfg['user'] ?? 'guest');
        $password = (string)($cfg['password'] ?? 'guest');
        $vhost = (string)($cfg['vhost'] ?? '/');
        
        try {
            $connection = new \PhpAmqpLib\Connection\AMQPStreamConnection(
                $host,
                $port,
                $user,
                $password,
                $vhost
            );
            $channel = $connection->channel();
            $channel->queue_declare($queue, false, true, false, false);
            $message = new \PhpAmqpLib\Message\AMQPMessage($body, [
                'content_type' => 'application/json',
                'delivery_mode' => \PhpAmqpLib\Message\AMQPMessage::DELIVERY_MODE_PERSISTENT,
            ]);
            $channel->basic_publish($message, '', $queue);
            $channel->close();
            $connection->close();
        } catch (\Throwable $e) {
            \think\facade\Log::warning('publishToQueue failed', ['message' => $e->getMessage(), 'queue' => $queue]);
        }
    }

    private function stopSpiderTask(int $taskId): void
    {
    }
}