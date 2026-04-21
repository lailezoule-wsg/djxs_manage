<?php
declare (strict_types = 1);

namespace app\api\model;

use think\Model;

/**
 * 广告模型
 */
class Ad extends Model
{
    protected $name = 'ad';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;

    public function position()
    {
        return $this->belongsTo(AdPosition::class, 'position_id');
    }
}
