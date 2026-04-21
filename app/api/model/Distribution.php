<?php
declare (strict_types = 1);

namespace app\api\model;

use think\Model;

/**
 * 分销模型
 */
class Distribution extends Model
{
    // 表名
    protected $name = 'distribution';

    // 主键
    protected $pk = 'id';

    // 仅维护 create_time，表中无 update_time
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'create_time';
    protected $updateTime = false;

    // 关联用户
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
