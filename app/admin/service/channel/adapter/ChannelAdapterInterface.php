<?php
declare(strict_types=1);

namespace app\admin\service\channel\adapter;

interface ChannelAdapterInterface
{
    public function getChannelCode(): string;

    /**
     * @return array{success:bool,channel_content_id:string,error_code:?string,error_msg:?string,raw:array}
     */
    public function publish(array $payload): array;

    /**
     * @return array<string,mixed>
     */
    public function parseCallback(array $headers, string $rawBody): array;

    public function verifyCallbackSign(array $headers, string $rawBody, array $context = []): bool;
}
