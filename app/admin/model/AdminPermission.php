<?php
declare (strict_types = 1);

namespace app\admin\model;

use think\Model;

class AdminPermission extends Model
{
    protected $name = 'admin_permission';

    protected $pk = 'id';

    protected $autoWriteTimestamp = false;

    protected $type = [
        'id' => 'integer',
        'type' => 'integer',
        'parent_id' => 'integer',
        'sort' => 'integer',
        'status' => 'integer',
    ];
}
