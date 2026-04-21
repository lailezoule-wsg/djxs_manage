-- 菜单与 RBAC 扩展表 + 种子数据（依赖 djxs_admin_role id=1 super_admin）
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `djxs_admin_permission` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(64) NOT NULL COMMENT '权限标识',
  `name` varchar(64) NOT NULL COMMENT '展示名称',
  `type` tinyint NOT NULL DEFAULT '1' COMMENT '1菜单 2按钮 3接口',
  `parent_id` int unsigned NOT NULL DEFAULT '0',
  `sort` int NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `remark` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_parent` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理端权限点';

CREATE TABLE IF NOT EXISTS `djxs_admin_menu` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int unsigned NOT NULL DEFAULT '0',
  `name` varchar(64) NOT NULL,
  `path` varchar(128) NOT NULL,
  `component` varchar(128) DEFAULT NULL,
  `icon` varchar(64) DEFAULT NULL,
  `permission_code` varchar(64) DEFAULT NULL,
  `sort` int NOT NULL DEFAULT '0',
  `visible` tinyint(1) NOT NULL DEFAULT '1',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `create_time` datetime NOT NULL,
  `update_time` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_perm` (`permission_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理端菜单';

CREATE TABLE IF NOT EXISTS `djxs_admin_role_permission` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int unsigned NOT NULL,
  `permission_id` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_role_perm` (`role_id`,`permission_id`),
  KEY `fk_perm` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色权限';

-- 权限点（扁平 + 分组 parent_id）
INSERT INTO `djxs_admin_permission` (`id`, `code`, `name`, `type`, `parent_id`, `sort`, `status`) VALUES
(1, 'dashboard:view', '仪表盘', 1, 0, 10, 1),
(2, 'user:manage', '用户管理', 1, 0, 20, 1),
(3, 'device:manage', '设备管理', 1, 0, 30, 1),
(4, 'content:drama:manage', '短剧管理', 1, 0, 40, 1),
(5, 'content:episode:manage', '剧集管理', 1, 0, 50, 1),
(6, 'content:novel:manage', '小说管理', 1, 0, 60, 1),
(7, 'content:chapter:manage', '章节管理', 1, 0, 70, 1),
(8, 'content:category:manage', '分类管理', 1, 0, 80, 1),
(9, 'content:tag:manage', '标签管理', 1, 0, 90, 1),
(10, 'content:audit', '内容审核', 1, 0, 100, 1),
(11, 'order:manage', '订单查询与统计', 1, 0, 110, 1),
(12, 'order:refund', '订单退款', 1, 0, 120, 1),
(13, 'member:manage', '会员等级', 1, 0, 130, 1),
(14, 'distribution:manage', '分销管理', 1, 0, 140, 1),
(15, 'ad:position:manage', '广告位管理', 1, 0, 150, 1),
(16, 'ad:manage', '广告管理', 1, 0, 160, 1),
(17, 'statistics:view', '数据统计', 1, 0, 170, 1),
(18, 'config:manage', '系统配置', 1, 0, 180, 1),
(19, 'system:job:view', '系统任务状态', 1, 0, 190, 1),
(20, 'system:permission:manage', '权限点管理', 1, 0, 200, 1),
(21, 'system:menu:manage', '菜单管理', 1, 0, 210, 1),
(22, 'system:role:manage', '角色管理', 1, 0, 220, 1),
(23, 'system:admin-user:manage', '管理员账号', 1, 0, 230, 1),
(24, 'content:drama:category:manage', '短剧分类管理', 1, 0, 240, 1),
(25, 'content:drama:tag:manage', '短剧标签管理', 1, 0, 250, 1),
(26, 'content:drama:audit', '短剧审核', 1, 0, 260, 1),
(27, 'content:novel:category:manage', '小说分类管理', 1, 0, 270, 1),
(28, 'content:novel:tag:manage', '小说标签管理', 1, 0, 280, 1),
(29, 'content:novel:audit', '小说审核', 1, 0, 290, 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `sort` = VALUES(`sort`);

-- 菜单树（path 与 Vue 路由一致；大模块为目录节点 permission_code 为空，子菜单按权限显示）
INSERT INTO `djxs_admin_menu` (`id`, `parent_id`, `name`, `path`, `component`, `icon`, `permission_code`, `sort`, `visible`, `status`, `create_time`, `update_time`) VALUES
(1, 0, '仪表盘', '/dashboard', 'Dashboard', 'Odometer', 'dashboard:view', 10, 1, 1, NOW(), NOW()),
(20, 0, '用户管理', '/user-center', NULL, 'User', NULL, 22, 1, 1, NOW(), NOW()),
(2, 20, '用户管理', '/users', 'UserList', 'User', 'user:manage', 10, 1, 1, NOW(), NOW()),
(7, 20, '会员等级', '/members', 'MemberLevel', 'Medal', 'member:manage', 20, 1, 1, NOW(), NOW()),
(17, 0, '内容管理', '/content', NULL, 'FolderOpened', NULL, 32, 1, 1, NOW(), NOW()),
(3, 17, '短剧管理', '/dramas', NULL, 'VideoCamera', NULL, 10, 1, 1, NOW(), NOW()),
(4, 17, '小说管理', '/novels', NULL, 'Reading', NULL, 20, 1, 1, NOW(), NOW()),
(24, 3, '短剧列表', '/dramas/list', 'DramaList', 'List', 'content:drama:manage', 10, 1, 1, NOW(), NOW()),
(25, 3, '短剧分类与标签', '/dramas/meta', 'ContentAudit', 'Collection', 'content:drama:category:manage', 20, 1, 1, NOW(), NOW()),
(26, 3, '短剧审核', '/dramas/audit', 'ContentAudit', 'DocumentChecked', 'content:drama:audit', 30, 1, 1, NOW(), NOW()),
(27, 4, '小说列表', '/novels/list', 'NovelList', 'List', 'content:novel:manage', 10, 1, 1, NOW(), NOW()),
(28, 4, '小说分类与标签', '/novels/meta', 'ContentAudit', 'Collection', 'content:novel:category:manage', 20, 1, 1, NOW(), NOW()),
(29, 4, '小说审核', '/novels/audit', 'ContentAudit', 'DocumentChecked', 'content:novel:audit', 30, 1, 1, NOW(), NOW()),
(6, 0, '订单管理', '/orders', 'OrderList', 'Tickets', 'order:manage', 50, 1, 1, NOW(), NOW()),
(8, 0, '分销管理', '/distribution', 'DistributionList', 'Share', 'distribution:manage', 75, 1, 1, NOW(), NOW()),
(11, 0, '系统配置', '/system-config', NULL, 'Setting', NULL, 105, 1, 1, NOW(), NOW()),
(21, 11, '基础配置', '/configs', 'ConfigList', 'Setting', 'config:manage', 10, 1, 1, NOW(), NOW()),
(22, 11, '广告中心', '/ad-center', NULL, 'Promotion', NULL, 22, 1, 1, NOW(), NOW()),
(9, 22, '广告位管理', '/ad-positions', 'AdPosition', 'Monitor', 'ad:position:manage', 10, 1, 1, NOW(), NOW()),
(10, 22, '广告素材', '/ads', 'AdList', 'Picture', 'ad:manage', 20, 1, 1, NOW(), NOW()),
(12, 0, '系统管理', '/system', NULL, 'Tools', NULL, 200, 1, 1, NOW(), NOW()),
(13, 12, '权限点', '/system/permissions', 'SystemPermission', 'Key', 'system:permission:manage', 10, 1, 1, NOW(), NOW()),
(14, 12, '菜单', '/system/menus', 'SystemMenu', 'Menu', 'system:menu:manage', 20, 1, 1, NOW(), NOW()),
(15, 12, '角色', '/system/roles', 'SystemRole', 'UserFilled', 'system:role:manage', 30, 1, 1, NOW(), NOW()),
(16, 12, '管理员', '/system/admin-users', 'SystemAdminUser', 'Avatar', 'system:admin-user:manage', 40, 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `path` = VALUES(`path`), `parent_id` = VALUES(`parent_id`), `sort` = VALUES(`sort`), `update_time` = VALUES(`update_time`);

-- 超级管理员拥有全部权限
INSERT IGNORE INTO `djxs_admin_role_permission` (`role_id`, `permission_id`)
SELECT 1, p.id FROM `djxs_admin_permission` p;
