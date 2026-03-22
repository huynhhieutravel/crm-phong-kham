<?php
// vps_health_check.php
require_once 'includes/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== CRM VPS HEALTH CHECK ===\n\n";

try {
    $db = getDB();
    echo "[PASS] Database Connection\n";
} catch (Exception $e) {
    echo "[FAIL] Database Connection: " . $e->getMessage() . "\n";
    exit;
}

$tables = [
    'appointments', 'patients', 'leads', 'users', 'roles', 
    'audit_logs', 'lead_logs', 'reexam_rules', 'patient_packages', 'transactions'
];

echo "\n--- Table Existence Check ---\n";
foreach ($tables as $table) {
    try {
        $db->query("SELECT 1 FROM $table LIMIT 1");
        echo "[PASS] Table '$table' exists\n";
    } catch (Exception $e) {
        echo "[FAIL] Table '$table' is MISSING\n";
    }
}

$columns = [
    'appointments' => ['branch_id', 'appointment_end_time', 'reexam_rule_id', 'type', 'status'],
    'leads' => ['appointment_booking_time', 'status', 'branch_id'],
    'patients' => ['branch_id', 'customer_id', 'consultant_id']
];

echo "\n--- Column Integrity Check ---\n";
foreach ($columns as $table => $cols) {
    try {
        $existing = $db->query("SHOW COLUMNS FROM $table")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($cols as $col) {
            if (in_array($col, $existing)) {
                echo "[PASS] $table -> '$col' exists\n";
            } else {
                echo "[FAIL] $table -> '$col' is MISSING\n";
            }
        }
    } catch (Exception $e) {
        echo "[SKIP] $table columns (table missing)\n";
    }
}

echo "\n=== END OF REPORT ===\n";
