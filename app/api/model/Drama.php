<?php
declare (strict_types = 1);

namespace app\api\model;

use think\Model;

/**
 * 短剧模型
 */
class Drama extends Model
{
    // 表名
    protected $name = 'drama';

    // 主键
    protected $pk = 'id';

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    /**
     * 关联分类
     */
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    /**
     * 关联标签
     */
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'drama_tag', 'tag_id', 'drama_id');
    }
}
