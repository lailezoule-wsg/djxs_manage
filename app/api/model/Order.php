<?php
declare (strict_types = 1);

namespace app\api\model;

use think\Model;

/**
 * 订单模型
 */
class Order extends Model
{
    // 表名
    protected $name = 'order';

    // 主键
    protected $pk = 'id';

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    /**
     * 关联下单用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
