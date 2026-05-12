<?php
/**
 * API endpoint: POST /includes/delete_attachment.php
 * Deletes an attachment from medical_history and deletes the file from disk.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$record_id = isset($_POST['record_id']) ? (int)$_POST['record_id'] : 0;
$path = isset($_POST['path']) ? trim($_POST['path']) : '';

if (!$record_id || !$path) {
    echo json_encode(['success' => false, 'error' => 'Thiếu thông tin file']);
    exit;
}

try {
    $db = getDB();
    
    // Check lock status
    $stmt = $db->prepare("SELECT m.session_id, m.attachments, s.status FROM medical_history m JOIN medical_sessions s ON m.session_id = s.id WHERE m.id = ?");
    $stmt->execute([$record_id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$record) {
        echo json_encode(['success' => false, 'error' => 'Không tìm thấy hồ sơ']);
        exit;
    }
    
    // Check permission - cannot delete if completed unless admin
    if ($record['status'] === 'completed' && !has_role('admin')) {
        echo json_encode(['success' => false, 'error' => 'Buổi khám đã khóa, không thể xóa ảnh']);
        exit;
    }
    
    $attachments = json_decode($record['attachments'], true);
    if (!is_array($attachments)) {
        echo json_encode(['success' => false, 'error' => 'Dữ liệu ảnh không hợp lệ']);
        exit;
    }
    
    // Filter out the deleted path
    $new_attachments = [];
    $file_to_delete = false;
    
    foreach ($attachments as $att) {
        if ($att['path'] === $path) {
            $file_to_delete = true;
        } else {
            $new_attachments[] = $att;
        }
    }
    
    if (!$file_to_delete) {
        echo json_encode(['success' => false, 'error' => 'File không tồn tại trong DB']);
        exit;
    }
    
    // Save to DB
    $stmt_update = $db->prepare("UPDATE medical_history SET attachments = ? WHERE id = ?");
    $stmt_update->execute([json_encode($new_attachments, JSON_UNESCAPED_UNICODE), $record_id]);
    
    // Delete physical file
    $physical_path = __DIR__ . '/..' . $path;
    if (file_exists($physical_path)) {
        unlink($physical_path);
    }
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Lỗi hệ thống: ' . $e->getMessage()]);
}
