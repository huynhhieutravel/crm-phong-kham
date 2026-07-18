<?php
// vps_migration_final.php — Migration for Simon Center New Server Setup
require_once 'includes/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== MIGRATING DATA TO V2 ARCHITECTURE ===\n\n";

try {
    $db = getDB();
    echo "[PASS] Database Connection established.\n";
} catch (Exception $e) {
    echo "[FAIL] Database Connection: " . $e->getMessage() . "\n";
    exit;
}

// 1. ADD MISSING COLUMNS from V1 -> V2 evolution
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

echo "\n--- 1. Syncing Schema ---\n";
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

echo "\n--- 2. Fixing Medical History Types (The Crucial Part) ---\n";
// The core issue: Unifying `chiro_exam` and `chiropractic_v2` into just `chiropractic`
// so the V2 logic seamlessly picks up all historical data.
try {
    $db->exec("UPDATE medical_history SET type = 'chiropractic' WHERE type IN ('chiro_exam', 'chiropractic_v2')");
    $affected = $db->query("SELECT ROW_COUNT()")->fetchColumn();
    echo "[SUCCESS] Migrated old chiropractic forms to unified 'chiropractic' type.\n";
} catch (Exception $e) {
    echo "[ERROR] Failed to update medical history type: " . $e->getMessage() . "\n";
}

echo "\n--- 3. Creating Missing Tables Infrastructure ---\n";
$missing_tables = [
    'reexam_rules' => "CREATE TABLE `reexam_rules` (
      `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `patient_id` int NOT NULL,
      `frequency` int DEFAULT 1,
      `current_session` int DEFAULT 0,
      `last_reexam_at` date NULL,
      `next_due_at` date NULL,
      `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
    )",
    'audit_logs' => "CREATE TABLE `audit_logs` (
      `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `user_id` int NOT NULL,
      `action` varchar(50) NOT NULL,
      `target_table` varchar(50) NOT NULL,
      `target_id` int NOT NULL,
      `old_data` json DEFAULT NULL,
      `new_data` json DEFAULT NULL,
      `ip_address` varchar(45) DEFAULT NULL,
      `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
    )",
    'branches' => "CREATE TABLE `branches` (
      `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `name` varchar(100) NOT NULL,
      `address` text,
      `phone` varchar(20) DEFAULT NULL,
      `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
    )"
];

foreach ($missing_tables as $tableName => $createSql) {
    try {
        $db->query("SELECT 1 FROM $tableName LIMIT 1");
        echo "[EXISTING] Table '$tableName' is already present.\n";
    } catch (Exception $e) {
        echo "[CREATING] Table '$tableName'... ";
        try {
            $db->exec($createSql);
            if ($tableName === 'branches') {
                $db->exec("INSERT INTO branches (id, name) VALUES (1, 'Cơ sở chính')");
            }
            echo "OK.\n";
        } catch (Exception $e2) {
            echo "FAILED: " . $e2->getMessage() . "\n";
        }
    }
}

echo "\n=== MIGRATION V2 COMPLETED SUCCESSFULLY ===\n";
echo "You can now login to the system and verify the patient records.\n";
echo "Remember to delete this file later for security.\n";
