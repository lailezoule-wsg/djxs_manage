<?php
declare (strict_types = 1);

namespace app\admin\service;

use think\facade\Db;

class MemberAdminService extends BaseAdminService
{
    public function levelList(int $page, int $pageSize): array
    {
        $query = Db::name('member_level')->order('id', 'asc');
        return $this->paginateToArray($query, $page, $pageSize);
    }

    public function levelCreate(array $payload): int
    {
        unset($payload['id']);
        return (int)Db::name('member_level')->insertGetId($payload);
    }

    public function levelUpdate(int $id, array $payload): void
    {
        $this->assertExists('member_level', $id, '会员等级不存在');
        unset($payload['id']);
        Db::name('member_level')->where('id', $id)->update($payload);
    }

    public function levelDelete(int $id): void
    {
        $this->assertExists('member_level', $id, '会员等级不存在');
        Db::name('member_level')->where('id', $id)->delete();
    }

    public function userList(array $params, int $page, int $pageSize): array
    {
        $status = $params['status'] ?? '';
        $query = Db::name('member')->alias('m')
            ->leftJoin('djxs_user u', 'u.id=m.user_id')
            ->leftJoin('djxs_member_level l', 'l.id=m.member_level_id')
            ->field('m.*,u.mobile,u.nickname,l.name as level_name');
        if ($status !== '' && $status !== null) {
            $query->where('m.status', (int)$status);
        }
        return $this->paginateToArray($query->order('m.id', 'desc'), $page, $pageSize);
    }
}
