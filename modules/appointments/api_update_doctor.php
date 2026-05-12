<?php
// modules/appointments/api_update_doctor.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('edit_appointments');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['appointment_id']) || !isset($input['doctor_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$appointment_id = (int)$input['appointment_id'];
$new_doctor_id = (int)$input['doctor_id'];

// If doctor_id is 0, it means 'Chưa chỉ định' (Unassigned) -> stored as NULL in database (assuming typical logic)
$db_doctor_id = $new_doctor_id > 0 ? $new_doctor_id : null;

$db = getDB();

try {
    // Audit check: get old doctor
    $stmt = $db->prepare("SELECT doctor_id FROM appointments WHERE id = ?");
    $stmt->execute([$appointment_id]);
    $old_appt = $stmt->fetch();
    
    if (!$old_appt) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        exit;
    }
    
    // Update db
    $update_stmt = $db->prepare("UPDATE appointments SET doctor_id = ?, updated_at = NOW() WHERE id = ?");
    $update_stmt->execute([$db_doctor_id, $appointment_id]);
    
    // Log activity
    log_activity(
        $_SESSION['user_id'], 
        'edit', 
        'appointments', 
        $appointment_id, 
        sprintf('Reassigned appointment ID %d to doctor ID %s', $appointment_id, $db_doctor_id ?? 'NULL')
    );

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
}
