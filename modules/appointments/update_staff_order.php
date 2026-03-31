<?php
// modules/appointments/update_staff_order.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

// Check if user has permission to view appointments (minimum)
require_permission('view_appointments');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['orders']) || !is_array($input['orders'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data format']);
    exit;
}

$db = getDB();
$db->beginTransaction();

try {
    $stmt = $db->prepare("UPDATE users SET sort_order = ? WHERE id = ?");
    
    foreach ($input['orders'] as $item) {
        if (isset($item['id']) && isset($item['sort_order'])) {
            $stmt->execute([$item['sort_order'], $item['id']]);
        }
    }
    
    $db->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
