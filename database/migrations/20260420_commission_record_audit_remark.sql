-- 佣金提现审核备注字段（可重复执行）
-- 为 djxs_commission_record 增加 audit_remark，用于记录通过/拒绝审核说明

SET @db := DATABASE();

SET @exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'djxs_commission_record'
    AND COLUMN_NAME = 'audit_remark'
);

SET @sql := IF(
  @exists > 0,
  "SELECT 'skip: djxs_commission_record.audit_remark exists' AS migration_note",
  "ALTER TABLE `djxs_commission_record`
     ADD COLUMN `audit_remark` varchar(255) NULL COMMENT '提现审核备注' AFTER `status`"
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
