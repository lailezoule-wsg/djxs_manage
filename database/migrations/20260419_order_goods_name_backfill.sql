-- 历史订单商品名称回填（可重复执行）
-- 目标：
-- 1) 单集/单章订单显示“父内容标题 / 子内容标题”
-- 2) 整剧/整本订单优先显示父内容标题
-- 说明：仅更新空名称或缺少“ / ”分隔符的记录，避免覆盖已人工修正名称

SET @db := DATABASE();

-- 1) 单集（goods_type=1）：短剧标题 / 剧集标题
SET @exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME IN ('djxs_order_goods', 'djxs_drama_episode', 'djxs_drama')
);
SET @sql := IF(
  @exists = 3,
  "UPDATE `djxs_order_goods` og
     JOIN `djxs_drama_episode` ep
       ON ep.id = og.goods_id
      AND og.goods_type = 1
     JOIN `djxs_drama` d
       ON d.id = ep.drama_id
    SET og.goods_name = CONCAT(
      TRIM(d.title),
      ' / ',
      TRIM(ep.title)
    )
  WHERE og.goods_type = 1
    AND (
      og.goods_name IS NULL
      OR TRIM(og.goods_name) = ''
      OR INSTR(og.goods_name, ' / ') = 0
    )",
  "SELECT 'skip: missing tables for single episode backfill' AS migration_note"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) 单章（goods_type=2）：小说标题 / 章节标题
SET @exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME IN ('djxs_order_goods', 'djxs_novel_chapter', 'djxs_novel')
);
SET @sql := IF(
  @exists = 3,
  "UPDATE `djxs_order_goods` og
     JOIN `djxs_novel_chapter` ch
       ON ch.id = og.goods_id
      AND og.goods_type = 2
     JOIN `djxs_novel` n
       ON n.id = ch.novel_id
    SET og.goods_name = CONCAT(
      TRIM(n.title),
      ' / ',
      TRIM(ch.title)
    )
  WHERE og.goods_type = 2
    AND (
      og.goods_name IS NULL
      OR TRIM(og.goods_name) = ''
      OR INSTR(og.goods_name, ' / ') = 0
    )",
  "SELECT 'skip: missing tables for novel chapter backfill' AS migration_note"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3) 整剧（goods_type=10）：短剧标题
SET @exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME IN ('djxs_order_goods', 'djxs_drama')
);
SET @sql := IF(
  @exists = 2,
  "UPDATE `djxs_order_goods` og
     JOIN `djxs_drama` d
       ON d.id = og.goods_id
      AND og.goods_type = 10
    SET og.goods_name = TRIM(d.title)
  WHERE og.goods_type = 10
    AND (
      og.goods_name IS NULL
      OR TRIM(og.goods_name) = ''
      OR INSTR(og.goods_name, ' / ') > 0
    )",
  "SELECT 'skip: missing tables for whole drama backfill' AS migration_note"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4) 整本（goods_type=20）：小说标题
SET @exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME IN ('djxs_order_goods', 'djxs_novel')
);
SET @sql := IF(
  @exists = 2,
  "UPDATE `djxs_order_goods` og
     JOIN `djxs_novel` n
       ON n.id = og.goods_id
      AND og.goods_type = 20
    SET og.goods_name = TRIM(n.title)
  WHERE og.goods_type = 20
    AND (
      og.goods_name IS NULL
      OR TRIM(og.goods_name) = ''
      OR INSTR(og.goods_name, ' / ') > 0
    )",
  "SELECT 'skip: missing tables for whole novel backfill' AS migration_note"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
