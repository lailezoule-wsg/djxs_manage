<?php
declare(strict_types=1);

namespace app\admin\service;

use app\admin\service\channel\ChannelAdapterFactory;
use app\common\exception\BizException;
use think\facade\Db;

/**
 * 管理端渠道注册表业务服务
 */
class ChannelRegistryAdminService extends BaseAdminService
{
    private const DEFAULT_CHANNELS = [
        ['id' => 1, 'channel_code' => 'douyin', 'channel_name' => '抖音', 'status' => 1, 'sort' => 10],
        ['id' => 2, 'channel_code' => 'kuaishou', 'channel_name' => '快手', 'status' => 1, 'sort' => 20],
        ['id' => 3, 'channel_code' => 'tencent_video', 'channel_name' => '腾讯视频', 'status' => 1, 'sort' => 30],
        ['id' => 4, 'channel_code' => 'xiaohongshu', 'channel_name' => '小红书', 'status' => 1, 'sort' => 40],
    ];

    /**
     * 分页查询渠道配置
     */
    public function list(array $params, int $page, int $pageSize): array
    {
        if (!$this->tableExists()) {
            return $this->paginateDefault($params, $page, $pageSize);
        }
        $query = Db::name('channel_registry')->order('sort', 'asc')->order('id', 'asc');
        $keyword = trim((string)($params['keyword'] ?? ''));
        $status = $params['status'] ?? '';
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('channel_code', '%' . $keyword . '%')
                    ->whereOrLike('channel_name', '%' . $keyword . '%');
            });
        }
        if ($status !== '' && is_numeric((string)$status)) {
            $query->where('status', (int)$status);
        }
        return $this->paginateToArray($query, $page, $pageSize);
    }

    /**
     * 获取启用渠道选项
     */
    public function options(): array
    {
        if (!$this->tableExists()) {
            return array_map(function (array $row): array {
                $row['supported'] = ChannelAdapterFactory::isSupported((string)($row['channel_code'] ?? '')) ? 1 : 0;
                return $row;
            }, self::DEFAULT_CHANNELS);
        }
        $rows = Db::name('channel_registry')
            ->where('status', 1)
            ->order('sort', 'asc')
            ->order('id', 'asc')
            ->field('id,channel_code,channel_name,status,sort')
            ->select()
            ->toArray();
        foreach ($rows as &$row) {
            $row['supported'] = ChannelAdapterFactory::isSupported((string)($row['channel_code'] ?? '')) ? 1 : 0;
        }
        unset($row);
        return $rows;
    }

    /**
     * 创建渠道配置
     */
    public function create(array $payload): int
    {
        $this->assertTableReady();
        $data = $this->normalize($payload, false);
        $data = $this->ensureCreateTimeForInsert('channel_registry', $data);
        return (int)Db::name('channel_registry')->insertGetId($data);
    }

    /**
     * 更新渠道配置
     */
    public function update(int $id, array $payload): void
    {
        $this->assertTableReady();
        $this->assertExists('channel_registry', $id, '渠道名称记录不存在');
        $data = $this->normalize($payload, true);
        if (empty($data)) {
            return;
        }
        Db::name('channel_registry')->where('id', $id)->update($data);
    }

    /**
     * 启停渠道配置
     */
    public function toggle(int $id, int $status): void
    {
        $this->assertTableReady();
        $this->assertExists('channel_registry', $id, '渠道名称记录不存在');
        if (!in_array($status, [0, 1], true)) {
            throw new BizException('状态仅支持 0/1', 400, 40001);
        }
        Db::name('channel_registry')->where('id', $id)->update([
            'status' => $status,
            'update_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function normalize(array $payload, bool $isUpdate): array
    {
        $code = strtolower(trim((string)($payload['channel_code'] ?? '')));
        $name = trim((string)($payload['channel_name'] ?? ''));
        $status = (int)($payload['status'] ?? 1);
        $sort = (int)($payload['sort'] ?? 100);
        $remark = trim((string)($payload['remark'] ?? ''));

        if (!$isUpdate || array_key_exists('channel_code', $payload)) {
            if ($code === '' || strlen($code) > 32) {
                throw new BizException('channel_code 不能为空且长度<=32', 400, 40001);
            }
        }
        if (!$isUpdate || array_key_exists('channel_name', $payload)) {
            if ($name === '' || strlen($name) > 64) {
                throw new BizException('channel_name 不能为空且长度<=64', 400, 40001);
            }
        }
        if (!$isUpdate || array_key_exists('status', $payload)) {
            if (!in_array($status, [0, 1], true)) {
                throw new BizException('status 仅支持 0/1', 400, 40001);
            }
        }

        $data = [];
        $map = [
            'channel_code' => $code,
            'channel_name' => $name,
            'status' => $status,
            'sort' => $sort,
            'remark' => $remark,
        ];
        foreach ($map as $field => $value) {
            if (!$isUpdate || array_key_exists($field, $payload)) {
                $data[$field] = $value;
            }
        }
        $data['update_time'] = date('Y-m-d H:i:s');
        return $data;
    }

    private function tableExists(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }
        try {
            Db::name('channel_registry')->where('id', 0)->find();
            $exists = true;
        } catch (\Throwable $e) {
            $exists = false;
        }
        return $exists;
    }

    private function paginateDefault(array $params, int $page, int $pageSize): array
    {
        $keyword = trim((string)($params['keyword'] ?? ''));
        $status = $params['status'] ?? '';
        $rows = array_values(array_filter(self::DEFAULT_CHANNELS, function (array $item) use ($keyword, $status): bool {
            if ($keyword !== '') {
                $matched = str_contains($item['channel_code'], $keyword) || str_contains($item['channel_name'], $keyword);
                if (!$matched) {
                    return false;
                }
            }
            if ($status !== '' && is_numeric((string)$status) && (int)$status !== (int)$item['status']) {
                return false;
            }
            return true;
        }));
        $offset = max(0, ($page - 1) * $pageSize);
        return [
            'list' => array_slice($rows, $offset, $pageSize),
            'total' => count($rows),
            'page' => $page,
            'page_size' => $pageSize,
        ];
    }

    private function assertTableReady(): void
    {
        if (!$this->tableExists()) {
            throw new BizException('缺少渠道名称配置表，请先执行 20260422_channel_registry.sql', 400, 40001);
        }
    }
}
