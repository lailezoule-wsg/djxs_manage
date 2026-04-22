-- 秒杀待支付防重回滚脚本（仅回滚“秒杀订单回填值”）
-- 说明：
-- 1) 本脚本不会删除 pending_lock_key 字段与 uk_pending_lock_key 索引；
-- 2) 若需完整回滚索引/字段，请使用专门 DDL 变更窗口执行。
SET NAMES utf8mb4;

UPDATE djxs_order o
JOIN djxs_flash_sale_order f ON f.order_id = o.id
SET o.pending_lock_key = NULL
WHERE o.status = 0
  AND f.status = 0
  AND o.pending_lock_key LIKE 'u:%|g:%';
