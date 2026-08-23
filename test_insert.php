<?php
require_once 'config/database.php';
$db = getDB();
try {
    $stmt = $db->prepare("
        INSERT INTO medical_history (patient_id, session_id, type, history_data, created_by)
        VALUES (193, 467, 'chiro_history_v2', '{}', 1)
    ");
    $stmt->execute();
    echo "Saved successfully. ID: " . $db->lastInsertId() . "\n";
    $db->exec("DELETE FROM medical_history WHERE id = " . $db->lastInsertId());
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
