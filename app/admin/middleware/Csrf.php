<?php
declare (strict_types = 1);

namespace app\admin\middleware;

use think\facade\Cache;

/**
 * CSRF 保护中间件
 * 
 * 参考 API 接口处理方式，将 CSRF token 存储到缓存中，与 admin_id 绑定
 * 避免依赖 Session，解决前后端分离场景下 Session 无法正确维护的问题
 */
class Csrf
{
    const CSRF_HEADER_NAME = 'X-CSRF-Token';
    const CSRF_TOKEN_LENGTH = 32;
    const CSRF_TOKEN_TTL = 86400; // 24小时过期

    public function handle($request, \Closure $next)
    {
        $token = $this->getToken($request);
        
        if ($request->isPost() || $request->isPut() || $request->isDelete()) {
            $headerToken = (string)$request->header(self::CSRF_HEADER_NAME, '');
            if (!$headerToken || !hash_equals($token, $headerToken)) {
                return json([
                    'code' => 419,
                    'biz_code' => 41901,
                    'msg' => 'CSRF Token 无效或已过期',
                    'data' => (object)[],
                ], 419);
            }
        }

        $response = $next($request);
        $response->header([self::CSRF_HEADER_NAME => $token]);
        
        cookie('XSRF-TOKEN', $token, 0, '/', '', false, false);
        
        return $response;
    }

    /**
     * 获取或生成 CSRF token
     * 使用 admin_id 作为缓存 key，避免依赖 Session
     */
    protected function getToken($request): string
    {
        // 尝试从请求中获取当前登录管理员ID
        $adminId = $this->getAdminId($request);
        
        // 使用管理员ID或客户端IP作为缓存key（未登录时使用IP）
        $cacheKey = $this->buildCacheKey($adminId);
        $token = Cache::get($cacheKey);
        
        if (!$token || !$this->isValidToken($token)) {
            $token = $this->generateToken();
            Cache::set($cacheKey, $token, self::CSRF_TOKEN_TTL);
        }
        
        return $token;
    }

    /**
     * 从请求中获取当前登录管理员ID
     * Auth中间件会将用户信息注入到request->user中
     */
    protected function getAdminId($request): int
    {
        $user = $request->user ?? [];
        if (is_array($user)) {
            return (int)($user['id'] ?? 0);
        }
        if (is_object($user)) {
            return (int)($user->id ?? 0);
        }
        return 0;
    }

    /**
     * 构建缓存key
     */
    protected function buildCacheKey(int $adminId): string
    {
        if ($adminId > 0) {
            return "admin_csrf_token:{$adminId}";
        }
        // 未登录时使用客户端IP作为key
        $clientIp = request()->ip();
        return "admin_csrf_token:ip:{$clientIp}";
    }

    protected function generateToken(): string
    {
        return bin2hex(random_bytes(self::CSRF_TOKEN_LENGTH));
    }

    protected function isValidToken(string $token): bool
    {
        return strlen($token) === self::CSRF_TOKEN_LENGTH * 2 && ctype_xdigit($token);
    }
}