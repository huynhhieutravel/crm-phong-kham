<?php
// modules/hr/user_delete.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

// Only Admin can delete users
if ($_SESSION['role'] !== 'admin') {
    set_flash('Bạn không có quyền thực hiện thao tác xóa nhân viên!', 'danger');
    redirect('users.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('Yêu cầu không hợp lệ.', 'danger');
    redirect('users.php');
}

verify_csrf('users.php');

$id = (int)($_POST['id'] ?? 0);
$db = getDB();

if (!$id) {
    set_flash('Không tìm thấy nhân viên cần xóa.', 'danger');
    redirect('users.php');
}

// Cannot delete self
if ($id === (int)$_SESSION['user_id']) {
    set_flash('Bạn không thể tự xóa tài khoản đang đăng nhập của chính mình!', 'danger');
    redirect('users.php');
}

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    set_flash('Nhân viên không tồn tại trên hệ thống.', 'danger');
    redirect('users.php');
}

// Cannot delete the primary administrator if id 1 or username admin
if ($user['username'] === 'admin' || ($user['role_id'] == 1 && $user['id'] == 1)) {
    set_flash('Không thể xóa tài khoản Administrator hệ thống mặc định!', 'danger');
    redirect('users.php');
}

try {
    // Check foreign key dependencies
    $tables_to_check = [
        ['table' => 'appointments', 'col' => 'doctor_id', 'label' => 'Lịch hẹn'],
        ['table' => 'medical_sessions', 'col' => 'doctor_id', 'label' => 'Phiên khám'],
        ['table' => 'treatments', 'col' => 'technician_id', 'label' => 'Ca điều trị'],
        ['table' => 'invoices', 'col' => 'created_by', 'label' => 'Hóa đơn'],
        ['table' => 'transactions', 'col' => 'created_by', 'label' => 'Giao dịch thu chi'],
        ['table' => 'payments', 'col' => 'created_by', 'label' => 'Thanh toán'],
        ['table' => 'timekeeping', 'col' => 'user_id', 'label' => 'Chấm công'],
        ['table' => 'lead_logs', 'col' => 'user_id', 'label' => 'Nhật ký tư vấn'],
        ['table' => 'medical_history', 'col' => 'created_by', 'label' => 'Bệnh án'],
        ['table' => 'package_shared_users', 'col' => 'added_by', 'label' => 'Gói dịch vụ'],
        ['table' => 'leave_requests', 'col' => 'user_id', 'label' => 'Nghỉ phép'],
    ];

    $total_related = 0;
    $related_details = [];

    foreach ($tables_to_check as $chk) {
        $q = $db->prepare("SELECT COUNT(*) FROM `{$chk['table']}` WHERE `{$chk['col']}` = ?");
        $q->execute([$id]);
        $cnt = (int)$q->fetchColumn();
        if ($cnt > 0) {
            $total_related += $cnt;
            $related_details[] = "{$cnt} {$chk['label']}";
        }
    }

    if ($total_related === 0) {
        // No dependencies: Safe to hard delete permanently
        $db->prepare("UPDATE leads SET consultant_id = NULL WHERE consultant_id = ?")->execute([$id]);
        
        $del_stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $del_stmt->execute([$id]);
        
        set_flash("Đã xóa vĩnh viễn tài khoản nhân viên <strong>" . htmlspecialchars($user['full_name']) . " (@" . htmlspecialchars($user['username']) . ")</strong> khỏi hệ thống thành công!", "success");
    } else {
        // Has historical data: Safe soft-delete (mark as inactive)
        $update_stmt = $db->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
        $update_stmt->execute([$id]);

        $detail_str = implode(', ', array_slice($related_details, 0, 3));
        if (count($related_details) > 3) {
            $detail_str .= '...';
        }

        set_flash("Nhân viên <strong>" . htmlspecialchars($user['full_name']) . "</strong> có {$total_related} dữ liệu lịch sử ({$detail_str}). Hệ thống đã chuyển trạng thái sang <strong>Đã nghỉ việc / Ngừng hoạt động</strong> và ẩn khỏi danh sách nhân sự làm việc để đảm bảo toàn vẹn dữ liệu sổ sách phòng khám.", "warning");
    }
} catch (PDOException $e) {
    error_log("Error deleting user {$id}: " . $e->getMessage());
    set_flash('Lỗi khi thực hiện xóa nhân viên: ' . $e->getMessage(), 'danger');
}

redirect('users.php');
