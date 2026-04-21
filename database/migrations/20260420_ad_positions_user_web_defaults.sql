-- 用户端广告位默认项初始化（可重复执行）
-- 目标：
-- 1) 补齐用户端常用广告位（home/home_bottom/drama_detail/drama_play/novel_detail/novel_read）
-- 2) 仅插入缺失 position，不覆盖既有配置

SET @db := DATABASE();

SET @table_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'djxs_ad_position'
);

DROP TEMPORARY TABLE IF EXISTS `tmp_ad_position_seed`;
CREATE TEMPORARY TABLE `tmp_ad_position_seed` (
  `name` varchar(50) NOT NULL,
  `position` varchar(50) NOT NULL,
  `width` int NOT NULL,
  `height` int NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1
);

INSERT INTO `tmp_ad_position_seed` (`name`, `position`, `width`, `height`, `status`) VALUES
('首页顶部广告', 'home', 1200, 120, 1),
('首页底部广告', 'home_bottom', 1200, 100, 1),
('短剧详情页广告', 'drama_detail', 1200, 100, 1),
('短剧播放页广告', 'drama_play', 1200, 80, 1),
('小说详情页广告', 'novel_detail', 1200, 100, 1),
('小说阅读页广告', 'novel_read', 1200, 80, 1);

SET @sql := IF(
  @table_exists = 0,
  "SELECT 'skip: djxs_ad_position not found' AS migration_note",
  "INSERT INTO `djxs_ad_position` (`name`, `position`, `width`, `height`, `status`)
   SELECT
     t.name,
     t.position,
     t.width,
     t.height,
     t.status
   FROM `tmp_ad_position_seed` t
   LEFT JOIN `djxs_ad_position` p ON p.position = t.position
   WHERE p.id IS NULL"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 输出执行后状态，便于核对
SELECT
  t.position,
  t.name AS expected_name,
  t.width AS expected_width,
  t.height AS expected_height,
  IF(p.id IS NULL, 'missing', 'present') AS status,
  p.id AS current_id,
  p.name AS current_name,
  p.width AS current_width,
  p.height AS current_height,
  p.status AS current_status
FROM `tmp_ad_position_seed` t
LEFT JOIN `djxs_ad_position` p ON p.position = t.position
ORDER BY t.position ASC;

DROP TEMPORARY TABLE IF EXISTS `tmp_ad_position_seed`;
