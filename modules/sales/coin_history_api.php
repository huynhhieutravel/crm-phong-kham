<?php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_sales');

header('Content-Type: application/json');

$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if ($patient_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Thiếu thông tin bệnh nhân']);
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT ct.*, u.full_name as created_by_name
        FROM coin_transactions ct
        LEFT JOIN users u ON ct.created_by = u.id
        WHERE ct.patient_id = ?
        ORDER BY ct.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$patient_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $history
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Lỗi: ' . $e->getMessage()]);
}
