<?php
require_once __DIR__ . '/includes/db.php';

try {
    $db = getDB();
    
    // Check if column exists
    $stmt = $db->query("SHOW COLUMNS FROM invoices LIKE 'card_amount'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE invoices ADD COLUMN card_amount DECIMAL(12,2) DEFAULT 0.00 AFTER transfer_company_amount");
        echo "Successfully added 'card_amount' column to 'invoices' table.\n";
    } else {
        echo "Column 'card_amount' already exists in 'invoices' table.\n";
    }
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
