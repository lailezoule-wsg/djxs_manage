<?php
declare (strict_types = 1);

namespace app\api\model;

use think\Model;

/**
 * 用户模型
 */
class User extends Model
{
    // 表名
    protected $name = 'user';

    // 主键
    protected $pk = 'id';

    // 自动时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'reg_time';

    // 隐藏字段
    protected $hidden = ['password', 'salt'];

    // 密码加密
    public function setPasswordAttr($value)
    {
        return password_hash($value, PASSWORD_DEFAULT);
    }

    // 验证密码
    public function checkPassword($password)
    {
        return password_verify($password, $this->password);
    }
}
