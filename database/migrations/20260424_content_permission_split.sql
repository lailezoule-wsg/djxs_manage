-- 内容管理：拆分短剧/小说的分类、标签、审核权限点
SET NAMES utf8mb4;

INSERT INTO `djxs_admin_permission` (`id`, `code`, `name`, `type`, `parent_id`, `sort`, `status`) VALUES
(24, 'content:drama:category:manage', '短剧分类管理', 1, 0, 240, 1),
(25, 'content:drama:tag:manage', '短剧标签管理', 1, 0, 250, 1),
(26, 'content:drama:audit', '短剧审核', 1, 0, 260, 1),
(27, 'content:novel:category:manage', '小说分类管理', 1, 0, 270, 1),
(28, 'content:novel:tag:manage', '小说标签管理', 1, 0, 280, 1),
(29, 'content:novel:audit', '小说审核', 1, 0, 290, 1)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `sort` = VALUES(`sort`),
  `status` = VALUES(`status`);

-- 超级管理员自动拥有新权限
INSERT IGNORE INTO `djxs_admin_role_permission` (`role_id`, `permission_id`)
SELECT 1, p.id
FROM `djxs_admin_permission` p
WHERE p.id IN (24, 25, 26, 27, 28, 29);

-- 分类标签与审核菜单放开为目录项，避免只分配子权限时菜单不可见
UPDATE `djxs_admin_menu`
SET `permission_code` = NULL, `update_time` = NOW()
WHERE `id` = 5 OR `path` = '/content-audit';
