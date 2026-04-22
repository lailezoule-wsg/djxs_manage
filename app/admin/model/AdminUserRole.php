<?php
declare (strict_types = 1);

namespace app\admin\model;

use think\Model;

/**
 * 管理员与角色关联模型
 */
class AdminUserRole extends Model
{
    protected $name = 'admin_user_role';

    protected $pk = 'id';

    protected $autoWriteTimestamp = false;
}
