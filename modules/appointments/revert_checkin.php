<?php
// modules/appointments/revert_checkin.php

require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_appointments');

// C3 FIX: POST-only, no GET mutations
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('Yêu cầu không hợp lệ.', 'error');
    $redirect_url = $_SESSION['appointment_list_url'] ?? 'index.php';
    redirect($redirect_url);
}

// C3 FIX: Verify CSRF
verify_csrf('index.php');

$id = (int)($_POST['id'] ?? 0);
$db = getDB();

if ($id <= 0) {
    set_flash('Mã lịch hẹn không hợp lệ.', 'error');
    $redirect_url = $_SESSION['appointment_list_url'] ?? 'index.php';
    redirect($redirect_url);
}

try {
    $db->beginTransaction();

    // 1. Get appointment data
    $stmt = $db->prepare("SELECT * FROM appointments WHERE id = ?");
    $stmt->execute([$id]);
    $appt = $stmt->fetch();

    if (!$appt) {
        throw new Exception(__('appointment.msg.not_found'));
    }

    if ($appt['status'] !== 'arrived') {
        throw new Exception("Chỉ có thể hoàn tác lịch hẹn đã check-in (Arrived).");
    }

    if (empty($appt['lead_id'])) {
        throw new Exception("Không thể hoàn tác: Lịch hẹn này không được tạo từ Lead.");
    }

    // 2. Revert Appointment
    $stmt = $db->prepare("UPDATE appointments SET status = 'scheduled', patient_id = NULL WHERE id = ?");
    $stmt->execute([$id]);

    // 3. Revert Lead status
    $stmt = $db->prepare("UPDATE leads SET status = 'scheduled' WHERE id = ?");
    $stmt->execute([$appt['lead_id']]);

    // 4. Log the action (Audit)
    if (function_exists('log_audit')) {
        log_audit($_SESSION['user_id'] ?? 0, 'REVERT_CHECKIN', 'appointments', $id, ['old_status' => 'arrived'], ['new_status' => 'scheduled']);
    }

    $db->commit();
    set_flash(__('appointment.msg.revert_success'));
    $redirect_url = $_SESSION['appointment_list_url'] ?? 'index.php';
    redirect($redirect_url);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    set_flash($e->getMessage(), 'error');
    $redirect_url = $_SESSION['appointment_list_url'] ?? 'index.php';
    redirect($redirect_url);
}
