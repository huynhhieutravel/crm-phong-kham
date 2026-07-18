<?php
require_once __DIR__ . '/../includes/db.php';
$db = getDB();
try {
    $stmt = $db->query("INSERT INTO products (name, price, category, status) VALUES ('Test Del Product', 100, 'general', 'active')");
    $id = $db->lastInsertId();
    echo "Inserted ID $id<br>";
    $db->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
    echo "Deleted ID $id successfully<br>";
} catch (PDOException $e) {
    echo "Product Error: " . $e->getMessage() . "<br>";
}

try {
    $stmt = $db->query("INSERT INTO packages (name, total_sessions, total_price) VALUES ('Test Del Pkg', 10, 1000)");
    $id = $db->lastInsertId();
    echo "Inserted Package ID $id<br>";
    $db->prepare("DELETE FROM packages WHERE id = ?")->execute([$id]);
    echo "Deleted Package ID $id successfully<br>";
} catch (PDOException $e) {
    echo "Package Error: " . $e->getMessage() . "<br>";
}
