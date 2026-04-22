-- 秒杀待支付防重上线前预检
-- 目标：
-- 1) 检查秒杀待支付订单在“同用户同商品”维度是否已重复；
-- 2) 检查秒杀待支付订单是否缺失 order_goods 行；
-- 3) 预览 pending_lock_key 回填结果。
SET NAMES utf8mb4;

-- =========================================================
-- 1) 秒杀待支付重复检查（若有结果，先处理历史重复数据）
-- =========================================================
SELECT
  o.user_id,
  g.goods_type,
  g.goods_id,
  COUNT(*) AS dup_count,
  GROUP_CONCAT(o.id ORDER BY o.id) AS order_ids
FROM djxs_order o
JOIN djxs_flash_sale_order f ON f.order_id = o.id
JOIN djxs_order_goods g ON g.order_id = o.id
WHERE o.status = 0
  AND f.status = 0
GROUP BY o.user_id, g.goods_type, g.goods_id
HAVING COUNT(*) > 1;

-- =========================================================
-- 2) 秒杀待支付但缺失商品明细检查（应为 0）
-- =========================================================
SELECT
  o.id AS order_id,
  o.user_id,
  o.order_sn
FROM djxs_order o
JOIN djxs_flash_sale_order f ON f.order_id = o.id
LEFT JOIN djxs_order_goods g ON g.order_id = o.id
WHERE o.status = 0
  AND f.status = 0
  AND g.id IS NULL;

-- =========================================================
-- 3) 回填预览（不改数据）
-- =========================================================
SELECT
  o.id AS order_id,
  o.user_id,
  g.goods_type,
  g.goods_id,
  o.pending_lock_key AS current_pending_lock_key,
  CONCAT('u:', o.user_id, '|g:', g.goods_type, ':', g.goods_id) AS pending_lock_key_preview
FROM djxs_order o
JOIN djxs_flash_sale_order f ON f.order_id = o.id
JOIN djxs_order_goods g ON g.order_id = o.id
WHERE o.status = 0
  AND f.status = 0
ORDER BY o.id DESC
LIMIT 200;
