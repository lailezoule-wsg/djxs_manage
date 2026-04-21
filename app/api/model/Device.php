<?php
declare (strict_types = 1);

namespace app\api\model;

use think\Model;

/**
 * 设备模型
 */
class Device extends Model
{
    // 表名
    protected $name = 'device';

    // 主键
    protected $pk = 'id';

    // 设备表只有 bind_time，没有 update_time
    protected $autoWriteTimestamp = false;
}
