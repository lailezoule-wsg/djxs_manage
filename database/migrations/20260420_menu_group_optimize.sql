-- 菜单分组优化：内容管理、订单与会员、广告中心（已执行过 20260419 的环境可单独跑本脚本）
SET NAMES utf8mb4;

INSERT INTO `djxs_admin_menu` (`id`, `parent_id`, `name`, `path`, `component`, `icon`, `permission_code`, `sort`, `visible`, `status`, `create_time`, `update_time`) VALUES
(17, 0, '内容管理', '/content', NULL, 'FolderOpened', NULL, 32, 1, 1, NOW(), NOW()),
(18, 0, '订单与会员', '/order-member', NULL, 'ShoppingCart', NULL, 55, 1, 1, NOW(), NOW()),
(19, 0, '广告中心', '/ad-center', NULL, 'Promotion', NULL, 88, 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `path` = VALUES(`path`),
  `parent_id` = VALUES(`parent_id`),
  `icon` = VALUES(`icon`),
  `permission_code` = VALUES(`permission_code`),
  `sort` = VALUES(`sort`),
  `update_time` = VALUES(`update_time`);

UPDATE `djxs_admin_menu` SET `parent_id` = 17, `sort` = 10 WHERE `id` = 3;
UPDATE `djxs_admin_menu` SET `parent_id` = 17, `sort` = 20 WHERE `id` = 4;
UPDATE `djxs_admin_menu` SET `parent_id` = 17, `sort` = 30 WHERE `id` = 5;
UPDATE `djxs_admin_menu` SET `name` = '分类标签与审核' WHERE `id` = 5;

UPDATE `djxs_admin_menu` SET `parent_id` = 18, `sort` = 10 WHERE `id` = 6;
UPDATE `djxs_admin_menu` SET `parent_id` = 18, `sort` = 20 WHERE `id` = 7;

UPDATE `djxs_admin_menu` SET `parent_id` = 19, `sort` = 10 WHERE `id` = 9;
UPDATE `djxs_admin_menu` SET `parent_id` = 19, `sort` = 20, `name` = '广告素材' WHERE `id` = 10;

UPDATE `djxs_admin_menu` SET `sort` = 75 WHERE `id` = 8;
UPDATE `djxs_admin_menu` SET `sort` = 105 WHERE `id` = 11;
