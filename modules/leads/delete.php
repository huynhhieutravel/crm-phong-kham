<?php
// modules/leads/delete.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

$id = $_GET['id'] ?? 0;
$db = getDB();

try {
    $stmt = $db->prepare("DELETE FROM leads WHERE id = ?");
    $stmt->execute([$id]);
    set_flash('Đã xóa Lead thành công!');
} catch (Exception $e) {
    set_flash('Lỗi khi xóa Lead: ' . $e->getMessage(), 'error');
}

redirect('index.php');
