<?php
declare (strict_types = 1);

namespace app\api\model;

use think\Model;

/**
 * 标签模型
 */
class Tag extends Model
{
    // 表名
    protected $name = 'tag';

    // 主键
    protected $pk = 'id';

    // 自动时间戳
    protected $autoWriteTimestamp = true;
}
