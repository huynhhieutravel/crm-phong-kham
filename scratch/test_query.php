<?php
require_once 'includes/db.php';
$db = getDB();
try {
    $stmt = $db->prepare("
        SELECT pul.*, p.full_name as used_by_name, t.treatment_date, t.session_data,
               u.full_name as technician_name, pay.method as payment_method, pay.amount as payment_amount,
               a.id as appt_id
        FROM package_usage_logs pul
        JOIN patients p ON pul.patient_id = p.id
        LEFT JOIN treatments t ON pul.treatment_id = t.id
        LEFT JOIN appointments a ON pul.appointment_id = a.id
        LEFT JOIN users u ON COALESCE(t.technician_id, pul.technician_id) = u.id
        LEFT JOIN payments pay ON pay.treatment_id = t.id AND pay.patient_package_id = pul.patient_package_id
        WHERE pul.patient_package_id = 1
        ORDER BY pul.used_at DESC
    ");
    $stmt->execute();
    echo "Query OK\n";
} catch (Exception $e) {
    echo "Error 1: " . $e->getMessage() . "\n";
}

try {
    $stmt = $db->prepare("
        SELECT psu.*, p.full_name, p.phone, u.full_name as added_by_name
        FROM package_shared_users psu
        JOIN patients p ON psu.patient_id = p.id
        LEFT JOIN users u ON psu.added_by = u.id
        WHERE psu.patient_package_id = 1
        ORDER BY psu.added_at DESC
    ");
    $stmt->execute();
    echo "Query 2 OK\n";
} catch (Exception $e) {
    echo "Error 2: " . $e->getMessage() . "\n";
}
