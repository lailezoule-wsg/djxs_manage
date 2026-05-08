<?php
declare (strict_types = 1);

namespace app\admin\model;

use think\Model;

/**
 * 爬虫任务模型
 */
class PaquTask extends Model
{
    protected $name = 'paqu_task';
    
    protected $pk = 'id';
    
    protected $autoWriteTimestamp = false;

    const STATUS_PENDING = 0;
    const STATUS_RUNNING = 1;
    const STATUS_COMPLETED = 2;
    const STATUS_PAUSED = 3;
    const STATUS_CANCELLED = 4;
    const STATUS_DELETED = -1;

    const TYPE_NOVEL = 1;
    const TYPE_DRAMA = 2;

    public function source()
    {
        return $this->belongsTo(PaquSource::class, 'source_id', 'id');
    }

    public function logs()
    {
        return $this->hasMany(PaquLog::class, 'task_id', 'id');
    }

    public function getStatusTextAttr($value, $data)
    {
        $statusMap = [
            self::STATUS_PENDING => '待执行',
            self::STATUS_RUNNING => '运行中',
            self::STATUS_COMPLETED => '已完成',
            self::STATUS_PAUSED => '暂停',
            self::STATUS_CANCELLED => '已取消',
            self::STATUS_DELETED => '已删除',
        ];
        return $statusMap[$data['status']] ?? '未知';
    }

    public function getTypeTextAttr($value, $data)
    {
        $typeMap = [
            self::TYPE_NOVEL => '小说',
            self::TYPE_DRAMA => '短剧',
        ];
        return $typeMap[$data['type']] ?? '未知';
    }
}