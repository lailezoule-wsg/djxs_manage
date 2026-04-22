<?php
declare (strict_types = 1);

namespace app\api\model;

use think\Model;

/**
 * 会员等级模型
 */
class MemberLevel extends Model
{
    protected $name = 'member_level';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;
}
