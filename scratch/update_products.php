<?php
require_once __DIR__ . '/../includes/db.php';
$db = getDB();

try {
    // Delete existing dummy products
    $db->exec("DELETE FROM products WHERE name IN ('Kem boi giam dau', 'Dai lung cao cap') OR id > 0");
    echo "Deleted old products.\n";

    // Insert realistic clinic products
    $stmt = $db->prepare("INSERT INTO products (name, price, category, status) VALUES (?, ?, ?, 'active')");
    $stmt->execute(['Gối chỉnh hình cổ vai gáy', 750000, 'san_pham_vat_ly']);
    $stmt->execute(['Đai hỗ trợ thắt lưng cột sống', 1200000, 'san_pham_vat_ly']);
    $stmt->execute(['Dầu xoa bóp trị liệu đông y', 250000, 'thuoc']);
    $stmt->execute(['Miếng dán thảo dược giảm đau', 100000, 'thuoc']);
    $stmt->execute(['Băng dán cơ Kinesiology', 350000, 'san_pham_vat_ly']);

    echo "Inserted realistic products.\n";
    
    // Step 2: Update packages table schema (add items_config column)
    $db->exec("ALTER TABLE packages ADD COLUMN items_config JSON NULL AFTER total_price");
    echo "Added items_config column to packages.\n";

} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "items_config column already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
