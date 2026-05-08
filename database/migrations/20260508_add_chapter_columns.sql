-- 为数据源分类映射表添加章节URL模板和提取规则字段
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 添加章节URL模板字段
ALTER TABLE `djxs_paqu_source_category` 
ADD COLUMN IF NOT EXISTS `chapter_url_pattern` text COLLATE utf8mb4_unicode_ci COMMENT '章节URL模板' AFTER `page_end`;

-- 添加章节列表提取规则字段
ALTER TABLE `djxs_paqu_source_category` 
ADD COLUMN IF NOT EXISTS `chapter_parse_rules` text COLLATE utf8mb4_unicode_ci COMMENT '章节列表提取规则' AFTER `chapter_url_pattern`;

-- 添加内容提取规则字段
ALTER TABLE `djxs_paqu_source_category` 
ADD COLUMN IF NOT EXISTS `content_parse_rules` text COLLATE utf8mb4_unicode_ci COMMENT '内容提取规则' AFTER `chapter_parse_rules`;

SET FOREIGN_KEY_CHECKS = 1;