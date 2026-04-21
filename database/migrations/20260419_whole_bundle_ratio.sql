-- 整本/整剧价由「上架子内容标价合计 × whole_bundle_ratio」自动维护；后台不再手填 drama/novel.price。
-- 可重复执行：若列已存在则跳过。执行前请备份。表名含前缀 djxs_（与 DB_PREFIX 一致）。

SET @db := DATABASE();

-- djxs_drama.whole_bundle_ratio
SET @exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'djxs_drama' AND COLUMN_NAME = 'whole_bundle_ratio'
);
SET @sql := IF(@exists > 0,
  'SELECT ''skip: djxs_drama.whole_bundle_ratio'' AS migration_note',
  'ALTER TABLE `djxs_drama` ADD COLUMN `whole_bundle_ratio` decimal(8,4) NOT NULL DEFAULT 1.0000 COMMENT ''整剧价相对上架单集标价合计的比例'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- djxs_novel.whole_bundle_ratio
SET @exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'djxs_novel' AND COLUMN_NAME = 'whole_bundle_ratio'
);
SET @sql := IF(@exists > 0,
  'SELECT ''skip: djxs_novel.whole_bundle_ratio'' AS migration_note',
  'ALTER TABLE `djxs_novel` ADD COLUMN `whole_bundle_ratio` decimal(8,4) NOT NULL DEFAULT 1.0000 COMMENT ''整本价相对上架章节标价合计的比例'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
