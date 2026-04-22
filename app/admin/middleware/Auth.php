<?php
declare (strict_types = 1);

namespace app\admin\middleware;

use app\admin\service\AdminJwtService;

/**
 * 管理端鉴权中间件
 */
class Auth
{
    /**
     * 校验请求 token 并注入当前管理员信息
     */
    public function handle($request, \Closure $next)
    {
        $token = (string)$request->header('Authorization', '');
        if ($token === '') {
            return json([
                'code' => 401,
                'biz_code' => 40101,
                'msg' => '未登录或登录已过期',
                'data' => (object)[],
            ], 401);
        }

        if (strpos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
        }

        try {
            $jwtService = new AdminJwtService();
            $userData = $jwtService->decodePayload($token);
            $adminId = (int)($userData['id'] ?? 0);
            if ($adminId <= 0) {
                throw new \RuntimeException('token 无效');
            }
            $request->user = $userData;
        } catch (\Throwable $e) {
            return json([
                'code' => 401,
                'biz_code' => 40101,
                'msg' => '未登录或登录已过期',
                'data' => (object)[],
            ], 401);
        }

        return $next($request);
    }
}
