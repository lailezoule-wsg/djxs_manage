-- 数据源配置表扩展字段
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 更新数据源表，添加新增字段
ALTER TABLE `djxs_paqu_source`
ADD COLUMN `charset` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'GBK' COMMENT '编码格式' AFTER `headers`,
ADD COLUMN `timeout` int NOT NULL DEFAULT 30 COMMENT '连接超时(秒)' AFTER `charset`,
ADD COLUMN `request_delay` int NOT NULL DEFAULT 500 COMMENT '请求间隔(毫秒)' AFTER `timeout`,
ADD COLUMN `default_category_id` int DEFAULT NULL COMMENT '默认分类ID' AFTER `request_delay`,
ADD COLUMN `list_parse_rules` text COLLATE utf8mb4_unicode_ci COMMENT '书籍列表解析规则' AFTER `default_category_id`,
ADD COLUMN `chapter_parse_rules` text COLLATE utf8mb4_unicode_ci COMMENT '章节列表解析规则' AFTER `list_parse_rules`,
ADD COLUMN `content_parse_rules` text COLLATE utf8mb4_unicode_ci COMMENT '内容详情解析规则' AFTER `chapter_parse_rules`,
ADD COLUMN `cookie` text COLLATE utf8mb4_unicode_ci COMMENT 'Cookie' AFTER `content_parse_rules`,
ADD COLUMN `tag_rules` json DEFAULT NULL COMMENT '标签提取规则' AFTER `cookie`;

-- 更新分类映射表，添加结束页字段
ALTER TABLE `djxs_paqu_source_category`
ADD COLUMN `page_end` int NOT NULL DEFAULT 0 COMMENT '结束页码(0表示不限制)' AFTER `page_start`;

SET FOREIGN_KEY_CHECKS = 1;