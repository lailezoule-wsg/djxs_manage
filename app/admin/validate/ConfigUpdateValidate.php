<?php
declare (strict_types = 1);

namespace app\admin\validate;

use think\Validate;

class ConfigUpdateValidate extends Validate
{
    protected $rule = [
        'items' => 'require|array',
    ];

    protected $message = [
        'items.require' => 'items 必填',
        'items.array' => 'items 必须为数组',
    ];
}
