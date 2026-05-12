<?php
require_once 'includes/db.php';
$db = getDB();
try {
    $stmt = $db->query("SELECT a.id, a.appointment_date, a.doctor_id, p.full_name as patient_name FROM appointments a JOIN patients p ON a.patient_id = p.id LIMIT 1");
    print_r($stmt->fetch());
    echo "Appointments query OK\n";
} catch (Exception $e) {
    echo "Appointments Error: " . $e->getMessage() . "\n";
}
