<?php
declare (strict_types = 1);

namespace app\api\validate;

use think\Validate;

/**
 * 用户验证规则
 */
class User extends Validate
{
    /**
     * 定义验证规则
     */
    protected $rule = [
        'mobile'   => 'require|mobile',
        'password' => 'require|min:6|max:20',
        'code'     => 'require|length:6',
        'old_password' => 'require|min:6|max:20',
        'new_password' => 'require|min:6|max:20|different:old_password',
        'nickname' => 'max:50',
        'avatar'   => 'max:255',
        'gender'   => 'in:0,1,2',
        'birthday' => 'date',
    ];

    /**
     * 定义错误提示
     */
    protected $message = [
        'mobile.require'   => '手机号不能为空',
        'mobile.mobile'    => '手机号格式不正确',
        'password.require' => '密码不能为空',
        'password.min'     => '密码长度不能少于6位',
        'password.max'     => '密码长度不能超过20位',
        'code.require'     => '验证码不能为空',
        'code.length'      => '验证码长度必须为6位',
        'old_password.require' => '旧密码不能为空',
        'new_password.require' => '新密码不能为空',
        'new_password.min'     => '新密码长度不能少于6位',
        'new_password.max'     => '新密码长度不能超过20位',
        'new_password.different' => '新密码不能与旧密码相同',
        'nickname.max'     => '昵称长度不能超过50个字符',
        'avatar.max'       => '头像URL长度不能超过255个字符',
        'gender.in'       => '性别值不正确',
        'birthday.date'   => '生日格式不正确',
    ];

    /**
     * 定义验证场景
     */
    protected $scene = [
        'register' => ['mobile', 'password'],
        'login'    => ['mobile', 'password'],
        'change_password' => ['old_password', 'new_password'],
        'update_info' => ['nickname', 'avatar', 'gender', 'birthday'],
    ];
}
