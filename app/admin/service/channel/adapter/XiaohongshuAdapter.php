<?php
declare(strict_types=1);

namespace app\admin\service\channel\adapter;

/**
 * 小红书渠道适配器
 */
class XiaohongshuAdapter extends AbstractChannelAdapter
{
    /**
     * 返回渠道编码
     */
    public function getChannelCode(): string
    {
        return 'xiaohongshu';
    }

    /**
     * 执行发布（当前为模拟实现）
     */
    public function publish(array $payload): array
    {
        return $this->buildPublishResult(true, [
            'channel_content_id' => 'xhs_' . date('YmdHis') . random_int(1000, 9999),
            'request' => $payload,
        ]);
    }
}
