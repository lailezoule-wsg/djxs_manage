-- 广告并入「系统配置」：父级 id=11 为目录；子项含基础配置(21)、广告位(9)、广告素材(10)；删除原顶级「广告中心」id=19
SET NAMES utf8mb4;

INSERT INTO `djxs_admin_menu` (`id`, `parent_id`, `name`, `path`, `component`, `icon`, `permission_code`, `sort`, `visible`, `status`, `create_time`, `update_time`) VALUES
(21, 11, '基础配置', '/configs', 'ConfigList', 'Setting', 'config:manage', 10, 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `parent_id` = VALUES(`parent_id`),
  `name` = VALUES(`name`),
  `path` = VALUES(`path`),
  `component` = VALUES(`component`),
  `icon` = VALUES(`icon`),
  `permission_code` = VALUES(`permission_code`),
  `sort` = VALUES(`sort`),
  `update_time` = VALUES(`update_time`);

UPDATE `djxs_admin_menu` SET
  `name` = '系统配置',
  `path` = '/system-config',
  `component` = NULL,
  `permission_code` = NULL,
  `parent_id` = 0,
  `sort` = 105
WHERE `id` = 11;

UPDATE `djxs_admin_menu` SET `parent_id` = 11, `sort` = 20 WHERE `id` = 9;
UPDATE `djxs_admin_menu` SET `parent_id` = 11, `sort` = 30 WHERE `id` = 10;

DELETE FROM `djxs_admin_menu` WHERE `id` = 19;
