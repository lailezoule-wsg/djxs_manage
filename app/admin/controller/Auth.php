<?php
declare (strict_types = 1);

namespace app\admin\controller;

use app\admin\model\AdminUser;
use app\admin\service\AdminJwtService;
use app\admin\service\RbacService;
use think\facade\Cache;
use think\facade\Db;

/**
 * 管理端认证接口
 */
class Auth extends BaseAdminController
{
    /**
     * 管理员登录并返回 token、权限与菜单
     */
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
            
            $csrfToken = $this->generateCsrfToken((int)$admin->id);

            $response = $this->success([
                'token' => $token,
                'csrf_token' => $csrfToken,
                'user' => [
                    'id' => (int)$admin->id,
                    'username' => (string)$admin->username,
                    'roles' => array_values($roleCodes),
                ],
                'permissions' => $permissionCodes,
                'menus' => $menus,
            ], '登录成功');
            $response->header(['X-CSRF-Token' => $csrfToken]);
            cookie('XSRF-TOKEN', $csrfToken, 0, '/', '', false, false);
            return $response;
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

    /**
     * 获取 CSRF token（公开接口，无需认证）
     */
    public function csrfToken()
    {
        try {
            $token = $this->generateCsrfToken();
            $response = json([
                'code' => 200,
                'biz_code' => 0,
                'msg' => 'success',
                'data' => ['csrf_token' => $token],
            ]);
            $response->header(['X-CSRF-Token' => $token]);
            cookie('XSRF-TOKEN', $token, 0, '/', '', false, false);
            return $response;
        } catch (\Throwable $e) {
            return json([
                'code' => 500,
                'biz_code' => 50000,
                'msg' => '获取 CSRF token 失败',
                'data' => (object)[],
            ], 500);
        }
    }

    /**
     * 生成 CSRF token（与管理员ID绑定，存储到缓存）
     * 
     * @param int|null $adminId 管理员ID，不传则使用当前登录用户ID或客户端IP
     */
    protected function generateCsrfToken(?int $adminId = null): string
    {
        // 如果没有传入adminId，尝试从request获取
        if ($adminId === null) {
            $user = $this->request->user ?? [];
            if (is_array($user)) {
                $adminId = (int)($user['id'] ?? 0);
            } elseif (is_object($user)) {
                $adminId = (int)($user->id ?? 0);
            }
        }
        
        // 构建缓存key
        if ($adminId > 0) {
            $cacheKey = "admin_csrf_token:{$adminId}";
        } else {
            $cacheKey = "admin_csrf_token:ip:" . $this->request->ip();
        }
        
        $token = Cache::get($cacheKey);
        
        if (!$token || !$this->isValidCsrfToken($token)) {
            $token = bin2hex(random_bytes(32));
            Cache::set($cacheKey, $token, 86400); // 24小时过期
        }
        
        return $token;
    }

    protected function isValidCsrfToken(string $token): bool
    {
        return strlen($token) === 64 && ctype_xdigit($token);
    }
}
