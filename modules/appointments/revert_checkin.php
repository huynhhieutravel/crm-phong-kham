<?php
// modules/appointments/revert_checkin.php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

// Ensure ID is passed and is a valid integer
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$db = getDB();

if ($id <= 0) {
    set_flash('Mã lịch hẹn không hợp lệ.', 'error');
    redirect("index.php");
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
    // Set status back to 'scheduled' and remove patient_id link for this specific appointment
    // Note: We DO NOT delete the patient record, just unlink it from this appointment's check-in state
    $stmt = $db->prepare("UPDATE appointments SET status = 'scheduled', patient_id = NULL WHERE id = ?");
    $stmt->execute([$id]);

    // 3. Revert Lead status
    // 'scheduled' is the standard status for a lead with an active appointment
    $stmt = $db->prepare("UPDATE leads SET status = 'scheduled' WHERE id = ?");
    $stmt->execute([$appt['lead_id']]);

    // 4. Log the action (Audit)
    if (function_exists('log_audit')) {
        log_audit($_SESSION['user_id'] ?? 0, 'REVERT_CHECKIN', 'appointments', $id, ['old_status' => 'arrived'], ['new_status' => 'scheduled']);
    }

    $db->commit();
    set_flash(__('appointment.msg.revert_success'));
    redirect("index.php");

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    set_flash($e->getMessage(), 'error');
    redirect("index.php");
}
