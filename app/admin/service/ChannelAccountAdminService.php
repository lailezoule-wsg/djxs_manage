<?php
declare(strict_types=1);

namespace app\admin\service;

use app\common\exception\BizException;
use think\facade\Db;

/**
 * 管理端渠道账号业务服务
 */
class ChannelAccountAdminService extends BaseAdminService
{
    /**
     * 分页查询渠道账号列表
     */
    public function accountList(array $params, int $page, int $pageSize): array
    {
        $query = Db::name('channel_account')->order('id', 'desc');
        $channelCode = trim((string)($params['channel_code'] ?? ''));
        $status = $params['status'] ?? '';
        if ($channelCode !== '') {
            $query->where('channel_code', $channelCode);
        }
        if ($status !== '' && is_numeric((string)$status)) {
            $query->where('status', (int)$status);
        }
        $result = $this->paginateToArray($query, $page, $pageSize);
        $result['list'] = array_map(function (array $row): array {
            unset($row['app_secret_enc'], $row['access_token_enc'], $row['refresh_token_enc'], $row['callback_secret_enc']);
            $row['credential_configured'] = ((string)($row['app_key'] ?? '') !== '' && (string)($row['app_secret_enc'] ?? '') !== '') ? 1 : 0;
            return $row;
        }, $result['list']);
        return $result;
    }

    /**
     * 创建渠道账号
     */
    public function accountCreate(array $payload): int
    {
        $row = $this->normalizeAccountPayload($payload, false);
        $row = $this->ensureCreateTimeForInsert('channel_account', $row);
        return (int)Db::name('channel_account')->insertGetId($row);
    }

    /**
     * 更新渠道账号
     */
    public function accountUpdate(int $id, array $payload): void
    {
        $this->assertExists('channel_account', $id, '渠道账号不存在');
        $row = $this->normalizeAccountPayload($payload, true);
        if (empty($row)) {
            return;
        }
        Db::name('channel_account')->where('id', $id)->update($row);
    }

    /**
     * 启停渠道账号
     */
    public function accountToggle(int $id, int $status): void
    {
        $this->assertExists('channel_account', $id, '渠道账号不存在');
        if (!in_array($status, [0, 1], true)) {
            throw new BizException('状态仅支持 0/1', 400, 40001);
        }
        Db::name('channel_account')->where('id', $id)->update([
            'status' => $status,
            'update_time' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 测试渠道账号配置
     */
    public function accountTest(int $id): array
    {
        $row = Db::name('channel_account')->where('id', $id)->find();
        if (!$row) {
            throw new BizException('渠道账号不存在', 404, 40401);
        }
        if ((string)($row['app_key'] ?? '') === '' || (string)($row['app_secret_enc'] ?? '') === '') {
            throw new BizException('缺少 app_key/app_secret 配置', 400, 40001);
        }
        return [
            'id' => $id,
            'channel_code' => (string)$row['channel_code'],
            'account_key' => (string)$row['account_key'],
            'status' => 'ok',
            'checked_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * 获取回调签名密钥
     */
    public function getCallbackSecret(string $channelCode, string $accountKey = ''): string
    {
        try {
            $query = Db::name('channel_account')
                ->where('channel_code', $channelCode)
                ->where('status', 1);
            if ($accountKey !== '') {
                $query->where('account_key', $accountKey);
            }
            $row = $query->order('id', 'desc')->find();
            if (!$row) {
                return '';
            }
            return trim((string)($row['callback_secret_enc'] ?? ''));
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function normalizeAccountPayload(array $payload, bool $isUpdate): array
    {
        $channelCode = strtolower(trim((string)($payload['channel_code'] ?? '')));
        $accountName = trim((string)($payload['account_name'] ?? ''));
        $accountKey = trim((string)($payload['account_key'] ?? ''));
        $status = (int)($payload['status'] ?? 1);
        $qpsLimit = max(1, (int)($payload['qps_limit'] ?? 50));

        if (!$isUpdate || array_key_exists('channel_code', $payload)) {
            if (!in_array($channelCode, ['douyin', 'kuaishou', 'tencent_video'], true)) {
                throw new BizException('channel_code 不支持', 400, 40001);
            }
        }
        if (!$isUpdate || array_key_exists('account_name', $payload)) {
            if ($accountName === '') {
                throw new BizException('account_name 不能为空', 400, 40001);
            }
        }
        if (!$isUpdate || array_key_exists('account_key', $payload)) {
            if ($accountKey === '') {
                throw new BizException('account_key 不能为空', 400, 40001);
            }
        }
        if (!$isUpdate || array_key_exists('status', $payload)) {
            if (!in_array($status, [0, 1], true)) {
                throw new BizException('status 仅支持 0/1', 400, 40001);
            }
        }

        $row = [];
        $fieldMap = [
            'channel_code' => $channelCode,
            'account_name' => $accountName,
            'account_key' => $accountKey,
            'app_key' => trim((string)($payload['app_key'] ?? '')),
            'app_secret_enc' => trim((string)($payload['app_secret_enc'] ?? '')),
            'access_token_enc' => trim((string)($payload['access_token_enc'] ?? '')),
            'refresh_token_enc' => trim((string)($payload['refresh_token_enc'] ?? '')),
            'callback_secret_enc' => trim((string)($payload['callback_secret_enc'] ?? '')),
            'status' => $status,
            'qps_limit' => $qpsLimit,
            'expire_time' => trim((string)($payload['expire_time'] ?? '')),
        ];
        foreach ($fieldMap as $field => $value) {
            if (!$isUpdate || array_key_exists($field, $payload)) {
                if ($field === 'expire_time') {
                    $row[$field] = $value === '' ? null : $value;
                } else {
                    $row[$field] = $value;
                }
            }
        }
        if (!$isUpdate || array_key_exists('ext_json', $payload)) {
            $ext = $payload['ext_json'] ?? [];
            $row['ext_json'] = is_array($ext) ? json_encode($ext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        }
        $row['update_time'] = date('Y-m-d H:i:s');
        return $row;
    }
}
