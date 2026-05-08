<?php
declare (strict_types = 1);

namespace app\admin\model;

use think\Model;

/**
 * 数据源模型
 */
class PaquSource extends Model
{
    protected $name = 'paqu_source';
    
    protected $pk = 'id';
    
    protected $autoWriteTimestamp = false;

    const TYPE_NOVEL = 1;
    const TYPE_DRAMA = 2;

    const STATUS_DISABLED = 0;
    const STATUS_ENABLED = 1;

    public function tasks()
    {
        return $this->hasMany(PaquTask::class, 'source_id', 'id');
    }

    public function getStatusTextAttr($value, $data)
    {
        $statusMap = [
            self::STATUS_DISABLED => '禁用',
            self::STATUS_ENABLED => '启用',
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