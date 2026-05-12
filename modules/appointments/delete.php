<?php
// modules/appointments/delete.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_appointments');

// Chỉ cho phép xóa qua POST (bảo mật)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('Phương thức không hợp lệ.', 'error');
    $redirect_url = $_SESSION['appointment_list_url'] ?? 'index.php';
    redirect($redirect_url);
}

// C1 FIX: Verify CSRF
verify_csrf('index.php');

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$db = getDB();

if ($id) {
    try {
        $stmt = $db->prepare("DELETE FROM appointments WHERE id = ?");
        $stmt->execute([$id]);
        set_flash('Đã xóa lịch hẹn.');
    } catch (Exception $e) {
        set_flash('Lỗi khi xóa: ' . $e->getMessage(), 'error');
    }
}

redirect('index.php');
