CREATE TABLE IF NOT EXISTS `package_shared_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_package_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL COMMENT 'Người được dùng chung gói',
  `added_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_share` (`patient_package_id`, `patient_id`),
  KEY `patient_package_id` (`patient_package_id`),
  KEY `patient_id` (`patient_id`),
  CONSTRAINT `fk_psu_package` FOREIGN KEY (`patient_package_id`) REFERENCES `patient_packages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_psu_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `treatment_id` int(11) DEFAULT NULL,
  `patient_id` int(11) NOT NULL,
  `method` ENUM('cash','transfer','package','debt') NOT NULL,
  `status` ENUM('pending','completed','voided') DEFAULT 'completed',
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `patient_package_id` int(11) DEFAULT NULL,
  `payer_patient_id` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `treatment_id` (`treatment_id`),
  KEY `patient_id` (`patient_id`),
  CONSTRAINT `fk_pay_treatment` FOREIGN KEY (`treatment_id`) REFERENCES `treatments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payment_audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_id` int(11) NOT NULL,
  `patient_package_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL COMMENT 'create, void, adjust',
  `old_value` longtext DEFAULT NULL,
  `new_value` longtext DEFAULT NULL,
  `performed_by` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `payment_id` (`payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Use a safe way to add column if not exists in MySQL without stored procedure
SET @dbname = 'clinic_management';
SET @tablename = 'treatments';
SET @columnname = 'payment_status';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " ENUM('pending','paid','debt') DEFAULT 'pending'")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
