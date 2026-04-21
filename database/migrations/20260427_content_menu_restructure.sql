-- 内容管理重构：
-- 1) 去除“分类标签与审核”菜单
-- 2) 短剧管理下新增：短剧列表 / 短剧分类与标签 / 短剧审核
-- 3) 小说管理下新增：小说列表 / 小说分类与标签 / 小说审核
SET NAMES utf8mb4;

-- 父菜单改为目录节点（不直接对应页面）
UPDATE `djxs_admin_menu`
SET
  `parent_id` = 17,
  `name` = '短剧管理',
  `path` = '/dramas',
  `component` = NULL,
  `permission_code` = NULL,
  `sort` = 10,
  `visible` = 1,
  `status` = 1,
  `update_time` = NOW()
WHERE `id` = 3 OR `path` = '/dramas';

UPDATE `djxs_admin_menu`
SET
  `parent_id` = 17,
  `name` = '小说管理',
  `path` = '/novels',
  `component` = NULL,
  `permission_code` = NULL,
  `sort` = 20,
  `visible` = 1,
  `status` = 1,
  `update_time` = NOW()
WHERE `id` = 4 OR `path` = '/novels';

-- 去除旧菜单入口
UPDATE `djxs_admin_menu`
SET
  `status` = 0,
  `visible` = 0,
  `update_time` = NOW()
WHERE `id` = 5 OR `path` = '/content-audit';

-- 清理旧的拆分子菜单（若存在）
UPDATE `djxs_admin_menu`
SET
  `status` = 0,
  `visible` = 0,
  `update_time` = NOW()
WHERE `path` IN ('/content-audit/drama', '/content-audit/novel');

-- 短剧子菜单
INSERT INTO `djxs_admin_menu`
(`id`, `parent_id`, `name`, `path`, `component`, `icon`, `permission_code`, `sort`, `visible`, `status`, `create_time`, `update_time`)
VALUES
(24, 3, '短剧列表', '/dramas/list', 'DramaList', 'List', 'content:drama:manage', 10, 1, 1, NOW(), NOW())
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

INSERT INTO `djxs_admin_menu`
(`id`, `parent_id`, `name`, `path`, `component`, `icon`, `permission_code`, `sort`, `visible`, `status`, `create_time`, `update_time`)
VALUES
(25, 3, '短剧分类与标签', '/dramas/meta', 'ContentAudit', 'Collection', 'content:drama:category:manage', 20, 1, 1, NOW(), NOW())
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

INSERT INTO `djxs_admin_menu`
(`id`, `parent_id`, `name`, `path`, `component`, `icon`, `permission_code`, `sort`, `visible`, `status`, `create_time`, `update_time`)
VALUES
(26, 3, '短剧审核', '/dramas/audit', 'ContentAudit', 'DocumentChecked', 'content:drama:audit', 30, 1, 1, NOW(), NOW())
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

-- 小说子菜单
INSERT INTO `djxs_admin_menu`
(`id`, `parent_id`, `name`, `path`, `component`, `icon`, `permission_code`, `sort`, `visible`, `status`, `create_time`, `update_time`)
VALUES
(27, 4, '小说列表', '/novels/list', 'NovelList', 'List', 'content:novel:manage', 10, 1, 1, NOW(), NOW())
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

INSERT INTO `djxs_admin_menu`
(`id`, `parent_id`, `name`, `path`, `component`, `icon`, `permission_code`, `sort`, `visible`, `status`, `create_time`, `update_time`)
VALUES
(28, 4, '小说分类与标签', '/novels/meta', 'ContentAudit', 'Collection', 'content:novel:category:manage', 20, 1, 1, NOW(), NOW())
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

INSERT INTO `djxs_admin_menu`
(`id`, `parent_id`, `name`, `path`, `component`, `icon`, `permission_code`, `sort`, `visible`, `status`, `create_time`, `update_time`)
VALUES
(29, 4, '小说审核', '/novels/audit', 'ContentAudit', 'DocumentChecked', 'content:novel:audit', 30, 1, 1, NOW(), NOW())
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
