<?php
// modules/medical/session_add.php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_medical');

$db = getDB();
$patient_id = isset($_GET['patient_id']) ? $_GET['patient_id'] : 0;
$appointment_id = isset($_GET['appointment_id']) ? $_GET['appointment_id'] : null;

if (!$patient_id) {
    set_flash('Thiếu thông tin bệnh nhân.', 'error');
    redirect('../patients/index.php');
}

try {
    // Check for an active session (in_progress) for this patient today
    $stmt = $db->prepare("
        SELECT id FROM medical_sessions 
        WHERE patient_id = ? AND status = 'active' 
        AND DATE(session_date) = CURDATE()
        LIMIT 1
    ");
    $stmt->execute([$patient_id]);
    $existing_session = $stmt->fetchColumn();

    if ($existing_session) {
        // Redirect to existing active session instead of creating a new one
        redirect("session_view.php?id=$existing_session");
    }

    // Create a new session
    $stmt = $db->prepare("
        INSERT INTO medical_sessions (patient_id, doctor_id, appointment_id, session_date, status)
        VALUES (?, ?, ?, NOW(), 'active')
    ");
    $stmt->execute([
        $patient_id,
        $_SESSION['user_id'],
        $appointment_id
    ]);
    
    $session_id = $db->lastInsertId();
    
    // If appointment ID is provided, link it
    if ($appointment_id) {
        $db->prepare("UPDATE appointments SET status = 'completed' WHERE id = ?")
           ->execute([$appointment_id]);
    }

    redirect("session_view.php?id=$session_id");

} catch (Exception $e) {
    set_flash('Lỗi khi tạo buổi khám: ' . $e->getMessage(), 'error');
    redirect("../patients/view.php?id=$patient_id");
}
