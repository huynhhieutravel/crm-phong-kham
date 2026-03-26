<?php
// modules/appointments/delete.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_appointments');

// Chỉ cho phép xóa qua POST (bảo mật)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('Phương thức không hợp lệ.', 'error');
    redirect('index.php');
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$db = getDB();

if ($id) {
    $stmt = $db->prepare("DELETE FROM appointments WHERE id = ?");
    $stmt->execute([$id]);
    set_flash('Đã xóa lịch hẹn.');
}

redirect('index.php');
