<?php
require_once __DIR__ . '/../includes/db.php';

try {
    $db = getDB();
    
    echo "Bắt đầu tạo các bảng còn thiếu cho hệ thống Thu ngân (Sales)...\n";
    
    // 1. package_shared_users
    $db->exec("
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
    ");
    echo "- Đã tạo bảng package_shared_users\n";
    
    // 2. payments
    $db->exec("
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
    ");
    echo "- Đã tạo bảng payments\n";
    
    // 3. payment_audit_logs
    $db->exec("
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
    ");
    echo "- Đã tạo bảng payment_audit_logs\n";
    
    // 4. Alter treatments: add payment_status
    // Check if column exists first
    $stmt = $db->query("SHOW COLUMNS FROM `treatments` LIKE 'payment_status'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE `treatments` ADD COLUMN `payment_status` ENUM('pending','paid','debt') DEFAULT 'pending'");
        echo "- Đã thêm cột payment_status vào bảng treatments\n";
    } else {
        echo "- Cột payment_status đã tồn tại trong treatments\n";
    }
    
    echo "Thành công! Đã vá lỗi Database xong.\n";
    
} catch (PDOException $e) {
    echo "Lỗi: " . $e->getMessage() . "\n";
}
