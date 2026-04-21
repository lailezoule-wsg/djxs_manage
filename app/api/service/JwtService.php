<?php
declare (strict_types = 1);

namespace app\api\service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * JWT 服务层
 */
class JwtService
{
    /**
     * 生成 token
     */
    public function generateToken($user)
    {
        $payload = [
            'iss' => 'djxs_manage',
            'aud' => 'djxs_manage',
            'iat' => time(),
            'exp' => time() + 7 * 24 * 3600,
            'data' => [
                'id'    => $user['id'],
                'mobile' => $user['mobile'],
            ],
        ];

        return JWT::encode($payload, $this->getSecretKey(), 'HS256');
    }

    /**
     * 验证 token
     */
    public function verifyToken($token)
    {
        try {
            $decoded = JWT::decode($token, new Key($this->getSecretKey(), 'HS256'));
            return (array) $decoded->data;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 获取 JWT 密钥
     */
    private function getSecretKey()
    {
        return config('app.jwt_secret') ?? 'djxs_jwt_token_secure_secret_key_1234567890123456789012345678901234567890123456789012345678901234';
    }
}
