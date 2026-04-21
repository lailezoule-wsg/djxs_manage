-- 播放/阅读统计去重表（不依赖 runtime 文件缓存，避免 Docker 下 cache 目录不可写导致接口中断）
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `djxs_content_stat_dedupe` (
  `hash` char(32) NOT NULL COMMENT 'md5(维度键)',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`hash`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='内容统计去重（INSERT IGNORE）';
