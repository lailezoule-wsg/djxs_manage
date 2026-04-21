<?php
declare (strict_types = 1);

namespace app\api\model;

use think\Model;

class Member extends Model
{
    protected $name = 'member';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';

    public function level()
    {
        return $this->belongsTo(MemberLevel::class, 'member_level_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
