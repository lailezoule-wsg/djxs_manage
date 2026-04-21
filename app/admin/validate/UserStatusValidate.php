<?php
declare (strict_types = 1);

namespace app\admin\validate;

use think\Validate;

class UserStatusValidate extends Validate
{
    protected $rule = [
        'status' => 'require|in:0,1',
    ];

    protected $message = [
        'status.require' => 'status 必填',
        'status.in' => 'status 仅支持 0 或 1',
    ];
}
