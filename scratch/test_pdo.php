<?php
require_once __DIR__ . '/../includes/db.php';
$db = getDB();

try {
    $stmt = $db->query("SELECT * FROM products ORDER BY id DESC LIMIT 1");
    $product = $stmt->fetch();
    if ($product) {
        echo "Trying to delete product: {$product['name']} (ID: {$product['id']})<br>";
        $del = $db->prepare("DELETE FROM products WHERE id = ?");
        $del->execute([$product['id']]);
        echo "Deleted successfully!<br>";
    } else {
        echo "No products found.<br>";
    }
} catch (PDOException $e) {
    echo "PDO Error: " . $e->getMessage() . "<br>";
}
