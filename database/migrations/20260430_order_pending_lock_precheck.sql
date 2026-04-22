-- 订单唯一索引上线前预检（建议先在预发执行）
-- 目标：
-- 1) 校验 order_sn 无重复（用于 uk_order_sn）
-- 2) 校验待支付单“同用户同商品”无重复（用于 pending_lock_key 防重）
-- 3) 提供历史 pending_lock_key 回填 SQL（可选）
SET NAMES utf8mb4;

-- =========================================================
-- 1) order_sn 重复检查（若有结果，先清理后再加唯一索引）
-- =========================================================
SELECT
  o.order_sn,
  COUNT(*) AS dup_count,
  GROUP_CONCAT(o.id ORDER BY o.id) AS order_ids
FROM djxs_order o
GROUP BY o.order_sn
HAVING COUNT(*) > 1;

-- =========================================================
-- 2) 待支付防重冲突检查（status=0 且同 user_id + goods_type + goods_id）
--    若有结果，说明历史上已存在重复待支付单，需先处置（取消旧单或人工合并）
-- =========================================================
SELECT
  o.user_id,
  g.goods_type,
  g.goods_id,
  COUNT(*) AS dup_count,
  GROUP_CONCAT(o.id ORDER BY o.id) AS order_ids
FROM djxs_order o
JOIN djxs_order_goods g ON g.order_id = o.id
WHERE o.status = 0
GROUP BY o.user_id, g.goods_type, g.goods_id
HAVING COUNT(*) > 1;

-- =========================================================
-- 3) 待支付但缺失订单商品行检查（应为 0）
--    若有结果，建议先修复脏数据，避免 pending_lock_key 回填不完整
-- =========================================================
SELECT
  o.id AS order_id,
  o.user_id,
  o.order_sn
FROM djxs_order o
LEFT JOIN djxs_order_goods g ON g.order_id = o.id
WHERE o.status = 0
  AND g.id IS NULL;

-- =========================================================
-- 4) 回填预览：查看将写入的 pending_lock_key（不改数据）
-- =========================================================
SELECT
  o.id AS order_id,
  o.user_id,
  g.goods_type,
  g.goods_id,
  CONCAT('u:', o.user_id, '|g:', g.goods_type, ':', g.goods_id) AS pending_lock_key_preview
FROM djxs_order o
JOIN djxs_order_goods g ON g.order_id = o.id
WHERE o.status = 0
ORDER BY o.id DESC
LIMIT 200;

-- =========================================================
-- 5) 可选回填：仅在上述检查都通过后执行
--    说明：
--    - 仅回填 status=0 的待支付订单；
--    - 非待支付订单由业务代码在状态流转时自动清空 pending_lock_key。
-- =========================================================
-- UPDATE djxs_order o
-- JOIN djxs_order_goods g ON g.order_id = o.id
-- SET o.pending_lock_key = CONCAT('u:', o.user_id, '|g:', g.goods_type, ':', g.goods_id)
-- WHERE o.status = 0;

