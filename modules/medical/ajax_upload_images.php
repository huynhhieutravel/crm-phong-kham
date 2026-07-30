<?php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_medical');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$patient_id = (int)($_POST['patient_id'] ?? 0);
if (!$patient_id) {
    echo json_encode(['success' => false, 'message' => 'Missing patient ID.']);
    exit;
}

$uploaded_files = [];
$errors = [];

if (!empty($_FILES['exam_images']['name'][0])) {
    $upload_dir = '../../uploads/patients/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    
    $file_count = count($_FILES['exam_images']['name']);
    
    for ($i = 0; $i < $file_count; $i++) {
        if ($_FILES['exam_images']['error'][$i] === UPLOAD_ERR_OK) {
            $tmp_name = $_FILES['exam_images']['tmp_name'][$i];
            $name = basename($_FILES['exam_images']['name'][$i]);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $new_name = $patient_id . '_' . time() . '_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($tmp_name, $upload_dir . $new_name)) {
                    $uploaded_files[] = 'uploads/patients/' . $new_name;
                } else {
                    $errors[] = "Lỗi khi lưu file: $name";
                }
            } else {
                $errors[] = "Định dạng không hợp lệ: $name";
            }
        }
    }
}

if (count($uploaded_files) > 0) {
    echo json_encode(['success' => true, 'files' => $uploaded_files, 'message' => 'Upload thành công!', 'errors' => $errors]);
} else {
    echo json_encode(['success' => false, 'message' => 'Không có file nào được upload thành công.', 'errors' => $errors]);
}
