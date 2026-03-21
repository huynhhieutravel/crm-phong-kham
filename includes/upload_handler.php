<?php
/**
 * Upload Handler — Xử lý upload ảnh X-quang / Body scan
 * API endpoint: POST /includes/upload_handler.php
 * Returns JSON: { success: true, files: [ { name, path, size } ] }
 */
session_start();
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

$patient_id = $_POST['patient_id'] ?? 0;
$record_id = $_POST['record_id'] ?? 0;

if (!$patient_id) {
    echo json_encode(['success' => false, 'error' => 'Missing patient_id']);
    exit;
}

// Create upload directory
$upload_dir = __DIR__ . '/../uploads/medical/' . $patient_id . '/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];
$max_size = 10 * 1024 * 1024; // 10MB

$uploaded = [];
$errors = [];

if (!empty($_FILES['images'])) {
    $files = $_FILES['images'];
    $count = is_array($files['name']) ? count($files['name']) : 1;
    
    for ($i = 0; $i < $count; $i++) {
        $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
        $tmp = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
        $size = is_array($files['size']) ? $files['size'][$i] : $files['size'];
        $type = is_array($files['type']) ? $files['type'][$i] : $files['type'];
        $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];
        
        if ($error !== UPLOAD_ERR_OK) {
            $errors[] = "$name: Upload error ($error)";
            continue;
        }
        
        if (!in_array($type, $allowed_types)) {
            $errors[] = "$name: File type not allowed ($type)";
            continue;
        }
        
        if ($size > $max_size) {
            $errors[] = "$name: File too large (" . round($size/1024/1024, 1) . "MB > 10MB)";
            continue;
        }
        
        // Generate unique filename
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $filename = date('Ymd_His') . '_' . uniqid() . '.' . $ext;
        $filepath = $upload_dir . $filename;
        $web_path = '/uploads/medical/' . $patient_id . '/' . $filename;
        
        if (move_uploaded_file($tmp, $filepath)) {
            $uploaded[] = [
                'name' => $name,
                'path' => $web_path,
                'size' => $size,
                'type' => $type
            ];
        } else {
            $errors[] = "$name: Failed to save file";
        }
    }
}

// Update attachments in medical_history if record_id provided
if ($record_id && !empty($uploaded)) {
    $db = getDB();
    $stmt = $db->prepare("SELECT attachments FROM medical_history WHERE id = ?");
    $stmt->execute([$record_id]);
    $existing = json_decode($stmt->fetchColumn() ?: '[]', true);
    
    $merged = array_merge($existing, $uploaded);
    $stmt = $db->prepare("UPDATE medical_history SET attachments = ? WHERE id = ?");
    $stmt->execute([json_encode($merged, JSON_UNESCAPED_UNICODE), $record_id]);
}

echo json_encode([
    'success' => true,
    'files' => $uploaded,
    'errors' => $errors
]);
