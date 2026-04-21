-- 秒杀模块：表结构 + 后台权限菜单 + 默认活动数据
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `djxs_flash_sale_activity` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '活动名称',
  `cover` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '活动封面',
  `status` tinyint NOT NULL DEFAULT 0 COMMENT '0草稿 1待开始 2进行中 3已结束 4已关闭',
  `preheat_time` datetime DEFAULT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `sort` int NOT NULL DEFAULT 0,
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_time` (`status`,`start_time`,`end_time`),
  KEY `idx_sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='秒杀活动';

CREATE TABLE IF NOT EXISTS `djxs_flash_sale_item` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `activity_id` bigint unsigned NOT NULL,
  `goods_type` tinyint NOT NULL COMMENT '10整剧 20整本',
  `goods_id` bigint unsigned NOT NULL,
  `title_snapshot` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `cover_snapshot` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `origin_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `seckill_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_stock` int NOT NULL DEFAULT 0,
  `sold_stock` int NOT NULL DEFAULT 0,
  `locked_stock` int NOT NULL DEFAULT 0,
  `limit_per_user` int NOT NULL DEFAULT 1,
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '1启用 0停用',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_activity_goods` (`activity_id`,`goods_type`,`goods_id`),
  KEY `idx_activity_status` (`activity_id`,`status`),
  CONSTRAINT `fk_flash_sale_item_activity` FOREIGN KEY (`activity_id`) REFERENCES `djxs_flash_sale_activity` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='秒杀活动商品';

CREATE TABLE IF NOT EXISTS `djxs_flash_sale_order` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `activity_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `user_id` int NOT NULL,
  `request_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `buy_count` int NOT NULL DEFAULT 1,
  `seckill_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` tinyint NOT NULL DEFAULT 0 COMMENT '0待支付 1已支付 2已取消 3已超时',
  `reserve_expire_time` datetime DEFAULT NULL COMMENT '库存锁定过期时间',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_request` (`request_id`),
  KEY `idx_user_item` (`user_id`,`item_id`,`status`),
  KEY `idx_status_reserve_expire` (`status`,`reserve_expire_time`),
  KEY `idx_order` (`order_id`),
  KEY `idx_activity` (`activity_id`),
  CONSTRAINT `fk_flash_sale_order_order` FOREIGN KEY (`order_id`) REFERENCES `djxs_order` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_flash_sale_order_activity` FOREIGN KEY (`activity_id`) REFERENCES `djxs_flash_sale_activity` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_flash_sale_order_item` FOREIGN KEY (`item_id`) REFERENCES `djxs_flash_sale_item` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='秒杀订单扩展';

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

-- 权限点
INSERT INTO `djxs_admin_permission` (`code`, `name`, `type`, `parent_id`, `sort`, `status`, `remark`)
SELECT 'marketing:flash-sale:manage', '秒杀活动管理', 1, 0, 310, 1, '秒杀活动后台管理'
WHERE NOT EXISTS (
  SELECT 1 FROM `djxs_admin_permission` WHERE `code` = 'marketing:flash-sale:manage'
);

-- 菜单分组：营销中心
INSERT INTO `djxs_admin_menu`
(`parent_id`, `name`, `path`, `component`, `icon`, `permission_code`, `sort`, `visible`, `status`, `create_time`, `update_time`)
SELECT 0, '营销中心', '/marketing', NULL, 'Promotion', NULL, 184, 1, 1, NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `djxs_admin_menu` WHERE `path` = '/marketing'
);

-- 菜单：秒杀管理（挂在营销中心下，作为目录）
INSERT INTO `djxs_admin_menu`
(`parent_id`, `name`, `path`, `component`, `icon`, `permission_code`, `sort`, `visible`, `status`, `create_time`, `update_time`)
SELECT
  (SELECT id FROM (SELECT id FROM `djxs_admin_menu` WHERE `path` = '/marketing' LIMIT 1) t),
  '秒杀管理', '/flash-sale', NULL, 'Promotion', NULL, 185, 1, 1, NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `djxs_admin_menu` WHERE `path` = '/flash-sale'
);

-- 历史数据纠偏：已存在秒杀菜单时，归到营销中心下并转为目录
UPDATE `djxs_admin_menu`
SET `parent_id` = (SELECT id FROM (SELECT id FROM `djxs_admin_menu` WHERE `path` = '/marketing' LIMIT 1) x),
    `name` = '秒杀管理',
    `component` = NULL,
    `permission_code` = NULL,
    `icon` = 'Promotion',
    `update_time` = NOW()
WHERE `path` = '/flash-sale';

-- 子菜单：活动管理
INSERT INTO `djxs_admin_menu`
(`parent_id`, `name`, `path`, `component`, `icon`, `permission_code`, `sort`, `visible`, `status`, `create_time`, `update_time`)
SELECT
  (SELECT id FROM (SELECT id FROM `djxs_admin_menu` WHERE `path` = '/flash-sale' LIMIT 1) t),
  '活动管理', '/flash-sale/activities', 'FlashSaleActivity', 'Promotion', 'marketing:flash-sale:manage', 10, 1, 1, NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `djxs_admin_menu` WHERE `path` = '/flash-sale/activities'
);

-- 子菜单：订单管理
INSERT INTO `djxs_admin_menu`
(`parent_id`, `name`, `path`, `component`, `icon`, `permission_code`, `sort`, `visible`, `status`, `create_time`, `update_time`)
SELECT
  (SELECT id FROM (SELECT id FROM `djxs_admin_menu` WHERE `path` = '/flash-sale' LIMIT 1) t),
  '订单管理', '/flash-sale/orders', 'FlashSaleOrder', 'Tickets', 'marketing:flash-sale:manage', 20, 1, 1, NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `djxs_admin_menu` WHERE `path` = '/flash-sale/orders'
);

-- 子菜单：统计看板
INSERT INTO `djxs_admin_menu`
(`parent_id`, `name`, `path`, `component`, `icon`, `permission_code`, `sort`, `visible`, `status`, `create_time`, `update_time`)
SELECT
  (SELECT id FROM (SELECT id FROM `djxs_admin_menu` WHERE `path` = '/flash-sale' LIMIT 1) t),
  '统计看板', '/flash-sale/stats', 'FlashSaleStats', 'Odometer', 'marketing:flash-sale:manage', 30, 1, 1, NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `djxs_admin_menu` WHERE `path` = '/flash-sale/stats'
);

-- 默认活动（进行中，便于联调）
INSERT INTO `djxs_flash_sale_activity` (`name`, `cover`, `status`, `preheat_time`, `start_time`, `end_time`, `sort`, `create_time`, `update_time`)
SELECT '开服秒杀专场', '', 2, DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_SUB(NOW(), INTERVAL 10 MINUTE), DATE_ADD(NOW(), INTERVAL 7 DAY), 100, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `djxs_flash_sale_activity` LIMIT 1);

-- 默认活动商品：取一条短剧和一条小说作为示例（若存在）
INSERT INTO `djxs_flash_sale_item`
(`activity_id`, `goods_type`, `goods_id`, `title_snapshot`, `cover_snapshot`, `origin_price`, `seckill_price`, `total_stock`, `sold_stock`, `locked_stock`, `limit_per_user`, `status`, `create_time`, `update_time`)
SELECT
  a.id, 10, d.id, d.title, IFNULL(d.cover, ''), GREATEST(IFNULL(d.price, 0.00), 9.90), ROUND(GREATEST(IFNULL(d.price, 0.00), 9.90) * 0.5, 2),
  500, 0, 0, 1, 1, NOW(), NOW()
FROM `djxs_flash_sale_activity` a
JOIN `djxs_drama` d ON d.id = (SELECT id FROM `djxs_drama` ORDER BY id ASC LIMIT 1)
WHERE a.id = (SELECT id FROM `djxs_flash_sale_activity` ORDER BY id ASC LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM `djxs_flash_sale_item` i WHERE i.activity_id = a.id AND i.goods_type = 10
  );

INSERT INTO `djxs_flash_sale_item`
(`activity_id`, `goods_type`, `goods_id`, `title_snapshot`, `cover_snapshot`, `origin_price`, `seckill_price`, `total_stock`, `sold_stock`, `locked_stock`, `limit_per_user`, `status`, `create_time`, `update_time`)
SELECT
  a.id, 20, n.id, n.title, IFNULL(n.cover, ''), GREATEST(IFNULL(n.price, 0.00), 9.90), ROUND(GREATEST(IFNULL(n.price, 0.00), 9.90) * 0.5, 2),
  500, 0, 0, 1, 1, NOW(), NOW()
FROM `djxs_flash_sale_activity` a
JOIN `djxs_novel` n ON n.id = (SELECT id FROM `djxs_novel` ORDER BY id ASC LIMIT 1)
WHERE a.id = (SELECT id FROM `djxs_flash_sale_activity` ORDER BY id ASC LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM `djxs_flash_sale_item` i WHERE i.activity_id = a.id AND i.goods_type = 20
  );

SET FOREIGN_KEY_CHECKS = 1;

