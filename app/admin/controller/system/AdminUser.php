<?php
declare (strict_types = 1);

namespace app\admin\controller\system;

use app\admin\controller\BaseAdminController;
use app\admin\model\AdminUser as AdminUserModel;
use app\admin\service\RbacService;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 管理端管理员账号管理接口
 */
class AdminUser extends BaseAdminController
{
    /**
     * 获取 RBAC 服务实例
     */
    private function rbac(): RbacService
    {
        return new RbacService();
    }

    /**
     * 分页查询管理员列表
     */
    public function list()
    {
        try {
            [$page, $pageSize] = $this->pageParams();
            $includeSystem = (int)$this->request->param('include_system', 0) === 1;
            $query = AdminUserModel::order('id', 'desc');
            if (!$includeSystem) {
                $protectedIds = Db::name('admin_user_role')->alias('ur')
                    ->join('admin_role r', 'r.id = ur.role_id')
                    ->where('r.code', RbacService::SUPER_ROLE_CODE)
                    ->column('ur.admin_user_id');
                $protectedIds = array_values(array_unique(array_filter(array_map('intval', $protectedIds))));
                if ($protectedIds !== []) {
                    $query->whereNotIn('id', $protectedIds);
                }
            }
            $total = (int)(clone $query)->count();
            $list = $query->page($page, $pageSize)->select()->toArray();
            $rbac = $this->rbac();
            foreach ($list as &$row) {
                unset($row['password']);
                $row['is_protected'] = $rbac->isAdminUserProtected((int)$row['id']);
            }
            unset($row);

            $userIds = array_values(array_filter(array_map('intval', array_column($list, 'id'))));
            $rolesByUser = [];
            if ($userIds !== []) {
                $links = Db::name('admin_user_role')->alias('ur')
                    ->join('admin_role r', 'r.id = ur.role_id')
                    ->whereIn('ur.admin_user_id', $userIds)
                    ->field('ur.admin_user_id,r.id as id,r.code,r.name')
                    ->order('r.id', 'asc')
                    ->select()
                    ->toArray();
                foreach ($links as $link) {
                    $uid = (int)($link['admin_user_id'] ?? 0);
                    if ($uid <= 0) {
                        continue;
                    }
                    $rolesByUser[$uid][] = [
                        'id' => (int)($link['id'] ?? 0),
                        'code' => (string)($link['code'] ?? ''),
                        'name' => (string)($link['name'] ?? ''),
                    ];
                }
            }
            foreach ($list as &$row) {
                $uid = (int)$row['id'];
                $roles = $rolesByUser[$uid] ?? [];
                $row['roles'] = $roles;
                $row['role_names'] = $roles === []
                    ? ''
                    : implode('、', array_map(static fn ($r) => (string)($r['name'] !== '' ? $r['name'] : $r['code']), $roles));
            }
            unset($row);
            return $this->success([
                'list' => $list,
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize,
            ], '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 新增管理员账号
     */
    public function create()
    {
        try {
            $data = $this->request->post();
            $username = trim((string)($data['username'] ?? ''));
            $password = (string)($data['password'] ?? '');
            if ($username === '' || $password === '') {
                throw new ValidateException('账号与密码必填');
            }
            if (strlen($password) < 6) {
                throw new ValidateException('密码至少 6 位');
            }
            if (AdminUserModel::where('username', $username)->find()) {
                throw new ValidateException('账号已存在');
            }
            $row = new AdminUserModel();
            $row->username = $username;
            $row->password = $password;
            $row->real_name = isset($data['real_name']) ? trim((string)$data['real_name']) : null;
            $row->mobile = isset($data['mobile']) ? trim((string)$data['mobile']) : null;
            $row->status = (int)($data['status'] ?? 1);
            $row->save();
            return $this->success(['id' => (int)$row->id], '创建成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 更新管理员账号信息
     */
    public function update(int $id)
    {
        try {
            $u = AdminUserModel::find($id);
            if (!$u) {
                throw new ValidateException('管理员不存在');
            }
            if ($this->rbac()->isAdminUserProtected($id)) {
                throw new ValidateException('系统管理员不可修改');
            }
            $data = $this->requestPayload();
            if (isset($data['real_name'])) {
                $u->real_name = $data['real_name'] !== '' ? trim((string)$data['real_name']) : null;
            }
            if (isset($data['mobile'])) {
                $u->mobile = $data['mobile'] !== '' ? trim((string)$data['mobile']) : null;
            }
            if (isset($data['status'])) {
                $u->status = (int)$data['status'];
            }
            if (!empty($data['password'])) {
                $pwd = (string)$data['password'];
                if (strlen($pwd) < 6) {
                    throw new ValidateException('密码至少 6 位');
                }
                $u->password = $pwd;
            }
            $u->save();
            return $this->success([], '更新成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 删除管理员账号
     */
    public function delete(int $id)
    {
        try {
            $u = AdminUserModel::find($id);
            if (!$u) {
                throw new ValidateException('管理员不存在');
            }
            if ($this->rbac()->isAdminUserProtected($id)) {
                throw new ValidateException('系统管理员不可删除');
            }
            Db::transaction(function () use ($id) {
                Db::name('admin_user_role')->where('admin_user_id', $id)->delete();
                Db::name('admin_user')->where('id', $id)->delete();
            });
            return $this->success([], '删除成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 获取管理员角色分配
     */
    public function roles(int $id)
    {
        try {
            $u = AdminUserModel::find($id);
            if (!$u) {
                throw new ValidateException('管理员不存在');
            }
            $ids = Db::name('admin_user_role')->where('admin_user_id', $id)->column('role_id');
            return $this->success([
                'admin_user_id' => $id,
                'role_ids' => array_map('intval', $ids),
                'is_protected' => $this->rbac()->isAdminUserProtected($id),
            ], '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 保存管理员角色分配
     */
    public function saveRoles(int $id)
    {
        try {
            $u = AdminUserModel::find($id);
            if (!$u) {
                throw new ValidateException('管理员不存在');
            }
            if ($this->rbac()->isAdminUserProtected($id)) {
                throw new ValidateException('系统管理员角色分配不可修改');
            }
            $data = $this->requestPayload();
            $ids = (array)($data['role_ids'] ?? []);
            $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
            Db::transaction(function () use ($id, $ids) {
                Db::name('admin_user_role')->where('admin_user_id', $id)->delete();
                foreach ($ids as $rid) {
                    if ($rid <= 0) {
                        continue;
                    }
                    Db::name('admin_user_role')->insert([
                        'admin_user_id' => $id,
                        'role_id' => $rid,
                    ]);
                }
            });
            return $this->success([], '保存成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }
}
