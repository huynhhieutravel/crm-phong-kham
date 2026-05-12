<?php
// modules/patients/delete.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_patients');

// C1 FIX: POST-only, no GET deletion
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('Yêu cầu không hợp lệ.', 'error');
    redirect('index.php');
}

// C1 FIX: Verify CSRF
verify_csrf('index.php');

$id = (int)($_POST['id'] ?? 0);
$db = getDB();

if ($id) {
    try {
        $db->beginTransaction();
        // Delete related records first
        $db->prepare("DELETE FROM medical_sessions WHERE patient_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM reexam_rules WHERE patient_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM appointments WHERE patient_id = ?")->execute([$id]);
        
        $stmt = $db->prepare("DELETE FROM patients WHERE id = ?");
        $stmt->execute([$id]);
        $db->commit();
        set_flash('Đã xóa bệnh nhân thành công!');
    } catch (PDOException $e) {
        if ($db->inTransaction()) $db->rollBack();
        // H5 FIX: Don't leak SQL error details
        error_log("Patient delete failed (ID: $id): " . $e->getMessage());
        set_flash('Lỗi khi xóa bệnh nhân.', 'error');
    }
} else {
    set_flash('Không tìm thấy bệnh nhân cần xóa.', 'error');
}

redirect('index.php');
