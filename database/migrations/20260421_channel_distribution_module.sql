-- 多渠道内容分发：任务表 + 回调事件表
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `djxs_channel_distribution_task` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `task_no` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '任务编号（同批次多个渠道）',
  `channel_code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'douyin|kuaishou|tencent_video|xiaohongshu',
  `content_id` bigint unsigned NOT NULL DEFAULT 0,
  `content_type` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'drama|novel',
  `status` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT 'pending|processing|success|failed',
  `retry_count` int NOT NULL DEFAULT 0,
  `channel_content_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `request_payload` json DEFAULT NULL,
  `response_payload` json DEFAULT NULL,
  `error_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_msg` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operator_id` int NOT NULL DEFAULT 0,
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_task_no` (`task_no`),
  KEY `idx_channel_status` (`channel_code`,`status`),
  KEY `idx_content` (`content_id`,`content_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='多渠道分发任务';

CREATE TABLE IF NOT EXISTS `djxs_channel_callback_event` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `channel_code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `event_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `task_no` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `channel_content_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `raw_payload` json DEFAULT NULL,
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_event_channel` (`event_id`,`channel_code`),
  KEY `idx_task_no` (`task_no`),
  KEY `idx_channel_event_type` (`channel_code`,`event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='渠道回调事件';

SET FOREIGN_KEY_CHECKS = 1;
