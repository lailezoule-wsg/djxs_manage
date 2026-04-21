<?php
declare (strict_types = 1);

namespace app\admin\service;

use app\admin\model\AdminMenu;
use think\facade\Db;

class RbacService
{
    public const SUPER_ROLE_CODE = 'super_admin';
    private const LEGACY_CHANNEL_DISTRIBUTION_CODE = 'marketing:channel-distribution:manage';
    private const CHANNEL_DISTRIBUTION_COMPAT_CODES = [
        'marketing:channel-distribution:task:view',
        'marketing:channel-distribution:task:create',
        'marketing:channel-distribution:task:audit',
        'marketing:channel-distribution:task:retry',
        'marketing:channel-distribution:callback:view',
        'marketing:channel-distribution:account:manage',
    ];

    /**
     * 某些菜单需要按“任一子权限”决定可见性（避免拆分权限后菜单挂空）
     */
    private function hasAnyMenuPermission(string $path, array $codeSet): bool
    {
        $path = trim($path);
        if ($path === '/content-audit') {
            return isset($codeSet['content:audit'])
                || isset($codeSet['content:drama:category:manage'])
                || isset($codeSet['content:drama:tag:manage'])
                || isset($codeSet['content:drama:audit'])
                || isset($codeSet['content:novel:category:manage'])
                || isset($codeSet['content:novel:tag:manage'])
                || isset($codeSet['content:novel:audit']);
        }
        if ($path === '/content-audit/drama') {
            return isset($codeSet['content:drama:category:manage'])
                || isset($codeSet['content:drama:tag:manage'])
                || isset($codeSet['content:drama:audit'])
                || isset($codeSet['content:drama:manage']);
        }
        if ($path === '/dramas/meta') {
            return isset($codeSet['content:drama:category:manage'])
                || isset($codeSet['content:drama:tag:manage']);
        }
        if ($path === '/dramas/audit') {
            return isset($codeSet['content:drama:audit']) || isset($codeSet['content:drama:manage']);
        }
        if ($path === '/content-audit/novel') {
            return isset($codeSet['content:novel:category:manage'])
                || isset($codeSet['content:novel:tag:manage'])
                || isset($codeSet['content:novel:audit'])
                || isset($codeSet['content:novel:manage']);
        }
        if ($path === '/novels/meta') {
            return isset($codeSet['content:novel:category:manage'])
                || isset($codeSet['content:novel:tag:manage']);
        }
        if ($path === '/novels/audit') {
            return isset($codeSet['content:novel:audit']) || isset($codeSet['content:novel:manage']);
        }
        return false;
    }

    /**
     * 菜单展示名归一（不依赖是否已跑 SQL 迁移；与种子 id/path 一致）
     */
    private function normalizeMenuDisplayName(array $node): string
    {
        $id = (int)($node['id'] ?? 0);
        $path = (string)($node['path'] ?? '');
        $raw = trim((string)($node['name'] ?? ''));

        if ($id === 20 || $path === '/user-center') {
            return '用户管理';
        }
        if ($id === 7 || $path === '/members') {
            return '会员等级';
        }
        if ($raw === '用户与会员') {
            return '用户管理';
        }
        if ($raw === '会员管理' && ($path === '/members' || $id === 7)) {
            return '会员等级';
        }

        return $raw !== '' ? $raw : (string)($node['name'] ?? '');
    }

    /**
     * @param array<int, string> $roles
     */
    public function isSuperAdmin(array $roles): bool
    {
        return in_array(self::SUPER_ROLE_CODE, $roles, true);
    }

    /**
     * 管理员拥有的权限码（不含 *，超管在外层处理）
     *
     * @return list<string>
     */
    public function getPermissionCodesForAdmin(int $adminId): array
    {
        if ($adminId <= 0) {
            return [];
        }
        $codes = Db::name('admin_permission')->alias('p')
            ->join('admin_role_permission rp', 'rp.permission_id = p.id')
            ->join('admin_user_role ur', 'ur.role_id = rp.role_id')
            ->join('admin_role r', 'r.id = ur.role_id')
            ->where('ur.admin_user_id', $adminId)
            ->where('p.status', 1)
            ->where('r.status', 1)
            ->column('p.code');
        $normalized = array_values(array_unique(array_map('strval', $codes)));
        return $this->expandCompatibilityPermissions($normalized);
    }

    /**
     * 全部启用中的权限码
     *
     * @return list<string>
     */
    public function getAllEnabledPermissionCodes(): array
    {
        $codes = Db::name('admin_permission')->where('status', 1)->column('code');
        $normalized = array_values(array_map('strval', $codes));
        return $this->expandCompatibilityPermissions($normalized);
    }

    /**
     * 兼容历史权限：如果已分配旧权限码，则自动扩展为新拆分权限码。
     *
     * @param list<string> $codes
     * @return list<string>
     */
    private function expandCompatibilityPermissions(array $codes): array
    {
        if (in_array(self::LEGACY_CHANNEL_DISTRIBUTION_CODE, $codes, true)) {
            $codes = array_merge($codes, self::CHANNEL_DISTRIBUTION_COMPAT_CODES);
        }
        return array_values(array_unique($codes));
    }

    /**
     * 按权限过滤后的菜单树（path 与前端路由一致）
     *
     * @param list<string> $permissionCodes 已合并权限码；含 * 表示全部
     * @return list<array<string, mixed>>
     */
    public function buildMenuTreeForCodes(array $permissionCodes): array
    {
        $allAccess = in_array('*', $permissionCodes, true);
        $codeSet = array_flip($permissionCodes);

        $rows = AdminMenu::where('status', 1)
            ->order('sort', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        $byParent = [];
        foreach ($rows as $row) {
            $pid = (int)$row['parent_id'];
            $byParent[$pid][] = $row;
        }

        $build = function (int $parentId) use (&$build, &$byParent, $allAccess, $codeSet): array {
            $out = [];
            foreach ($byParent[$parentId] ?? [] as $node) {
                $perm = $node['permission_code'];
                $path = (string)($node['path'] ?? '');
                $component = $node['component'] !== null ? (string)$node['component'] : '';
                $childTrees = $build((int)$node['id']);
                $selfOk = $allAccess
                    || $perm === null
                    || $perm === ''
                    || isset($codeSet[(string)$perm])
                    || $this->hasAnyMenuPermission($path, $codeSet);
                $visible = (int)$node['visible'] === 1;

                if (!empty($childTrees)) {
                    $item = [
                        'id' => (int)$node['id'],
                        'name' => $this->normalizeMenuDisplayName($node),
                        'path' => $path,
                        'component' => $component,
                        'icon' => $node['icon'] !== null ? (string)$node['icon'] : '',
                        'permission_code' => $perm !== null ? (string)$perm : null,
                        'children' => $childTrees,
                    ];
                    $out[] = $item;
                    continue;
                }

                // 目录节点（无组件）在没有可见子菜单时不渲染，避免层级错位
                if ($component === '') {
                    continue;
                }

                if ($selfOk && $visible) {
                    $out[] = [
                        'id' => (int)$node['id'],
                        'name' => $this->normalizeMenuDisplayName($node),
                        'path' => $path,
                        'component' => $component,
                        'icon' => $node['icon'] !== null ? (string)$node['icon'] : '',
                        'permission_code' => $perm !== null ? (string)$perm : null,
                    ];
                }
            }
            return $out;
        };

        return $build(0);
    }

    /** 是否为内置超级管理员角色 */
    public function isSuperAdminRoleId(int $roleId): bool
    {
        if ($roleId <= 0) {
            return false;
        }
        $code = (string)Db::name('admin_role')->where('id', $roleId)->value('code');
        return $code === self::SUPER_ROLE_CODE;
    }

    /** 是否绑定超级管理员角色（不可编辑/删除账号与角色分配） */
    public function isAdminUserProtected(int $adminUserId): bool
    {
        if ($adminUserId <= 0) {
            return false;
        }
        return Db::name('admin_user_role')->alias('ur')
                ->join('admin_role r', 'r.id = ur.role_id')
                ->where('ur.admin_user_id', $adminUserId)
                ->where('r.code', self::SUPER_ROLE_CODE)
                ->count() > 0;
    }
}
