<?php
declare (strict_types = 1);

namespace app\api\model;

use think\Model;

class AdPosition extends Model
{
    protected $name = 'ad_position';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;

    public function ads()
    {
        return $this->hasMany(Ad::class, 'position_id');
    }
}
