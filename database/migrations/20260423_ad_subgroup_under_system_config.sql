-- 在「系统配置」下增加「广告中心」分组，广告位/广告素材为其子菜单（三级）
SET NAMES utf8mb4;

INSERT INTO `djxs_admin_menu` (`id`, `parent_id`, `name`, `path`, `component`, `icon`, `permission_code`, `sort`, `visible`, `status`, `create_time`, `update_time`) VALUES
(22, 11, '广告中心', '/ad-center', NULL, 'Promotion', NULL, 22, 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `parent_id` = VALUES(`parent_id`),
  `name` = VALUES(`name`),
  `path` = VALUES(`path`),
  `icon` = VALUES(`icon`),
  `permission_code` = VALUES(`permission_code`),
  `sort` = VALUES(`sort`),
  `update_time` = VALUES(`update_time`);

UPDATE `djxs_admin_menu` SET `parent_id` = 22, `sort` = 10 WHERE `id` = 9;
UPDATE `djxs_admin_menu` SET `parent_id` = 22, `sort` = 20 WHERE `id` = 10;
