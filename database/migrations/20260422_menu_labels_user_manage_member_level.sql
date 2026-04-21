-- 侧栏：一级「用户与会员」→「用户管理」；子项「会员管理」→「会员等级」；权限点 member:manage 展示名同步
-- 兼容按 id 与按旧名称更新（已为目标名的行不受影响）
SET NAMES utf8mb4;

UPDATE `djxs_admin_menu` SET `name` = '用户管理', `update_time` = NOW() WHERE `id` = 20;
UPDATE `djxs_admin_menu` SET `name` = '会员等级', `update_time` = NOW() WHERE `id` = 7;

UPDATE `djxs_admin_menu` SET `name` = '用户管理', `update_time` = NOW() WHERE `name` = '用户与会员';
UPDATE `djxs_admin_menu` SET `name` = '会员等级', `update_time` = NOW() WHERE `name` = '会员管理' AND `path` = '/members';

UPDATE `djxs_admin_permission` SET `name` = '会员等级' WHERE `id` = 13 AND `code` = 'member:manage';
