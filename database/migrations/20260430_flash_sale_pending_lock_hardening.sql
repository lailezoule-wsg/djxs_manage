-- 秒杀待支付防重硬化：
-- 1) 确保 order.pending_lock_key 字段存在；
-- 2) 回填秒杀待支付订单 pending_lock_key；
-- 3) 确保 uk_pending_lock_key 唯一索引存在。
SET NAMES utf8mb4;

-- 1) pending_lock_key 字段
SET @has_pending_lock_key := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'djxs_order'
    AND COLUMN_NAME = 'pending_lock_key'
);
SET @sql_pending_lock_key := IF(
  @has_pending_lock_key = 0,
  'ALTER TABLE `djxs_order` ADD COLUMN `pending_lock_key` varchar(191) NULL COMMENT ''待支付防重键（同用户同商品）'' AFTER `status`',
  'SELECT ''skip: column pending_lock_key already exists'''
);
PREPARE stmt_pending_lock_key FROM @sql_pending_lock_key;
EXECUTE stmt_pending_lock_key;
DEALLOCATE PREPARE stmt_pending_lock_key;

-- 2) 回填秒杀待支付订单 pending_lock_key
UPDATE djxs_order o
JOIN djxs_flash_sale_order f ON f.order_id = o.id
JOIN djxs_order_goods g ON g.order_id = o.id
SET o.pending_lock_key = CONCAT('u:', o.user_id, '|g:', g.goods_type, ':', g.goods_id)
WHERE o.status = 0
  AND f.status = 0
  AND (o.pending_lock_key IS NULL OR o.pending_lock_key = '');

-- 3) pending_lock_key 唯一索引（NULL 可重复，不影响非待支付订单）
SET @has_uk_pending_lock_key := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'djxs_order'
    AND INDEX_NAME = 'uk_pending_lock_key'
);
SET @sql_uk_pending_lock_key := IF(
  @has_uk_pending_lock_key = 0,
  'ALTER TABLE `djxs_order` ADD UNIQUE KEY `uk_pending_lock_key` (`pending_lock_key`)',
  'SELECT ''skip: index uk_pending_lock_key already exists'''
);
PREPARE stmt_uk_pending_lock_key FROM @sql_uk_pending_lock_key;
EXECUTE stmt_uk_pending_lock_key;
DEALLOCATE PREPARE stmt_uk_pending_lock_key;
