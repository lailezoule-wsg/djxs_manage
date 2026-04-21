<?php
declare (strict_types = 1);

namespace app\api\model;

use think\Model;

/**
 * 小说模型
 */
class Novel extends Model
{
    // 表名
    protected $name = 'novel';

    // 主键
    protected $pk = 'id';

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // 关联分类
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    // 关联标签
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'novel_tag', 'tag_id', 'novel_id');
    }
}
