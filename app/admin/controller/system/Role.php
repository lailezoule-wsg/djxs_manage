<?php
declare (strict_types = 1);

namespace app\admin\controller\system;

use app\admin\controller\BaseAdminController;
use app\admin\model\AdminRole;
use app\admin\service\RbacService;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 管理端角色管理接口
 */
class Role extends BaseAdminController
{
    /**
     * 获取角色列表
     */
    public function list()
    {
        try {
            $list = AdminRole::order('id', 'asc')->select()->toArray();
            foreach ($list as &$row) {
                $row['is_system'] = ((string)($row['code'] ?? '')) === RbacService::SUPER_ROLE_CODE;
            }
            unset($row);
            return $this->success(['list' => $list], '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 新增角色
     */
    public function create()
    {
        try {
            $data = $this->request->post();
            $code = trim((string)($data['code'] ?? ''));
            $name = trim((string)($data['name'] ?? ''));
            if ($code === '' || $name === '') {
                throw new ValidateException('编码与名称必填');
            }
            if ($code === RbacService::SUPER_ROLE_CODE) {
                throw new ValidateException('不可使用内置超级管理员编码');
            }
            if (AdminRole::where('code', $code)->find()) {
                throw new ValidateException('编码已存在');
            }
            $row = new AdminRole();
            $row->code = $code;
            $row->name = $name;
            $row->status = (int)($data['status'] ?? 1);
            $row->create_time = date('Y-m-d H:i:s');
            $row->save();
            return $this->success(['id' => (int)$row->id], '创建成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 更新角色
     */
    public function update(int $id)
    {
        try {
            $role = AdminRole::find($id);
            if (!$role) {
                throw new ValidateException('角色不存在');
            }
            if ((string)$role->code === RbacService::SUPER_ROLE_CODE) {
                throw new ValidateException('内置超级管理员角色不可修改');
            }
            $data = $this->requestPayload();
            if (isset($data['name'])) {
                $role->name = trim((string)$data['name']);
            }
            if (isset($data['status'])) {
                $role->status = (int)$data['status'];
            }
            if (isset($data['code'])) {
                $newCode = trim((string)$data['code']);
                if ($newCode !== '' && $newCode !== $role->code) {
                    if ($newCode === RbacService::SUPER_ROLE_CODE) {
                        throw new ValidateException('不可修改为超级管理员编码');
                    }
                    if (AdminRole::where('code', $newCode)->where('id', '<>', $id)->find()) {
                        throw new ValidateException('编码已存在');
                    }
                    $role->code = $newCode;
                }
            }
            $role->save();
            return $this->success([], '更新成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 删除角色
     */
    public function delete(int $id)
    {
        try {
            $role = AdminRole::find($id);
            if (!$role) {
                throw new ValidateException('角色不存在');
            }
            if ((string)$role->code === RbacService::SUPER_ROLE_CODE) {
                throw new ValidateException('内置超级管理员角色不可删除');
            }
            $cnt = (int)Db::name('admin_user_role')->where('role_id', $id)->count();
            if ($cnt > 0) {
                throw new ValidateException('仍有管理员绑定该角色，无法删除');
            }
            Db::name('admin_role_permission')->where('role_id', $id)->delete();
            $role->delete();
            return $this->success([], '删除成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 获取角色权限分配
     */
    public function permissions(int $id)
    {
        try {
            $role = AdminRole::find($id);
            if (!$role) {
                throw new ValidateException('角色不存在');
            }
            $ids = Db::name('admin_role_permission')->where('role_id', $id)->column('permission_id');
            return $this->success([
                'role_id' => $id,
                'permission_ids' => array_map('intval', $ids),
            ], '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 保存角色权限分配
     */
    public function savePermissions(int $id)
    {
        try {
            $role = AdminRole::find($id);
            if (!$role) {
                throw new ValidateException('角色不存在');
            }
            if ((string)$role->code === RbacService::SUPER_ROLE_CODE) {
                throw new ValidateException('超级管理员角色权限不可修改');
            }
            $data = $this->requestPayload();
            $ids = (array)($data['permission_ids'] ?? []);
            $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
            Db::transaction(function () use ($id, $ids) {
                Db::name('admin_role_permission')->where('role_id', $id)->delete();
                foreach ($ids as $pid) {
                    if ($pid <= 0) {
                        continue;
                    }
                    Db::name('admin_role_permission')->insert([
                        'role_id' => $id,
                        'permission_id' => $pid,
                    ]);
                }
            });
            return $this->success([], '保存成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }
}
