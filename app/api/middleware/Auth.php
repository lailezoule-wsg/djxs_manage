<?php
declare (strict_types = 1);

namespace app\api\middleware;

use think\Response;
use think\exception\ValidateException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use think\facade\Log;

/**
 * JWT 认证中间件
 */
class Auth
{
    /**
     * 处理请求
     */
    public function handle($request, \Closure $next)
    {
        // 获取 token
        $token = $request->header('Authorization');
        
        if (!$token) {
            return json([
                'code' => 401,
                'msg'  => '未登录或登录已过期',
            ], 401);
        }

        // 解析 token
        try {
            // 移除 Bearer 前缀
            if (strpos($token, 'Bearer ') === 0) {
                $token = substr($token, 7);
            }

            // 验证 token
            $decoded = JWT::decode($token, new Key($this->getSecretKey(), 'HS256'));

            // 将用户信息存入请求对象
            $request->user = (array) $decoded->data;

        } catch (\Exception $e) {
            return json([
                'code' => 401,
                'msg'  => '登录失败 : ' . $e->getMessage(),
            ], 401);
        }

        return $next($request);
    }

    /**
     * 获取 JWT 密钥
     */
    private function getSecretKey()
    {
        return config('app.jwt_secret') ?? 'djxs_jwt_token_secure_secret_key_1234567890123456789012345678901234567890123456789012345678901234';
    }
}
