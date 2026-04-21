<?php
declare (strict_types = 1);

namespace app\api\service;

/**
 * 支付宝沙箱支付服务
 */
class AlipayService
{
    /**
     * 生成网页支付链接（alipay.trade.page.pay）
     */
    public function buildPagePayUrl(string $orderSn, float $amount, string $subject): string
    {
        $gateway = (string)config('alipay.gateway', 'https://openapi-sandbox.dl.alipaydev.com/gateway.do');
        $appId = (string)config('alipay.app_id', '');
        $notifyUrl = (string)config('alipay.notify_url', '');
        $returnUrl = (string)config('alipay.return_url', '');

        if ($appId === '') {
            throw new \RuntimeException('支付宝配置缺失：app_id');
        }

        $params = [
            'app_id' => $appId,
            'method' => 'alipay.trade.page.pay',
            'format' => (string)config('alipay.format', 'JSON'),
            'charset' => (string)config('alipay.charset', 'utf-8'),
            'sign_type' => (string)config('alipay.sign_type', 'RSA2'),
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => (string)config('alipay.version', '1.0'),
            'notify_url' => $notifyUrl,
            'return_url' => $returnUrl,
            'biz_content' => json_encode([
                'out_trade_no' => $orderSn,
                'product_code' => 'FAST_INSTANT_TRADE_PAY',
                'total_amount' => number_format($amount, 2, '.', ''),
                'subject' => $subject,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        $params['sign'] = $this->sign($params);
        return $gateway . '?' . http_build_query($params);
    }

    /**
     * 验签支付宝回调
     */
    public function verifyNotify(array $data): bool
    {
        if (empty($data['sign'])) {
            return false;
        }
        $sign = (string)$data['sign'];
        unset($data['sign']);
        unset($data['sign_type']);

        ksort($data);
        $signContent = [];
        foreach ($data as $k => $v) {
            if ($v === '' || $v === null) {
                continue;
            }
            $signContent[] = $k . '=' . $v;
        }
        $content = implode('&', $signContent);

        $publicKeyRaw = $this->getConfiguredPublicKey();
        if ($publicKeyRaw === '') {
            return false;
        }

        foreach ($this->buildPublicKeyCandidates($publicKeyRaw) as $publicKey) {
            $publicKeyRes = openssl_pkey_get_public($publicKey);
            if ($publicKeyRes === false) {
                continue;
            }
            $res = openssl_verify($content, base64_decode($sign), $publicKeyRes, OPENSSL_ALGO_SHA256);
            if ($res === 1) {
                return true;
            }
        }
        return false;
    }

    /**
     * 签名
     */
    private function sign(array $params): string
    {
        unset($params['sign']);
        ksort($params);

        $pairs = [];
        foreach ($params as $k => $v) {
            if ($v === '' || $v === null) {
                continue;
            }
            $pairs[] = $k . '=' . $v;
        }
        $content = implode('&', $pairs);

        $privateKeyRaw = $this->getConfiguredPrivateKey();
        if ($privateKeyRaw === '') {
            throw new \RuntimeException('支付宝配置缺失：app_private_key');
        }

        foreach ($this->buildPrivateKeyCandidates($privateKeyRaw) as $privateKey) {
            $privateKeyRes = openssl_pkey_get_private($privateKey);
            if ($privateKeyRes === false) {
                continue;
            }
            $signature = '';
            $ok = openssl_sign($content, $signature, $privateKeyRes, OPENSSL_ALGO_SHA256);
            if ($ok) {
                return base64_encode($signature);
            }
        }
        throw new \RuntimeException('支付宝签名失败');
    }

    private function formatPrivateKey(string $key): string
    {
        $key = $this->normalizeKeyString($key);
        if ($key === '') {
            return '';
        }
        if (str_contains($key, 'BEGIN')) {
            return $key;
        }
        $body = chunk_split(preg_replace('/\s+/', '', $key), 64, "\n");
        return "-----BEGIN PRIVATE KEY-----\n{$body}-----END PRIVATE KEY-----";
    }

    private function formatPublicKey(string $key): string
    {
        $key = $this->normalizeKeyString($key);
        if ($key === '') {
            return '';
        }
        if (str_contains($key, 'BEGIN')) {
            return $key;
        }
        $body = chunk_split(preg_replace('/\s+/', '', $key), 64, "\n");
        return "-----BEGIN PUBLIC KEY-----\n{$body}-----END PUBLIC KEY-----";
    }

    private function normalizeKeyString(string $key): string
    {
        $key = trim($key);
        if (str_contains($key, '\\n')) {
            $key = str_replace('\\n', "\n", $key);
        }
        return trim($key);
    }

    /**
     * 私钥候选（兼容 PKCS8 与 PKCS1）
     */
    private function buildPrivateKeyCandidates(string $key): array
    {
        $key = $this->normalizeKeyString($key);
        if ($key === '') {
            return [];
        }

        if (str_contains($key, 'BEGIN')) {
            return [$key];
        }

        $body = chunk_split(preg_replace('/\s+/', '', $key), 64, "\n");
        return [
            "-----BEGIN PRIVATE KEY-----\n{$body}-----END PRIVATE KEY-----",
            "-----BEGIN RSA PRIVATE KEY-----\n{$body}-----END RSA PRIVATE KEY-----",
        ];
    }

    /**
     * 公钥候选（兼容不同头部格式）
     */
    private function buildPublicKeyCandidates(string $key): array
    {
        $key = $this->normalizeKeyString($key);
        if ($key === '') {
            return [];
        }

        if (str_contains($key, 'BEGIN')) {
            return [$key];
        }

        $body = chunk_split(preg_replace('/\s+/', '', $key), 64, "\n");
        return [
            "-----BEGIN PUBLIC KEY-----\n{$body}-----END PUBLIC KEY-----",
            "-----BEGIN RSA PUBLIC KEY-----\n{$body}-----END RSA PUBLIC KEY-----",
        ];
    }

    /**
     * 统一读取应用私钥（路径优先，字符串兜底）
     */
    private function getConfiguredPrivateKey(): string
    {
        $path = trim((string)config('alipay.app_private_key_path', ''));
        if ($path !== '') {
            $fromFile = $this->readKeyFromPath($path);
            if ($fromFile !== '') {
                return $this->normalizeKeyString($fromFile);
            }
        }

        $raw = (string)config('alipay.app_private_key', '');
        return $this->normalizeKeyString($raw);
    }

    /**
     * 统一读取支付宝公钥（路径优先，字符串兜底）
     */
    private function getConfiguredPublicKey(): string
    {
        $path = trim((string)config('alipay.alipay_public_key_path', ''));
        if ($path !== '') {
            $fromFile = $this->readKeyFromPath($path);
            if ($fromFile !== '') {
                return $this->normalizeKeyString($fromFile);
            }
        }

        $raw = (string)config('alipay.alipay_public_key', '');
        return $this->normalizeKeyString($raw);
    }

    /**
     * 读取密钥文件（支持相对路径与绝对路径）
     */
    private function readKeyFromPath(string $path): string
    {
        $candidate = $path;
        if (!str_starts_with($candidate, '/')) {
            $candidate = rtrim(app()->getRootPath(), '/') . '/' . ltrim($candidate, '/');
        }
        if (!is_file($candidate)) {
            return '';
        }

        $content = (string)file_get_contents($candidate);
        return trim($content);
    }
}

