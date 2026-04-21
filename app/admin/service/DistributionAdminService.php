<?php
declare (strict_types = 1);

namespace app\admin\service;

use think\exception\ValidateException;
use think\facade\Db;

class DistributionAdminService extends BaseAdminService
{
    public function recordList(int $page, int $pageSize): array
    {
        $query = Db::name('distribution')->alias('d')
            ->leftJoin('djxs_user u', 'u.id=d.user_id')
            ->leftJoin('djxs_user p', 'p.id=d.parent_id')
            ->field('d.*,u.mobile,u.nickname,p.mobile as parent_mobile,p.nickname as parent_nickname');
        return $this->paginateToArray($query->order('d.id', 'desc'), $page, $pageSize);
    }

    public function withdrawList(array $params, int $page, int $pageSize): array
    {
        $status = $params['status'] ?? '';
        $fields = $this->getTableFields('commission_record');
        $auditRemarkExpr = isset($fields['audit_remark']) ? 'c.audit_remark' : "'' as audit_remark";
        $query = Db::name('commission_record')->alias('c')
            ->leftJoin('djxs_user u', 'u.id=c.user_id')
            ->field('c.id,c.user_id,c.amount,c.status,c.type,c.create_time,c.process_time,' . $auditRemarkExpr . ',u.mobile,u.nickname')
            ->where('c.type', 2);
        if ($status !== '' && $status !== null) {
            $query->where('c.status', (int)$status);
        }
        $result = $this->paginateToArray($query->order('c.id', 'desc'), $page, $pageSize);
        foreach ($result['list'] as &$row) {
            $row['status_text'] = $this->withdrawStatusText((int)($row['status'] ?? 0));
        }
        unset($row);
        return $result;
    }

    public function withdrawAudit(int $id, int $status, string $remark = ''): void
    {
        $remark = trim($remark);
        if ($status === 2 && $remark === '') {
            throw new ValidateException('拒绝时请填写审核备注');
        }
        Db::transaction(function () use ($id, $status, $remark) {
            $record = Db::name('commission_record')->alias('c')->where('c.id', $id)->lock(true)->find();
            if (!$record || (int)($record['type'] ?? 0) !== 2) {
                throw new ValidateException('提现记录不存在');
            }
            if ((int)$record['status'] !== 0) {
                throw new ValidateException('该提现记录已审核，请勿重复操作');
            }

            $update = ['status' => $status, 'process_time' => date('Y-m-d H:i:s')];
            $fields = $this->getTableFields('commission_record');
            if (isset($fields['audit_remark'])) {
                $update['audit_remark'] = $remark;
            }
            Db::name('commission_record')->alias('c')->where('c.id', $id)->update($update);

            // 拒绝提现时退回可用佣金
            if ($status === 2) {
                Db::name('distribution')->alias('d')
                    ->where('d.user_id', (int)$record['user_id'])
                    ->inc('available_commission', (float)$record['amount'])
                    ->update();
            }
        });
    }

    public function configGet(): array
    {
        $row = Db::name('system_config')->whereRaw('`key` = ?', ['distribution_config'])->find();
        if (!$row || !isset($row['value'])) {
            return [];
        }
        $decoded = json_decode((string)$row['value'], true);
        return is_array($decoded) ? $decoded : [];
    }

    public function configSet(array $data): void
    {
        $now = date('Y-m-d H:i:s');
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $exists = Db::name('system_config')->whereRaw('`key` = ?', ['distribution_config'])->find();
        if ($exists) {
            Db::name('system_config')->whereRaw('`key` = ?', ['distribution_config'])->update([
                'value' => $json,
                'update_time' => $now,
            ]);
            return;
        }
        Db::name('system_config')->insert([
            'key' => 'distribution_config',
            'value' => $json,
            'description' => '分销配置',
            'update_time' => $now,
        ]);
    }

    private function withdrawStatusText(int $status): string
    {
        return match ($status) {
            0 => '待审核',
            1 => '已通过',
            2 => '已拒绝',
            default => '未知',
        };
    }
}

