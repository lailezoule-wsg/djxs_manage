<?php
declare (strict_types = 1);

namespace app\api\model;

use think\Model;

class FlashSaleItem extends Model
{
    protected $name = 'flash_sale_item';

    protected $pk = 'id';

    protected $autoWriteTimestamp = true;

    protected $createTime = 'create_time';

    protected $updateTime = 'update_time';
}

