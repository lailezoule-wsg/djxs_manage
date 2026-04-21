<?php
declare (strict_types = 1);

namespace app\admin\model;

use think\Model;

class AdminUserRole extends Model
{
    protected $name = 'admin_user_role';

    protected $pk = 'id';

    protected $autoWriteTimestamp = false;
}
