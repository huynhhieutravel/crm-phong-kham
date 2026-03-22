-- deep_migration_fix.sql
-- Run this on your production database (clinic_management) to fix 500 errors and sync schema

-- 1. Create Missing Tables

CREATE TABLE IF NOT EXISTS `branches` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_table` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_id` int NOT NULL,
  `old_data` json DEFAULT NULL,
  `new_data` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lead_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `lead_id` int NOT NULL,
  `user_id` int NOT NULL,
  `note` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `lead_id` (`lead_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `lead_logs_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lead_logs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reexam_rules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `patient_id` int NOT NULL,
  `service_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `frequency` int NOT NULL COMMENT 'Frequency in months',
  `total_sessions` int DEFAULT '0',
  `current_session` int DEFAULT '0',
  `last_reexam_at` date DEFAULT NULL,
  `next_due_at` date DEFAULT NULL,
  `status` enum('active','paused','completed') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  CONSTRAINT `reexam_rules_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `packages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_sessions` int NOT NULL COMMENT 'Số buổi trong gói',
  `total_price` decimal(12,2) NOT NULL,
  `is_corporate` tinyint(1) DEFAULT '0' COMMENT 'Gói cho công ty/nhóm',
  `valid_days` int DEFAULT '365' COMMENT 'Hạn sử dụng',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `patient_packages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `patient_id` int NOT NULL COMMENT 'Chủ gói (hoặc đại diện công ty)',
  `package_id` int NOT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `paid_amount` decimal(12,2) DEFAULT '0.00',
  `sessions_remaining` int NOT NULL,
  `status` enum('active','exhausted','expired') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `purchase_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `expire_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `package_id` (`package_id`),
  CONSTRAINT `patient_packages_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `patient_packages_ibfk_2` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `package_usage_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `patient_package_id` int NOT NULL,
  `patient_id` int NOT NULL COMMENT 'Người thực tế sử dụng buổi này',
  `treatment_id` int NOT NULL,
  `used_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `patient_package_id` (`patient_package_id`),
  KEY `patient_id` (`patient_id`),
  KEY `treatment_id` (`treatment_id`),
  CONSTRAINT `package_usage_logs_ibfk_1` FOREIGN KEY (`patient_package_id`) REFERENCES `patient_packages` (`id`),
  CONSTRAINT `package_usage_logs_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `package_usage_logs_ibfk_3` FOREIGN KEY (`treatment_id`) REFERENCES `treatments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `type` enum('income','expense') COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Dịch vụ, Gói, Bán hàng, Nhập kho, Lương...',
  `amount` decimal(12,2) NOT NULL,
  `reference_id` int DEFAULT NULL COMMENT 'Link tới treatment_id, patient_package_id, v.v.',
  `description` text COLLATE utf8mb4_unicode_ci,
  `branch_id` int NOT NULL,
  `created_by` int NOT NULL,
  `transaction_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `branch_id` (`branch_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vouchers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_type` enum('percent','amount') COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_value` decimal(12,2) NOT NULL,
  `min_spend` decimal(12,2) DEFAULT '0.00',
  `expire_date` date DEFAULT NULL,
  `usage_limit` int DEFAULT '1',
  `used_count` int DEFAULT '0',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Update Existing Tables (Adding Columns)

-- Patients
ALTER TABLE `patients` ADD COLUMN IF NOT EXISTS `label` varchar(50) DEFAULT 'Khách mới' AFTER `zalo_number`;
ALTER TABLE `patients` ADD COLUMN IF NOT EXISTS `facebook_link` varchar(255) DEFAULT NULL;
ALTER TABLE `patients` ADD COLUMN IF NOT EXISTS `instagram_link` varchar(255) DEFAULT NULL;
ALTER TABLE `patients` ADD COLUMN IF NOT EXISTS `twitter_link` varchar(255) DEFAULT NULL;
ALTER TABLE `patients` ADD COLUMN IF NOT EXISTS `branch` varchar(50) DEFAULT NULL;
ALTER TABLE `patients` ADD COLUMN IF NOT EXISTS `occupation` varchar(100) DEFAULT NULL;
ALTER TABLE `patients` ADD COLUMN IF NOT EXISTS `district` varchar(100) DEFAULT NULL;
ALTER TABLE `patients` ADD COLUMN IF NOT EXISTS `city` varchar(100) DEFAULT NULL;
ALTER TABLE `patients` ADD COLUMN IF NOT EXISTS `medical_history` text DEFAULT NULL;
ALTER TABLE `patients` ADD COLUMN IF NOT EXISTS `consultant_id` int(11) DEFAULT NULL;

-- Leads
ALTER TABLE `leads` ADD COLUMN IF NOT EXISTS `status` enum('new','contacted','scheduled','converted','cancelled') DEFAULT 'new';
ALTER TABLE `leads` ADD COLUMN IF NOT EXISTS `medical_group` varchar(50) DEFAULT NULL;
ALTER TABLE `leads` ADD COLUMN IF NOT EXISTS `appointment_booking_time` datetime DEFAULT NULL;

-- Appointments
ALTER TABLE `appointments` ADD COLUMN IF NOT EXISTS `type` varchar(50) DEFAULT 'consultation' AFTER `appointment_date`;
ALTER TABLE `appointments` ADD COLUMN IF NOT EXISTS `appointment_end_time` time DEFAULT NULL AFTER `appointment_date`;
ALTER TABLE `appointments` ADD COLUMN IF NOT EXISTS `reexam_rule_id` int(11) DEFAULT NULL;
ALTER TABLE `appointments` ADD COLUMN IF NOT EXISTS `branch_id` int(11) DEFAULT 1;

-- Roles
ALTER TABLE `roles` ADD COLUMN IF NOT EXISTS `display_name` varchar(100) DEFAULT NULL;

-- 3. Sync Branches
INSERT IGNORE INTO `branches` (id, name) VALUES (1, 'Cơ sở chính');
