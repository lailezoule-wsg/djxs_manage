<?php
declare (strict_types = 1);

namespace app\api\model;

use think\Model;

/**
 * 订单商品明细模型
 */
class OrderGoods extends Model
{
    protected $name = 'order_goods';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;
}
