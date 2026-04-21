-- 公开配置默认项初始化（可重复执行）
-- 目标：
-- 1) 仅插入缺失 key，不覆盖已有 value
-- 2) 用于 C 端展示：公告、页脚、客服联系方式

SET @db := DATABASE();

SET @table_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'djxs_system_config'
);

SET @has_desc := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'djxs_system_config'
    AND COLUMN_NAME = 'description'
);

SET @has_update_time := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'djxs_system_config'
    AND COLUMN_NAME = 'update_time'
);

DROP TEMPORARY TABLE IF EXISTS `tmp_public_config_seed`;
CREATE TEMPORARY TABLE `tmp_public_config_seed` (
  `config_key` varchar(64) NOT NULL,
  `config_value` text NULL,
  `description` varchar(255) NULL
);

INSERT INTO `tmp_public_config_seed` (`config_key`, `config_value`, `description`) VALUES
('site_announcement', '欢迎来到短剧+小说平台，更多活动请关注站内公告。', 'C端顶部公告文案'),
('site_footer_text', 'Copyright © 2026 DJXS All Rights Reserved.', 'C端页脚文案'),
('customer_service_wechat', 'djxs_kefu', 'C端客服微信'),
('customer_service_qq', '800012345', 'C端客服QQ'),
('customer_service_phone', '400-800-8888', 'C端客服电话');

SET @sql := IF(
  @table_exists = 0,
  "SELECT 'skip: djxs_system_config not found' AS migration_note",
  IF(
    @has_desc > 0 AND @has_update_time > 0,
    "INSERT INTO `djxs_system_config` (`key`, `value`, `description`, `update_time`)
     SELECT
       t.config_key,
       t.config_value,
       t.description,
       NOW()
     FROM `tmp_public_config_seed` t
     LEFT JOIN `djxs_system_config` c ON c.`key` = t.config_key
     WHERE c.id IS NULL",
    IF(
      @has_desc > 0,
      "INSERT INTO `djxs_system_config` (`key`, `value`, `description`)
       SELECT
         t.config_key,
         t.config_value,
         t.description
       FROM `tmp_public_config_seed` t
       LEFT JOIN `djxs_system_config` c ON c.`key` = t.config_key
       WHERE c.id IS NULL",
      IF(
        @has_update_time > 0,
        "INSERT INTO `djxs_system_config` (`key`, `value`, `update_time`)
         SELECT
           t.config_key,
           t.config_value,
           NOW()
         FROM `tmp_public_config_seed` t
         LEFT JOIN `djxs_system_config` c ON c.`key` = t.config_key
         WHERE c.id IS NULL",
        "INSERT INTO `djxs_system_config` (`key`, `value`)
         SELECT
           t.config_key,
           t.config_value
         FROM `tmp_public_config_seed` t
         LEFT JOIN `djxs_system_config` c ON c.`key` = t.config_key
         WHERE c.id IS NULL"
      )
    )
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 输出当前 key 状态（便于执行后核对）
SELECT
  t.config_key,
  IF(c.id IS NULL, 'missing', 'present') AS status,
  IFNULL(c.`value`, '') AS current_value
FROM `tmp_public_config_seed` t
LEFT JOIN `djxs_system_config` c ON c.`key` = t.config_key
ORDER BY t.config_key ASC;

DROP TEMPORARY TABLE IF EXISTS `tmp_public_config_seed`;
