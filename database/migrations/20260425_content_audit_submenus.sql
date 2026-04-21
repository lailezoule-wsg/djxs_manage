-- 内容管理：将“分类标签与审核”细拆为“短剧分类标签审核/小说分类标签审核”两个子菜单
SET NAMES utf8mb4;

-- 父菜单改为目录节点
UPDATE `djxs_admin_menu`
SET
  `name` = '分类标签与审核',
  `path` = '/content-audit',
  `component` = NULL,
  `permission_code` = NULL,
  `update_time` = NOW()
WHERE `id` = 5 OR `path` = '/content-audit';

-- 子菜单：短剧
INSERT INTO `djxs_admin_menu`
(`id`, `parent_id`, `name`, `path`, `component`, `icon`, `permission_code`, `sort`, `visible`, `status`, `create_time`, `update_time`)
VALUES
(24, 5, '短剧分类标签审核', '/content-audit/drama', 'ContentAudit', 'VideoCamera', 'content:drama:manage', 10, 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `parent_id` = VALUES(`parent_id`),
  `name` = VALUES(`name`),
  `path` = VALUES(`path`),
  `component` = VALUES(`component`),
  `icon` = VALUES(`icon`),
  `permission_code` = VALUES(`permission_code`),
  `sort` = VALUES(`sort`),
  `visible` = VALUES(`visible`),
  `status` = VALUES(`status`),
  `update_time` = VALUES(`update_time`);

-- 子菜单：小说
INSERT INTO `djxs_admin_menu`
(`id`, `parent_id`, `name`, `path`, `component`, `icon`, `permission_code`, `sort`, `visible`, `status`, `create_time`, `update_time`)
VALUES
(25, 5, '小说分类标签审核', '/content-audit/novel', 'ContentAudit', 'Reading', 'content:novel:manage', 20, 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `parent_id` = VALUES(`parent_id`),
  `name` = VALUES(`name`),
  `path` = VALUES(`path`),
  `component` = VALUES(`component`),
  `icon` = VALUES(`icon`),
  `permission_code` = VALUES(`permission_code`),
  `sort` = VALUES(`sort`),
  `visible` = VALUES(`visible`),
  `status` = VALUES(`status`),
  `update_time` = VALUES(`update_time`);

-- 子菜单权限码与拆分后的权限点对齐
UPDATE `djxs_admin_menu`
SET `permission_code` = 'content:drama:category:manage', `update_time` = NOW()
WHERE `id` = 24 OR `path` = '/content-audit/drama';

UPDATE `djxs_admin_menu`
SET `permission_code` = 'content:novel:category:manage', `update_time` = NOW()
WHERE `id` = 25 OR `path` = '/content-audit/novel';
