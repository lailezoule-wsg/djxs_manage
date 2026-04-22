<?php
declare (strict_types = 1);

namespace app\api\model;

use think\Model;

/**
 * 秒杀活动模型
 */
class FlashSaleActivity extends Model
{
    protected $name = 'flash_sale_activity';

    protected $pk = 'id';

    protected $autoWriteTimestamp = true;

    protected $createTime = 'create_time';

    protected $updateTime = 'update_time';
}

