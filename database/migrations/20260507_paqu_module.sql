-- 爬取管理模块：任务表 + 数据源表 + 日志表
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `djxs_paqu_task` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '任务名称',
  `source_id` int NOT NULL DEFAULT 0 COMMENT '数据源ID',
  `type` tinyint NOT NULL DEFAULT 1 COMMENT '类型(1小说,2短剧)',
  `status` tinyint NOT NULL DEFAULT 0 COMMENT '状态(0待执行,1运行中,2已完成,3暂停,4已取消)',
  `start_time` datetime DEFAULT NULL COMMENT '开始时间',
  `end_time` datetime DEFAULT NULL COMMENT '结束时间',
  `total_count` int NOT NULL DEFAULT 0 COMMENT '目标总数',
  `success_count` int NOT NULL DEFAULT 0 COMMENT '成功数量',
  `failed_count` int NOT NULL DEFAULT 0 COMMENT '失败数量',
  `config` json DEFAULT NULL COMMENT '任务配置',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_source_id` (`source_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='爬虫任务表';

CREATE TABLE IF NOT EXISTS `djxs_paqu_source` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '数据源名称',
  `type` tinyint NOT NULL DEFAULT 1 COMMENT '类型(1小说,2短剧)',
  `base_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '基础URL',
  `list_url_pattern` text COLLATE utf8mb4_unicode_ci COMMENT '列表页URL模板',
  `detail_url_pattern` text COLLATE utf8mb4_unicode_ci COMMENT '详情页URL模板',
  `parse_rules` json DEFAULT NULL COMMENT '解析规则',
  `headers` json DEFAULT NULL COMMENT '请求头配置',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='数据源配置表';

CREATE TABLE IF NOT EXISTS `djxs_paqu_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `task_id` int NOT NULL DEFAULT 0 COMMENT '任务ID',
  `level` tinyint NOT NULL DEFAULT 2 COMMENT '日志级别(1调试,2信息,3警告,4错误)',
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '日志内容',
  `url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '请求URL',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_task_id` (`task_id`),
  KEY `idx_level` (`level`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='爬取日志表';

SET FOREIGN_KEY_CHECKS = 1;