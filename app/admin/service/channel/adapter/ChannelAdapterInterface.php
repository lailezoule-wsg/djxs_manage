<?php
declare(strict_types=1);

namespace app\admin\service\channel\adapter;

/**
 * 渠道发布适配器接口
 */
interface ChannelAdapterInterface
{
    /**
     * 获取渠道编码
     */
    public function getChannelCode(): string;

    /**
     * @return array{success:bool,channel_content_id:string,error_code:?string,error_msg:?string,raw:array}
     */
    public function publish(array $payload): array;

    /**
     * @return array<string,mixed>
     */
    public function parseCallback(array $headers, string $rawBody): array;

    /**
     * 验证渠道回调签名
     */
    public function verifyCallbackSign(array $headers, string $rawBody, array $context = []): bool;
}
