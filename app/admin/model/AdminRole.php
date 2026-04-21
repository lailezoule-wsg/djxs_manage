<?php
declare (strict_types = 1);

namespace app\admin\model;

use think\Model;

class AdminRole extends Model
{
    protected $name = 'admin_role';

    protected $pk = 'id';

    protected $autoWriteTimestamp = false;

    protected $type = [
        'id' => 'integer',
        'status' => 'integer',
    ];
}
