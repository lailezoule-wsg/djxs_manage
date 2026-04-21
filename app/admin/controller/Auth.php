<?php
declare (strict_types = 1);

namespace app\admin\controller;

use app\admin\model\AdminUser;
use app\admin\service\AdminJwtService;
use app\admin\service\RbacService;
use think\facade\Db;

class Auth extends BaseAdminController
{
    public function login()
    {
        try {
            $payload = $this->request->post();
            $this->validateOrFail(\app\admin\validate\AuthLoginValidate::class, $payload);
            $username = trim((string)($payload['username'] ?? ''));
            $password = (string)($payload['password'] ?? '');

            $admin = AdminUser::where('username', $username)->find();
            if (!$admin || (int)$admin->status !== 1 || !$admin->checkPassword($password)) {
                return $this->error('账号或密码错误', 400, self::BIZ_INVALID_PARAMS);
            }

            $roleCodes = Db::name('admin_user_role')->alias('ur')
                ->join('admin_role r', 'r.id = ur.role_id')
                ->where('ur.admin_user_id', (int)$admin->id)
                ->where('r.status', 1)
                ->column('r.code');

            $jwtService = new AdminJwtService();
            $token = $jwtService->generateToken([
                'id' => (int)$admin->id,
                'username' => (string)$admin->username,
                'roles' => array_values($roleCodes),
            ]);

            $admin->last_login_time = date('Y-m-d H:i:s');
            $admin->last_login_ip = $this->request->ip();
            $admin->save();

            $rbac = new RbacService();
            $isSuper = $rbac->isSuperAdmin($roleCodes);
            $permissionCodes = $isSuper ? $rbac->getAllEnabledPermissionCodes() : $rbac->getPermissionCodesForAdmin((int)$admin->id);
            $menus = $rbac->buildMenuTreeForCodes($isSuper ? ['*'] : $permissionCodes);

            return $this->success([
                'token' => $token,
                'user' => [
                    'id' => (int)$admin->id,
                    'username' => (string)$admin->username,
                    'roles' => array_values($roleCodes),
                ],
                'permissions' => $permissionCodes,
                'menus' => $menus,
            ], '登录成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 当前登录管理员信息（需 admin_auth）
     */
    public function profile()
    {
        try {
            $u = (array)($this->request->user ?? []);
            return $this->success([
                'id' => (int)($u['id'] ?? 0),
                'username' => (string)($u['username'] ?? ''),
                'roles' => array_values((array)($u['roles'] ?? [])),
            ], '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 当前管理员权限码列表
     */
    public function permissions()
    {
        try {
            $u = (array)($this->request->user ?? []);
            $roles = (array)($u['roles'] ?? []);
            $adminId = (int)($u['id'] ?? 0);
            $rbac = new RbacService();
            if ($rbac->isSuperAdmin($roles)) {
                $codes = $rbac->getAllEnabledPermissionCodes();
            } else {
                $codes = $rbac->getPermissionCodesForAdmin($adminId);
            }
            return $this->success(['permissions' => $codes], '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 当前管理员可见菜单树
     */
    public function menus()
    {
        try {
            $u = (array)($this->request->user ?? []);
            $roles = (array)($u['roles'] ?? []);
            $adminId = (int)($u['id'] ?? 0);
            $rbac = new RbacService();
            if ($rbac->isSuperAdmin($roles)) {
                $tree = $rbac->buildMenuTreeForCodes(['*']);
            } else {
                $tree = $rbac->buildMenuTreeForCodes($rbac->getPermissionCodesForAdmin($adminId));
            }
            return $this->success(['menus' => $tree], '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }
}
