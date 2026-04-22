<?php
declare (strict_types = 1);

namespace app\admin\model;

use think\Model;

/**
 * 管理员用户（与 C 端 djxs_user 隔离）
 */
class AdminUser extends Model
{
    protected $name = 'admin_user';

    protected $pk = 'id';

    protected $autoWriteTimestamp = true;

    protected $createTime = 'create_time';

    protected $updateTime = 'update_time';

    protected $hidden = ['password'];

    /**
     * 密码写入时自动加密
     */
    public function setPasswordAttr($value): string
    {
        return password_hash((string)$value, PASSWORD_DEFAULT);
    }

    /**
     * 校验明文密码
     */
    public function checkPassword(string $password): bool
    {
        return password_verify($password, (string)$this->password);
    }

    /**
     * 关联管理员角色
     */
    public function roles()
    {
        return $this->belongsToMany(
            AdminRole::class,
            'admin_user_role',
            'role_id',
            'admin_user_id'
        );
    }
}
