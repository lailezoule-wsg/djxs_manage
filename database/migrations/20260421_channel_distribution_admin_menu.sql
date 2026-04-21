-- 多渠道分发：后台权限与菜单
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

INSERT INTO `djxs_admin_permission` (`code`, `name`, `type`, `parent_id`, `sort`, `status`, `remark`)
SELECT 'marketing:channel-distribution:manage', '多渠道分发管理', 1, 0, 320, 1, '多渠道内容分发任务与回调管理'
WHERE NOT EXISTS (
  SELECT 1 FROM `djxs_admin_permission` WHERE `code` = 'marketing:channel-distribution:manage'
);

INSERT INTO `djxs_admin_menu`
(`parent_id`, `name`, `path`, `component`, `icon`, `permission_code`, `sort`, `visible`, `status`, `create_time`, `update_time`)
SELECT 0, '营销中心', '/marketing', NULL, 'Promotion', NULL, 184, 1, 1, NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `djxs_admin_menu` WHERE `path` = '/marketing'
);

INSERT INTO `djxs_admin_menu`
(`parent_id`, `name`, `path`, `component`, `icon`, `permission_code`, `sort`, `visible`, `status`, `create_time`, `update_time`)
SELECT
  (SELECT id FROM (SELECT id FROM `djxs_admin_menu` WHERE `path` = '/marketing' LIMIT 1) t),
  '多渠道分发', '/channel-distribution', NULL, 'Share', NULL, 186, 1, 1, NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `djxs_admin_menu` WHERE `path` = '/channel-distribution'
);

INSERT INTO `djxs_admin_menu`
(`parent_id`, `name`, `path`, `component`, `icon`, `permission_code`, `sort`, `visible`, `status`, `create_time`, `update_time`)
SELECT
  (SELECT id FROM (SELECT id FROM `djxs_admin_menu` WHERE `path` = '/channel-distribution' LIMIT 1) t),
  '分发任务', '/channel-distribution/tasks', 'ChannelDistributionTask', 'List', 'marketing:channel-distribution:task:view', 10, 1, 1, NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `djxs_admin_menu` WHERE `path` = '/channel-distribution/tasks'
);

INSERT INTO `djxs_admin_menu`
(`parent_id`, `name`, `path`, `component`, `icon`, `permission_code`, `sort`, `visible`, `status`, `create_time`, `update_time`)
SELECT
  (SELECT id FROM (SELECT id FROM `djxs_admin_menu` WHERE `path` = '/channel-distribution' LIMIT 1) t),
  '回调记录', '/channel-distribution/callbacks', 'ChannelDistributionCallback', 'Bell', 'marketing:channel-distribution:callback:view', 20, 1, 1, NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `djxs_admin_menu` WHERE `path` = '/channel-distribution/callbacks'
);

INSERT INTO `djxs_admin_menu`
(`parent_id`, `name`, `path`, `component`, `icon`, `permission_code`, `sort`, `visible`, `status`, `create_time`, `update_time`)
SELECT
  (SELECT id FROM (SELECT id FROM `djxs_admin_menu` WHERE `path` = '/channel-distribution' LIMIT 1) t),
  '渠道名称', '/channel-distribution/channels', 'ChannelDistributionChannel', 'CollectionTag', 'marketing:channel-distribution:account:manage', 25, 1, 1, NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `djxs_admin_menu` WHERE `path` = '/channel-distribution/channels'
);

INSERT INTO `djxs_admin_menu`
(`parent_id`, `name`, `path`, `component`, `icon`, `permission_code`, `sort`, `visible`, `status`, `create_time`, `update_time`)
SELECT
  (SELECT id FROM (SELECT id FROM `djxs_admin_menu` WHERE `path` = '/channel-distribution' LIMIT 1) t),
  '渠道账号', '/channel-distribution/accounts', 'ChannelDistributionAccount', 'Setting', 'marketing:channel-distribution:account:manage', 30, 1, 1, NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `djxs_admin_menu` WHERE `path` = '/channel-distribution/accounts'
);

SET FOREIGN_KEY_CHECKS = 1;
