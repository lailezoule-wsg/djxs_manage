-- 秒杀风控：黑名单与拦截日志
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `djxs_flash_sale_risk_blacklist` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `scene` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'create_order' COMMENT 'all/create_order',
  `target_type` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'user/ip/device',
  `target_value` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '1生效 0停用',
  `expire_time` datetime DEFAULT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_by` int NOT NULL DEFAULT 0,
  `updated_by` int NOT NULL DEFAULT 0,
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_scene_target` (`scene`,`target_type`,`target_value`),
  KEY `idx_status_expire` (`status`,`expire_time`),
  KEY `idx_target` (`target_type`,`target_value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='秒杀风控黑名单';

CREATE TABLE IF NOT EXISTS `djxs_flash_sale_risk_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `scene` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'create_order',
  `reason` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_id` int NOT NULL DEFAULT 0,
  `activity_id` bigint unsigned NOT NULL DEFAULT 0,
  `item_id` bigint unsigned NOT NULL DEFAULT 0,
  `client_ip` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `device_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `extra_json` text COLLATE utf8mb4_unicode_ci,
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_scene_reason_time` (`scene`,`reason`,`create_time`),
  KEY `idx_activity_time` (`activity_id`,`create_time`),
  KEY `idx_item_time` (`item_id`,`create_time`),
  KEY `idx_user_time` (`user_id`,`create_time`),
  KEY `idx_ip_time` (`client_ip`,`create_time`),
  KEY `idx_device_time` (`device_id`,`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='秒杀风控命中日志';

-- 风险健康分默认阈值（若已存在则忽略）
INSERT INTO `djxs_system_config` (`key`, `value`, `description`, `update_time`)
SELECT 'flash_sale_risk_threshold_safe', '85', '秒杀风控健康分安全阈值', NOW()
WHERE NOT EXISTS (SELECT 1 FROM `djxs_system_config` WHERE `key` = 'flash_sale_risk_threshold_safe');

INSERT INTO `djxs_system_config` (`key`, `value`, `description`, `update_time`)
SELECT 'flash_sale_risk_threshold_attention', '60', '秒杀风控健康分关注阈值', NOW()
WHERE NOT EXISTS (SELECT 1 FROM `djxs_system_config` WHERE `key` = 'flash_sale_risk_threshold_attention');

INSERT INTO `djxs_system_config` (`key`, `value`, `description`, `update_time`)
SELECT 'flash_sale_risk_threshold_warning', '35', '秒杀风控健康分预警阈值', NOW()
WHERE NOT EXISTS (SELECT 1 FROM `djxs_system_config` WHERE `key` = 'flash_sale_risk_threshold_warning');
