<?php
declare (strict_types = 1);

namespace app\api\model;

use think\Model;

class FlashSaleOrder extends Model
{
    protected $name = 'flash_sale_order';

    protected $pk = 'id';

    protected $autoWriteTimestamp = true;

    protected $createTime = 'create_time';

    protected $updateTime = 'update_time';
}

