<?php
declare (strict_types = 1);

namespace app\admin\service;

use think\exception\ValidateException;
use think\facade\Db;

/**
 * 管理端服务层基础能力
 */
class BaseAdminService
{
    /**
     * 将分页对象统一转换为前端分页结构
     */
    protected function paginateToArray($query, int $page, int $pageSize): array
    {
        $result = $query->paginate([
            'page' => $page,
            'list_rows' => $pageSize,
        ])->toArray();

        return [
            'list' => $result['data'],
            'total' => (int)$result['total'],
            'page' => (int)$result['current_page'],
            'page_size' => (int)$result['per_page'],
        ];
    }

    /**
     * 获取数据表字段信息
     */
    protected function getTableFields(string $table): array
    {
        $fields = Db::name($table)->getFields();
        return is_array($fields) ? $fields : [];
    }

    /**
     * 仅保留表中允许写入的字段
     */
    protected function filterPayload(string $table, array $payload, bool $isUpdate): array
    {
        $fields = array_keys($this->getTableFields($table));
        $blocked = ['id'];
        if ($isUpdate) {
            $blocked[] = 'create_time';
        }
        $allowed = array_values(array_diff($fields, $blocked));
        $data = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $payload)) {
                $data[$field] = $payload[$field];
            }
        }
        return $data;
    }

    /**
     * 插入时若表含 create_time 且请求未带该字段，则写入当前时间（兼容无默认值的数据库列）。
     */
    protected function ensureCreateTimeForInsert(string $table, array $data): array
    {
        $fields = $this->getTableFields($table);
        if (isset($fields['create_time']) && !array_key_exists('create_time', $data)) {
            $data['create_time'] = date('Y-m-d H:i:s');
        }

        return $data;
    }

    /**
     * 校验指定 ID 记录存在
     */
    protected function assertExists(string $table, int $id, string $msg = '数据不存在'): void
    {
        $exists = Db::name($table)->where('id', $id)->find();
        if (!$exists) {
            throw new ValidateException($msg);
        }
    }
}
