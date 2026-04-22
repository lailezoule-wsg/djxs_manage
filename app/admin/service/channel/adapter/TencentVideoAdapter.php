<?php
declare(strict_types=1);

namespace app\admin\service\channel\adapter;

/**
 * 腾讯视频渠道适配器
 */
class TencentVideoAdapter extends AbstractChannelAdapter
{
    /**
     * 返回渠道编码
     */
    public function getChannelCode(): string
    {
        return 'tencent_video';
    }

    /**
     * 执行发布（当前为模拟实现）
     */
    public function publish(array $payload): array
    {
        return $this->buildPublishResult(true, [
            'channel_content_id' => 'txv_' . date('YmdHis') . random_int(1000, 9999),
            'request' => $payload,
        ]);
    }
}
