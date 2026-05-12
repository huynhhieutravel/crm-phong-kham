<?php
require __DIR__ . '/../includes/db.php';
try {
    $db = getDB();
    
    $stmt = $db->query("SELECT * FROM patient_packages WHERE patient_id IN (SELECT id FROM patients WHERE name LIKE '%Than Cảng%')");
    echo "Patient Packages:\n";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

    $stmt = $db->query("SELECT * FROM package_payments WHERE package_id IN (SELECT id FROM patient_packages WHERE patient_id IN (SELECT id FROM patients WHERE name LIKE '%Than Cảng%'))");
    echo "Package Payments:\n";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

    $stmt = $db->query("SELECT * FROM financial_transactions WHERE patient_id IN (SELECT id FROM patients WHERE name LIKE '%Than Cảng%')");
    echo "Financial Transactions (Than Cang):\n";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo $e->getMessage();
}
