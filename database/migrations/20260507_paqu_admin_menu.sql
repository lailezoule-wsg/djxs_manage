-- 爬取管理：后台权限与菜单
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

INSERT INTO `djxs_admin_permission` (`code`, `name`, `type`, `parent_id`, `sort`, `status`, `remark`)
SELECT 'paqu:manage', '爬取管理', 1, 0, 330, 1, '小说短剧爬取任务管理'
WHERE NOT EXISTS (
  SELECT 1 FROM `djxs_admin_permission` WHERE `code` = 'paqu:manage'
);

INSERT INTO `djxs_admin_permission` (`code`, `name`, `type`, `parent_id`, `sort`, `status`, `remark`)
SELECT 'paqu:task:manage', '任务管理', 2, (SELECT id FROM `djxs_admin_permission` WHERE `code` = 'paqu:manage'), 10, 1, '爬虫任务增删改查'
WHERE NOT EXISTS (
  SELECT 1 FROM `djxs_admin_permission` WHERE `code` = 'paqu:task:manage'
);

INSERT INTO `djxs_admin_permission` (`code`, `name`, `type`, `parent_id`, `sort`, `status`, `remark`)
SELECT 'paqu:task:start', '任务启停', 2, (SELECT id FROM `djxs_admin_permission` WHERE `code` = 'paqu:manage'), 20, 1, '启动/停止爬虫任务'
WHERE NOT EXISTS (
  SELECT 1 FROM `djxs_admin_permission` WHERE `code` = 'paqu:task:start'
);

INSERT INTO `djxs_admin_permission` (`code`, `name`, `type`, `parent_id`, `sort`, `status`, `remark`)
SELECT 'paqu:source:manage', '数据源管理', 2, (SELECT id FROM `djxs_admin_permission` WHERE `code` = 'paqu:manage'), 30, 1, '爬取数据源配置管理'
WHERE NOT EXISTS (
  SELECT 1 FROM `djxs_admin_permission` WHERE `code` = 'paqu:source:manage'
);

INSERT INTO `djxs_admin_permission` (`code`, `name`, `type`, `parent_id`, `sort`, `status`, `remark`)
SELECT 'paqu:monitor:view', '数据监控', 2, (SELECT id FROM `djxs_admin_permission` WHERE `code` = 'paqu:manage'), 40, 1, '爬取任务监控查看'
WHERE NOT EXISTS (
  SELECT 1 FROM `djxs_admin_permission` WHERE `code` = 'paqu:monitor:view'
);

INSERT INTO `djxs_admin_menu`
(`parent_id`, `name`, `path`, `component`, `icon`, `permission_code`, `sort`, `visible`, `status`, `create_time`, `update_time`)
SELECT 0, '内容管理', '/content', NULL, 'FileText', NULL, 100, 1, 1, NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `djxs_admin_menu` WHERE `path` = '/content'
);

INSERT INTO `djxs_admin_menu`
(`parent_id`, `name`, `path`, `component`, `icon`, `permission_code`, `sort`, `visible`, `status`, `create_time`, `update_time`)
SELECT
  (SELECT id FROM (SELECT id FROM `djxs_admin_menu` WHERE `path` = '/content' LIMIT 1) t),
  '爬取管理', '/paqu', NULL, 'Download', NULL, 190, 1, 1, NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `djxs_admin_menu` WHERE `path` = '/paqu'
);

INSERT INTO `djxs_admin_menu`
(`parent_id`, `name`, `path`, `component`, `icon`, `permission_code`, `sort`, `visible`, `status`, `create_time`, `update_time`)
SELECT
  (SELECT id FROM (SELECT id FROM `djxs_admin_menu` WHERE `path` = '/paqu' LIMIT 1) t),
  '任务列表', '/paqu/tasks', 'PaquTask', 'List', 'paqu:task:manage', 10, 1, 1, NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `djxs_admin_menu` WHERE `path` = '/paqu/tasks'
);

INSERT INTO `djxs_admin_menu`
(`parent_id`, `name`, `path`, `component`, `icon`, `permission_code`, `sort`, `visible`, `status`, `create_time`, `update_time`)
SELECT
  (SELECT id FROM (SELECT id FROM `djxs_admin_menu` WHERE `path` = '/paqu' LIMIT 1) t),
  '数据源', '/paqu/sources', 'PaquSource', 'Globe', 'paqu:source:manage', 20, 1, 1, NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `djxs_admin_menu` WHERE `path` = '/paqu/sources'
);

INSERT INTO `djxs_admin_menu`
(`parent_id`, `name`, `path`, `component`, `icon`, `permission_code`, `sort`, `visible`, `status`, `create_time`, `update_time`)
SELECT
  (SELECT id FROM (SELECT id FROM `djxs_admin_menu` WHERE `path` = '/paqu' LIMIT 1) t),
  '监控中心', '/paqu/monitor', 'PaquMonitor', 'Activity', 'paqu:monitor:view', 30, 1, 1, NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `djxs_admin_menu` WHERE `path` = '/paqu/monitor'
);

SET FOREIGN_KEY_CHECKS = 1;