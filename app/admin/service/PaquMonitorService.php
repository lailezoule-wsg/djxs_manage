<?php
declare (strict_types = 1);

namespace app\admin\service;

use app\admin\model\PaquLog;
use app\admin\model\PaquTask;
use think\facade\Db;

/**
 * 监控服务
 */
class PaquMonitorService extends BaseAdminService
{
    public function tasks(): array
    {
        $tasks = PaquTask::where('status', PaquTask::STATUS_RUNNING)->select();
        $result = [];
        foreach ($tasks as $task) {
            $progress = 0;
            if ($task['total_count'] > 0) {
                $progress = round(($task['success_count'] + $task['failed_count']) / $task['total_count'] * 100, 2);
            }
            
            $result[] = [
                'id' => $task['id'],
                'name' => $task['name'],
                'type' => $task['type'],
                'type_text' => $task['type_text'],
                'status' => $task['status'],
                'status_text' => $task['status_text'],
                'source_id' => $task['source_id'],
                'start_time' => $task['start_time'],
                'total_count' => $task['total_count'],
                'success_count' => $task['success_count'],
                'failed_count' => $task['failed_count'],
                'progress' => $progress,
            ];
        }
        return $result;
    }

    public function logs(int $taskId, int $page, int $pageSize): array
    {
        $query = PaquLog::where('task_id', $taskId)->order('create_time', 'desc');
        
        return $this->paginateToArray($query, $page, $pageSize);
    }

    public function progress(int $taskId): array
    {
        $task = PaquTask::find($taskId);
        if (!$task) {
            return [
                'task_id' => $taskId,
                'total_count' => 0,
                'success_count' => 0,
                'failed_count' => 0,
                'progress' => 0,
                'status' => 'unknown',
            ];
        }
        
        $progress = 0;
        if ($task['total_count'] > 0) {
            $progress = round(($task['success_count'] + $task['failed_count']) / $task['total_count'] * 100, 2);
        }
        
        return [
            'task_id' => $task['id'],
            'task_name' => $task['name'],
            'total_count' => $task['total_count'],
            'success_count' => $task['success_count'],
            'failed_count' => $task['failed_count'],
            'progress' => $progress,
            'status' => $task['status'],
            'status_text' => $task['status_text'],
            'start_time' => $task['start_time'],
            'end_time' => $task['end_time'],
        ];
    }

    public function errors(array $params = []): array
    {
        $query = PaquLog::where('level', PaquLog::LEVEL_ERROR)->order('create_time', 'desc');
        
        if (isset($params['task_id']) && (int)$params['task_id'] > 0) {
            $query->where('task_id', (int)$params['task_id']);
        }
        
        $page = (int)($params['page'] ?? 1);
        $pageSize = (int)($params['page_size'] ?? 20);
        
        return $this->paginateToArray($query, $page, $pageSize);
    }

    public function stats(): array
    {
        $today = date('Y-m-d');
        
        $totalTasks = PaquTask::where('status', '<>', PaquTask::STATUS_DELETED)->count();
        $runningTasks = PaquTask::where('status', PaquTask::STATUS_RUNNING)->count();
        $completedToday = PaquTask::where('status', PaquTask::STATUS_COMPLETED)
            ->whereTime('end_time', 'today')->count();
        
        $errorCount = PaquLog::where('level', PaquLog::LEVEL_ERROR)
            ->whereTime('create_time', 'today')->count();
        
        $totalSuccess = PaquTask::where('status', '<>', PaquTask::STATUS_DELETED)->sum('success_count');
        $totalFailed = PaquTask::where('status', '<>', PaquTask::STATUS_DELETED)->sum('failed_count');
        
        return [
            'total_tasks' => (int)$totalTasks,
            'running_tasks' => (int)$runningTasks,
            'completed_today' => (int)$completedToday,
            'error_count_today' => (int)$errorCount,
            'total_success' => (int)$totalSuccess,
            'total_failed' => (int)$totalFailed,
        ];
    }

    public function addLog(int $taskId, int $level, string $message, string $url = ''): void
    {
        Db::name('paqu_log')->insert([
            'task_id' => $taskId,
            'level' => $level,
            'message' => $message,
            'url' => $url,
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }
}