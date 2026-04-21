<?php
declare (strict_types = 1);

namespace app\admin\service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * 管理端专用 JWT（aud 与密钥与用户端隔离）
 */
class AdminJwtService
{
    public function generateToken(array $payloadData): string
    {
        $now = time();
        $payload = [
            'iss' => 'djxs_manage',
            'aud' => $this->getAudience(),
            'iat' => $now,
            'exp' => $now + 7 * 24 * 3600,
            'data' => $payloadData,
        ];

        return JWT::encode($payload, $this->getSecretKey(), 'HS256');
    }

    /**
     * @return array<string, mixed>
     */
    public function decodePayload(string $token): array
    {
        $decoded = JWT::decode($token, new Key($this->getSecretKey(), 'HS256'));
        $raw = $decoded->data ?? null;
        $data = json_decode(json_encode($raw), true);
        if (!is_array($data)) {
            $data = [];
        }
        $aud = $decoded->aud ?? null;
        if (is_array($aud)) {
            $aud = (string)($aud[0] ?? '');
        } else {
            $aud = (string)($aud ?? '');
        }
        if ($aud !== $this->getAudience()) {
            throw new \RuntimeException('Token 受众无效');
        }
        return $data;
    }

    private function getAudience(): string
    {
        return (string)(config('app.jwt_aud_admin') ?? 'djxs_admin');
    }

    private function getSecretKey(): string
    {
        $key = (string)(config('app.jwt_secret_admin') ?? '');
        if ($key !== '') {
            return $key;
        }
        $base = (string)(config('app.jwt_secret') ?? '');
        return hash_hmac('sha256', $base, 'djxs_admin_jwt_v1');
    }
}
