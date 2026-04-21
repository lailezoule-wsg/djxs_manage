-- 独立管理员账号体系（与 djxs_user 隔离）
-- 执行：mysql -u... < 20260418_admin_user_tables.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `djxs_admin_role` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL COMMENT '角色编码',
  `name` varchar(50) NOT NULL COMMENT '展示名称',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0禁用 1启用',
  `create_time` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员角色';

CREATE TABLE IF NOT EXISTS `djxs_admin_user` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL COMMENT '登录账号',
  `password` varchar(255) NOT NULL COMMENT '密码哈希',
  `real_name` varchar(50) DEFAULT NULL,
  `mobile` varchar(11) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `bind_user_id` int DEFAULT NULL COMMENT '可选关联 C 端用户',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0禁用 1启用',
  `last_login_time` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `create_time` datetime NOT NULL,
  `update_time` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`),
  KEY `idx_mobile` (`mobile`),
  KEY `idx_bind_user` (`bind_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员用户';

CREATE TABLE IF NOT EXISTS `djxs_admin_user_role` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `admin_user_id` int unsigned NOT NULL,
  `role_id` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_role` (`admin_user_id`,`role_id`),
  KEY `fk_role` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员角色关联';

-- 初始角色
INSERT INTO `djxs_admin_role` (`id`, `code`, `name`, `status`, `create_time`)
VALUES (1, 'super_admin', '超级管理员', 1, NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 默认管理员 admin / Admin@2026!（部署后请立即修改密码）
INSERT INTO `djxs_admin_user` (`id`, `username`, `password`, `real_name`, `status`, `create_time`, `update_time`)
VALUES (
  1,
  'admin',
  '$2y$10$6MbGQvpawmwhSGdWQEgf6eUy6rbLjir1qBBaynyPLZiXNsRBimcQW',
  '系统管理员',
  1,
  NOW(),
  NOW()
) ON DUPLICATE KEY UPDATE `update_time` = VALUES(`update_time`);

INSERT INTO `djxs_admin_user_role` (`admin_user_id`, `role_id`) VALUES (1, 1)
ON DUPLICATE KEY UPDATE `role_id` = VALUES(`role_id`);
