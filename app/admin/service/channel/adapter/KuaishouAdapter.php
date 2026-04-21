<?php
declare(strict_types=1);

namespace app\admin\service\channel\adapter;

class KuaishouAdapter extends AbstractChannelAdapter
{
    public function getChannelCode(): string
    {
        return 'kuaishou';
    }

    public function publish(array $payload): array
    {
        return $this->buildPublishResult(true, [
            'channel_content_id' => 'ks_' . date('YmdHis') . random_int(1000, 9999),
            'request' => $payload,
        ]);
    }
}
