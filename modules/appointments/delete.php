<?php
// modules/appointments/delete.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

$id = isset($_GET['id']) ? $_GET['id'] : 0;
$db = getDB();

if ($id) {
    $stmt = $db->prepare("DELETE FROM appointments WHERE id = ?");
    $stmt->execute([$id]);
    set_flash('Đã xóa lịch hẹn.');
}

redirect('index.php');
