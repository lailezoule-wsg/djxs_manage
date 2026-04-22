<?php
declare (strict_types = 1);

namespace app\api\model;

use think\Model;

/**
 * 秒杀订单映射模型
 */
class FlashSaleOrder extends Model
{
    protected $name = 'flash_sale_order';

    protected $pk = 'id';

    protected $autoWriteTimestamp = true;

    protected $createTime = 'create_time';

    protected $updateTime = 'update_time';
}

