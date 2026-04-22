<?php
declare (strict_types = 1);

namespace app\api\model;

use think\Model;

/**
 * 秒杀活动商品模型
 */
class FlashSaleItem extends Model
{
    protected $name = 'flash_sale_item';

    protected $pk = 'id';

    protected $autoWriteTimestamp = true;

    protected $createTime = 'create_time';

    protected $updateTime = 'update_time';
}

