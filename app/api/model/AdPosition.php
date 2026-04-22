<?php
declare (strict_types = 1);

namespace app\api\model;

use think\Model;

/**
 * 广告位模型
 */
class AdPosition extends Model
{
    protected $name = 'ad_position';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;

    /**
     * 关联广告列表
     */
    public function ads()
    {
        return $this->hasMany(Ad::class, 'position_id');
    }
}
