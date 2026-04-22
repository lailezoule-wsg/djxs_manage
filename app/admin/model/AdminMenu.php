<?php
declare (strict_types = 1);

namespace app\admin\model;

use think\Model;

/**
 * 管理端菜单模型
 */
class AdminMenu extends Model
{
    protected $name = 'admin_menu';

    protected $pk = 'id';

    protected $autoWriteTimestamp = true;

    protected $createTime = 'create_time';

    protected $updateTime = 'update_time';

    protected $type = [
        'id' => 'integer',
        'parent_id' => 'integer',
        'sort' => 'integer',
        'visible' => 'integer',
        'status' => 'integer',
    ];
}
