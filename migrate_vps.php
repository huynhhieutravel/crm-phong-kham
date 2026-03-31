<?php
// migrate_vps.php — One-Click Root Fix for VPS Schema
require_once 'includes/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== CRM PRODUCTION MIGRATION & HEALTH CHECK ===\n\n";

try {
    $db = getDB();
    echo "[PASS] Database Connection established.\n";
} catch (Exception $e) {
    echo "[FAIL] Database Connection: " . $e->getMessage() . "\n";
    exit;
}

$migrations = [
    'appointments' => [
        'branch_id' => "ALTER TABLE appointments ADD COLUMN branch_id INT DEFAULT 1",
        'appointment_end_time' => "ALTER TABLE appointments ADD COLUMN appointment_end_time TIME NULL",
        'reexam_rule_id' => "ALTER TABLE appointments ADD COLUMN reexam_rule_id INT NULL",
        'type' => "ALTER TABLE appointments ADD COLUMN type ENUM('consultation', 'treatment', 're_exam', 'adjustment') DEFAULT 'consultation'"
    ],
    'leads' => [
        'branch_id' => "ALTER TABLE leads ADD COLUMN branch_id INT DEFAULT 1",
        'appointment_booking_time' => "ALTER TABLE leads ADD COLUMN appointment_booking_time DATETIME NULL",
        'consultant_id' => "ALTER TABLE leads ADD COLUMN consultant_id INT NULL",
        'source' => "ALTER TABLE leads ADD COLUMN source VARCHAR(50) DEFAULT 'facebook'",
        'medical_group' => "ALTER TABLE leads ADD COLUMN medical_group VARCHAR(100) NULL",
        'consultation_status' => "ALTER TABLE leads ADD COLUMN consultation_status VARCHAR(100) DEFAULT 'Mới'"
    ],
    'patients' => [
        'branch_id' => "ALTER TABLE patients ADD COLUMN branch_id INT DEFAULT 1",
        'customer_id' => "ALTER TABLE patients ADD COLUMN customer_id VARCHAR(50) NULL",
        'lead_id' => "ALTER TABLE patients ADD COLUMN lead_id INT NULL",
        'consultant_id' => "ALTER TABLE patients ADD COLUMN consultant_id INT NULL",
        'label' => "ALTER TABLE patients ADD COLUMN label VARCHAR(50) NULL",
        'zalo_number' => "ALTER TABLE patients ADD COLUMN zalo_number VARCHAR(20) NULL",
        'source' => "ALTER TABLE patients ADD COLUMN source VARCHAR(50) NULL",
        'personal_notes' => "ALTER TABLE patients ADD COLUMN personal_notes TEXT NULL"
    ],
    'users' => [
        'sort_order' => "ALTER TABLE users ADD COLUMN sort_order INT DEFAULT 0"
    ]
];

echo "\n--- Executing Root Schema Sync ---\n";

foreach ($migrations as $table => $cols) {
    try {
        $existing = $db->query("SHOW COLUMNS FROM $table")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($cols as $col => $sql) {
            if (in_array($col, $existing)) {
                echo "[ALREADY EXISTS] $table -> '$col'\n";
            } else {
                echo "[MIGRATING] Adding '$col' to '$table'... ";
                try {
                    $db->exec($sql);
                    echo "OK.\n";
                } catch (Exception $migrateError) {
                    echo "FAILED: " . $migrateError->getMessage() . "\n";
                }
            }
        }
    } catch (Exception $tableError) {
        echo "[ERROR] Table '$table' error: " . $tableError->getMessage() . "\n";
    }
}

// 4. Ensure Reexam Rules table
echo "\n--- Checking Core Infrastructure ---\n";
try {
    $db->query("SELECT 1 FROM reexam_rules LIMIT 1");
    echo "[PASS] Table 'reexam_rules' exists.\n";
} catch (Exception $e) {
    echo "[MISSING] Table 'reexam_rules' NOT FOUND. Creating... ";
    $createRuleSql = "CREATE TABLE reexam_rules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        frequency INT DEFAULT 1,
        current_session INT DEFAULT 0,
        last_reexam_at DATE NULL,
        next_due_at DATE NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    try {
        $db->exec($createRuleSql);
        echo "OK.\n";
    } catch (Exception $ce) {
        echo "FAILED: " . $ce->getMessage() . "\n";
    }
}

echo "\n=== MIGRATION COMPLETE ===\n";
echo "You can now safely delete this file (migrate_vps.php) from your server.\n";
