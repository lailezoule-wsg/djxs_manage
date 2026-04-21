<?php
declare (strict_types = 1);

namespace app\admin\middleware;

use app\admin\service\RbacService;

class AdminRbac
{
    public function handle($request, \Closure $next)
    {
        $user = (array)($request->user ?? []);
        $roles = (array)($user['roles'] ?? []);
        $adminId = (int)($user['id'] ?? 0);

        $rbac = new RbacService();
        if ($rbac->isSuperAdmin($roles)) {
            $request->adminPermissions = ['*'];
            return $next($request);
        }

        $codes = $rbac->getPermissionCodesForAdmin($adminId);
        $request->adminPermissions = $codes;

        $controller = (string)$request->controller();
        $action = (string)$request->action();
        $key = $controller . '@' . $action;

        $map = (array)(config('admin_route_permission.map') ?? []);
        if (!array_key_exists($key, $map)) {
            return $next($request);
        }

        $required = $map[$key];
        if ($required === [] || $required === null) {
            return $next($request);
        }

        $need = (array)$required;
        $codeSet = array_flip($codes);
        foreach ($need as $code) {
            if (!isset($codeSet[(string)$code])) {
                return json([
                    'code' => 403,
                    'biz_code' => 40301,
                    'msg' => '无操作权限',
                    'data' => (object)[],
                ], 403);
            }
        }

        return $next($request);
    }
}
