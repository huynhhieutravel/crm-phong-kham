-- migration_fix.sql
-- Run this on your production database (clinic_management) to fix the 500 errors

-- 1. Update roles table if missing display_name
ALTER TABLE `roles` ADD COLUMN IF NOT EXISTS `display_name` varchar(50) NOT NULL AFTER `name`;

-- 2. Update leads table if missing status or booking time
ALTER TABLE `leads` ADD COLUMN IF NOT EXISTS `status` enum('new','contacted','scheduled','converted','cancelled') DEFAULT 'new' AFTER `notes`;
ALTER TABLE `leads` ADD COLUMN IF NOT EXISTS `appointment_booking_time` datetime DEFAULT NULL AFTER `recontact_time`;

-- 3. Update appointments table for new columns
ALTER TABLE `appointments` ADD COLUMN IF NOT EXISTS `reexam_rule_id` int(11) DEFAULT NULL AFTER `type`;
ALTER TABLE `appointments` ADD COLUMN IF NOT EXISTS `appointment_end_time` time DEFAULT NULL AFTER `appointment_date`;

-- 4. Ensure patients table has all columns (syncing with your local state)
ALTER TABLE `patients` ADD COLUMN IF NOT EXISTS `label` varchar(50) DEFAULT 'Khách mới' AFTER `zalo_number`;
ALTER TABLE `patients` ADD COLUMN IF NOT EXISTS `facebook_link` varchar(255) DEFAULT NULL AFTER `notes`;
ALTER TABLE `patients` ADD COLUMN IF NOT EXISTS `instagram_link` varchar(255) DEFAULT NULL AFTER `facebook_link`;
ALTER TABLE `patients` ADD COLUMN IF NOT EXISTS `twitter_link` varchar(255) DEFAULT NULL AFTER `instagram_link`;
ALTER TABLE `patients` ADD COLUMN IF NOT EXISTS `branch` varchar(50) DEFAULT NULL AFTER `created_at`;
ALTER TABLE `patients` ADD COLUMN IF NOT EXISTS `occupation` varchar(100) DEFAULT NULL AFTER `branch`;
ALTER TABLE `patients` ADD COLUMN IF NOT EXISTS `district` varchar(100) DEFAULT NULL AFTER `address`;
ALTER TABLE `patients` ADD COLUMN IF NOT EXISTS `city` varchar(100) DEFAULT NULL AFTER `district`;
ALTER TABLE `patients` ADD COLUMN IF NOT EXISTS `medical_history` text DEFAULT NULL AFTER `city`;
ALTER TABLE `patients` ADD COLUMN IF NOT EXISTS `consultant_id` int(11) DEFAULT NULL AFTER `medical_history`;

-- Ensure branches are correct
-- INSERT IGNORE INTO branches (id, name) VALUES (1, 'Cơ sở chính');
