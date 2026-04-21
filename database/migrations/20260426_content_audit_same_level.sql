-- 将“分类标签与审核”恢复为与“短剧管理/小说管理”同级（不再做子菜单展开）
SET NAMES utf8mb4;

-- 主菜单恢复为可直达页面
UPDATE `djxs_admin_menu`
SET
  `parent_id` = 17,
  `name` = '分类标签与审核',
  `path` = '/content-audit',
  `component` = 'ContentAudit',
  `permission_code` = 'content:audit',
  `sort` = 30,
  `visible` = 1,
  `status` = 1,
  `update_time` = NOW()
WHERE `id` = 5 OR `path` = '/content-audit';

-- 清理上一次拆分出的二级菜单（保留历史记录但不显示）
UPDATE `djxs_admin_menu`
SET
  `status` = 0,
  `visible` = 0,
  `update_time` = NOW()
WHERE `id` IN (24, 25) OR `path` IN ('/content-audit/drama', '/content-audit/novel');
