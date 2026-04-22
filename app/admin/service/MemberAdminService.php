<?php
declare (strict_types = 1);

namespace app\admin\service;

use think\facade\Db;

/**
 * 管理端会员业务服务
 */
class MemberAdminService extends BaseAdminService
{
    /**
     * 分页查询会员等级
     */
    public function levelList(int $page, int $pageSize): array
    {
        $query = Db::name('member_level')->order('id', 'asc');
        return $this->paginateToArray($query, $page, $pageSize);
    }

    /**
     * 创建会员等级
     */
    public function levelCreate(array $payload): int
    {
        unset($payload['id']);
        return (int)Db::name('member_level')->insertGetId($payload);
    }

    /**
     * 更新会员等级
     */
    public function levelUpdate(int $id, array $payload): void
    {
        $this->assertExists('member_level', $id, '会员等级不存在');
        unset($payload['id']);
        Db::name('member_level')->where('id', $id)->update($payload);
    }

    /**
     * 删除会员等级
     */
    public function levelDelete(int $id): void
    {
        $this->assertExists('member_level', $id, '会员等级不存在');
        Db::name('member_level')->where('id', $id)->delete();
    }

    /**
     * 分页查询会员用户
     */
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
