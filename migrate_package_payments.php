<?php
/**
 * Migration: Tạo bảng package_payments + Migrate dữ liệu cũ
 * Chạy 1 lần duy nhất: php migrate_package_payments.php
 * Hoặc truy cập: http://localhost:8000/migrate_package_payments.php
 */
require_once __DIR__ . '/includes/db.php';

$db = getDB();

echo "<pre>\n";
echo "=== Migration: Package Payments ===\n\n";

// Step 1: Tạo bảng package_payments
echo "1. Creating table package_payments...\n";
$db->exec("
    CREATE TABLE IF NOT EXISTS `package_payments` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `patient_package_id` INT(11) NOT NULL,
        `amount` DECIMAL(12,2) NOT NULL,
        `payment_method` VARCHAR(30) DEFAULT 'cash' COMMENT 'cash, transfer_personal, transfer_company, card',
        `note` TEXT DEFAULT NULL,
        `created_by` INT(11) DEFAULT NULL,
        `paid_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Thời điểm thực tế thu tiền',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `patient_package_id` (`patient_package_id`),
        CONSTRAINT `pkg_pay_ibfk_1` FOREIGN KEY (`patient_package_id`) REFERENCES `patient_packages` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "   ✅ Done!\n\n";

// Step 2: Migrate dữ liệu cũ — Mỗi patient_package có paid_amount > 0 → Insert 1 record
echo "2. Migrating existing paid amounts...\n";

$existing = $db->query("SELECT COUNT(*) FROM package_payments")->fetchColumn();
if ($existing > 0) {
    echo "   ⚠️  Đã có {$existing} records trong package_payments. Bỏ qua migration.\n\n";
} else {
    $stmt = $db->query("
        SELECT pp.id, pp.paid_amount, pp.purchase_date, 
               t.created_by as tx_creator, t.description as tx_desc
        FROM patient_packages pp
        LEFT JOIN transactions t ON t.reference_id = pp.id AND t.category = 'package' AND t.type = 'income'
        WHERE pp.paid_amount > 0
    ");
    $rows = $stmt->fetchAll();
    
    $count = 0;
    $insert = $db->prepare("
        INSERT INTO package_payments (patient_package_id, amount, payment_method, note, created_by, paid_at)
        VALUES (?, ?, 'cash', ?, ?, ?)
    ");
    
    foreach ($rows as $row) {
        // Parse payment method from transaction description if possible
        $method = 'cash';
        $desc = $row['tx_desc'] ?? '';
        if (strpos($desc, 'CK Cá nhân') !== false) $method = 'transfer_personal';
        elseif (strpos($desc, 'TK Công ty') !== false) $method = 'transfer_company';
        elseif (strpos($desc, 'Quẹt Thẻ') !== false) $method = 'card';
        
        $db->prepare("
            INSERT INTO package_payments (patient_package_id, amount, payment_method, note, created_by, paid_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
            $row['id'],
            $row['paid_amount'],
            $method,
            'Thanh toán lần đầu (lúc mua gói)',
            $row['tx_creator'] ?: 1,
            $row['purchase_date']
        ]);
        $count++;
    }
    
    echo "   ✅ Migrated {$count} payment records.\n\n";
}

echo "=== Migration Complete! ===\n";
echo "</pre>";
