-- 创建分类映射表
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `djxs_paqu_source_category` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `source_id` int NOT NULL DEFAULT 0 COMMENT '数据源ID',
  `category_id` int NOT NULL DEFAULT 0 COMMENT '分类ID',
  `list_url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '列表页URL',
  `page_param` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'page' COMMENT '分页参数名',
  `page_start` int NOT NULL DEFAULT 1 COMMENT '起始页码',
  `page_end` int NOT NULL DEFAULT 0 COMMENT '结束页码(0表示不限制)',
  `chapter_url_pattern` text COLLATE utf8mb4_unicode_ci COMMENT '章节URL模板',
  `chapter_parse_rules` text COLLATE utf8mb4_unicode_ci COMMENT '章节列表提取规则',
  `content_parse_rules` text COLLATE utf8mb4_unicode_ci COMMENT '内容提取规则',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_source_id` (`source_id`),
  KEY `idx_category_id` (`category_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='数据源分类映射表';

SET FOREIGN_KEY_CHECKS = 1;