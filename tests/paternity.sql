-- --------------------------------------------------------
-- 主机:                           127.0.0.1
-- 服务器版本:                        8.0.41-0ubuntu0.22.04.1 - (Ubuntu)
-- 服务器操作系统:                      Linux
-- HeidiSQL 版本:                  9.3.0.4984
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

-- 导出 paternity_test 的数据库结构
CREATE DATABASE IF NOT EXISTS `paternity_test` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `paternity_test`;


-- 导出  表 paternity_test.families 结构
CREATE TABLE IF NOT EXISTS `families` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT ' ' COMMENT '家系编号',
  `report_time` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '报告日期',
  `report_result` tinyint(1) NOT NULL DEFAULT '0' COMMENT '分析结果0未分析,1分析中,2分析成功,3分析失败',
  `report_times` int NOT NULL DEFAULT '0' COMMENT '报告分析操作次数',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='家系';

-- 数据导出被取消选择。


-- 导出  表 paternity_test.families_samples 结构
CREATE TABLE IF NOT EXISTS `families_samples` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `family_id` bigint unsigned NOT NULL,
  `sample_id` bigint unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `family_line_id` (`family_id`),
  KEY `sample_id` (`sample_id`),
  CONSTRAINT `families_samples_ibfk_1` FOREIGN KEY (`family_id`) REFERENCES `families` (`id`) ON DELETE CASCADE,
  CONSTRAINT `families_samples_ibfk_2` FOREIGN KEY (`sample_id`) REFERENCES `samples` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 数据导出被取消选择。


-- 导出  表 paternity_test.samples 结构
CREATE TABLE IF NOT EXISTS `samples` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `batch_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '批次号',
  `sample_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1母本名,2儿名,3父名',
  `sample_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '样本名称',
  `family_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '家系名',
  `check_result` tinyint(1) NOT NULL DEFAULT '0' COMMENT '本样检测结果,0未检测，1检测中，2检测成功，3检测失败',
  `check_times` int NOT NULL DEFAULT '0' COMMENT '检测操作次数',
  `analysis_result` tinyint(1) NOT NULL DEFAULT '0' COMMENT '启动报告分析，0未分析，1分析中，2分析完成，3分析失败',
  `analysis_times` int NOT NULL DEFAULT '0' COMMENT '分析操作次数',
  `off_machine_time` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '下机时间',
  `off_machine_data` int NOT NULL DEFAULT '0' COMMENT '下机数据量',
  `analysis_time` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '分析时间',
  `report_time` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '报告时间',
  `pregnancy_week` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '孕周',
  `analysis_process` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '分析流程：空或umi',
  `r1_url` varchar(250) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '该样本的R1数据绝对路径',
  `r2_url` varchar(250) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '该样本的R2数据绝对路径',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 数据导出被取消选择。
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
