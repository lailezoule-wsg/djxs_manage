<?php
declare(strict_types=1);

namespace app\admin\service\channel;

use app\admin\service\channel\adapter\ChannelAdapterInterface;
use app\admin\service\channel\adapter\DouyinAdapter;
use app\admin\service\channel\adapter\KuaishouAdapter;
use app\admin\service\channel\adapter\TencentVideoAdapter;
use app\admin\service\channel\adapter\XiaohongshuAdapter;
use app\common\exception\BizException;

class ChannelAdapterFactory
{
    public static function supportedCodes(): array
    {
        return ['douyin', 'kuaishou', 'tencent_video', 'xiaohongshu'];
    }

    public static function isSupported(string $channelCode): bool
    {
        return in_array(strtolower(trim($channelCode)), self::supportedCodes(), true);
    }

    public static function make(string $channelCode): ChannelAdapterInterface
    {
        return match (strtolower(trim($channelCode))) {
            'douyin' => new DouyinAdapter(),
            'kuaishou' => new KuaishouAdapter(),
            'tencent_video' => new TencentVideoAdapter(),
            'xiaohongshu' => new XiaohongshuAdapter(),
            default => throw new BizException('暂不支持该渠道：' . $channelCode, 400, 40001),
        };
    }
}
