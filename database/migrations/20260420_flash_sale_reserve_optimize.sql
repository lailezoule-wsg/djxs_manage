-- 秒杀库存短锁优化：过期字段与索引
SET NAMES utf8mb4;

ALTER TABLE `djxs_flash_sale_order`
  ADD COLUMN IF NOT EXISTS `reserve_expire_time` datetime NULL COMMENT '库存锁定过期时间' AFTER `status`;

ALTER TABLE `djxs_flash_sale_order`
  ADD KEY IF NOT EXISTS `idx_status_reserve_expire` (`status`,`reserve_expire_time`);

UPDATE `djxs_flash_sale_order`
SET `reserve_expire_time` = DATE_ADD(`create_time`, INTERVAL 5 MINUTE)
WHERE `reserve_expire_time` IS NULL;
