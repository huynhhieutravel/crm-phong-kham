<?php
require_once __DIR__ . '/../includes/db.php';
$db = getDB();

// Move services with "Mua lẻ" to products
$items = $db->query("SELECT * FROM services WHERE name LIKE '%(Mua lẻ 1 buổi)%'")->fetchAll(PDO::FETCH_ASSOC);

foreach ($items as $item) {
    // Insert into products
    $stmt = $db->prepare("INSERT INTO products (name, price, category, status) VALUES (?, ?, 'general', 'active')");
    $stmt->execute([$item['name'], $item['price']]);
    
    // Delete from services
    $db->prepare("DELETE FROM services WHERE id = ?")->execute([$item['id']]);
    echo "Moved " . $item['name'] . " to products.<br>";
}

// Ensure linked_product_id exists in packages
try {
    $db->exec("ALTER TABLE packages ADD COLUMN linked_product_id INT NULL");
    $db->exec("ALTER TABLE packages ADD CONSTRAINT fk_packages_product FOREIGN KEY (linked_product_id) REFERENCES products(id) ON DELETE SET NULL");
    echo "Added linked_product_id to packages table.";
} catch (PDOException $e) { 
    echo "Notice: " . $e->getMessage();
}
