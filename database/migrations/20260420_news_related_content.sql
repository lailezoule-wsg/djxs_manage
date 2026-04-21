-- 资讯关联内容能力：支持从资讯详情跳转到短剧/小说详情
SET NAMES utf8mb4;

SET @db := DATABASE();

SET @has_related_type := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'djxs_news'
    AND COLUMN_NAME = 'related_type'
);

SET @has_related_id := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'djxs_news'
    AND COLUMN_NAME = 'related_id'
);

SET @sql_related_type := IF(
  @has_related_type = 0,
  "ALTER TABLE `djxs_news` ADD COLUMN `related_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '关联内容类型 0无 1短剧 2小说' AFTER `news_type`",
  "SELECT 'skip: related_type exists' AS migration_note"
);
PREPARE stmt_related_type FROM @sql_related_type;
EXECUTE stmt_related_type;
DEALLOCATE PREPARE stmt_related_type;

SET @sql_related_id := IF(
  @has_related_id = 0,
  "ALTER TABLE `djxs_news` ADD COLUMN `related_id` int(11) NOT NULL DEFAULT 0 COMMENT '关联内容ID' AFTER `related_type`",
  "SELECT 'skip: related_id exists' AS migration_note"
);
PREPARE stmt_related_id FROM @sql_related_id;
EXECUTE stmt_related_id;
DEALLOCATE PREPARE stmt_related_id;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'djxs_news'
    AND INDEX_NAME = 'idx_related_type_id'
);

SET @sql_idx := IF(
  @idx_exists = 0,
  "ALTER TABLE `djxs_news` ADD KEY `idx_related_type_id` (`related_type`, `related_id`)",
  "SELECT 'skip: idx_related_type_id exists' AS migration_note"
);
PREPARE stmt_idx FROM @sql_idx;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;
