<?php
// modules/cskh/process.php — AJAX + Form handler for CSKH actions
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('view_patients');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$task_id = (int)($_POST['task_id'] ?? 0);
if (!$task_id) {
    set_flash('Task không hợp lệ.', 'error');
    redirect('index.php');
}

// Load task
$stmt = $db->prepare("SELECT t.*, r.retry_max, r.retry_interval_days FROM cskh_tasks t JOIN cskh_rules r ON t.rule_id = r.id WHERE t.id = ?");
$stmt->execute([$task_id]);
$task = $stmt->fetch();

if (!$task) {
    set_flash('Task không tồn tại.', 'error');
    redirect('index.php');
}

// --- ACTION: Skip/Cancel ---
if (isset($_POST['action']) && $_POST['action'] === 'skip') {
    $db->prepare("UPDATE cskh_tasks SET status = 'canceled', resolved_by = ?, resolved_at = NOW() WHERE id = ?")
       ->execute([$_SESSION['user_id'], $task_id]);
    // Return JSON for AJAX or redirect
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        echo json_encode(['ok' => true]);
        exit;
    }
    set_flash('Đã bỏ qua task.', 'success');
    redirect('index.php');
}

// --- ACTION: Process Call Result ---
$db->beginTransaction();
try {
    $result = $_POST['interaction_result'] ?? 'other';
    $outcome = $_POST['call_outcome'] ?? null;
    $notes = trim($_POST['notes'] ?? '');
    
    // Build post-care notes from checkboxes
    $post_care = [];
    if (!empty($_POST['remind_rest'])) $post_care[] = 'Nhắc nghỉ ngơi, tránh mang vác nặng';
    if (!empty($_POST['remind_exercise'])) $post_care[] = 'Nhắc tập vận động nhẹ, uống nước';
    if (!empty($_POST['remind_review'])) $post_care[] = 'Nhắc đánh giá Google Maps / Facebook';
    if (!empty($_POST['remind_package'])) $post_care[] = 'Thông báo gói sắp hết, tư vấn gia hạn';
    $post_care_str = implode('; ', $post_care);
    $has_asked_review = !empty($_POST['remind_review']) ? 1 : 0;

    // 1. Log the interaction
    $db->prepare("
        INSERT INTO cskh_interaction_logs (task_id, patient_id, interaction_result, call_outcome, notes, has_asked_review, post_care_notes, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        $task_id,
        $task['patient_id'],
        $result,
        ($result === 'answered') ? $outcome : null,
        $notes ?: null,
        $has_asked_review,
        $post_care_str ?: null,
        $_SESSION['user_id']
    ]);

    // 2. Update task based on interaction result
    if ($result === 'answered') {
        // Customer answered the phone
        switch ($outcome) {
            case 'booked':
                // Khách đặt lịch -> Hoàn thành, Xanh
                $db->prepare("UPDATE cskh_tasks SET status = 'completed', priority_color = 'green', resolved_by = ?, resolved_at = NOW() WHERE id = ?")
                   ->execute([$_SESSION['user_id'], $task_id]);
                set_flash('✅ Đã ghi nhận: Khách đặt lịch hẹn thành công!', 'success');
                break;
                
            case 'thinking':
                // Khách cần suy nghĩ -> Retry, Vàng, due_date +N days
                $new_retry = $task['retry_count'] + 1;
                $interval = $task['retry_interval_days'] ?: 2;
                $new_due = date('Y-m-d', strtotime("+{$interval} days"));
                $db->prepare("UPDATE cskh_tasks SET status = 'pending', priority_color = 'yellow', retry_count = ?, due_date = ? WHERE id = ?")
                   ->execute([$new_retry, $new_due, $task_id]);
                set_flash("🟡 Đã hẹn gọi lại ngày " . date('d/m', strtotime($new_due)), 'success');
                break;

            case 'refused':
                // Khách từ chối -> Hoàn thành, Xám
                $db->prepare("UPDATE cskh_tasks SET status = 'completed', priority_color = 'gray', resolved_by = ?, resolved_at = NOW() WHERE id = ?")
                   ->execute([$_SESSION['user_id'], $task_id]);
                set_flash('⚪ Khách không muốn tiếp tục. Đã chuyển vào danh sách tạm ẩn.', 'success');
                break;

            case 'info_only':
                // Hỏi thăm xong -> Hoàn thành, giữ nguyên màu
                $db->prepare("UPDATE cskh_tasks SET status = 'completed', resolved_by = ?, resolved_at = NOW() WHERE id = ?")
                   ->execute([$_SESSION['user_id'], $task_id]);
                set_flash('✅ Đã hoàn thành hỏi thăm sức khỏe.', 'success');
                break;

            default:
                $db->prepare("UPDATE cskh_tasks SET status = 'completed', resolved_by = ?, resolved_at = NOW() WHERE id = ?")
                   ->execute([$_SESSION['user_id'], $task_id]);
                set_flash('✅ Đã ghi nhận kết quả.', 'success');
        }
    } else {
        // Customer didn't answer (busy, no_answer, wrong_number)
        $new_retry = $task['retry_count'] + 1;
        
        if ($new_retry >= $task['retry_max']) {
            // Exceeded max retries -> auto cancel
            $db->prepare("UPDATE cskh_tasks SET status = 'canceled', retry_count = ?, resolved_by = ?, resolved_at = NOW() WHERE id = ?")
               ->execute([$new_retry, $_SESSION['user_id'], $task_id]);
            set_flash("❌ Đã gọi {$new_retry} lần không liên lạc được. Task tự động đóng.", 'error');
        } else {
            // Schedule retry
            $interval = $task['retry_interval_days'] ?: 1;
            $new_due = date('Y-m-d', strtotime("+{$interval} days"));
            $db->prepare("UPDATE cskh_tasks SET status = 'pending', retry_count = ?, due_date = ? WHERE id = ?")
               ->execute([$new_retry, $new_due, $task_id]);
            set_flash("📞 Không liên lạc được (lần {$new_retry}/{$task['retry_max']}). Hẹn gọi lại ngày " . date('d/m', strtotime($new_due)), 'success');
        }
    }

    $db->commit();
    $msg = '✅ Đã ghi nhận kết quả.';
    if ($result === 'answered') {
        if ($outcome === 'booked') $msg = '✅ Khách đặt lịch hẹn thành công!';
        if ($outcome === 'thinking') $msg = "🟡 Đã hẹn gọi lại ngày " . date('d/m', strtotime($new_due));
        if ($outcome === 'refused') $msg = '⚪ Khách không muốn tiếp tục. Đã tạm ẩn.';
    } else {
        if ($new_retry >= $task['retry_max']) $msg = "❌ Đã hủy task sau {$new_retry} lần gọi không được.";
        else $msg = "📞 Gọi lại ngày " . date('d/m', strtotime($new_due));
    }

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json' || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'json') !== false) || isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'message' => $msg]);
        exit;
    }

    set_flash($msg, 'success');

} catch (Exception $e) {
    $db->rollBack();
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_POST['ajax']) && $_POST['ajax'] == 1)) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        exit;
    }
    set_flash('Lỗi hệ thống: ' . $e->getMessage(), 'error');
}

redirect('index.php');
