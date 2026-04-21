SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

INSERT INTO `djxs_admin_permission` (`code`, `name`, `type`, `parent_id`, `sort`, `status`, `remark`)
SELECT 'marketing:channel-distribution:task:view', '多渠道分发任务查看', 1, 0, 321, 1, '查看分发任务及日志'
WHERE NOT EXISTS (SELECT 1 FROM `djxs_admin_permission` WHERE `code` = 'marketing:channel-distribution:task:view');

INSERT INTO `djxs_admin_permission` (`code`, `name`, `type`, `parent_id`, `sort`, `status`, `remark`)
SELECT 'marketing:channel-distribution:task:create', '多渠道分发任务创建', 1, 0, 322, 1, '创建分发任务'
WHERE NOT EXISTS (SELECT 1 FROM `djxs_admin_permission` WHERE `code` = 'marketing:channel-distribution:task:create');

INSERT INTO `djxs_admin_permission` (`code`, `name`, `type`, `parent_id`, `sort`, `status`, `remark`)
SELECT 'marketing:channel-distribution:task:audit', '多渠道分发任务审核', 1, 0, 323, 1, '审核通过/驳回分发任务'
WHERE NOT EXISTS (SELECT 1 FROM `djxs_admin_permission` WHERE `code` = 'marketing:channel-distribution:task:audit');

INSERT INTO `djxs_admin_permission` (`code`, `name`, `type`, `parent_id`, `sort`, `status`, `remark`)
SELECT 'marketing:channel-distribution:task:retry', '多渠道分发任务重试', 1, 0, 324, 1, '重试失败分发任务'
WHERE NOT EXISTS (SELECT 1 FROM `djxs_admin_permission` WHERE `code` = 'marketing:channel-distribution:task:retry');

INSERT INTO `djxs_admin_permission` (`code`, `name`, `type`, `parent_id`, `sort`, `status`, `remark`)
SELECT 'marketing:channel-distribution:callback:view', '多渠道回调记录查看', 1, 0, 325, 1, '查看渠道回调结果'
WHERE NOT EXISTS (SELECT 1 FROM `djxs_admin_permission` WHERE `code` = 'marketing:channel-distribution:callback:view');

INSERT INTO `djxs_admin_permission` (`code`, `name`, `type`, `parent_id`, `sort`, `status`, `remark`)
SELECT 'marketing:channel-distribution:account:manage', '多渠道账号配置管理', 1, 0, 326, 1, '管理渠道账号与密钥'
WHERE NOT EXISTS (SELECT 1 FROM `djxs_admin_permission` WHERE `code` = 'marketing:channel-distribution:account:manage');

UPDATE `djxs_admin_menu`
SET `permission_code` = 'marketing:channel-distribution:task:view',
    `update_time` = NOW()
WHERE `path` = '/channel-distribution/tasks';

UPDATE `djxs_admin_menu`
SET `permission_code` = 'marketing:channel-distribution:callback:view',
    `update_time` = NOW()
WHERE `path` = '/channel-distribution/callbacks';

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
