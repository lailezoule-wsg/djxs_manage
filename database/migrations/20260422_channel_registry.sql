SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `djxs_channel_registry` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `channel_code` varchar(32) NOT NULL DEFAULT '' COMMENT '渠道编码',
  `channel_name` varchar(64) NOT NULL DEFAULT '' COMMENT '渠道名称',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '1启用 0停用',
  `sort` int NOT NULL DEFAULT 100,
  `remark` varchar(255) NOT NULL DEFAULT '',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_channel_code` (`channel_code`),
  KEY `idx_status_sort` (`status`,`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='渠道名称配置';

INSERT INTO `djxs_channel_registry` (`channel_code`, `channel_name`, `status`, `sort`, `remark`, `create_time`, `update_time`)
SELECT 'douyin', '抖音', 1, 10, '', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `djxs_channel_registry` WHERE `channel_code` = 'douyin');

INSERT INTO `djxs_channel_registry` (`channel_code`, `channel_name`, `status`, `sort`, `remark`, `create_time`, `update_time`)
SELECT 'kuaishou', '快手', 1, 20, '', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `djxs_channel_registry` WHERE `channel_code` = 'kuaishou');

INSERT INTO `djxs_channel_registry` (`channel_code`, `channel_name`, `status`, `sort`, `remark`, `create_time`, `update_time`)
SELECT 'tencent_video', '腾讯视频', 1, 30, '', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `djxs_channel_registry` WHERE `channel_code` = 'tencent_video');

INSERT INTO `djxs_channel_registry` (`channel_code`, `channel_name`, `status`, `sort`, `remark`, `create_time`, `update_time`)
SELECT 'xiaohongshu', '小红书', 1, 40, '', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `djxs_channel_registry` WHERE `channel_code` = 'xiaohongshu');

SET FOREIGN_KEY_CHECKS = 1;
