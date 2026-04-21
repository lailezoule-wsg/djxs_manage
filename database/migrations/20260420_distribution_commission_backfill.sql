-- 历史订单分佣补结算（可重复执行）
-- 规则：
-- 1) 仅处理已支付订单（djxs_order.status=1）
-- 2) 买家在 djxs_distribution 中存在且 parent_id>0 且 parent_id!=buyer
-- 3) 按 system_config.distribution_config.rate（百分比）计算佣金
-- 4) 仅补写 commission_record(type=1) 不存在的订单，避免重复结算
-- 5) 对本次补写记录，累计到上级 distribution.total_commission / available_commission
--
-- 执行前建议备份；执行后可重复跑，第二次开始通常增量为 0。

SET @db := DATABASE();

-- 依赖表检查（order / distribution / commission_record / system_config）
SET @need_tables := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME IN ('djxs_order', 'djxs_distribution', 'djxs_commission_record', 'djxs_system_config')
);

SET @rate_percent := (
  SELECT CAST(
    JSON_UNQUOTE(JSON_EXTRACT(`value`, '$.rate')) AS DECIMAL(10,2)
  )
  FROM `djxs_system_config`
  WHERE `key` = 'distribution_config'
  LIMIT 1
);
SET @rate_percent := IFNULL(@rate_percent, 0.00);

-- 仅当依赖表齐全且比例>0时执行补结算
SET @can_run := IF(@need_tables = 4 AND @rate_percent > 0, 1, 0);

-- 临时候选：本次需要补结算的佣金明细
DROP TEMPORARY TABLE IF EXISTS `tmp_backfill_distribution_commission`;

SET @sql := IF(
  @can_run = 1,
  "CREATE TEMPORARY TABLE `tmp_backfill_distribution_commission` AS
   SELECT
     o.id AS order_id,
     d.parent_id AS parent_user_id,
     o.user_id AS buyer_user_id,
     ROUND(o.pay_amount * @rate_percent / 100, 2) AS commission_amount,
     IFNULL(o.pay_time, o.create_time) AS commission_time
   FROM `djxs_order` o
   INNER JOIN `djxs_distribution` d ON d.user_id = o.user_id
   LEFT JOIN `djxs_commission_record` cr
     ON cr.order_id = o.id
    AND cr.user_id = d.parent_id
    AND cr.type = 1
   WHERE o.status = 1
     AND o.pay_amount > 0
     AND d.parent_id > 0
     AND d.parent_id <> o.user_id
     AND cr.id IS NULL
     AND ROUND(o.pay_amount * @rate_percent / 100, 2) > 0",
  "CREATE TEMPORARY TABLE `tmp_backfill_distribution_commission` (
     order_id INT,
     parent_user_id INT,
     buyer_user_id INT,
     commission_amount DECIMAL(10,2),
     commission_time DATETIME
   )"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 为上级补齐 distribution 档案（若历史缺失）
-- promotion_code 采用 BF + 17位 user_id（总长19），降低与常规6位码冲突概率
SET @sql := IF(
  @can_run = 1,
  "INSERT INTO `djxs_distribution`
   (`user_id`, `parent_id`, `promotion_code`, `total_commission`, `available_commission`, `status`, `create_time`)
   SELECT
     t.parent_user_id,
     0,
     CONCAT('BF', LPAD(t.parent_user_id, 17, '0')),
     0.00,
     0.00,
     1,
     NOW()
   FROM (SELECT DISTINCT parent_user_id FROM `tmp_backfill_distribution_commission`) t
   LEFT JOIN `djxs_distribution` d ON d.user_id = t.parent_user_id
   LEFT JOIN `djxs_distribution` dc ON dc.promotion_code = CONCAT('BF', LPAD(t.parent_user_id, 17, '0'))
   WHERE d.user_id IS NULL
     AND dc.id IS NULL",
  "SELECT 'skip: backfill disabled (missing tables or rate<=0)' AS migration_note"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 先落佣金明细（幂等由候选集保证）
SET @sql := IF(
  @can_run = 1,
  "INSERT INTO `djxs_commission_record`
   (`user_id`, `order_id`, `amount`, `type`, `status`, `create_time`, `process_time`)
   SELECT
     t.parent_user_id,
     t.order_id,
     t.commission_amount,
     1,
     1,
     t.commission_time,
     t.commission_time
   FROM `tmp_backfill_distribution_commission` t",
  "SELECT 'skip: no commission insert' AS migration_note"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 再累加上级佣金余额（仅基于本次候选集）
SET @sql := IF(
  @can_run = 1,
  "UPDATE `djxs_distribution` d
   INNER JOIN (
     SELECT parent_user_id, ROUND(SUM(commission_amount), 2) AS total_add
     FROM `tmp_backfill_distribution_commission`
     GROUP BY parent_user_id
   ) x ON x.parent_user_id = d.user_id
   SET d.total_commission = ROUND(IFNULL(d.total_commission, 0) + x.total_add, 2),
       d.available_commission = ROUND(IFNULL(d.available_commission, 0) + x.total_add, 2)",
  "SELECT 'skip: no distribution update' AS migration_note"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 输出执行摘要（避免同一 SELECT 中多次引用临时表触发 Can't reopen table）
SET @candidate_orders_count := (
  SELECT COUNT(*) FROM `tmp_backfill_distribution_commission`
);
SET @candidate_commission_sum := (
  SELECT IFNULL(ROUND(SUM(commission_amount), 2), 0.00)
  FROM `tmp_backfill_distribution_commission`
);
SELECT
  @rate_percent AS rate_percent_used,
  @candidate_orders_count AS candidate_orders_count,
  @candidate_commission_sum AS candidate_commission_sum;

DROP TEMPORARY TABLE IF EXISTS `tmp_backfill_distribution_commission`;
