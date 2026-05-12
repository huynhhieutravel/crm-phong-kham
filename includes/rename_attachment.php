<?php
/**
 * Rename Attachment — Đổi tên hình ảnh đính kèm
 * API endpoint: POST /includes/rename_attachment.php
 * Returns JSON: { success: true }
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
$path = isset($_POST['path']) ? $_POST['path'] : '';
$new_name = isset($_POST['new_name']) ? trim($_POST['new_name']) : '';

if (!$record_id || !$path || $new_name === '') {
    echo json_encode(['success' => false, 'error' => 'Thiếu thông tin bắt buộc']);
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT attachments FROM medical_history WHERE id = ?");
$stmt->execute([$record_id]);
$record = $stmt->fetchColumn();

if (!$record) {
    echo json_encode(['success' => false, 'error' => 'Không tìm thấy hồ sơ']);
    exit;
}

$attachments = json_decode($record, true);
$found = false;

if ($attachments) {
    foreach ($attachments as &$att) {
        if ($att['path'] === $path) {
            $att['name'] = $new_name;
            $found = true;
            break;
        }
    }
}

if ($found) {
    $stmt = $db->prepare("UPDATE medical_history SET attachments = ? WHERE id = ?");
    $stmt->execute([json_encode($attachments, JSON_UNESCAPED_UNICODE), $record_id]);
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Không tìm thấy file đính kèm']);
}
