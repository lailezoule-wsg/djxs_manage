<?php
declare(strict_types=1);

namespace app\admin\service\channel\adapter;

abstract class AbstractChannelAdapter implements ChannelAdapterInterface
{
    public function verifyCallbackSign(array $headers, string $rawBody, array $context = []): bool
    {
        $secret = trim((string)($context['callback_secret'] ?? ''));
        if ($secret === '') {
            return false;
        }
        $timestamp = $this->getHeader($headers, 'x-timestamp');
        $nonce = $this->getHeader($headers, 'x-nonce');
        $sign = strtolower($this->getHeader($headers, 'x-sign'));
        if ($timestamp === '' || $nonce === '' || $sign === '') {
            return false;
        }
        $baseString = $timestamp . "\n" . $nonce . "\n" . $rawBody;
        $expected = strtolower(hash_hmac('sha256', $baseString, $secret));
        return hash_equals($expected, $sign);
    }

    public function parseCallback(array $headers, string $rawBody): array
    {
        $payload = json_decode($rawBody, true);
        return [
            'event_id' => (string)($payload['event_id'] ?? ''),
            'event_type' => (string)($payload['event_type'] ?? 'unknown'),
            'task_no' => (string)($payload['task_no'] ?? ''),
            'channel_content_id' => (string)($payload['channel_content_id'] ?? ''),
            'raw_payload' => is_array($payload) ? $payload : [],
        ];
    }

    protected function buildPublishResult(bool $success, array $payload = []): array
    {
        return [
            'success' => $success,
            'channel_content_id' => (string)($payload['channel_content_id'] ?? ''),
            'error_code' => $payload['error_code'] ?? null,
            'error_msg' => $payload['error_msg'] ?? null,
            'raw' => $payload,
        ];
    }

    protected function getHeader(array $headers, string $name): string
    {
        $target = strtolower($name);
        foreach ($headers as $key => $value) {
            if (strtolower((string)$key) !== $target) {
                continue;
            }
            if (is_array($value)) {
                return trim((string)($value[0] ?? ''));
            }
            return trim((string)$value);
        }
        return '';
    }
}
