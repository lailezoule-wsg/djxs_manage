<?php
declare (strict_types = 1);

namespace app\admin\controller\system;

use app\admin\controller\BaseAdminController;
use app\admin\model\AdminMenu;
use think\exception\ValidateException;

/**
 * 管理端菜单管理接口
 */
class Menu extends BaseAdminController
{
    /**
     * 获取菜单列表
     */
    public function list()
    {
        try {
            $list = AdminMenu::order('sort', 'asc')->order('id', 'asc')->select()->toArray();
            return $this->success(['list' => $list], '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 新增菜单
     */
    public function create()
    {
        try {
            $data = $this->request->post();
            $name = trim((string)($data['name'] ?? ''));
            $path = trim((string)($data['path'] ?? ''));
            if ($name === '' || $path === '') {
                throw new ValidateException('name 与 path 必填');
            }
            $row = new AdminMenu();
            $row->parent_id = (int)($data['parent_id'] ?? 0);
            $row->name = $name;
            $row->path = $path;
            $row->component = isset($data['component']) ? (string)$data['component'] : null;
            $row->icon = isset($data['icon']) ? (string)$data['icon'] : null;
            $row->permission_code = isset($data['permission_code']) && $data['permission_code'] !== ''
                ? (string)$data['permission_code'] : null;
            $row->sort = (int)($data['sort'] ?? 0);
            $row->visible = (int)($data['visible'] ?? 1);
            $row->status = (int)($data['status'] ?? 1);
            $row->save();
            return $this->success(['id' => (int)$row->id], '创建成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 更新菜单
     */
    public function update(int $id)
    {
        try {
            $row = AdminMenu::find($id);
            if (!$row) {
                throw new ValidateException('记录不存在');
            }
            $data = $this->requestPayload();
            foreach (['parent_id', 'sort', 'visible', 'status'] as $k) {
                if (isset($data[$k])) {
                    $row->$k = (int)$data[$k];
                }
            }
            if (isset($data['name'])) {
                $row->name = trim((string)$data['name']);
            }
            if (isset($data['path'])) {
                $row->path = trim((string)$data['path']);
            }
            if (array_key_exists('component', $data)) {
                $row->component = $data['component'] !== null && $data['component'] !== ''
                    ? (string)$data['component'] : null;
            }
            if (array_key_exists('icon', $data)) {
                $row->icon = $data['icon'] !== null && $data['icon'] !== '' ? (string)$data['icon'] : null;
            }
            if (array_key_exists('permission_code', $data)) {
                $row->permission_code = $data['permission_code'] !== null && $data['permission_code'] !== ''
                    ? (string)$data['permission_code'] : null;
            }
            $row->save();
            return $this->success([], '更新成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 删除菜单
     */
    public function delete(int $id)
    {
        try {
            $row = AdminMenu::find($id);
            if (!$row) {
                throw new ValidateException('记录不存在');
            }
            if (AdminMenu::where('parent_id', $id)->count() > 0) {
                throw new ValidateException('请先删除子菜单');
            }
            $row->delete();
            return $this->success([], '删除成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }
}
