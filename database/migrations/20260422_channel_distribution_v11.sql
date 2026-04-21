SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `djxs_channel_account` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `channel_code` varchar(32) NOT NULL DEFAULT '' COMMENT 'douyin|kuaishou|tencent_video|xiaohongshu',
  `account_name` varchar(64) NOT NULL DEFAULT '',
  `account_key` varchar(128) NOT NULL DEFAULT '',
  `app_key` varchar(255) NOT NULL DEFAULT '',
  `app_secret_enc` text,
  `access_token_enc` text,
  `refresh_token_enc` text,
  `callback_secret_enc` text,
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '1启用 0停用',
  `qps_limit` int NOT NULL DEFAULT 50,
  `expire_time` datetime DEFAULT NULL,
  `ext_json` json DEFAULT NULL,
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_channel_account_key` (`channel_code`,`account_key`),
  KEY `idx_channel_status` (`channel_code`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='渠道账号配置';

ALTER TABLE `djxs_channel_distribution_task`
  ADD COLUMN IF NOT EXISTS `idempotency_key` varchar(128) NOT NULL DEFAULT '' AFTER `task_no`,
  ADD COLUMN IF NOT EXISTS `version` int NOT NULL DEFAULT 1 AFTER `content_type`,
  ADD COLUMN IF NOT EXISTS `channel_account_id` bigint unsigned NOT NULL DEFAULT 0 AFTER `channel_code`,
  ADD COLUMN IF NOT EXISTS `audit_status` varchar(16) NOT NULL DEFAULT 'pending' AFTER `status`,
  ADD COLUMN IF NOT EXISTS `audit_by` int NOT NULL DEFAULT 0 AFTER `audit_status`,
  ADD COLUMN IF NOT EXISTS `audit_time` datetime DEFAULT NULL AFTER `audit_by`,
  ADD COLUMN IF NOT EXISTS `audit_remark` varchar(255) NOT NULL DEFAULT '' AFTER `audit_time`,
  ADD COLUMN IF NOT EXISTS `trace_id` varchar(64) NOT NULL DEFAULT '' AFTER `retry_count`,
  ADD KEY `idx_task_idempotency` (`idempotency_key`),
  ADD KEY `idx_task_audit` (`audit_status`,`status`);

ALTER TABLE `djxs_channel_callback_event`
  ADD COLUMN IF NOT EXISTS `nonce` varchar(64) NOT NULL DEFAULT '' AFTER `event_type`,
  ADD COLUMN IF NOT EXISTS `ts` bigint NOT NULL DEFAULT 0 AFTER `nonce`,
  ADD COLUMN IF NOT EXISTS `sign` varchar(256) NOT NULL DEFAULT '' AFTER `ts`,
  ADD COLUMN IF NOT EXISTS `verify_pass` tinyint NOT NULL DEFAULT 0 AFTER `sign`,
  ADD KEY `idx_callback_ts` (`ts`);

CREATE TABLE IF NOT EXISTS `djxs_channel_distribution_op_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `task_no` varchar(64) NOT NULL DEFAULT '',
  `action` varchar(32) NOT NULL DEFAULT '' COMMENT 'create|audit|retry|offline|callback',
  `operator_id` int NOT NULL DEFAULT 0,
  `operator_type` varchar(16) NOT NULL DEFAULT 'admin',
  `before_json` json DEFAULT NULL,
  `after_json` json DEFAULT NULL,
  `remark` varchar(255) NOT NULL DEFAULT '',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_task_action` (`task_no`,`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='分发操作日志';

SET FOREIGN_KEY_CHECKS = 1;
