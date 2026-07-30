<?php
require_once 'includes/db.php';
$db = getDB();
try {
    $db->exec("
    CREATE TABLE IF NOT EXISTS `coin_tiers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `price` DECIMAL(12,2) NOT NULL,
        `coins_amount` DECIMAL(10,2) NOT NULL,
        `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
        `display_order` INT NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    
    // Seed data
    $stmt = $db->query("SELECT COUNT(*) FROM coin_tiers");
    if ($stmt->fetchColumn() == 0) {
        $db->exec("
            INSERT INTO coin_tiers (name, price, coins_amount, display_order) VALUES
            ('Gói Cơ Bản (2 Coins)', 600000, 2, 1),
            ('Gói Phổ Thông (3 Coins)', 900000, 3, 2),
            ('Gói Ưu Đãi (10 Coins)', 2800000, 10, 3);
        ");
        echo "Seeded coin_tiers.\n";
    }
    
    echo "Migration successful.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
