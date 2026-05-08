<?php
declare (strict_types = 1);

namespace app\admin\model;

use think\Model;

/**
 * 数据源分类URL映射模型
 */
class PaquSourceCategory extends Model
{
    protected $name = 'paqu_source_category';
    
    protected $pk = 'id';
    
    protected $autoWriteTimestamp = false;

    const STATUS_DISABLED = 0;
    const STATUS_ENABLED = 1;

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    public function source()
    {
        return $this->belongsTo(PaquSource::class, 'source_id', 'id');
    }

    public function getStatusTextAttr($value, $data)
    {
        $statusMap = [
            self::STATUS_DISABLED => '禁用',
            self::STATUS_ENABLED => '启用',
        ];
        return $statusMap[$data['status']] ?? '未知';
    }
}
?>