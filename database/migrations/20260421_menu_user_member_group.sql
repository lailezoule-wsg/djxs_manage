-- 用户管理 + 会员等级 归为一级「用户管理」；订单管理单独一级；删除原「订单与会员」父级 id=18
SET NAMES utf8mb4;

INSERT INTO `djxs_admin_menu` (`id`, `parent_id`, `name`, `path`, `component`, `icon`, `permission_code`, `sort`, `visible`, `status`, `create_time`, `update_time`) VALUES
(20, 0, '用户管理', '/user-center', NULL, 'User', NULL, 22, 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `path` = VALUES(`path`),
  `parent_id` = VALUES(`parent_id`),
  `icon` = VALUES(`icon`),
  `permission_code` = VALUES(`permission_code`),
  `sort` = VALUES(`sort`),
  `update_time` = VALUES(`update_time`);

UPDATE `djxs_admin_menu` SET `parent_id` = 20, `sort` = 10 WHERE `id` = 2;
UPDATE `djxs_admin_menu` SET `parent_id` = 20, `sort` = 20 WHERE `id` = 7;
UPDATE `djxs_admin_menu` SET `parent_id` = 0, `sort` = 50 WHERE `id` = 6;

DELETE FROM `djxs_admin_menu` WHERE `id` = 18;
