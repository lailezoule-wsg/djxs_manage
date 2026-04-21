-- 后台「系统配置」下增加「站内公告」入口（C 端顶部条读取 system_config.site_announcement）
SET NAMES utf8mb4;

INSERT INTO `djxs_admin_menu` (`id`, `parent_id`, `name`, `path`, `component`, `icon`, `permission_code`, `sort`, `visible`, `status`, `create_time`, `update_time`) VALUES
(33, 11, '站内公告', '/site-notice', 'SiteNotice', 'Bell', 'config:manage', 5, 1, 1, NOW(), NOW())
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

-- 将「基础配置」排在公告之后
UPDATE `djxs_admin_menu` SET `sort` = 10, `update_time` = NOW() WHERE `id` = 21;
