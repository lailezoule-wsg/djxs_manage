<?php
declare (strict_types = 1);

namespace app\admin\controller\system;

use app\admin\controller\BaseAdminController;
use app\admin\model\AdminPermission;
use think\exception\ValidateException;

class Permission extends BaseAdminController
{
    public function list()
    {
        try {
            $list = AdminPermission::order('sort', 'asc')->order('id', 'asc')->select()->toArray();
            return $this->success(['list' => $list], '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function create()
    {
        try {
            $data = $this->request->post();
            $code = trim((string)($data['code'] ?? ''));
            $name = trim((string)($data['name'] ?? ''));
            if ($code === '' || $name === '') {
                throw new ValidateException('code 与 name 必填');
            }
            if (AdminPermission::where('code', $code)->find()) {
                throw new ValidateException('权限码已存在');
            }
            $row = new AdminPermission();
            $row->code = $code;
            $row->name = $name;
            $row->type = (int)($data['type'] ?? 1);
            $row->parent_id = (int)($data['parent_id'] ?? 0);
            $row->sort = (int)($data['sort'] ?? 0);
            $row->status = (int)($data['status'] ?? 1);
            $row->remark = isset($data['remark']) ? (string)$data['remark'] : null;
            $row->save();
            return $this->success(['id' => (int)$row->id], '创建成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function update(int $id)
    {
        try {
            $row = AdminPermission::find($id);
            if (!$row) {
                throw new ValidateException('记录不存在');
            }
            $data = $this->requestPayload();
            if (isset($data['name'])) {
                $row->name = trim((string)$data['name']);
            }
            if (isset($data['type'])) {
                $row->type = (int)$data['type'];
            }
            if (isset($data['parent_id'])) {
                $row->parent_id = (int)$data['parent_id'];
            }
            if (isset($data['sort'])) {
                $row->sort = (int)$data['sort'];
            }
            if (isset($data['status'])) {
                $row->status = (int)$data['status'];
            }
            if (array_key_exists('remark', $data)) {
                $row->remark = $data['remark'] !== null ? (string)$data['remark'] : null;
            }
            if (isset($data['code'])) {
                $newCode = trim((string)$data['code']);
                if ($newCode !== '' && $newCode !== $row->code) {
                    if (AdminPermission::where('code', $newCode)->where('id', '<>', $id)->find()) {
                        throw new ValidateException('权限码已存在');
                    }
                    $row->code = $newCode;
                }
            }
            $row->save();
            return $this->success([], '更新成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function delete(int $id)
    {
        try {
            $row = AdminPermission::find($id);
            if (!$row) {
                throw new ValidateException('记录不存在');
            }
            \think\facade\Db::name('admin_role_permission')->where('permission_id', $id)->delete();
            $row->delete();
            return $this->success([], '删除成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }
}
