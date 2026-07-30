<?php
require_once __DIR__ . '/includes/db.php';
$db = getDB();
try {
    $db->exec("ALTER TABLE patients ADD COLUMN customer_group VARCHAR(255) NULL AFTER label");
    echo "Added customer_group successfully.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
