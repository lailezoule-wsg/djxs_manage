<?php
declare (strict_types = 1);

namespace app\api\model;

use think\Model;

/**
 * 分类模型
 */
class Category extends Model
{
    // 表名
    protected $name = 'category';

    // 主键
    protected $pk = 'id';

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // 关联短剧
    public function dramas()
    {
        return $this->hasMany(Drama::class, 'category_id', 'id');
    }

    // 关联小说
    public function novels()
    {
        return $this->hasMany(Novel::class, 'category_id', 'id');
    }
}
