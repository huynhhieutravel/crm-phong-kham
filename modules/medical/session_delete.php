<?php
// modules/medical/session_delete.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

// Only Admin can delete medical sessions
if (!has_role('admin')) {
    set_flash('Bạn không có quyền thực hiện thao tác xóa buổi khám!', 'danger');
    redirect(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('Yêu cầu không hợp lệ.', 'danger');
    redirect('index.php');
}

verify_csrf('index.php');

$id = (int)($_POST['id'] ?? 0);
$patient_id = (int)($_POST['patient_id'] ?? 0);
$db = getDB();

if (!$id) {
    set_flash('Không tìm thấy phiên khám cần xóa.', 'danger');
    redirect('index.php');
}

try {
    $db->beginTransaction();

    // 1. Check if the session has related treatments
    $stmt = $db->prepare("SELECT COUNT(*) FROM treatments WHERE session_id = ?");
    $stmt->execute([$id]);
    $treatments_count = (int)$stmt->fetchColumn();

    if ($treatments_count > 0) {
        throw new Exception("Buổi khám này đã có $treatments_count ca điều trị/thủ thuật liên quan. Vui lòng xoá các ca điều trị trước khi xoá buổi khám để không làm sai lệch số liệu trừ buổi liệu trình.");
    }

    // 2. Check if the session has an appointment linked. If so, revert it to 'confirmed'
    $stmt = $db->prepare("SELECT appointment_id FROM medical_sessions WHERE id = ?");
    $stmt->execute([$id]);
    $appt_id = $stmt->fetchColumn();

    if ($appt_id) {
        $db->prepare("UPDATE appointments SET status = 'confirmed' WHERE id = ? AND status = 'completed'")->execute([$appt_id]);
    }

    // 3. Delete related medical_history logs
    $db->prepare("DELETE FROM medical_history WHERE session_id = ?")->execute([$id]);

    // 4. Delete the session itself
    $db->prepare("DELETE FROM medical_sessions WHERE id = ?")->execute([$id]);

    $db->commit();
    set_flash('Đã xóa buổi khám tạo sai thành công!', 'success');
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Session delete error: " . $e->getMessage());
    set_flash($e->getMessage(), 'danger');
}

if ($patient_id) {
    redirect("../patients/view.php?id=$patient_id");
} else {
    redirect("index.php");
}
