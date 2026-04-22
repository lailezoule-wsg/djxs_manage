-- 订单安全硬化：
-- 1) order_sn 全局唯一；
-- 2) 待支付防重键 pending_lock_key（同用户同商品仅保留一个待支付单）。
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

-- 2) order_sn 唯一索引
SET @has_uk_order_sn := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'djxs_order'
    AND INDEX_NAME = 'uk_order_sn'
);
SET @sql_uk_order_sn := IF(
  @has_uk_order_sn = 0,
  'ALTER TABLE `djxs_order` ADD UNIQUE KEY `uk_order_sn` (`order_sn`)',
  'SELECT ''skip: index uk_order_sn already exists'''
);
PREPARE stmt_uk_order_sn FROM @sql_uk_order_sn;
EXECUTE stmt_uk_order_sn;
DEALLOCATE PREPARE stmt_uk_order_sn;

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
