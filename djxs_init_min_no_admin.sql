-- djxs_manage minimal init SQL (structure + minimal seeds)
-- generated from live mysql-djxs schema
-- NOTE: admin account seeds removed (djxs_admin_user, djxs_admin_user_role)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET collation_connection = 'utf8mb4_unicode_ci';


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `djxs_manage` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;

USE `djxs_manage`;
DROP TABLE IF EXISTS `djxs_ad`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `djxs_ad` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '广告ID',
  `position_id` int NOT NULL COMMENT '广告位ID',
  `title` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '广告标题',
  `image_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '图片地址',
  `link_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '链接地址',
  `start_time` datetime NOT NULL COMMENT '开始时间',
  `end_time` datetime NOT NULL COMMENT '结束时间',
  `click_count` int DEFAULT '0' COMMENT '点击次数',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态（0下架，1上架）',
  PRIMARY KEY (`id`),
  KEY `position_id` (`position_id`),
  CONSTRAINT `djxs_ad_ibfk_1` FOREIGN KEY (`position_id`) REFERENCES `djxs_ad_position` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='广告表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `djxs_ad_position`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `djxs_ad_position` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '广告位ID',
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '广告位名称',
  `position` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '广告位位置',
  `width` int NOT NULL COMMENT '宽度',
  `height` int NOT NULL COMMENT '高度',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态（0禁用，1启用）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `position` (`position`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='广告位表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `djxs_admin_menu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `djxs_admin_menu` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int unsigned NOT NULL DEFAULT '0',
  `name` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `component` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `permission_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort` int NOT NULL DEFAULT '0',
  `visible` tinyint(1) NOT NULL DEFAULT '1',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `create_time` datetime NOT NULL,
  `update_time` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_perm` (`permission_code`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理端菜单';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `djxs_admin_permission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `djxs_admin_permission` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '权限标识',
  `name` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '展示名称',
  `type` tinyint NOT NULL DEFAULT '1' COMMENT '1菜单 2按钮 3接口',
  `parent_id` int unsigned NOT NULL DEFAULT '0',
  `sort` int NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `remark` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_parent` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理端权限点';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `djxs_admin_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `djxs_admin_role` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '角色编码',
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '展示名称',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0禁用 1启用',
  `create_time` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员角色';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `djxs_admin_role_permission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `djxs_admin_role_permission` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int unsigned NOT NULL,
  `permission_id` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_role_perm` (`role_id`,`permission_id`),
  KEY `fk_perm` (`permission_id`)
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色权限';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `djxs_admin_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `djxs_admin_user` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '登录账号',
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '密码哈希',
  `real_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile` varchar(11) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bind_user_id` int DEFAULT NULL COMMENT '可选关联 C 端用户',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0禁用 1启用',
  `last_login_time` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `create_time` datetime NOT NULL,
  `update_time` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`),
  KEY `idx_mobile` (`mobile`),
  KEY `idx_bind_user` (`bind_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员用户';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `djxs_admin_user_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `djxs_admin_user_role` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `admin_user_id` int unsigned NOT NULL,
  `role_id` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_role` (`admin_user_id`,`role_id`),
  KEY `fk_role` (`role_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员角色关联';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `djxs_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `djxs_category` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '分类ID',
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '分类名称',
  `type` tinyint(1) NOT NULL COMMENT '类型（1短剧，2小说）',
  `parent_id` int DEFAULT '0' COMMENT '父分类ID',
  `sort` int DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态（0禁用，1启用）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name_type` (`name`,`type`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='分类表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `djxs_commission_record`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `djxs_commission_record` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `user_id` int NOT NULL COMMENT '用户ID',
  `order_id` int DEFAULT NULL COMMENT '订单ID',
  `amount` decimal(10,2) NOT NULL COMMENT '佣金金额',
  `type` tinyint(1) NOT NULL COMMENT '类型（1获得，2提现）',
  `status` tinyint(1) DEFAULT '0' COMMENT '状态（0待处理，1已完成，2已拒绝）',
  `audit_remark` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '提现审核备注',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `process_time` datetime DEFAULT NULL COMMENT '处理时间',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `djxs_commission_record_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `djxs_user` (`id`) ON DELETE CASCADE,
  CONSTRAINT `djxs_commission_record_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `djxs_order` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='佣金记录表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `djxs_content_stat_dedupe`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `djxs_content_stat_dedupe` (
  `hash` char(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'md5(维度键)',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`hash`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='内容统计去重（INSERT IGNORE）';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `djxs_device`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `djxs_device` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '设备ID',
  `user_id` int NOT NULL COMMENT '用户ID',
  `device_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '设备标识',
  `device_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '设备类型（android, ios, web）',
  `device_model` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '设备型号',
  `os_version` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '系统版本',
  `bind_time` datetime NOT NULL COMMENT '绑定时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `device_id` (`device_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `djxs_device_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `djxs_user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='设备表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `djxs_distribution`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `djxs_distribution` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '分销ID',
  `user_id` int NOT NULL COMMENT '用户ID',
  `parent_id` int DEFAULT '0' COMMENT '上级用户ID',
  `promotion_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '推广码',
  `total_commission` decimal(10,2) DEFAULT '0.00' COMMENT '总佣金',
  `available_commission` decimal(10,2) DEFAULT '0.00' COMMENT '可用佣金',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态（0禁用，1启用）',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `promotion_code` (`promotion_code`),
  KEY `user_id` (`user_id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `djxs_distribution_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `djxs_user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='分销表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `djxs_drama`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `djxs_drama` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '短剧ID',
  `title` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '标题',
  `cover` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '封面图',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT '简介',
  `total_episodes` int DEFAULT '0' COMMENT '总集数',
  `score` decimal(3,1) DEFAULT '0.0' COMMENT '评分',
  `play_count` int DEFAULT '0' COMMENT '播放次数',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态（0下架，1上架）',
  `category_id` int NOT NULL COMMENT '分类ID',
  `sort` int DEFAULT '0' COMMENT '排序',
  `price` decimal(10,2) DEFAULT '0.00' COMMENT '价格',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  `whole_bundle_ratio` decimal(8,4) NOT NULL DEFAULT '1.0000' COMMENT '整剧价相对上架单集标价合计的比例',
  PRIMARY KEY (`id`),
  KEY `title` (`title`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `djxs_drama_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `djxs_category` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='短剧表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `djxs_drama_episode`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `djxs_drama_episode` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '剧集ID',
  `drama_id` int NOT NULL COMMENT '短剧ID',
  `episode_number` int NOT NULL COMMENT '集数',
  `title` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '剧集标题',
  `video_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '视频地址',
  `duration` int DEFAULT '0' COMMENT '时长（秒）',
  `price` decimal(10,2) DEFAULT '0.00' COMMENT '价格',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态（0下架，1上架）',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `drama_id_episode` (`drama_id`,`episode_number`),
  KEY `drama_id` (`drama_id`),
  CONSTRAINT `djxs_drama_episode_ibfk_1` FOREIGN KEY (`drama_id`) REFERENCES `djxs_drama` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='短剧剧集表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `djxs_drama_tag`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `djxs_drama_tag` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `drama_id` int NOT NULL COMMENT '短剧ID',
  `tag_id` int NOT NULL COMMENT '标签ID',
  PRIMARY KEY (`id`),
  UNIQUE KEY `drama_tag` (`drama_id`,`tag_id`),
  KEY `drama_id` (`drama_id`),
  KEY `tag_id` (`tag_id`),
  CONSTRAINT `djxs_drama_tag_ibfk_1` FOREIGN KEY (`drama_id`) REFERENCES `djxs_drama` (`id`) ON DELETE CASCADE,
  CONSTRAINT `djxs_drama_tag_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `djxs_tag` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='短剧标签关联表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `djxs_member`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `djxs_member` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '会员ID',
  `user_id` int NOT NULL COMMENT '用户ID',
  `member_level_id` int NOT NULL COMMENT '会员等级ID',
  `start_time` datetime NOT NULL COMMENT '开始时间',
  `end_time` datetime NOT NULL COMMENT '结束时间',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态（0过期，1有效）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `member_level_id` (`member_level_id`),
  CONSTRAINT `djxs_member_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `djxs_user` (`id`) ON DELETE CASCADE,
  CONSTRAINT `djxs_member_ibfk_2` FOREIGN KEY (`member_level_id`) REFERENCES `djxs_member_level` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='会员表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `djxs_member_level`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `djxs_member_level` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '等级ID',
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '等级名称',
  `price` decimal(10,2) NOT NULL COMMENT '价格',
  `duration` int NOT NULL COMMENT '有效期（天）',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT '权益描述',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态（0禁用，1启用）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='会员等级表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `djxs_news`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `djxs_news` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(180) NOT NULL DEFAULT '' COMMENT '资讯标题',
  `cover` varchar(500) NOT NULL DEFAULT '' COMMENT '封面图',
  `summary` varchar(500) NOT NULL DEFAULT '' COMMENT '摘要',
  `content` longtext NOT NULL COMMENT '正文',
  `news_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1短剧资讯 2小说资讯',
  `related_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '关联内容类型 0无 1短剧 2小说',
  `related_id` int NOT NULL DEFAULT '0' COMMENT '关联内容ID',
  `is_top` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否置顶 0否 1是',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序，越大越靠前',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态 0草稿 1已发布',
  `publish_time` datetime DEFAULT NULL COMMENT '发布时间',
  `view_count` int NOT NULL DEFAULT '0' COMMENT '浏览量',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_news_type_status_publish` (`news_type`,`status`,`publish_time`),
  KEY `idx_status_top_sort` (`status`,`is_top`,`sort`),
  KEY `idx_related_type_id` (`related_type`,`related_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='短剧/小说资讯';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `djxs_novel`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `djxs_novel` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '小说ID',
  `title` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '标题',
  `cover` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '封面图',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT '简介',
  `total_chapters` int DEFAULT '0' COMMENT '总章节数',
  `word_count` int DEFAULT '0' COMMENT '总字数',
  `score` decimal(3,1) DEFAULT '0.0' COMMENT '评分',
  `read_count` int DEFAULT '0' COMMENT '阅读次数',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态（0下架，1上架）',
  `category_id` int NOT NULL COMMENT '分类ID',
  `sort` int DEFAULT '0' COMMENT '排序',
  `price` decimal(10,2) DEFAULT '0.00' COMMENT '价格',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  `whole_bundle_ratio` decimal(8,4) NOT NULL DEFAULT '1.0000' COMMENT '整本价相对上架章节标价合计的比例',
  PRIMARY KEY (`id`),
  KEY `title` (`title`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `djxs_novel_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `djxs_category` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='小说表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `djxs_novel_chapter`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `djxs_novel_chapter` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '章节ID',
  `novel_id` int NOT NULL COMMENT '小说ID',
  `chapter_number` int NOT NULL COMMENT '章节号',
  `title` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '章节标题',
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '章节内容',
  `word_count` int DEFAULT '0' COMMENT '字数',
  `price` decimal(10,2) DEFAULT '0.00' COMMENT '价格',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态（0下架，1上架）',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `novel_id_chapter` (`novel_id`,`chapter_number`),
  KEY `novel_id` (`novel_id`),
  CONSTRAINT `djxs_novel_chapter_ibfk_1` FOREIGN KEY (`novel_id`) REFERENCES `djxs_novel` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='小说章节表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `djxs_novel_tag`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `djxs_novel_tag` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `novel_id` int NOT NULL COMMENT '小说ID',
  `tag_id` int NOT NULL COMMENT '标签ID',
  PRIMARY KEY (`id`),
  UNIQUE KEY `novel_tag` (`novel_id`,`tag_id`),
  KEY `novel_id` (`novel_id`),
  KEY `tag_id` (`tag_id`),
  CONSTRAINT `djxs_novel_tag_ibfk_1` FOREIGN KEY (`novel_id`) REFERENCES `djxs_novel` (`id`) ON DELETE CASCADE,
  CONSTRAINT `djxs_novel_tag_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `djxs_tag` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='小说标签关联表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `djxs_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
-- 注意：历史库 `djxs_order` 可能不存在 `update_time` 字段，当前服务代码已做自动兼容。
CREATE TABLE `djxs_order` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '订单ID',
  `order_sn` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '订单号',
  `user_id` int NOT NULL COMMENT '用户ID',
  `total_amount` decimal(10,2) NOT NULL COMMENT '总金额',
  `pay_amount` decimal(10,2) NOT NULL COMMENT '实际支付金额',
  `pay_type` tinyint(1) DEFAULT '1' COMMENT '支付方式（1微信，2支付宝）',
  `status` tinyint(1) DEFAULT '0' COMMENT '状态（0待支付，1已支付，2已取消，3已退款）',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `pay_time` datetime DEFAULT NULL COMMENT '支付时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_sn` (`order_sn`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `djxs_order_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `djxs_user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `djxs_order_goods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `djxs_order_goods` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '订单商品ID',
  `order_id` int NOT NULL COMMENT '订单ID',
  `goods_type` tinyint(1) NOT NULL COMMENT '商品类型（1短剧剧集，2小说章节，3会员）',
  `goods_id` int NOT NULL COMMENT '商品ID',
  `goods_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '商品名称',
  `price` decimal(10,2) NOT NULL COMMENT '价格',
  `quantity` int NOT NULL COMMENT '数量',
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `djxs_order_goods_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `djxs_order` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单商品表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `djxs_system_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `djxs_system_config` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '配置ID',
  `key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '配置键',
  `value` text COLLATE utf8mb4_unicode_ci COMMENT '配置值',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '配置描述',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统配置表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `djxs_tag`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `djxs_tag` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '标签ID',
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '标签名称',
  `type` tinyint(1) NOT NULL COMMENT '类型（1短剧，2小说）',
  `sort` int DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态（0禁用，1启用）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name_type` (`name`,`type`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='标签表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `djxs_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `djxs_user` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '用户ID',
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '用户名',
  `password` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '密码（加密）',
  `mobile` varchar(11) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '手机号',
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '头像',
  `nickname` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '昵称',
  `gender` tinyint(1) DEFAULT '0' COMMENT '性别（0未知，1男，2女）',
  `birthday` date DEFAULT NULL COMMENT '生日',
  `reg_time` datetime NOT NULL COMMENT '注册时间',
  `last_login_time` datetime DEFAULT NULL COMMENT '最后登录时间',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态（0禁用，1启用）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `mobile` (`mobile`),
  KEY `reg_time` (`reg_time`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `djxs_user_purchase`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `djxs_user_purchase` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `user_id` int NOT NULL COMMENT '用户ID',
  `goods_type` tinyint(1) NOT NULL COMMENT '商品类型（1短剧剧集，2小说章节，3会员）',
  `goods_id` int NOT NULL COMMENT '商品ID',
  `order_id` int NOT NULL COMMENT '订单ID',
  `purchase_time` datetime NOT NULL COMMENT '购买时间',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `djxs_user_purchase_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `djxs_user` (`id`) ON DELETE CASCADE,
  CONSTRAINT `djxs_user_purchase_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `djxs_order` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户购买记录';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `djxs_user_read`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `djxs_user_read` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `user_id` int NOT NULL COMMENT '用户ID',
  `novel_id` int NOT NULL COMMENT '小说ID',
  `chapter_id` int NOT NULL COMMENT '章节ID',
  `progress` int DEFAULT '0' COMMENT '阅读进度（章节号）',
  `read_time` int DEFAULT '0' COMMENT '阅读时长（秒）',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `novel_id` (`novel_id`),
  KEY `chapter_id` (`chapter_id`),
  CONSTRAINT `djxs_user_read_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `djxs_user` (`id`) ON DELETE CASCADE,
  CONSTRAINT `djxs_user_read_ibfk_2` FOREIGN KEY (`novel_id`) REFERENCES `djxs_novel` (`id`) ON DELETE CASCADE,
  CONSTRAINT `djxs_user_read_ibfk_3` FOREIGN KEY (`chapter_id`) REFERENCES `djxs_novel_chapter` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户阅读记录';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `djxs_user_watch`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `djxs_user_watch` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `user_id` int NOT NULL COMMENT '用户ID',
  `drama_id` int NOT NULL COMMENT '短剧ID',
  `episode_id` int NOT NULL COMMENT '剧集ID',
  `watch_time` int DEFAULT '0' COMMENT '观看时长（秒）',
  `progress` int DEFAULT '0' COMMENT '观看进度（秒）',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `drama_id` (`drama_id`),
  KEY `episode_id` (`episode_id`),
  CONSTRAINT `djxs_user_watch_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `djxs_user` (`id`) ON DELETE CASCADE,
  CONSTRAINT `djxs_user_watch_ibfk_2` FOREIGN KEY (`drama_id`) REFERENCES `djxs_drama` (`id`) ON DELETE CASCADE,
  CONSTRAINT `djxs_user_watch_ibfk_3` FOREIGN KEY (`episode_id`) REFERENCES `djxs_drama_episode` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户观看记录';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;



-- ===============================
-- Flash Sale tables (from djxsplan2.MD)
-- ===============================
CREATE TABLE IF NOT EXISTS `djxs_flash_sale_activity` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '活动名称',
  `cover` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '活动封面',
  `status` tinyint NOT NULL DEFAULT 0 COMMENT '0草稿 1待开始 2进行中 3已结束 4已关闭',
  `preheat_time` datetime DEFAULT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `sort` int NOT NULL DEFAULT 0,
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_time` (`status`,`start_time`,`end_time`),
  KEY `idx_sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `djxs_flash_sale_item` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `activity_id` bigint unsigned NOT NULL,
  `goods_type` tinyint NOT NULL COMMENT '首期 10整剧 20整本',
  `goods_id` bigint unsigned NOT NULL,
  `title_snapshot` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `cover_snapshot` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `origin_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `seckill_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_stock` int NOT NULL DEFAULT 0,
  `sold_stock` int NOT NULL DEFAULT 0,
  `locked_stock` int NOT NULL DEFAULT 0,
  `limit_per_user` int NOT NULL DEFAULT 1,
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '1启用 0停用',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_activity_goods` (`activity_id`,`goods_type`,`goods_id`),
  KEY `idx_activity_status` (`activity_id`,`status`),
  CONSTRAINT `fk_flash_sale_item_activity` FOREIGN KEY (`activity_id`) REFERENCES `djxs_flash_sale_activity` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `djxs_flash_sale_order` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `activity_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `user_id` int NOT NULL,
  `request_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `buy_count` int NOT NULL DEFAULT 1,
  `seckill_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` tinyint NOT NULL DEFAULT 0 COMMENT '0待支付 1已支付 2已取消 3已超时',
  `reserve_expire_time` datetime DEFAULT NULL COMMENT '库存锁定过期时间',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_request` (`request_id`),
  KEY `idx_user_item` (`user_id`,`item_id`,`status`),
  KEY `idx_status_reserve_expire` (`status`,`reserve_expire_time`),
  KEY `idx_order` (`order_id`),
  KEY `idx_activity` (`activity_id`),
  CONSTRAINT `fk_flash_sale_order_order` FOREIGN KEY (`order_id`) REFERENCES `djxs_order` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_flash_sale_order_activity` FOREIGN KEY (`activity_id`) REFERENCES `djxs_flash_sale_activity` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_flash_sale_order_item` FOREIGN KEY (`item_id`) REFERENCES `djxs_flash_sale_item` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 秒杀风控（黑名单 + 命中日志）
CREATE TABLE IF NOT EXISTS `djxs_flash_sale_risk_blacklist` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `scene` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'create_order' COMMENT 'all/create_order',
  `target_type` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'user/ip/device',
  `target_value` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '1生效 0停用',
  `expire_time` datetime DEFAULT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_by` int NOT NULL DEFAULT 0,
  `updated_by` int NOT NULL DEFAULT 0,
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_scene_target` (`scene`,`target_type`,`target_value`),
  KEY `idx_status_expire` (`status`,`expire_time`),
  KEY `idx_target` (`target_type`,`target_value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `djxs_flash_sale_risk_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `scene` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'create_order',
  `reason` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_id` int NOT NULL DEFAULT 0,
  `activity_id` bigint unsigned NOT NULL DEFAULT 0,
  `item_id` bigint unsigned NOT NULL DEFAULT 0,
  `client_ip` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `device_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `extra_json` text COLLATE utf8mb4_unicode_ci,
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_scene_reason_time` (`scene`,`reason`,`create_time`),
  KEY `idx_activity_time` (`activity_id`,`create_time`),
  KEY `idx_item_time` (`item_id`,`create_time`),
  KEY `idx_user_time` (`user_id`,`create_time`),
  KEY `idx_ip_time` (`client_ip`,`create_time`),
  KEY `idx_device_time` (`device_id`,`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 秒杀风控健康分默认阈值（幂等初始化）
INSERT INTO `djxs_system_config` (`key`, `value`, `description`, `update_time`)
SELECT 'flash_sale_risk_threshold_safe', '85', '秒杀风控健康分安全阈值', NOW()
WHERE NOT EXISTS (SELECT 1 FROM `djxs_system_config` WHERE `key` = 'flash_sale_risk_threshold_safe');

INSERT INTO `djxs_system_config` (`key`, `value`, `description`, `update_time`)
SELECT 'flash_sale_risk_threshold_attention', '60', '秒杀风控健康分关注阈值', NOW()
WHERE NOT EXISTS (SELECT 1 FROM `djxs_system_config` WHERE `key` = 'flash_sale_risk_threshold_attention');

INSERT INTO `djxs_system_config` (`key`, `value`, `description`, `update_time`)
SELECT 'flash_sale_risk_threshold_warning', '35', '秒杀风控健康分预警阈值', NOW()
WHERE NOT EXISTS (SELECT 1 FROM `djxs_system_config` WHERE `key` = 'flash_sale_risk_threshold_warning');


-- ===============================
-- Minimal seed data
-- ===============================

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

LOCK TABLES `djxs_admin_permission` WRITE;
/*!40000 ALTER TABLE `djxs_admin_permission` DISABLE KEYS */;
INSERT INTO `djxs_admin_permission` (`id`, `code`, `name`, `type`, `parent_id`, `sort`, `status`, `remark`) VALUES (1,'dashboard:view','仪表盘',1,0,10,1,NULL),(2,'user:manage','用户管理',1,0,20,1,NULL),(3,'device:manage','设备管理',1,0,30,1,NULL),(4,'content:drama:manage','短剧管理',1,0,40,1,NULL),(5,'content:episode:manage','剧集管理',1,0,50,1,NULL),(6,'content:novel:manage','小说管理',1,0,60,1,NULL),(7,'content:chapter:manage','章节管理',1,0,70,1,NULL),(8,'content:category:manage','分类管理',1,0,80,1,NULL),(9,'content:tag:manage','标签管理',1,0,90,1,NULL),(10,'content:audit','内容审核',1,0,100,1,NULL),(11,'order:manage','订单查询与统计',1,0,110,1,NULL),(12,'order:refund','订单退款',1,0,120,1,NULL),(13,'member:manage','会员管理',1,0,130,1,NULL),(14,'distribution:manage','分销管理',1,0,140,1,NULL),(15,'ad:position:manage','广告位管理',1,0,150,1,NULL),(16,'ad:manage','广告管理',1,0,160,1,NULL),(17,'statistics:view','数据统计',1,0,170,1,NULL),(18,'config:manage','系统配置',1,0,180,1,NULL),(19,'system:job:view','系统任务状态',1,0,190,1,NULL),(20,'system:permission:manage','权限点管理',1,0,200,1,NULL),(21,'system:menu:manage','菜单管理',1,0,210,1,NULL),(22,'system:role:manage','角色管理',1,0,220,1,NULL),(23,'system:admin-user:manage','管理员账号',1,0,230,1,NULL),(24,'content:drama:category:manage','短剧分类管理',1,0,240,1,NULL),(25,'content:drama:tag:manage','短剧标签管理',1,0,250,1,NULL),(26,'content:drama:audit','短剧审核',1,0,260,1,NULL),(27,'content:novel:category:manage','小说分类管理',1,0,270,1,NULL),(28,'content:novel:tag:manage','小说标签管理',1,0,280,1,NULL),(29,'content:novel:audit','小说审核',1,0,290,1,NULL),(30,'content:news:manage','资讯管理',1,0,300,1,NULL);
/*!40000 ALTER TABLE `djxs_admin_permission` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `djxs_admin_menu` WRITE;
/*!40000 ALTER TABLE `djxs_admin_menu` DISABLE KEYS */;
INSERT INTO `djxs_admin_menu` (`id`, `parent_id`, `name`, `path`, `component`, `icon`, `permission_code`, `sort`, `visible`, `status`, `create_time`, `update_time`) VALUES (1,0,'仪表盘','/dashboard','Dashboard','Odometer','dashboard:view',10,1,1,'2026-04-18 12:27:21','2026-04-18 12:27:21'),(2,20,'用户管理','/users','UserList','User','user:manage',10,1,1,'2026-04-18 12:27:21','2026-04-18 12:27:21'),(3,17,'短剧管理','/dramas',NULL,'VideoCamera',NULL,10,1,1,'2026-04-18 12:27:21','2026-04-18 14:55:41'),(4,17,'小说管理','/novels',NULL,'Reading',NULL,20,1,1,'2026-04-18 12:27:21','2026-04-18 14:55:41'),(5,17,'分类标签与审核','/content-audit','ContentAudit','Collection','content:audit',30,0,0,'2026-04-18 12:27:21','2026-04-18 14:55:41'),(6,0,'订单管理','/orders','OrderList','Tickets','order:manage',50,1,1,'2026-04-18 12:27:21','2026-04-18 12:27:21'),(7,20,'会员管理','/members','MemberLevel','Medal','member:manage',20,1,1,'2026-04-18 12:27:21','2026-04-18 12:27:21'),(8,0,'分销管理','/distribution',NULL,'Share',NULL,75,1,1,'2026-04-18 12:27:21','2026-04-19 03:10:02'),(9,22,'广告位管理','/ad-positions','AdPosition','Monitor','ad:position:manage',10,1,1,'2026-04-18 12:27:21','2026-04-18 12:27:21'),(10,22,'广告素材','/ads','AdList','Picture','ad:manage',20,1,1,'2026-04-18 12:27:21','2026-04-18 12:27:21'),(11,0,'系统配置','/system-config',NULL,'Setting',NULL,105,1,1,'2026-04-18 12:27:21','2026-04-18 12:27:21'),(12,0,'系统管理','/system',NULL,'Tools',NULL,200,1,1,'2026-04-18 12:27:21','2026-04-18 12:27:21'),(13,12,'权限点','/system/permissions','SystemPermission','Key','system:permission:manage',10,1,1,'2026-04-18 12:27:21','2026-04-18 12:27:21'),(14,12,'菜单','/system/menus','SystemMenu','Menu','system:menu:manage',20,1,1,'2026-04-18 12:27:21','2026-04-18 12:27:21'),(15,12,'角色','/system/roles','SystemRole','UserFilled','system:role:manage',30,1,1,'2026-04-18 12:27:21','2026-04-18 12:27:21'),(16,12,'管理员','/system/admin-users','SystemAdminUser','Avatar','system:admin-user:manage',40,1,1,'2026-04-18 12:27:21','2026-04-18 12:27:21'),(17,0,'内容管理','/content',NULL,'FolderOpened',NULL,32,1,1,'2026-04-18 12:33:24','2026-04-18 12:33:24'),(20,0,'用户与会员','/user-center',NULL,'User',NULL,22,1,1,'2026-04-18 12:35:16','2026-04-18 12:35:16'),(21,11,'基础配置','/configs','ConfigList','Setting','config:manage',10,1,1,'2026-04-18 12:36:40','2026-04-20 04:46:11'),(22,11,'广告中心','/ad-center',NULL,'Promotion',NULL,22,1,1,'2026-04-18 12:38:02','2026-04-18 12:38:02'),(24,3,'短剧列表','/dramas/list','DramaList','List','content:drama:manage',10,1,1,'2026-04-18 14:40:53','2026-04-18 14:55:41'),(25,3,'短剧分类与标签','/dramas/meta','ContentAudit','Collection','content:drama:category:manage',20,1,1,'2026-04-18 14:40:53','2026-04-18 14:55:41'),(26,3,'短剧审核','/dramas/audit','ContentAudit','DocumentChecked','content:drama:audit',30,1,1,'2026-04-18 14:55:41','2026-04-18 14:55:41'),(27,4,'小说列表','/novels/list','NovelList','List','content:novel:manage',10,1,1,'2026-04-18 14:55:41','2026-04-18 14:55:41'),(28,4,'小说分类与标签','/novels/meta','ContentAudit','Collection','content:novel:category:manage',20,1,1,'2026-04-18 14:55:41','2026-04-18 14:55:41'),(29,4,'小说审核','/novels/audit','ContentAudit','DocumentChecked','content:novel:audit',30,1,1,'2026-04-18 14:55:41','2026-04-18 14:55:41'),(30,8,'分销记录','/distribution/records','DistributionRecords','List','distribution:manage',10,1,1,'2026-04-19 03:10:02','2026-04-19 03:10:02'),(31,8,'提现审核','/distribution/withdraw','DistributionWithdraw','Tickets','distribution:manage',20,1,1,'2026-04-19 03:10:02','2026-04-19 03:10:02'),(32,8,'分销配置','/distribution/config','DistributionConfig','Setting','distribution:manage',30,1,1,'2026-04-19 03:10:02','2026-04-19 03:10:02'),(33,11,'站内公告','/site-notice','SiteNotice','Bell','config:manage',5,1,1,'2026-04-20 04:46:11','2026-04-20 04:46:11'),(34,17,'资讯管理','/news','NewsList','Memo','content:news:manage',35,1,1,'2026-04-20 06:12:17','2026-04-20 06:12:17');
/*!40000 ALTER TABLE `djxs_admin_menu` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `djxs_admin_role` WRITE;
/*!40000 ALTER TABLE `djxs_admin_role` DISABLE KEYS */;
INSERT INTO `djxs_admin_role` (`id`, `code`, `name`, `status`, `create_time`) VALUES (1,'super_admin','超级管理员',1,'2026-04-18 12:17:47'),(2,'manager','管理员',1,'2026-04-18 21:11:11'),(3,'novel_manager','小说管理员',1,'2026-04-18 22:07:59'),(4,'drama_manager','短剧管理员',1,'2026-04-18 22:08:21');
/*!40000 ALTER TABLE `djxs_admin_role` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `djxs_admin_role_permission` WRITE;
/*!40000 ALTER TABLE `djxs_admin_role_permission` DISABLE KEYS */;
INSERT INTO `djxs_admin_role_permission` (`id`, `role_id`, `permission_id`) VALUES (1,1,1),(2,1,2),(3,1,3),(4,1,4),(5,1,5),(6,1,6),(7,1,7),(8,1,8),(9,1,9),(10,1,10),(11,1,11),(12,1,12),(13,1,13),(14,1,14),(15,1,15),(16,1,16),(17,1,17),(18,1,18),(19,1,19),(20,1,20),(21,1,21),(22,1,22),(23,1,23),(60,1,24),(61,1,25),(62,1,26),(63,1,27),(64,1,28),(65,1,29),(77,1,30),(32,2,1),(33,2,2),(34,2,3),(35,2,4),(36,2,5),(37,2,6),(38,2,7),(39,2,8),(40,2,9),(41,2,10),(42,2,11),(43,2,12),(44,2,13),(45,2,14),(46,2,15),(48,2,16),(47,2,17),(49,2,18),(50,2,19),(51,2,20),(52,2,21),(53,2,22),(54,2,23),(67,3,6),(68,3,7),(69,3,27),(70,3,28),(71,3,29),(72,4,4),(73,4,5),(74,4,24),(75,4,25),(76,4,26);
/*!40000 ALTER TABLE `djxs_admin_role_permission` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `djxs_admin_user` WRITE;
/*!40000 ALTER TABLE `djxs_admin_user` DISABLE KEYS */;
INSERT INTO `djxs_admin_user`
(`id`, `username`, `password`, `real_name`, `mobile`, `avatar`, `bind_user_id`, `status`, `last_login_time`, `last_login_ip`, `create_time`, `update_time`)
VALUES
(1, 'admin', '$2y$10$ujSyFZvmocs6JCz.itOg2eEiTDKkFDsjSeWgifs/KVQrLJx1vzp2K', '系统管理员', NULL, NULL, NULL, 1, NULL, NULL, '2026-04-20 00:00:00', '2026-04-20 00:00:00')
ON DUPLICATE KEY UPDATE
`password` = VALUES(`password`),
`real_name` = VALUES(`real_name`),
`status` = VALUES(`status`),
`update_time` = VALUES(`update_time`);
/*!40000 ALTER TABLE `djxs_admin_user` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `djxs_admin_user_role` WRITE;
/*!40000 ALTER TABLE `djxs_admin_user_role` DISABLE KEYS */;
INSERT INTO `djxs_admin_user_role` (`id`, `admin_user_id`, `role_id`)
VALUES (1, 1, 1)
ON DUPLICATE KEY UPDATE
`admin_user_id` = VALUES(`admin_user_id`),
`role_id` = VALUES(`role_id`);
/*!40000 ALTER TABLE `djxs_admin_user_role` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `djxs_member_level` WRITE;
/*!40000 ALTER TABLE `djxs_member_level` DISABLE KEYS */;
INSERT INTO `djxs_member_level` (`id`, `name`, `price`, `duration`, `description`, `status`) VALUES (1,'青铜会员',9.90,30,'免费观看部分短剧和小说章节',1),(2,'白银会员',19.90,30,'免费观看所有短剧和小说章节',1),(3,'黄金会员',39.90,90,'免费观看所有内容+专属客服',1),(4,'钻石会员',99.90,365,'全部权益+优先体验新内容',1),(5,'永恒会员',199.90,365,'全部权益+优先体验新内容+你是我大爷',1);
/*!40000 ALTER TABLE `djxs_member_level` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `djxs_category` WRITE;
/*!40000 ALTER TABLE `djxs_category` DISABLE KEYS */;
INSERT INTO `djxs_category` (`id`, `name`, `type`, `parent_id`, `sort`, `status`) VALUES (1,'都市',1,0,1,1),(2,'言情',1,0,2,1),(3,'悬疑',1,0,3,1),(4,'玄幻',1,0,4,1),(5,'仙侠',1,0,5,1),(6,'都市',2,0,1,1),(7,'言情',2,0,2,1),(8,'悬疑',2,0,3,1),(9,'玄幻',2,0,4,1),(10,'仙侠',2,0,5,1);
/*!40000 ALTER TABLE `djxs_category` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `djxs_tag` WRITE;
/*!40000 ALTER TABLE `djxs_tag` DISABLE KEYS */;
INSERT INTO `djxs_tag` (`id`, `name`, `type`, `sort`, `status`) VALUES (1,'总裁',1,1,1),(2,'穿越',1,2,1),(3,'重生',1,3,1),(4,'甜宠',1,4,1),(5,'虐恋',1,5,1),(6,'总裁',2,1,1),(7,'穿越',2,2,1),(8,'重生',2,3,1),(9,'甜宠',2,4,1),(10,'虐恋',2,5,1);
/*!40000 ALTER TABLE `djxs_tag` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `djxs_system_config` WRITE;
/*!40000 ALTER TABLE `djxs_system_config` DISABLE KEYS */;
INSERT INTO `djxs_system_config` (`id`, `key`, `value`, `description`, `update_time`) VALUES (1,'site_name','短剧+小说双模式运营平台','网站名称','2026-04-15 10:00:00'),(2,'site_url','https://example.com','网站域名','2026-04-15 10:00:00'),(3,'site_logo','https://example.com/logo.png','网站Logo','2026-04-15 10:00:00'),(4,'site_description','短剧+小说双模式运营平台','网站描述','2026-04-15 10:00:00'),(5,'site_keywords','短剧,小说,双模式,运营平台','网站关键词','2026-04-15 10:00:00'),(6,'site_copyright','© 2026 短剧+小说双模式运营平台','网站版权信息','2026-04-15 10:00:00'),(7,'site_icp','粤ICP备12345678号','网站ICP备案号','2026-04-15 10:00:00'),(8,'site_beian','粤公网安备12345678号','网站公安备案号','2026-04-15 10:00:00'),(9,'site_contact','contact@example.com','网站联系邮箱','2026-04-15 10:00:00'),(10,'site_phone','400-123-4567','网站联系电话','2026-04-15 10:00:00'),(11,'site_address','广东省深圳市南山区科技园','网站联系地址','2026-04-15 10:00:00'),(12,'site_qq','123456789','网站客服QQ','2026-04-15 10:00:00'),(13,'site_wechat','example_wechat','网站客服微信','2026-04-15 10:00:00'),(14,'site_weibo','example_weibo','网站官方微博','2026-04-15 10:00:00'),(15,'site_twitter','example_twitter','网站官方Twitter','2026-04-15 10:00:00'),(16,'site_facebook','example_facebook','网站官方Facebook','2026-04-15 10:00:00'),(17,'site_instagram','example_instagram','网站官方Instagram','2026-04-15 10:00:00'),(18,'site_youtube','example_youtube','网站官方YouTube','2026-04-15 10:00:00'),(19,'site_tiktok','example_tiktok','网站官方TikTok','2026-04-15 10:00:00'),(20,'site_github','example_github','网站官方GitHub','2026-04-15 10:00:00'),(21,'distribution_config','{\"rate\":\"80\",\"min_withdraw\":\"10\"}','分销配置','2026-04-20 10:38:17'),(22,'site_announcement','欢迎来到短剧+小说平台，更多活动请关注站内公告。哈哈哈哈','C端顶部公告文案','2026-04-20 13:37:03'),(23,'site_footer_text','Copyright © 2026 DJXS All Rights Reserved.','C端页脚文案','2026-04-20 03:19:56'),(24,'customer_service_wechat','djxs_kefu','C端客服微信','2026-04-20 03:19:56'),(25,'customer_service_qq','800012345','C端客服QQ','2026-04-20 03:19:56'),(26,'customer_service_phone','400-800-8888','C端客服电话','2026-04-20 03:19:56');
/*!40000 ALTER TABLE `djxs_system_config` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `djxs_ad_position` WRITE;
/*!40000 ALTER TABLE `djxs_ad_position` DISABLE KEYS */;
INSERT INTO `djxs_ad_position` (`id`, `name`, `position`, `width`, `height`, `status`) VALUES (1,'首页轮播','index_carousel',1920,500,1),(2,'首页侧边栏','index_sidebar',300,600,1),(3,'短剧详情页','drama_detail',728,90,1),(4,'小说详情页','novel_detail',728,90,1),(5,'分类页面','category_page',728,90,1),(6,'搜索页面','search_page',728,90,1),(7,'用户中心','user_center',300,600,1),(8,'支付页面','pay_page',728,90,1),(9,'会员页面','member_page',728,90,1),(10,'分销页面','distribution_page',728,90,1),(11,'广告管理','ad_manage',728,90,1),(12,'数据统计','data_statistics',728,90,1),(13,'系统设置','system_setting',728,90,1),(14,'帮助中心','help_center',728,90,1),(15,'关于我们','about_us',728,90,1),(16,'联系我们','contact_us',728,90,1),(17,'隐私政策','privacy_policy',728,90,1),(18,'用户协议','user_agreement',728,90,1),(19,'版权声明','copyright',728,90,1),(20,'友情链接','friend_link',728,90,1),(21,'首页顶部广告','home',1200,120,1),(22,'首页底部广告','home_bottom',1200,100,1),(23,'短剧播放页广告','drama_play',1200,80,1),(24,'小说阅读页广告','novel_read',1200,80,1);
/*!40000 ALTER TABLE `djxs_ad_position` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

