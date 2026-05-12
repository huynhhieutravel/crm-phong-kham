<?php
// api/leave_requests.php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth_middleware.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = getDB();
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_json();
    $action = $_POST['action'] ?? 'create';

    if ($action === 'approve' || $action === 'reject') {
        if (!isset($_SESSION['role']) || (strtolower($_SESSION['role']) !== 'admin' && !can('view_hr'))) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền duyệt đơn này.']);
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $new_status = ($action === 'approve') ? 'approved' : 'rejected';
        
        $stmt = $db->prepare("UPDATE leave_requests SET status = ? WHERE id = ?");
        if ($stmt->execute([$new_status, $id])) {
            echo json_encode(['success' => true, 'message' => 'Đã cập nhật trạng thái đơn!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi cập nhật CSDL.']);
        }
        exit;
    }
    if ($action === 'create') {
        $start_date = $_POST['start_date'] ?? null;
        $end_date = $_POST['end_date'] ?? null;
        $reason = $_POST['reason'] ?? '';

        if (!$start_date || (!$end_date)) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng chọn ngày']);
            exit;
        }

        $target_user_id = $_POST['assigned_user_id'] ?? $user_id;
        $target_user_name = $db->query("SELECT full_name FROM users WHERE id = " . (int)$target_user_id)->fetchColumn();
        $leave_type = $_POST['leave_type'] ?? 'full_day';
        $start_time = ($leave_type === 'hourly' && !empty($_POST['start_time'])) ? $_POST['start_time'] : null;
        $end_time = ($leave_type === 'hourly' && !empty($_POST['end_time'])) ? $_POST['end_time'] : null;
        // Nếu nghỉ theo giờ, end_date = start_date
        if ($leave_type === 'hourly') $end_date = $start_date;

        try {
            $db->beginTransaction();

            $db->prepare("INSERT INTO leave_requests (user_id, leave_type, start_date, start_time, end_time, end_date, reason, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$target_user_id, $leave_type, $start_date, $start_time, $end_time, $end_date, $reason, 'pending']);
            $leave_id = $db->lastInsertId();

            $stmt = $db->prepare("
                SELECT a.id, a.appointment_date, p.full_name as patient_name
                FROM appointments a
                LEFT JOIN patients p ON a.patient_id = p.id
                WHERE a.doctor_id = ? 
                  AND a.status IN ('scheduled', 'confirmed')
                  AND DATE(a.appointment_date) BETWEEN ? AND ?
            ");
            $stmt->execute([$target_user_id, $start_date, $end_date]);
            $conflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($conflicts) > 0) {
                $rec_stmt = $db->query("SELECT id FROM users WHERE role_id = 4 AND status = 'active' LIMIT 1");
                $assign_to = $rec_stmt->fetchColumn() ?: 1;

                foreach ($conflicts as $c) {
                    $apt_date = date('d/m/Y H:i', strtotime($c['appointment_date']));
                    $title = "BÁO KHÁCH ĐỔI NHÂN SỰ: " . $c['patient_name'];
                    $desc = "Nhân sự $target_user_name xin nghỉ từ $start_date đến $end_date.\nLịch của Bệnh nhân {$c['patient_name']} (Lúc: {$apt_date}) bị ảnh hưởng.\nVui lòng gọi điện dời lịch hoặc tìm Bác sĩ thay thế!";
                    
                    $db->prepare("INSERT INTO staff_tasks (user_id, created_by, title, description, due_date) VALUES (?, ?, ?, ?, ?)")
                       ->execute([$assign_to, $target_user_id, $title, $desc, $c['appointment_date']]);
                }
            }

            $db->commit();
            echo json_encode([
                'success' => true, 
                'conflict_count' => count($conflicts),
                'message' => 'Đã gửi đơn báo nghỉ! Hệ thống đã auto-generate ' . count($conflicts) . ' task nhắc nhở sửa lịch cho Lễ Tân.'
            ]);
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log('Leave request error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Đã xảy ra lỗi hệ thống.']);
        }
    } elseif ($action === 'delete' || $action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        
        $stmt = $db->prepare("SELECT user_id, status FROM leave_requests WHERE id = ?");
        $stmt->execute([$id]);
        $leave = $stmt->fetch();
        
        if (!$leave) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn.']);
            exit;
        }
        
        $is_admin = isset($_SESSION['role']) && (strtolower($_SESSION['role']) === 'admin' || can('view_hr'));
        if (!$is_admin && $leave['user_id'] != $user_id) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền thao tác trên đơn này.']);
            exit;
        }
        
        if ($leave['status'] !== 'pending' && !$is_admin) {
            echo json_encode(['success' => false, 'message' => 'Đơn đã được duyệt/từ chối, không thể sửa/xóa. Vui lòng liên hệ Admin.']);
            exit;
        }

        if ($action === 'delete') {
            if ($db->prepare("DELETE FROM leave_requests WHERE id = ?")->execute([$id])) {
                echo json_encode(['success' => true, 'message' => 'Đã xóa đơn nghỉ phép.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Lỗi khi xóa.']);
            }
        } elseif ($action === 'edit') {
            $start_date = $_POST['start_date'] ?? null;
            $end_date = $_POST['end_date'] ?? null;
            $reason = $_POST['reason'] ?? '';
            
            if (!$start_date || !$end_date) {
                echo json_encode(['success' => false, 'message' => 'Vui lòng chọn ngày']);
                exit;
            }
            
            $leave_type = $_POST['leave_type'] ?? 'full_day';
            $start_time = ($leave_type === 'hourly' && !empty($_POST['start_time'])) ? $_POST['start_time'] : null;
            $end_time = ($leave_type === 'hourly' && !empty($_POST['end_time'])) ? $_POST['end_time'] : null;
            if ($leave_type === 'hourly') $end_date = $start_date;
            
            if ($db->prepare("UPDATE leave_requests SET leave_type = ?, start_date = ?, start_time = ?, end_time = ?, end_date = ?, reason = ? WHERE id = ?")->execute([$leave_type, $start_date, $start_time, $end_time, $end_date, $reason, $id])) {
                if (!$is_admin) {
                    $db->prepare("UPDATE leave_requests SET status = 'pending' WHERE id = ?")->execute([$id]);
                }
                echo json_encode(['success' => true, 'message' => 'Đã cập nhật đơn nghỉ phép.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật.']);
            }
        }
    } else {
        echo json_encode(['error' => 'Invalid action']);
    }
} else {
    echo json_encode(['error' => 'Invalid method']);
}
