/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.14-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: 127.0.0.1    Database: edaratek
-- ------------------------------------------------------
-- Server version	10.11.14-MariaDB-0ubuntu0.24.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `association_requests`
--

DROP TABLE IF EXISTS `association_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `association_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `manager_user_id` bigint(20) unsigned NOT NULL,
  `supervisor_user_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT 'طلب ضم المدرسة للإشراف',
  `status` varchar(255) NOT NULL DEFAULT 'PENDING',
  `notes` text DEFAULT NULL,
  `responded_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `association_requests_responded_by_foreign` (`responded_by`),
  KEY `association_requests_school_id_status_index` (`school_id`,`status`),
  KEY `association_requests_manager_user_id_status_index` (`manager_user_id`,`status`),
  KEY `association_requests_supervisor_user_id_status_index` (`supervisor_user_id`,`status`),
  KEY `association_requests_status_index` (`status`),
  CONSTRAINT `association_requests_manager_user_id_foreign` FOREIGN KEY (`manager_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `association_requests_responded_by_foreign` FOREIGN KEY (`responded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `association_requests_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  CONSTRAINT `association_requests_supervisor_user_id_foreign` FOREIGN KEY (`supervisor_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `association_requests`
--

LOCK TABLES `association_requests` WRITE;
/*!40000 ALTER TABLE `association_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `association_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attachments`
--

DROP TABLE IF EXISTS `attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `attachments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_message_id` bigint(20) unsigned DEFAULT NULL,
  `uploaded_by` bigint(20) unsigned NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `mime_type` varchar(255) NOT NULL,
  `file_size` bigint(20) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `attachments_ticket_message_id_foreign` (`ticket_message_id`),
  KEY `attachments_uploaded_by_created_at_index` (`uploaded_by`,`created_at`),
  CONSTRAINT `attachments_ticket_message_id_foreign` FOREIGN KEY (`ticket_message_id`) REFERENCES `ticket_messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attachments_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attachments`
--

LOCK TABLES `attachments` WRITE;
/*!40000 ALTER TABLE `attachments` DISABLE KEYS */;
/*!40000 ALTER TABLE `attachments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `entity_type` varchar(255) NOT NULL,
  `entity_id` bigint(20) unsigned DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_logs_user_id_foreign` (`user_id`),
  KEY `audit_logs_action_index` (`action`),
  KEY `audit_logs_entity_type_index` (`entity_type`),
  KEY `audit_logs_entity_id_index` (`entity_id`),
  CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `departments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departments`
--

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
INSERT INTO `departments` VALUES
(1,'المعلميين','2026-02-10 12:17:24','2026-02-10 12:17:24'),
(2,'الشوان الادارية','2026-02-11 18:34:35','2026-02-11 18:34:35');
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `educational_directorates`
--

DROP TABLE IF EXISTS `educational_directorates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `educational_directorates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `governorate` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `educational_directorates`
--

LOCK TABLES `educational_directorates` WRITE;
/*!40000 ALTER TABLE `educational_directorates` DISABLE KEYS */;
INSERT INTO `educational_directorates` VALUES
(3,'الرياض','الرياض','2026-02-09 14:42:05','2026-02-09 14:42:05'),
(4,'جدا1','جدة','2026-02-09 15:11:26','2026-02-09 15:11:26'),
(5,'ادارة الدمام','الدمام','2026-02-11 18:24:21','2026-02-11 18:24:21'),
(6,'الدمام1','الدمام','2026-02-13 15:14:07','2026-02-13 15:14:07');
/*!40000 ALTER TABLE `educational_directorates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `footer_columns`
--

DROP TABLE IF EXISTS `footer_columns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `footer_columns` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `footer_columns`
--

LOCK TABLES `footer_columns` WRITE;
/*!40000 ALTER TABLE `footer_columns` DISABLE KEYS */;
INSERT INTO `footer_columns` VALUES
(1,'روابط سريعة',1,1,'2026-02-10 10:18:56','2026-02-10 10:18:56'),
(2,'السياسات',2,1,'2026-02-10 10:22:04','2026-02-10 10:22:04');
/*!40000 ALTER TABLE `footer_columns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `footer_items`
--

DROP TABLE IF EXISTS `footer_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `footer_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `footer_column_id` bigint(20) unsigned NOT NULL,
  `label` varchar(255) NOT NULL,
  `url` varchar(255) DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `footer_items_footer_column_id_foreign` (`footer_column_id`),
  CONSTRAINT `footer_items_footer_column_id_foreign` FOREIGN KEY (`footer_column_id`) REFERENCES `footer_columns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `footer_items`
--

LOCK TABLES `footer_items` WRITE;
/*!40000 ALTER TABLE `footer_items` DISABLE KEYS */;
INSERT INTO `footer_items` VALUES
(1,1,'من نحن','#',0,1,'2026-02-10 10:21:08','2026-02-10 10:21:08'),
(2,1,'عنا','#',0,1,'2026-02-10 10:21:17','2026-02-10 10:21:17'),
(3,2,'السياسات العامة','#',0,1,'2026-02-10 10:22:12','2026-02-10 10:22:12'),
(4,2,'سياسة الخصوصية','#',0,1,'2026-02-10 10:22:22','2026-02-10 10:22:22');
/*!40000 ALTER TABLE `footer_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `header_items`
--

DROP TABLE IF EXISTS `header_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `header_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `header_menu_id` bigint(20) unsigned NOT NULL,
  `label` varchar(255) NOT NULL,
  `url` varchar(255) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `header_items_header_menu_id_foreign` (`header_menu_id`),
  CONSTRAINT `header_items_header_menu_id_foreign` FOREIGN KEY (`header_menu_id`) REFERENCES `header_menus` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `header_items`
--

LOCK TABLES `header_items` WRITE;
/*!40000 ALTER TABLE `header_items` DISABLE KEYS */;
INSERT INTO `header_items` VALUES
(1,1,'التدريس','#',0,'2026-02-10 10:45:18','2026-02-10 10:45:18'),
(2,1,'فعغقع','#',0,'2026-02-10 16:10:12','2026-02-10 16:10:12');
/*!40000 ALTER TABLE `header_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `header_menus`
--

DROP TABLE IF EXISTS `header_menus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `header_menus` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `url` varchar(255) DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `header_menus`
--

LOCK TABLES `header_menus` WRITE;
/*!40000 ALTER TABLE `header_menus` DISABLE KEYS */;
INSERT INTO `header_menus` VALUES
(1,'خدماتنا','#',1,'2026-02-10 10:45:07','2026-02-10 10:45:07'),
(2,'خطط الاسعار','#',2,'2026-02-15 14:15:41','2026-02-15 14:15:41');
/*!40000 ALTER TABLE `header_menus` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `media`
--

DROP TABLE IF EXISTS `media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `media` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(255) NOT NULL,
  `mime_type` varchar(255) NOT NULL,
  `file_size` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `media`
--

LOCK TABLES `media` WRITE;
/*!40000 ALTER TABLE `media` DISABLE KEYS */;
INSERT INTO `media` VALUES
(3,'logo.png','uploads/1X0UgDjEtZbwPCnvxDIS1UMe2tfzUVTwpkKXiRA7.png','image','image/png',465014,'2026-02-10 13:21:07','2026-02-10 13:21:07'),
(4,'intro.mp4','uploads/KjMlghg9RGvWMGrcfVaHasQI9K3eUtmOMvPQPfY2.mp4','video','video/mp4',3173568,'2026-02-10 13:48:11','2026-02-10 13:48:11'),
(5,'Gemini_Generated_Image_o6ybi7o6ybi7o6yb.png','uploads/uNEpWL3s3FasY0jRO7vcVCnHggzLB3TcCJ1e70wh.png','image','image/png',6931054,'2026-02-10 15:26:57','2026-02-10 15:26:57'),
(6,'Saudi_Educational_Platform_Video_Generation.mp4','uploads/GPrGXOsRuPRjRhAVvTsYU8ZGpuOs4RJxtJvROkp2.mp4','video','video/mp4',2707055,'2026-02-11 15:27:55','2026-02-11 15:27:55'),
(7,'logo2 copy.svg','uploads/c1at10NKJfzu7MW6PJ3kmT0r1Zhi19dC0G7UwcXh.svg','image','image/svg+xml',1085235,'2026-02-12 21:03:43','2026-02-12 21:03:43'),
(8,'بنر ادارة الجديد.gif','uploads/pRpTBSiBnry0RQYC1GiP6aDkH7nnYwy0YZ5bcnhw.gif','image','image/gif',21068674,'2026-02-13 14:51:45','2026-02-13 14:51:45'),
(9,'التوقيع.png','uploads/p6OkQ0yXbxXI3cccUO3Tpodl25oOGJiTA4a4y7Vr.png','image','image/png',1624210,'2026-02-13 14:53:28','2026-02-13 14:53:28'),
(10,'التوقيع.png','uploads/3vig6nlev4O8XpCVz1wgz7Mh4t5iz32YaSBKGrQi.png','image','image/png',1624210,'2026-02-13 14:56:47','2026-02-13 14:56:47'),
(11,'Saudi_Principal_s_Digital_Workflow.mp4','uploads/0LXjAb8fGOdWWbYM59fnMrfQotKlzFnUZh7NK51f.mp4','video','video/mp4',2468241,'2026-02-13 15:00:39','2026-02-13 15:00:39');
/*!40000 ALTER TABLE `media` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES
(1,'0001_01_01_000000_create_users_table',1),
(2,'0001_01_01_000001_create_cache_table',1),
(3,'0001_01_01_000002_create_jobs_table',1),
(4,'2026_02_09_130611_add_role_to_users_table',2),
(5,'2026_02_09_133333_create_educational_directorates_table',3),
(6,'2026_02_09_133334_create_schools_table',3),
(7,'2026_02_09_151513_add_avatar_and_phone_to_users_table',4),
(8,'2026_02_09_155025_create_settings_table',5),
(9,'2026_02_10_095207_create_footer_columns_table',6),
(10,'2026_02_10_095208_create_footer_items_table',6),
(11,'2026_02_10_102934_create_header_menus_table',7),
(12,'2026_02_10_102935_create_header_items_table',7),
(13,'2026_02_10_113659_create_permission_tables',8),
(14,'2026_02_10_120238_create_departments_table',9),
(15,'2026_02_10_120306_add_department_id_to_users_table',9),
(16,'2026_02_10_130925_create_media_table',10),
(17,'2026_02_10_135506_create_pages_table',11),
(18,'2026_02_10_143408_create_page_components_table',12),
(19,'2026_02_11_104703_add_mobile_to_users_table',13),
(20,'2026_02_11_110931_add_button_settings_to_settings_table',14),
(21,'2026_02_11_113910_add_home_page_content_setting',15),
(22,'2026_02_11_145836_add_button_settings_to_settings_table',16);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_permissions`
--

DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_permissions`
--

LOCK TABLES `model_has_permissions` WRITE;
/*!40000 ALTER TABLE `model_has_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `model_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_roles`
--

DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_roles`
--

LOCK TABLES `model_has_roles` WRITE;
/*!40000 ALTER TABLE `model_has_roles` DISABLE KEYS */;
INSERT INTO `model_has_roles` VALUES
(2,'App\\Models\\User',2);
/*!40000 ALTER TABLE `model_has_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `type` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text DEFAULT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_user_id_read_at_index` (`user_id`,`read_at`),
  KEY `notifications_type_index` (`type`),
  KEY `notifications_read_at_index` (`read_at`),
  CONSTRAINT `notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `page_components`
--

DROP TABLE IF EXISTS `page_components`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `page_components` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `shortcode` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `page_components_shortcode_unique` (`shortcode`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `page_components`
--

LOCK TABLES `page_components` WRITE;
/*!40000 ALTER TABLE `page_components` DISABLE KEYS */;
INSERT INTO `page_components` VALUES
(8,'بنر الرئيسية','[mainbanner-477]','{\"type\":\"banner\",\"title\":\"رؤية واحدة، أداء متكامل\",\"subtitle\":\"جسر التواصل بين الرقابة والتنفيذ\",\"mediaType\":\"video\",\"media\":\"uploads/KjMlghg9RGvWMGrcfVaHasQI9K3eUtmOMvPQPfY2.mp4\",\"btnText\":\"ابداء مجانا\",\"btnUrl\":\"#\",\"height\":\"min-h-screen\",\"overlay\":\"bg-black/30\",\"alignment\":\"text-center\",\"titleColor\":\"#82bff8\",\"subtitleColor\":\"#e5e7eb\",\"btnBgColor\":\"#2563eb\",\"btnTextColor\":\"#ffffff\",\"glassBgColor\":\"#ffffff\",\"glassOpacity\":\"2\",\"glassHeight\":200,\"glassMarginTop\":200,\"glassMarginBottom\":0,\"glassMarginRight\":0,\"glassMarginLeft\":0,\"design\":{\"marginTop\":-78,\"marginBottom\":0,\"paddingTop\":0,\"paddingBottom\":0,\"backgroundType\":\"color\",\"backgroundColor\":\"#2f53a7\",\"backgroundGradient\":\"linear-gradient(135deg, #111827 0%, #1f2937 100%)\",\"backgroundImage\":\"\",\"backgroundOpacity\":100,\"textAlign\":\"text-center\",\"titleSize\":44,\"subtitleSize\":20,\"bodySize\":18,\"titleWeight\":700,\"bodyWeight\":400,\"titleLineHeight\":1.2,\"bodyLineHeight\":1.8,\"imageWidth\":100,\"imageHeight\":0,\"imageFit\":\"cover\",\"imagePosition\":\"center center\",\"imageRadius\":0,\"imageDirection\":\"normal\"}}',1,'2026-02-11 19:24:38','2026-02-15 21:44:57'),
(9,'تعرف علينا','[section_title-289]','{\"type\":\"section_title\",\"title\":\"تعرف علينا\",\"subtitle\":\"من نحن\",\"style\":\"gradient\",\"alignment\":\"text-center\",\"titleColor\":\"#ffffff\",\"subtitleColor\":\"#619eff\",\"showSectionText\":false,\"titleSize\":\"40\",\"subtitleSize\":\"27\",\"image\":\"\",\"imageFit\":\"fill\",\"imageHeight\":300,\"marginTop\":\"1\",\"marginBottom\":\"3\",\"bgType\":\"image\",\"bgColor\":\"#111827\",\"bgGradient\":\"linear-gradient(135deg, #111827 0%, #1e293b 100%)\",\"bgImage\":\"uploads/c1at10NKJfzu7MW6PJ3kmT0r1Zhi19dC0G7UwcXh.svg\",\"bgImageOpacity\":100,\"imageWidth\":100,\"bg_text\":\"\",\"design\":{\"marginTop\":0,\"marginBottom\":0,\"paddingTop\":0,\"paddingBottom\":0,\"backgroundType\":\"none\",\"backgroundColor\":\"#324671\",\"backgroundGradient\":\"linear-gradient(135deg, #111827 0%, #1f2937 100%)\",\"backgroundImage\":\"\",\"backgroundOpacity\":100,\"textAlign\":\"text-center\",\"titleSize\":44,\"subtitleSize\":20,\"bodySize\":18,\"titleWeight\":700,\"bodyWeight\":400,\"titleLineHeight\":1.2,\"bodyLineHeight\":1.8,\"imageWidth\":100,\"imageHeight\":420,\"imageFit\":\"cover\",\"imagePosition\":\"center center\",\"imageRadius\":24,\"imageDirection\":\"normal\"}}',1,'2026-02-13 15:04:58','2026-02-15 21:26:25'),
(10,'احصائية','[stats-700]','{\"type\":\"stats\",\"title\":\"احصائية\",\"stats\":[{\"number\":\"2000\",\"label\":\"طالب\",\"suffix\":\"+\"},{\"number\":\"1000\",\"label\":\"معلم\",\"suffix\":\"+\"}]}',1,'2026-02-13 15:08:10','2026-02-13 15:08:10'),
(11,'الباقات','[pricing-486]','{\"type\":\"pricing\",\"title\":\"باقات الادارة\",\"subtitle\":\"الباقات\",\"plans\":[{\"name\":\"المميزة\",\"price\":\"2000\",\"features\":[{\"text\":\"انشاء مهام\",\"included\":true}],\"isFeatured\":false},{\"name\":\"المحدودة\",\"price\":\"1000\",\"features\":[{\"text\":\"انشاء \",\"included\":true}],\"isFeatured\":false}]}',1,'2026-02-13 15:10:19','2026-02-13 15:10:19'),
(12,'القسم1','[info_section-202]','{\"type\":\"info_section\",\"title\":\"لماذا هذه المنصة؟\",\"text\":\"الشفافية المطلقة: تبادل البيانات والتقارير في وقتها الحقيقي.\\n\\nأتمتة الحوكمة: تحويل الإجراءات الرقابية الروتينية إلى عمليات رقمية ذكية.\\n\\nتكامل الأداء: ربط الأهداف التنفيذية بالمعايير الرقابية لضمان جودة التعليم.\",\"image\":\"uploads/c1at10NKJfzu7MW6PJ3kmT0r1Zhi19dC0G7UwcXh.svg\",\"layout\":\"image_left\",\"btnText\":\"جرب الان\",\"btnUrl\":\"\",\"bgColor\":\"transparent\",\"animation\":\"fade-up\",\"titleColor\":\"#707070\",\"textColor\":\"#9ca3af\",\"btnBgColor\":\"#2563eb\",\"btnTextColor\":\"#ffffff\",\"design\":{\"marginTop\":-70,\"marginBottom\":0,\"paddingTop\":80,\"paddingBottom\":80,\"backgroundType\":\"none\",\"backgroundColor\":\"#111827\",\"backgroundGradient\":\"linear-gradient(135deg, #111827 0%, #1f2937 100%)\",\"backgroundImage\":\"\",\"backgroundOpacity\":100,\"textAlign\":\"text-right\",\"titleSize\":44,\"subtitleSize\":20,\"bodySize\":18,\"titleWeight\":700,\"bodyWeight\":400,\"titleLineHeight\":1.2,\"bodyLineHeight\":1.8,\"imageWidth\":100,\"imageHeight\":420,\"imageFit\":\"fill\",\"imagePosition\":\"center center\",\"imageRadius\":24,\"imageDirection\":\"normal\"}}',1,'2026-02-15 14:12:24','2026-02-15 21:27:13');
/*!40000 ALTER TABLE `page_components` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pages`
--

DROP TABLE IF EXISTS `pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `content` longtext DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pages_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pages`
--

LOCK TABLES `pages` WRITE;
/*!40000 ALTER TABLE `pages` DISABLE KEYS */;
/*!40000 ALTER TABLE `pages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_has_permissions`
--

DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_has_permissions`
--

LOCK TABLES `role_has_permissions` WRITE;
/*!40000 ALTER TABLE `role_has_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `role_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES
(1,'super_admin','web','2026-02-10 11:52:22','2026-02-10 11:52:22'),
(2,'school_manager','web','2026-02-10 11:52:22','2026-02-10 11:52:22'),
(3,'teacher','web','2026-02-10 11:52:22','2026-02-10 11:52:22'),
(4,'student','web','2026-02-10 11:52:22','2026-02-10 11:52:22'),
(5,'parent','web','2026-02-10 11:52:22','2026-02-10 11:52:22'),
(6,'supervisor','web','2026-02-10 11:52:22','2026-02-10 11:52:22'),
(7,'موجه','web','2026-02-11 18:34:48','2026-02-11 18:34:48');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `school_supervisor_assignments`
--

DROP TABLE IF EXISTS `school_supervisor_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `school_supervisor_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `supervisor_id` bigint(20) unsigned NOT NULL,
  `directorate_id` bigint(20) unsigned DEFAULT NULL,
  `school_id` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `school_supervisor_assignments_supervisor_id_is_active_index` (`supervisor_id`,`is_active`),
  KEY `school_supervisor_assignments_directorate_id_is_active_index` (`directorate_id`,`is_active`),
  KEY `school_supervisor_assignments_school_id_is_active_index` (`school_id`,`is_active`),
  CONSTRAINT `school_supervisor_assignments_directorate_id_foreign` FOREIGN KEY (`directorate_id`) REFERENCES `educational_directorates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `school_supervisor_assignments_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  CONSTRAINT `school_supervisor_assignments_supervisor_id_foreign` FOREIGN KEY (`supervisor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `school_supervisor_assignments`
--

LOCK TABLES `school_supervisor_assignments` WRITE;
/*!40000 ALTER TABLE `school_supervisor_assignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `school_supervisor_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schools`
--

DROP TABLE IF EXISTS `schools`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `schools` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `directorate_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `school_id` varchar(255) NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `schools_school_id_unique` (`school_id`),
  KEY `schools_directorate_id_foreign` (`directorate_id`),
  CONSTRAINT `schools_directorate_id_foreign` FOREIGN KEY (`directorate_id`) REFERENCES `educational_directorates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schools`
--

LOCK TABLES `schools` WRITE;
/*!40000 ALTER TABLE `schools` DISABLE KEYS */;
INSERT INTO `schools` VALUES
(3,3,'النخبة','SCH-562129','0501254545','mo.2bra@gmail.com','16 ش الشهيد','النصر لله','2026-02-09 14:43:01','2026-02-09 14:43:01'),
(4,3,'الحرية','SCH-625385','0501259545','mo.222e5d5@gmail.com','eesgfreg','srgerhg','2026-02-09 14:44:35','2026-02-09 14:44:35'),
(5,5,'الحمد','SCH-149694','0512454212','elhamd@edaratek.com','16 a kjhbj','tytuj','2026-02-11 18:25:22','2026-02-11 18:25:22');
/*!40000 ALTER TABLE `schools` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES
('10dLHEtSg8cOFZhr3sqtBc64RDVn7OvwmaaxgqdU',NULL,'13.229.91.176','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.114 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoidkV0aDdrZllvbm11eHVpbjlUeWliNllIalJjakZVZ2tweHNLOUNmdCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTk6Imh0dHA6Ly9lZGFyYXRlay5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1771235851),
('1Ens4GRDCfxHe2kNICDGZv7Wl7qmS0BVn1kLgH57',NULL,'113.31.186.146','Dalvik/2.1.0 (Linux; U; Android 9.0; ZTE BA520 Build/MRA58K)','YTozOntzOjY6Il90b2tlbiI7czo0MDoiVTNWbHlScTEwRVFEQzBiZ2FCR1AxSzREZnB4dG5JejJUOG9pTUx1RiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTk6Imh0dHA6Ly9lZGFyYXRlay5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1771199269),
('67ejPbZud4NPLWE52UDhqScr3ZbytB4JJLxn10i4',NULL,'157.230.253.139','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/95.0.4638.69 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiVktVSXRzZGhPdlhVc0RGaDVGRmNvbHo2WUhnN2I2d0NPVGFJYmh3bCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTk6Imh0dHA6Ly9lZGFyYXRlay5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1771198526),
('88DZ75wKAcOEqHaEF4aFbg64e8LBgQdhvqX0clPc',NULL,'54.162.95.96','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoibkhwNE8xZWRvaFhoNzFSVTNGbTYwZ3htenFiT09mbTV0aThueFRWQiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjM6Imh0dHA6Ly93d3cuZWRhcmF0ZWsuY29tIjtzOjU6InJvdXRlIjtzOjc6IndlbGNvbWUiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1771204059),
('arxQSWNVuLE0vKFrtmN5rt2wputu5cqtSttLiuhr',NULL,'134.209.72.177','Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)','YTozOntzOjY6Il90b2tlbiI7czo0MDoiTkJpYnB2RkVpOHZ1TjZyYlJqeU9xYWRPak5CUm0zS25ZSnpWOEtxcCI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjA6Imh0dHBzOi8vZWRhcmF0ZWsuY29tIjtzOjU6InJvdXRlIjtzOjc6IndlbGNvbWUiO319',1771213747),
('AyMo2C7QiNPl4zcquS1K83Q3B61bFlIFoZ9e9NfB',NULL,'43.159.138.217','Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1','YTozOntzOjY6Il90b2tlbiI7czo0MDoiakhKRVJIbzMwRU1nYzA5b3lMSWlaQlE2WVh4ZGp6a1M1enZFVE55UyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjM6Imh0dHA6Ly93d3cuZWRhcmF0ZWsuY29tIjtzOjU6InJvdXRlIjtzOjc6IndlbGNvbWUiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1771225234),
('bSHaF1JaxzkngzW6e3Ni3YTcuxr3DZm76jxJQiZz',NULL,'51.38.141.126','{USER_AGENT}','YTozOntzOjY6Il90b2tlbiI7czo0MDoiVklwYkpNMFJpdUo4UHlkTVFabjNCUEliNTJLVkVZN3hhTTZUNEVLTiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjA6Imh0dHBzOi8vZWRhcmF0ZWsuY29tIjtzOjU6InJvdXRlIjtzOjc6IndlbGNvbWUiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1771207929),
('byeZMVypVmNvkvAEUUT81Lwn07fcezgiRXORjCvU',NULL,'194.226.239.22','Python/3.11 aiohttp/3.11.11','YTozOntzOjY6Il90b2tlbiI7czo0MDoidTF1R2xycGhPTGVtQlF3MEJFUjZldW1ZaDg3bFBXSXQzMFhDamxmYiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTk6Imh0dHA6Ly9lZGFyYXRlay5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1771205828),
('Chdjc9xHaRGyM2IFM7jeYMOHLB9whvbUM4cq6Wd4',NULL,'149.102.233.166','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.114 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiN2V1c08wcjJlTVZwbThmMVFxaFVaaUpOSWNmSmRTN1BESGhMQ01IdyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTk6Imh0dHA6Ly9lZGFyYXRlay5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1771232578),
('df5rkfmnPxZ9TrJN8KHq1s4Cti74F9eIHkXwwLvO',NULL,'37.19.205.165','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3','YTozOntzOjY6Il90b2tlbiI7czo0MDoidEF3dHZtOEFsWml4Q1hIU0tXVHF4ZHJjNGpuU3V6WFNJOE02NXJEMSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTk6Imh0dHA6Ly9lZGFyYXRlay5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1771237022),
('DldTeBYsD0GK6eCSjPSu0UNL28nXMEXEkc7sldcq',NULL,'103.170.53.26','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiME1hRTJycFlaRk9VZThGOGFyNVlQblFITE5rMElCTEtZN1NJQ2p0cyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTk6Imh0dHA6Ly9lZGFyYXRlay5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1771211460),
('dmfe33O716Qy95znQE77yOOSorAVgB6VQbDKFHKm',NULL,'183.134.59.133','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11) AppleWebKit/601.1.27 (KHTML, like Gecko) Chrome/47.0.2526.106 Safari/601.1.27','YTozOntzOjY6Il90b2tlbiI7czo0MDoiU0lhVVliOHd3dzFRSDRCd3J3WUR4UE9qcFZHdDR1Q0o2OENodE91aiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjA6Imh0dHBzOi8vZWRhcmF0ZWsuY29tIjtzOjU6InJvdXRlIjtzOjc6IndlbGNvbWUiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1771199546),
('E2LYC94p8bgNkQ0NZ57U5mOnkEUdZZZBWOb797Rt',NULL,'35.237.255.0','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4.1 Safari/605.1.15','YTozOntzOjY6Il90b2tlbiI7czo0MDoiakpDUGlsNlF1bExKTE9Oc3FOZ08zQ1pYMGtYWmF5TnA4bXFpTzNxWSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjA6Imh0dHBzOi8vZWRhcmF0ZWsuY29tIjtzOjU6InJvdXRlIjtzOjc6IndlbGNvbWUiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1771234390),
('Gjb1rwpnt2wKcTU53mIOpNhKTac0F37YnFjFj4M6',NULL,'173.249.9.17','Go-http-client/1.1','YTozOntzOjY6Il90b2tlbiI7czo0MDoiZ1ZZcHVvU0VOZnBGa0w1NWRzS0p6SjJYcVZHcHpOb1V0allsMXdlOCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTk6Imh0dHA6Ly9lZGFyYXRlay5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1771212368),
('gl7knqh3tpYDoHbJCwrVC3iYanSKxuHvLv3SM3Ru',NULL,'156.200.3.6','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiYzBsMXR6SDNWN3R4Z01jQVpWUjByMU1lek1BdHJEdkVFYmlpcHRrZiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjA6Imh0dHBzOi8vZWRhcmF0ZWsuY29tIjtzOjU6InJvdXRlIjtzOjc6IndlbGNvbWUiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1771234560),
('HaWZ62DIbSfujzhtHmjuAnriLuqcY6nHzvXJ1CsY',NULL,'49.51.204.74','Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1','YTozOntzOjY6Il90b2tlbiI7czo0MDoiOU4xWDcxbTlFZTk3MFVQTVBCamprWTFwcURtSndQWjNoS1VpWkFNYSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjM6Imh0dHA6Ly93d3cuZWRhcmF0ZWsuY29tIjtzOjU6InJvdXRlIjtzOjc6IndlbGNvbWUiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1771201896),
('hChKq9IDOHPpE3j2l9LgtY7R2Swygwzkluev10dc',NULL,'79.127.129.203','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.114 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiRXBveG1qRVNqT0h5WEYySFlnNHRKdGh4V0ZUd2NLSmJmdmhMZ3lCTCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTk6Imh0dHA6Ly9lZGFyYXRlay5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1771230243),
('hWWpOb7EcA6jjFX6yOt1251BHagcVWxDJsnXDahG',NULL,'43.163.104.54','Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1','YTozOntzOjY6Il90b2tlbiI7czo0MDoiZ0dXQjd1RVJlTnJhVjczblJFTmZBWFVUOGVkZFhDa0FpTEl3OG5QViI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTk6Imh0dHA6Ly9lZGFyYXRlay5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1771197901),
('Ipqz2oZPBNmghNfNCMVCj3MUGoJVQgE7I6hTEBqs',NULL,'113.31.186.146','Mozilla/5.0 (Linux; U; Android 5.1; zh-cn; m2 note Build/LMY47D) AppleWebKit/537.36 (KHTML, like Gecko)Version/4.0 Chrome/37.0.0.0 MQQBrowser/7.8 Mobile Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiaWxKeEc1OXRMQUtyaElPUHk3RHlVRGNXSE0yVUVJVElXMTc1aXBrWiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTk6Imh0dHA6Ly9lZGFyYXRlay5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1771199245),
('JJqg10pPQzDTBxFY08SqILq7e4oAHBfEs2EZZBEk',NULL,'178.128.221.76','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.114 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoielY2TGIzSDhhNjg1d3hCNzU1cGl6VzY1VXlWckl6UGswb2c4WjR6WCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTk6Imh0dHA6Ly9lZGFyYXRlay5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1771207449),
('JrQPUV2rTQ6FYeO7CSHJF2WdHh2aBzxG40FBXywD',1,'41.38.207.14','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','YTo1OntzOjY6Il90b2tlbiI7czo0MDoicWFycGZjT3BMWFpudDluWHB5cXdFNWFTVW52VGVKTGhYajByZHBuTSI7czozOiJ1cmwiO2E6MDp7fXM6OToiX3ByZXZpb3VzIjthOjI6e3M6MzoidXJsIjtzOjIwOiJodHRwczovL2VkYXJhdGVrLmNvbSI7czo1OiJyb3V0ZSI7czo3OiJ3ZWxjb21lIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTt9',1771229463),
('kwoh04xzMw2vRHQDOzcfVN8ySnEkmLytZOSBQZR2',NULL,'185.91.69.242','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoicTZHZDhzQ1o1Q2FabkRjZUs1NHJmbTA3SHg0VWlWbU5RSFhFdmpUbyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTk6Imh0dHA6Ly9lZGFyYXRlay5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1771220030),
('L4VPjk2HYKsjejsstv2enHbAvTtiaBWOhglStw83',NULL,'37.19.205.165','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3','YTozOntzOjY6Il90b2tlbiI7czo0MDoiblY3eG5yeWJ4aDluellyRkRGMlFIUHZXOENnblRZcFRncDJkdDhwZiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTk6Imh0dHA6Ly9lZGFyYXRlay5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1771231421),
('lkbTBBwYPQLNzADmDVURkdzD460Dycx2YZhM1qlx',NULL,'13.229.91.176','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.114 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiRXF2SkFoQ0RrWTk3QXM2ZUVyakd2QVE1SlBJMnZ3NW1QSzZCWEtsMSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTk6Imh0dHA6Ly9lZGFyYXRlay5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1771238137),
('nUfPjY72F4S7OBcCTQv5Lwacz2fSUYAfElCM17bZ',NULL,'178.87.12.232','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0','YTozOntzOjY6Il90b2tlbiI7czo0MDoiODJLdHk3akpGbHNVRVBPYUZpTUl5RElXRUw2NE5lMGNyWXp1TmtwUyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjA6Imh0dHBzOi8vZWRhcmF0ZWsuY29tIjtzOjU6InJvdXRlIjtzOjc6IndlbGNvbWUiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1771225054),
('oyAQFxMs80ImSZsCRDHC8i0YTCLPAsybcbmrqoVR',NULL,'170.106.72.93','Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1','YTozOntzOjY6Il90b2tlbiI7czo0MDoialNsaVVQSDBKc3dLM0djemVpVFgxeTdyRnl1Umk4ejNRODIzaUZ0SSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTk6Imh0dHA6Ly9lZGFyYXRlay5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1771222111),
('p47dtoX42m1JQSEWk5jwHsp1P3PQZtuCGeUAt7Ri',NULL,'113.31.186.146','Mozilla/5.0 (Linux; U; Android 8.0.0; zh-CN; BKL-AL20 Build/HUAWEIBKL-AL20) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/57.0.2987.108 UCBrowser/11.9.4.974 UWS/2.14.0.11 Mobile Safari/537.36 AliApp(TB/7.10.0) UCBS/2.11.1.1 TTID/227200@taobao_android_7.10.0 WindVane/8.3.0 1080X2160','YTozOntzOjY6Il90b2tlbiI7czo0MDoiaW9RNWp3RGFHdmRkbmlTYTBTQ1JKN202Z0R1Vk9rbmR3b3FDTkxqTSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjA6Imh0dHBzOi8vZWRhcmF0ZWsuY29tIjtzOjU6InJvdXRlIjtzOjc6IndlbGNvbWUiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1771199451),
('q4AMKCariggmeMBTlx0HRzFWBdI2LGZ3Z15YpiOE',NULL,'162.120.188.213','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiQzJ4cm44S0xwUk1yMXZzWE5LcllBZ3k4MzJwZmFsWEpIR0FzeERTUCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjA6Imh0dHBzOi8vZWRhcmF0ZWsuY29tIjtzOjU6InJvdXRlIjtzOjc6IndlbGNvbWUiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1771230687),
('qa7Qgy4rAoXVIeyRsFg3xotHvc4j74fLyEC0AggW',NULL,'134.209.72.177','python-httpx/0.28.1','YTozOntzOjY6Il90b2tlbiI7czo0MDoiRXpNdFNMWWNhNE1ycE8wMVZxcVJGVWJmVkFhYlc4a1hReG9KYlFWdyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjA6Imh0dHBzOi8vZWRhcmF0ZWsuY29tIjtzOjU6InJvdXRlIjtzOjc6IndlbGNvbWUiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1771213856),
('QbVukZQMfHFkja2uaL2ClgnGh1VCY66vwXCMrRW7',NULL,'41.33.134.74','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiakFOdjJMMkExTXkxekltQ2tpTHJXSEJWR1VhdkhPa3c0dVFSRVpURyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjA6Imh0dHBzOi8vZWRhcmF0ZWsuY29tIjtzOjU6InJvdXRlIjtzOjc6IndlbGNvbWUiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1771234555),
('qQKV6XNZL9OGVzwTa7sYXNNnHjOFZzIassbX4ie2',NULL,'43.159.144.16','Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1','YTozOntzOjY6Il90b2tlbiI7czo0MDoiNlg3ckwxRGw0Wm5mQVdSUHhsbXZJSXFUYUdYRWNYMUxCUHVHZktSMyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjM6Imh0dHA6Ly93d3cuZWRhcmF0ZWsuY29tIjtzOjU6InJvdXRlIjtzOjc6IndlbGNvbWUiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1771207383),
('Qz3QOYx5gPU8o60PCNWXdvdtKMR9AUp0tkObu80J',NULL,'43.153.122.30','Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1','YTozOntzOjY6Il90b2tlbiI7czo0MDoiUDdhZlZJMzY1Y2ZIU2JCVWlzUVN5YWFWUGZGS3pXRHJMTDVJblVpbCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTk6Imh0dHA6Ly9lZGFyYXRlay5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1771203805),
('R07FmkLGO33RSXN5YqCDQjks0BFdLPfISFkR7xtQ',NULL,'41.33.134.74','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiSE4xbjFtZzZURzA1bHozeWtMWWhhMjMzS2p1ZG4xRXQ4ODRhT2FwUCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjA6Imh0dHBzOi8vZWRhcmF0ZWsuY29tIjtzOjU6InJvdXRlIjtzOjc6IndlbGNvbWUiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1771234741),
('reS7dKAzjqV9X3GBuMMJtmrvK8TKFhol0WrFlYss',NULL,'125.75.66.97','Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1','YTozOntzOjY6Il90b2tlbiI7czo0MDoibGM5QXRvekdZd01IU2lGbFNxbUszck53dUZCOEtsOTE4UU9qVlZ3ZyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjM6Imh0dHA6Ly93d3cuZWRhcmF0ZWsuY29tIjtzOjU6InJvdXRlIjtzOjc6IndlbGNvbWUiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1771217837),
('rgRFcoX0T9HFOgWB5smvF3fkUa9BXQiCpZ2wJeGk',NULL,'173.249.9.17','Go-http-client/1.1','YTozOntzOjY6Il90b2tlbiI7czo0MDoidzEzNjZ3N2MwTWlCdVBNa1lURmx5T1NGOGxnR25HVnhqcnJLeEd3TCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTk6Imh0dHA6Ly9lZGFyYXRlay5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1771210250),
('sy4qCOHkG5KDHv4ilciPSbKKUyzsxDQoyIlUYM3I',NULL,'45.134.252.158','Python/3.11 aiohttp/3.11.11','YTozOntzOjY6Il90b2tlbiI7czo0MDoiZUZhYW85QTFZa3RXS21kQWliT0s2SUhORXZRa1VOeW9aT21lbGJMTCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTk6Imh0dHA6Ly9lZGFyYXRlay5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1771215371),
('tqNv2exbVwMc2Wl3nBVttk7efFr2kgyXsLdCePea',NULL,'66.249.77.98','Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.7559.132 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)','YTozOntzOjY6Il90b2tlbiI7czo0MDoiclpCQlZwRjVSVnpDVGVTeDlPQ3hXM2NBTmM2NjIweWNRZXk3RWNYeSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTk6Imh0dHA6Ly9lZGFyYXRlay5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1771209340),
('tWYTE53yZXjj4IBABlRl5Op99hnDI3mOY9Q6ctri',NULL,'66.249.77.100','Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.7559.132 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)','YTozOntzOjY6Il90b2tlbiI7czo0MDoibU9TTVF6cEh6M1FBWGxTYkFZaVV2MWkzS3pIMVYzcWJtanpoYk02TiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTk6Imh0dHA6Ly9lZGFyYXRlay5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1771202590),
('UXuEjUPAz5FVRyMVqLqXSxPmZbw4n4fs4spdC4Tx',NULL,'64.176.34.178','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4240.193 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiSkFrRmxzajRGbHJSaURNNUdGZXA5TXN4NUhkNWZIM3h5Y2RmN3pERSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTk6Imh0dHA6Ly9lZGFyYXRlay5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1771219963),
('vHApZmEOk9fa1hNzObxbEKf9nsCGPImBeRejLnVu',NULL,'172.82.65.38','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiVk1Sc0JjUjdyQ1pYbFV1OGYyWTBkM1J3VlI3d1lCd1NsT3RvV3BmTCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjA6Imh0dHBzOi8vZWRhcmF0ZWsuY29tIjtzOjU6InJvdXRlIjtzOjc6IndlbGNvbWUiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1771209904),
('vML1XG5VGZ3S5Tj65xKVJsF8AryUWuAVZPvI4r9U',NULL,'34.204.6.156','Slackbot-LinkExpanding 1.0 (+https://api.slack.com/robots)','YTozOntzOjY6Il90b2tlbiI7czo0MDoiNW5zM1lYUTZHN1lBTllWYWxvWFVvZzR2dnZFR0RNbE96Z1JycFZzcSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjA6Imh0dHBzOi8vZWRhcmF0ZWsuY29tIjtzOjU6InJvdXRlIjtzOjc6IndlbGNvbWUiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1771234583),
('vVzEYcD9pVEct7Nn3rlCS9zZcHkwonHaQYA9S7Q4',NULL,'157.55.39.10','Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm) Chrome/116.0.1938.76 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiTFU2cTVXNDEwV1psMDRSdzdoSTRjR3pyME1EMmdtNElQVDVtTTZFcCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjY6Imh0dHBzOi8vZWRhcmF0ZWsuY29tL2xvZ2luIjtzOjU6InJvdXRlIjtzOjU6ImxvZ2luIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1771231343),
('Whafxq2sQNs3XS3iWQbXSFNe7wG2pAuPpRAqGKxo',NULL,'113.31.186.146','Dalvik/2.1.0 (Linux; U; Android 9.0; ZTE BA520 Build/MRA58K)','YTozOntzOjY6Il90b2tlbiI7czo0MDoiYzZCS2hzd3ZDNXRLU0Fnb2tzbnVqOEFHSTRaeVdkNUJiYW1kODA4dyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTk6Imh0dHA6Ly9lZGFyYXRlay5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1771199280),
('WTHBnRNSrOemU503UAkjI911BYrjAZOqeW12ksns',NULL,'183.134.59.133','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11) AppleWebKit/601.1.27 (KHTML, like Gecko) Chrome/47.0.2526.106 Safari/601.1.27','YTozOntzOjY6Il90b2tlbiI7czo0MDoiVWdrMVJ1QTRlZHRicVZWbThNdGxjZkxINE8xMzU1MUFqVlRNSE82YyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTk6Imh0dHA6Ly9lZGFyYXRlay5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1771199398),
('WUQvvnBvSZz27yEP7Pxlg8Ji89GdXC7mZ5KV7VTy',NULL,'185.44.76.189','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3','YTozOntzOjY6Il90b2tlbiI7czo0MDoiTk9UYXR6Y1pxcmRBWjhyamczQnJ3M2J0Q2NvOHdhd1JYNnVIemhxRSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTk6Imh0dHA6Ly9lZGFyYXRlay5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1771236690),
('y4viNVqE2Vn9SzCjxc4OZjRibYVYCIUQwmYaZviW',NULL,'66.249.77.99','Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.7559.132 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)','YTozOntzOjY6Il90b2tlbiI7czo0MDoiakN5R2JYQWV5d0JVQlF0WHNDUm9WMFhhZFdOd0lUcTJEdG1Nbzg5VSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjY6Imh0dHBzOi8vZWRhcmF0ZWsuY29tLz8lMjQ9IjtzOjU6InJvdXRlIjtzOjc6IndlbGNvbWUiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1771204091),
('ZX6J6tgxzmceczYOAP4sJuA6Kfr8ViGCX4hI2Snm',NULL,'142.248.80.123','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.114 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiTHhiT0tNN1VlR2txTlptc3FnTGdpNFdISk14eXJManJZYjhtOFFSYiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTk6Imh0dHA6Ly9lZGFyYXRlay5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1771203491);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) NOT NULL,
  `value` text DEFAULT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'text',
  `group` varchar(255) NOT NULL DEFAULT 'general',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_key_unique` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES
(1,'site_name',NULL,'text','general','2026-02-09 15:59:39','2026-02-15 11:38:33'),
(2,'primary_color','#2563eb','text','general','2026-02-09 15:59:39','2026-02-10 11:09:56'),
(3,'secondary_color','#1e1b4b','text','general','2026-02-09 15:59:39','2026-02-15 11:38:33'),
(4,'site_logo','uploads/c1at10NKJfzu7MW6PJ3kmT0r1Zhi19dC0G7UwcXh.svg','file','media','2026-02-09 15:59:39','2026-02-15 12:50:31'),
(5,'hero_video',NULL,'text','general','2026-02-09 15:59:39','2026-02-10 11:09:56'),
(6,'banner_image',NULL,'text','general','2026-02-09 15:59:39','2026-02-10 11:09:56'),
(7,'footer_text',NULL,'text','general','2026-02-09 15:59:39','2026-02-15 11:38:33'),
(8,'footer_desc',NULL,'text','general','2026-02-10 11:09:56','2026-02-15 11:38:33'),
(9,'bg_color','#001933','text','general','2026-02-10 11:19:32','2026-02-15 21:13:19'),
(10,'glass_color_1','#3b82f6','text','general','2026-02-10 11:19:32','2026-02-10 11:19:32'),
(11,'glass_color_2','#9333ea','text','general','2026-02-10 11:19:32','2026-02-10 12:00:24'),
(12,'heading_color','#ffffff','text','general','2026-02-10 11:19:32','2026-02-15 11:38:33'),
(13,'subheading_color','#e5e7eb','text','general','2026-02-10 11:19:32','2026-02-15 11:38:33'),
(14,'text_color','#9ca3af','text','general','2026-02-10 11:19:32','2026-02-15 11:38:33'),
(15,'show_skip_intro','0','text','banner','2026-02-10 11:19:32','2026-02-10 13:02:31'),
(16,'site_icon',NULL,'file','media','2026-02-10 11:19:32','2026-02-15 11:38:33'),
(17,'show_hero_video','1','text','general','2026-02-10 11:27:36','2026-02-10 11:27:49'),
(18,'banner_type','video','text','banner','2026-02-10 13:02:31','2026-02-10 13:48:18'),
(19,'banner_title','إدارتك.. بلمسة المستقبل','text','banner','2026-02-10 13:02:31','2026-02-10 13:02:31'),
(20,'banner_subtitle','تحكم في كل تفاصيل مؤسستك التعليمية...','text','banner','2026-02-10 13:02:31','2026-02-10 13:02:31'),
(21,'banner_btn_text','ابدأ الآن مجاناً','text','banner','2026-02-10 13:02:31','2026-02-10 13:02:31'),
(22,'banner_btn_url','/register','text','banner','2026-02-10 13:02:31','2026-02-10 13:02:31'),
(23,'show_banner_btn','1','text','general','2026-02-10 13:02:31','2026-02-10 13:02:31'),
(24,'banner_order','0','text','banner','2026-02-10 13:02:31','2026-02-10 13:02:31'),
(25,'banner_media','uploads/KjMlghg9RGvWMGrcfVaHasQI9K3eUtmOMvPQPfY2.mp4','file','banner','2026-02-10 13:02:31','2026-02-11 11:10:49'),
(26,'btn_bg_color','#2563eb','text','general','2026-02-11 11:09:36','2026-02-15 11:38:33'),
(27,'btn_text_color','#ffffff','text','general','2026-02-11 11:09:36','2026-02-15 10:34:20'),
(28,'btn_style','solid','text','general','2026-02-11 11:09:36','2026-02-15 11:38:33'),
(29,'btn_shape','rounded-lg','text','general','2026-02-11 11:09:36','2026-02-15 11:38:33'),
(30,'btn_animation','hover-scale','text','general','2026-02-11 11:09:36','2026-02-11 11:09:36'),
(31,'home_page_content','[mainbanner-477]\n[section_title-289]\n[info_section-202]','text','general','2026-02-11 11:39:15','2026-02-15 14:18:53'),
(32,'footer_bg_color','#141442','text','general','2026-02-15 11:06:14','2026-02-15 21:07:13'),
(33,'footer_text_color','#9ca3af','text','general','2026-02-15 11:06:14','2026-02-15 11:06:14'),
(34,'footer_heading_color','#ffffff','text','general','2026-02-15 11:06:14','2026-02-15 11:06:14'),
(35,'header_facebook','#','text','general','2026-02-15 11:38:33','2026-02-15 12:48:41'),
(36,'header_twitter','#','text','general','2026-02-15 11:38:33','2026-02-15 13:54:18'),
(37,'header_instagram','#','text','general','2026-02-15 11:38:33','2026-02-15 13:54:18'),
(38,'header_linkedin','#','text','general','2026-02-15 11:38:33','2026-02-15 13:54:18'),
(39,'header_contact_text','تواصل معنا','text','general','2026-02-15 11:38:33','2026-02-15 11:38:33'),
(40,'header_contact_url','#contact','text','general','2026-02-15 11:38:33','2026-02-15 11:38:33'),
(41,'header_bg_color','#001933','text','general','2026-02-15 12:47:31','2026-02-15 12:51:12'),
(42,'header_text_color','#ffffff','text','general','2026-02-15 12:47:31','2026-02-15 12:47:31'),
(43,'header_link_hover_color','#ffffff','text','general','2026-02-15 21:07:13','2026-02-15 21:47:56'),
(44,'header_height','119','text','general','2026-02-15 21:07:13','2026-02-15 21:46:48'),
(45,'header_logo_size','40','text','general','2026-02-15 21:07:13','2026-02-15 21:07:13'),
(46,'header_title_size','22','text','general','2026-02-15 21:07:13','2026-02-15 21:07:13'),
(47,'header_menu_size','15','text','general','2026-02-15 21:07:13','2026-02-15 21:07:13'),
(48,'header_padding_x','24','text','general','2026-02-15 21:07:13','2026-02-15 21:07:13'),
(49,'header_cta_radius','10','text','general','2026-02-15 21:07:13','2026-02-15 21:07:13'),
(50,'header_blur','14','text','general','2026-02-15 21:07:13','2026-02-15 21:07:13'),
(51,'header_border_opacity','10','text','general','2026-02-15 21:07:13','2026-02-15 21:07:13'),
(52,'footer_padding_top','64','text','general','2026-02-15 21:07:13','2026-02-15 21:07:13'),
(53,'footer_padding_bottom','32','text','general','2026-02-15 21:07:13','2026-02-15 21:07:13'),
(54,'footer_columns_gap','48','text','general','2026-02-15 21:07:14','2026-02-15 21:07:14'),
(55,'footer_title_size','18','text','general','2026-02-15 21:07:14','2026-02-15 21:07:14'),
(56,'footer_text_size','14','text','general','2026-02-15 21:07:14','2026-02-15 21:07:14'),
(57,'footer_link_size','14','text','general','2026-02-15 21:07:14','2026-02-15 21:07:14'),
(58,'footer_align','right','text','general','2026-02-15 21:07:14','2026-02-15 21:07:14');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `status_history`
--

DROP TABLE IF EXISTS `status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `status_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(255) NOT NULL,
  `entity_id` bigint(20) unsigned NOT NULL,
  `from_status` varchar(255) DEFAULT NULL,
  `to_status` varchar(255) NOT NULL,
  `changed_by` bigint(20) unsigned DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `status_history_changed_by_foreign` (`changed_by`),
  KEY `status_history_entity_type_entity_id_created_at_index` (`entity_type`,`entity_id`,`created_at`),
  KEY `status_history_entity_type_index` (`entity_type`),
  KEY `status_history_entity_id_index` (`entity_id`),
  CONSTRAINT `status_history_changed_by_foreign` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `status_history`
--

LOCK TABLES `status_history` WRITE;
/*!40000 ALTER TABLE `status_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `status_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subtasks`
--

DROP TABLE IF EXISTS `subtasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `subtasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint(20) unsigned NOT NULL,
  `school_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `assigned_to` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'OPEN',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subtasks_school_id_foreign` (`school_id`),
  KEY `subtasks_created_by_foreign` (`created_by`),
  KEY `subtasks_ticket_id_status_index` (`ticket_id`,`status`),
  KEY `subtasks_assigned_to_status_index` (`assigned_to`,`status`),
  KEY `subtasks_due_date_index` (`due_date`),
  KEY `subtasks_status_index` (`status`),
  CONSTRAINT `subtasks_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subtasks_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subtasks_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subtasks_ticket_id_foreign` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subtasks`
--

LOCK TABLES `subtasks` WRITE;
/*!40000 ALTER TABLE `subtasks` DISABLE KEYS */;
/*!40000 ALTER TABLE `subtasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ticket_messages`
--

DROP TABLE IF EXISTS `ticket_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ticket_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint(20) unsigned DEFAULT NULL,
  `subtask_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `message` longtext NOT NULL,
  `message_type` varchar(255) NOT NULL DEFAULT 'reply',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ticket_messages_user_id_foreign` (`user_id`),
  KEY `ticket_messages_ticket_id_created_at_index` (`ticket_id`,`created_at`),
  KEY `ticket_messages_subtask_id_created_at_index` (`subtask_id`,`created_at`),
  KEY `ticket_messages_message_type_index` (`message_type`),
  CONSTRAINT `ticket_messages_subtask_id_foreign` FOREIGN KEY (`subtask_id`) REFERENCES `subtasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_messages_ticket_id_foreign` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_messages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ticket_messages`
--

LOCK TABLES `ticket_messages` WRITE;
/*!40000 ALTER TABLE `ticket_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `ticket_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tickets`
--

DROP TABLE IF EXISTS `tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tickets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `assigned_to` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` longtext NOT NULL,
  `priority` varchar(255) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'OPEN',
  `manager_final_report` longtext DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tickets_school_id_status_index` (`school_id`,`status`),
  KEY `tickets_assigned_to_status_index` (`assigned_to`,`status`),
  KEY `tickets_created_by_status_index` (`created_by`,`status`),
  KEY `tickets_priority_index` (`priority`),
  KEY `tickets_due_date_index` (`due_date`),
  KEY `tickets_status_index` (`status`),
  CONSTRAINT `tickets_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tickets_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tickets_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tickets`
--

LOCK TABLES `tickets` WRITE;
/*!40000 ALTER TABLE `tickets` DISABLE KEYS */;
/*!40000 ALTER TABLE `tickets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `mobile` varchar(15) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `profile_photo_path` varchar(2048) DEFAULT NULL,
  `role` varchar(255) NOT NULL DEFAULT 'school_admin',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `department_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_mobile_unique` (`mobile`),
  KEY `users_department_id_foreign` (`department_id`),
  CONSTRAINT `users_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES
(1,'mohamed ebrahem','admin@edaratek.com',NULL,NULL,'profile-photos/hLOdIWZ2EaJbLJI7c86iVVco1LdNQD2Sf7cLxM4e.jpg','super_admin',1,'2026-02-09 13:08:34','$2y$12$0mKBJWSmSYEBskfSLdgbAu1txHL/tdrvOzTSo8Jii7zfTrN/LqeiK','VzaayCXmTxwBmf3XLCZ26ooejCIdLxEh2EKZCMdBb04sMHABBHKwRxT2nCVh','2026-02-09 13:08:34','2026-02-09 15:38:09',NULL),
(2,'احمد ابراهيم','ahmed@edaratek.com','0501235855',NULL,NULL,'school_admin',1,NULL,'$2y$12$i3pu4iYpCjzRMG7X2GKM7O5jPZ37W4lK4AnfKf0Uwd/5nghjye27C',NULL,'2026-02-11 10:59:44','2026-02-11 10:59:44',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'edaratek'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-02-16 11:53:11
