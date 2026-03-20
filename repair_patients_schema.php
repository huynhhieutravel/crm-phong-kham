<?php
// repair_patients_schema.php
// Place this in the root or a safe place and access it via browser on VPS to fix schema issues.

require_once 'includes/db.php';
$db = getDB();

$columns_to_add = [
    'customer_id' => "VARCHAR(50) NULL AFTER id",
    'occupation' => "VARCHAR(100) NULL AFTER branch",
    'guardian_name' => "VARCHAR(100) NULL AFTER occupation",
    'guardian_id_card' => "VARCHAR(20) NULL AFTER guardian_name",
    'guardian_phone' => "VARCHAR(20) NULL AFTER guardian_id_card",
    'guardian_relationship' => "VARCHAR(50) NULL AFTER guardian_phone",
    'zalo_number' => "VARCHAR(20) NULL AFTER guardian_relationship",
    'label' => "VARCHAR(50) NULL AFTER consultant_id",
    'facebook_link' => "VARCHAR(255) NULL AFTER notes",
    'instagram_link' => "VARCHAR(255) NULL AFTER facebook_link",
    'twitter_link' => "VARCHAR(255) NULL AFTER instagram_link"
];

echo "<h2>Checking Patients Table Schema...</h2>";

foreach ($columns_to_add as $column => $definition) {
    try {
        $check = $db->query("SHOW COLUMNS FROM patients LIKE '$column'");
        if ($check->rowCount() == 0) {
            echo "Adding column: <strong>$column</strong>... ";
            $db->exec("ALTER TABLE patients ADD COLUMN $column $definition");
            echo "<span style='color: green;'>Success</span><br>";
        } else {
            echo "Column <strong>$column</strong> already exists.<br>";
        }
    } catch (Exception $e) {
        echo "<span style='color: red;'>Error adding $column: " . $e->getMessage() . "</span><br>";
    }
}

echo "<br><p>Schema check complete. Try saving a patient again.</p>";
