<?php
declare (strict_types = 1);

namespace app\admin\model;

use think\Model;

/**
 * 爬取日志模型
 */
class PaquLog extends Model
{
    protected $name = 'paqu_log';
    
    protected $pk = 'id';
    
    protected $autoWriteTimestamp = false;

    const LEVEL_DEBUG = 1;
    const LEVEL_INFO = 2;
    const LEVEL_WARNING = 3;
    const LEVEL_ERROR = 4;

    public function task()
    {
        return $this->belongsTo(PaquTask::class, 'task_id', 'id');
    }

    public function getLevelTextAttr($value, $data)
    {
        $levelMap = [
            self::LEVEL_DEBUG => '调试',
            self::LEVEL_INFO => '信息',
            self::LEVEL_WARNING => '警告',
            self::LEVEL_ERROR => '错误',
        ];
        return $levelMap[$data['level']] ?? '未知';
    }
}