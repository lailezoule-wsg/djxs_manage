-- 分销管理拆为目录 + 三个子菜单（与前端路由 /distribution/records|withdraw|config 一致）
SET NAMES utf8mb4;

UPDATE `djxs_admin_menu`
SET
  `name` = '分销管理',
  `path` = '/distribution',
  `component` = NULL,
  `permission_code` = NULL,
  `sort` = 75,
  `visible` = 1,
  `status` = 1,
  `update_time` = NOW()
WHERE `id` = 8 OR (`parent_id` = 0 AND `path` = '/distribution');

INSERT INTO `djxs_admin_menu`
(`id`, `parent_id`, `name`, `path`, `component`, `icon`, `permission_code`, `sort`, `visible`, `status`, `create_time`, `update_time`)
VALUES
(30, 8, '分销记录', '/distribution/records', 'DistributionRecords', 'List', 'distribution:manage', 10, 1, 1, NOW(), NOW()),
(31, 8, '提现审核', '/distribution/withdraw', 'DistributionWithdraw', 'Tickets', 'distribution:manage', 20, 1, 1, NOW(), NOW()),
(32, 8, '分销配置', '/distribution/config', 'DistributionConfig', 'Setting', 'distribution:manage', 30, 1, 1, NOW(), NOW())
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
