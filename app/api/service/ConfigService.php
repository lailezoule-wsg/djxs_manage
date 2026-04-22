<?php
declare (strict_types = 1);

namespace app\api\service;

use think\facade\Db;

/**
 * 用户端公共配置服务
 */
class ConfigService
{
    /**
     * 仅返回允许公开给 C 端的配置键。
     */
    public function publicConfig(): array
    {
        $allowedKeys = [
            'site_announcement',
            'site_footer_text',
            'customer_service_wechat',
            'customer_service_qq',
            'customer_service_phone',
        ];

        $rows = Db::name('system_config')
            ->whereIn('key', $allowedKeys)
            ->column('value', 'key');

        $result = [];
        foreach ($allowedKeys as $key) {
            $result[$key] = isset($rows[$key]) ? (string)$rows[$key] : '';
        }

        return $result;
    }
}
