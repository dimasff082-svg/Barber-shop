-- ========================================================
-- BARBERFLOW Database Schema for MySQL / phpMyAdmin
-- Optimized for Shared Hosting (MariaDB 10.x / MySQL 8.x)
-- Compatible with hosts without TRIGGER / SUPER privileges
-- ========================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- Drop existing tables to ensure clean recreation
-- --------------------------------------------------------
DROP TABLE IF EXISTS `staff_audits`;
DROP TABLE IF EXISTS `gallery_images`;
DROP TABLE IF EXISTS `queue_events`;
DROP TABLE IF EXISTS `queue_tickets`;
DROP TABLE IF EXISTS `queue_counters`;
DROP TABLE IF EXISTS `barber_schedules`;
DROP TABLE IF EXISTS `business_closures`;
DROP TABLE IF EXISTS `business_hours`;
DROP TABLE IF EXISTS `customers`;
DROP TABLE IF EXISTS `services`;
DROP TABLE IF EXISTS `barbers`;
DROP TABLE IF EXISTS `business_settings`;
DROP TABLE IF EXISTS `qr_codes`;
DROP TABLE IF EXISTS `failed_jobs`;
DROP TABLE IF EXISTS `job_batches`;
DROP TABLE IF EXISTS `jobs`;
DROP TABLE IF EXISTS `cache_locks`;
DROP TABLE IF EXISTS `cache`;
DROP TABLE IF EXISTS `sessions`;
DROP TABLE IF EXISTS `password_reset_tokens`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `migrations`;

-- --------------------------------------------------------
-- Table: migrations
-- --------------------------------------------------------
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: users
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `two_factor_secret` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `two_factor_recovery_codes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('admin','barber') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'barber',
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: password_reset_tokens
-- --------------------------------------------------------
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: sessions
-- --------------------------------------------------------
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: cache
-- --------------------------------------------------------
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: cache_locks
-- --------------------------------------------------------
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: jobs
-- --------------------------------------------------------
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: job_batches
-- --------------------------------------------------------
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: failed_jobs
-- --------------------------------------------------------
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: barbers
-- --------------------------------------------------------
CREATE TABLE `barbers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `specialization` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `queue_prefix` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `availability_status` enum('available','busy','break','off_duty') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'off_duty',
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `accepts_queue` tinyint(1) NOT NULL DEFAULT 0,
  `daily_capacity` smallint(5) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `barbers_user_id_unique` (`user_id`),
  UNIQUE KEY `barbers_slug_unique` (`slug`),
  UNIQUE KEY `barbers_queue_prefix_unique` (`queue_prefix`),
  KEY `barbers_user_id_foreign` (`user_id`),
  CONSTRAINT `barbers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: services
-- --------------------------------------------------------
CREATE TABLE `services` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` decimal(12,2) NOT NULL,
  `estimated_duration_minutes` smallint(5) unsigned NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `services_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: customers
-- --------------------------------------------------------
CREATE TABLE `customers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `normalized_phone` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customers_normalized_phone_unique` (`normalized_phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: business_hours
-- --------------------------------------------------------
CREATE TABLE `business_hours` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `weekday` tinyint(3) unsigned NOT NULL,
  `starts_at` time DEFAULT NULL,
  `ends_at` time DEFAULT NULL,
  `is_closed` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `business_hours_weekday_unique` (`weekday`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: business_closures
-- --------------------------------------------------------
CREATE TABLE `business_closures` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `business_closures_date_unique` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: barber_schedules
-- --------------------------------------------------------
CREATE TABLE `barber_schedules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `barber_id` bigint(20) unsigned NOT NULL,
  `weekday` tinyint(3) unsigned NOT NULL,
  `starts_at` time NOT NULL,
  `ends_at` time NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `barber_schedules_barber_id_weekday_unique` (`barber_id`,`weekday`),
  KEY `barber_schedules_barber_id_foreign` (`barber_id`),
  CONSTRAINT `barber_schedules_barber_id_foreign` FOREIGN KEY (`barber_id`) REFERENCES `barbers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: business_settings
-- --------------------------------------------------------
CREATE TABLE `business_settings` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: qr_codes
-- --------------------------------------------------------
CREATE TABLE `qr_codes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `qr_codes_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: queue_counters
-- --------------------------------------------------------
CREATE TABLE `queue_counters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `barber_id` bigint(20) unsigned NOT NULL,
  `queue_date` date NOT NULL,
  `last_number` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `queue_counters_barber_id_queue_date_unique` (`barber_id`,`queue_date`),
  KEY `queue_counters_barber_id_foreign` (`barber_id`),
  CONSTRAINT `queue_counters_barber_id_foreign` FOREIGN KEY (`barber_id`) REFERENCES `barbers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: queue_tickets
-- --------------------------------------------------------
CREATE TABLE `queue_tickets` (
  `id` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `barber_id` bigint(20) unsigned NOT NULL,
  `service_id` bigint(20) unsigned NOT NULL,
  `customer_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `normalized_phone` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue_date` date NOT NULL,
  `sequence_number` int(10) unsigned NOT NULL,
  `queue_number` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` enum('online','onsite') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('waiting_checkin','checked_in','called','in_service','completed','missed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_hash` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_hash` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_fingerprint` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `idempotency_key` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price_snapshot` decimal(12,2) NOT NULL,
  `duration_snapshot` smallint(5) unsigned NOT NULL,
  `grace_started_at` timestamp NULL DEFAULT NULL,
  `grace_expires_at` timestamp NULL DEFAULT NULL,
  `checked_in_at` timestamp NULL DEFAULT NULL,
  `called_at` timestamp NULL DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `missed_at` timestamp NULL DEFAULT NULL,
  `cancellation_reason` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `queue_tickets_idempotency_key_unique` (`idempotency_key`),
  UNIQUE KEY `queue_tickets_barber_date_seq_unique` (`barber_id`,`queue_date`,`sequence_number`),
  UNIQUE KEY `queue_tickets_date_number_unique` (`queue_date`,`queue_number`),
  KEY `queue_tickets_barber_date_status_seq_idx` (`barber_id`,`queue_date`,`status`,`sequence_number`),
  KEY `queue_tickets_status_grace_idx` (`status`,`grace_expires_at`),
  KEY `queue_tickets_customer_id_foreign` (`customer_id`),
  KEY `queue_tickets_barber_id_foreign` (`barber_id`),
  KEY `queue_tickets_service_id_foreign` (`service_id`),
  CONSTRAINT `queue_tickets_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `queue_tickets_barber_id_foreign` FOREIGN KEY (`barber_id`) REFERENCES `barbers` (`id`),
  CONSTRAINT `queue_tickets_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: queue_events
-- --------------------------------------------------------
CREATE TABLE `queue_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue_ticket_id` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor_user_id` bigint(20) unsigned DEFAULT NULL,
  `event_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `previous_status` enum('waiting_checkin','checked_in','called','in_service','completed','missed','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` enum('waiting_checkin','checked_in','called','in_service','completed','missed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `queue_events_queue_ticket_id_created_at_index` (`queue_ticket_id`,`created_at`),
  KEY `queue_events_queue_ticket_id_foreign` (`queue_ticket_id`),
  KEY `queue_events_actor_user_id_foreign` (`actor_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: gallery_images
-- --------------------------------------------------------
CREATE TABLE `gallery_images` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caption` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `gallery_images_active_sort_idx` (`is_active`,`sort_order`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: staff_audits
-- --------------------------------------------------------
CREATE TABLE `staff_audits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `actor_user_id` bigint(20) unsigned NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `staff_audits_target_type_created_at_index` (`target_type`,`created_at`),
  KEY `staff_audits_actor_user_id_foreign` (`actor_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Seed Migration Records
-- --------------------------------------------------------
INSERT INTO `migrations` (`migration`, `batch`) VALUES
('0001_01_01_000000_create_users_table', 1),
('0001_01_01_000001_create_cache_table', 1),
('0001_01_01_000002_create_jobs_table', 1),
('2026_10_28_000100_add_staff_access_to_users_table', 1),
('2026_10_28_000200_create_barbers_table', 1),
('2026_10_28_000300_create_services_table', 1),
('2026_10_28_000400_create_queue_support_tables', 1),
('2026_10_28_000500_create_queue_tables', 1),
('2026_10_29_000600_create_gallery_images_table', 1),
('2026_10_29_000700_create_staff_audits_table', 1)
ON DUPLICATE KEY UPDATE `batch` = 1;

-- --------------------------------------------------------
-- Seed Default Staff Login Credentials
-- --------------------------------------------------------
INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `role`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Admin Barberflow', 'admin@barberflow.com', NOW(), '$2y$12$kyS88z.iarudoT6rbHSCmOkXKswBQbcE0b97EiI0z9OVRqfpzW6li', 'admin', 1, NOW(), NOW()),
(2, 'Barber Dimas', 'barber@barberflow.com', NOW(), '$2y$12$kyS88z.iarudoT6rbHSCmOkXKswBQbcE0b97EiI0z9OVRqfpzW6li', 'barber', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `password` = VALUES(`password`), `is_active` = VALUES(`is_active`), `role` = VALUES(`role`);

INSERT INTO `barbers` (`id`, `user_id`, `name`, `slug`, `photo`, `bio`, `specialization`, `queue_prefix`, `availability_status`, `is_active`, `accepts_queue`, `daily_capacity`, `created_at`, `updated_at`) VALUES
(1, 2, 'Barber Dimas', 'barber-dimas', NULL, 'Barber berpengalaman dengan keahlian potongan modern dan klasik.', 'Classic Cut & Fade Specialist', 'BDM', 'available', 1, 1, 30, NOW(), NOW())
ON DUPLICATE KEY UPDATE `is_active` = VALUES(`is_active`), `accepts_queue` = VALUES(`accepts_queue`);

SET FOREIGN_KEY_CHECKS = 1;
