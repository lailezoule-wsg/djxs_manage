-- 短剧/小说资讯模块：数据表 + 管理端权限/菜单
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `djxs_news` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(180) NOT NULL DEFAULT '' COMMENT '资讯标题',
  `cover` varchar(500) NOT NULL DEFAULT '' COMMENT '封面图',
  `summary` varchar(500) NOT NULL DEFAULT '' COMMENT '摘要',
  `content` longtext NOT NULL COMMENT '正文',
  `news_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1短剧资讯 2小说资讯',
  `related_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '关联内容类型 0无 1短剧 2小说',
  `related_id` int(11) NOT NULL DEFAULT '0' COMMENT '关联内容ID',
  `is_top` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否置顶 0否 1是',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序，越大越靠前',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态 0草稿 1已发布',
  `publish_time` datetime DEFAULT NULL COMMENT '发布时间',
  `view_count` int(11) NOT NULL DEFAULT '0' COMMENT '浏览量',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_news_type_status_publish` (`news_type`, `status`, `publish_time`),
  KEY `idx_status_top_sort` (`status`, `is_top`, `sort`),
  KEY `idx_related_type_id` (`related_type`, `related_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='短剧/小说资讯';

INSERT INTO `djxs_admin_permission` (`id`, `code`, `name`, `type`, `parent_id`, `sort`, `status`) VALUES
(30, 'content:news:manage', '资讯管理', 1, 0, 300, 1)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `sort` = VALUES(`sort`),
  `status` = VALUES(`status`);

INSERT IGNORE INTO `djxs_admin_role_permission` (`role_id`, `permission_id`)
SELECT 1, p.id
FROM `djxs_admin_permission` p
WHERE p.id = 30;

INSERT INTO `djxs_admin_menu`
(`id`, `parent_id`, `name`, `path`, `component`, `icon`, `permission_code`, `sort`, `visible`, `status`, `create_time`, `update_time`)
VALUES
(34, 17, '资讯管理', '/news', 'NewsList', 'Memo', 'content:news:manage', 35, 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `parent_id` = VALUES(`parent_id`),
  `name` = VALUES(`name`),
  `path` = VALUES(`path`),
  `component` = VALUES(`component`),
  `icon` = VALUES(`icon`),
  `permission_code` = VALUES(`permission_code`),
  `sort` = VALUES(`sort`),
  `visible` = VALUES(`visible`),
  `status` = VALUES(`status`),
  `update_time` = VALUES(`update_time`);
