<?php
declare (strict_types = 1);

namespace app\admin\validate;

use think\Validate;

class AuthLoginValidate extends Validate
{
    protected $rule = [
        'username' => 'require|alphaDash|length:2,50',
        'password' => 'require|min:6|max:64',
    ];

    protected $message = [
        'username.require' => '账号必填',
        'username.alphaDash' => '账号仅含字母数字下划线及短横线',
        'username.length' => '账号长度为2-50',
        'password.require' => '密码必填',
        'password.min' => '密码长度不能小于6位',
        'password.max' => '密码长度不能超过64位',
    ];
}
