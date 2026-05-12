<?php
// modules/leads/delete.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_leads');

// Chỉ cho phép xóa qua POST (bảo mật)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('Phương thức không hợp lệ.', 'error');
    redirect('index.php');
}

// C3 FIX: Verify CSRF token
verify_csrf('index.php');

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$db = getDB();

if ($id) {
    try {
        // Xóa logs liên quan trước
        $db->prepare("DELETE FROM lead_logs WHERE lead_id = ?")->execute([$id]);
        // Xóa lead
        $stmt = $db->prepare("DELETE FROM leads WHERE id = ?");
        $stmt->execute([$id]);
        set_flash('Đã xóa Lead thành công!');
    } catch (Exception $e) {
        set_flash('Lỗi khi xóa Lead: ' . $e->getMessage(), 'error');
    }
}

redirect('index.php');
