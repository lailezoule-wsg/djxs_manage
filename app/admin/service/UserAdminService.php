<?php
declare (strict_types = 1);

namespace app\admin\service;

use think\exception\ValidateException;
use think\facade\Db;

class UserAdminService extends BaseAdminService
{
    public function list(array $params, int $page, int $pageSize): array
    {
        $mobile = trim((string)($params['mobile'] ?? ''));
        $nickname = trim((string)($params['nickname'] ?? ''));
        $status = $params['status'] ?? '';

        $query = Db::name('user')->alias('u')
            ->leftJoin('djxs_member m', 'm.user_id = u.id')
            ->leftJoin('djxs_member_level l', 'l.id = m.member_level_id')
            ->field('u.*,m.member_level_id,m.status as member_status,l.name as member_level_name');
        if ($mobile !== '') {
            $query->whereLike('u.mobile', '%' . $mobile . '%');
        }
        if ($nickname !== '') {
            $query->whereLike('u.nickname', '%' . $nickname . '%');
        }
        if ($status !== '' && $status !== null) {
            $query->where('u.status', (int)$status);
        }
        $result = $this->paginateToArray($query->order('u.id', 'desc'), $page, $pageSize);
        foreach ($result['list'] as &$row) {
            if (is_array($row)) {
                unset($row['password'], $row['salt']);
            }
        }
        unset($row);

        return $result;
    }

    public function detail(int $id): array
    {
        $row = Db::name('user')->alias('u')
            ->leftJoin('djxs_member m', 'm.user_id = u.id')
            ->leftJoin('djxs_member_level l', 'l.id = m.member_level_id')
            ->field('u.*,m.member_level_id,m.status as member_status,m.start_time as member_start_time,m.end_time as member_end_time,l.name as member_level_name')
            ->where('u.id', $id)
            ->find();
        if (!$row) {
            throw new ValidateException('用户不存在');
        }
        unset($row['password'], $row['salt']);
        return $row;
    }

    public function updateStatus(int $id, int $status): void
    {
        $this->assertExists('user', $id, '用户不存在');
        Db::name('user')->where('id', $id)->update(['status' => $status]);
    }

    public function deviceList(array $params, int $page, int $pageSize): array
    {
        $userId = (int)($params['user_id'] ?? 0);
        $query = Db::name('device')->alias('d')
            ->leftJoin('djxs_user u', 'u.id = d.user_id')
            ->field('d.*,u.mobile,u.nickname');
        if ($userId > 0) {
            $query->where('d.user_id', $userId);
        }
        return $this->paginateToArray($query->order('d.id', 'desc'), $page, $pageSize);
    }

    public function deviceDelete(int $id): void
    {
        $this->assertExists('device', $id, '设备不存在');
        Db::name('device')->where('id', $id)->delete();
    }
}
