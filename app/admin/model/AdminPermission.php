<?php
declare (strict_types = 1);

namespace app\admin\model;

use think\Model;

/**
 * 管理端权限点模型
 */
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
