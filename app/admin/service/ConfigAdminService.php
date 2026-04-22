<?php
declare (strict_types = 1);

namespace app\admin\service;

use think\facade\Db;

/**
 * 管理端系统配置业务服务
 */
class ConfigAdminService extends BaseAdminService
{
    /**
     * 表字段为 key / value / update_time；对外统一映射为 config_key / config_value
     */
    public function list(): array
    {
        if ($this->hasSystemConfigTable()) {
            $rows = Db::name('system_config')->order('id', 'asc')->select()->toArray();
            return array_map(static function (array $row): array {
                return [
                    'id' => $row['id'] ?? null,
                    'config_key' => (string)($row['key'] ?? ''),
                    'config_value' => (string)($row['value'] ?? ''),
                    'description' => (string)($row['description'] ?? ''),
                ];
            }, $rows);
        }
        return $this->readConfigFile();
    }

    /**
     * 批量更新配置项
     */
    public function update(array $items): void
    {
        if ($this->hasSystemConfigTable()) {
            $now = date('Y-m-d H:i:s');
            foreach ($items as $item) {
                $key = (string)($item['config_key'] ?? $item['key'] ?? '');
                $value = (string)($item['config_value'] ?? $item['value'] ?? '');
                if ($key === '') {
                    continue;
                }
                $exists = Db::name('system_config')->whereRaw('`key` = ?', [$key])->find();
                if ($exists) {
                    Db::name('system_config')->whereRaw('`key` = ?', [$key])->update([
                        'value' => $value,
                        'update_time' => $now,
                    ]);
                } else {
                    Db::name('system_config')->insert([
                        'key' => $key,
                        'value' => $value,
                        'description' => '',
                        'update_time' => $now,
                    ]);
                }
            }
            return;
        }

        $current = $this->readConfigFile();
        $dict = [];
        foreach ($current as $row) {
            $dict[(string)($row['config_key'] ?? '')] = (string)($row['config_value'] ?? '');
        }
        foreach ($items as $item) {
            $key = (string)($item['config_key'] ?? '');
            if ($key === '') {
                continue;
            }
            $dict[$key] = (string)($item['config_value'] ?? '');
        }
        $rows = [];
        foreach ($dict as $key => $value) {
            $rows[] = ['config_key' => $key, 'config_value' => $value];
        }
        $this->writeConfigFile($rows);
    }

    private function hasSystemConfigTable(): bool
    {
        try {
            Db::name('system_config')->find();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function configFilePath(): string
    {
        return rtrim(app()->getRuntimePath(), '/') . '/admin-system-config.json';
    }

    private function readConfigFile(): array
    {
        $file = $this->configFilePath();
        if (!is_file($file)) {
            return [];
        }
        $decoded = json_decode((string)file_get_contents($file), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function writeConfigFile(array $rows): void
    {
        file_put_contents($this->configFilePath(), json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
