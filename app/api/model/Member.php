<?php
declare (strict_types = 1);

namespace app\api\model;

use think\Model;

/**
 * 会员模型
 */
class Member extends Model
{
    protected $name = 'member';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';

    /**
     * 关联会员等级
     */
    public function level()
    {
        return $this->belongsTo(MemberLevel::class, 'member_level_id');
    }

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
