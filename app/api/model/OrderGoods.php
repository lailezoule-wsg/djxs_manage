<?php
declare (strict_types = 1);

namespace app\api\model;

use think\Model;

class OrderGoods extends Model
{
    protected $name = 'order_goods';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;
}
