<?php
// api/staff_tasks.php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth_middleware.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_status') {
        $task_id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'pending';
        
        $stmt = $db->prepare("UPDATE staff_tasks SET status = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$status, $task_id, $user_id]);
        
        echo json_encode(['success' => true]);
        exit;
    }
}

// Fetch tasks
$count_only = isset($_GET['count_only']) && $_GET['count_only'] == 1;

if ($count_only) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM staff_tasks WHERE user_id = ? AND status = 'pending'");
    $stmt->execute([$user_id]);
    echo json_encode(['pending_count' => (int)$stmt->fetchColumn()]);
    exit;
}

$stmt = $db->prepare("
    SELECT * FROM staff_tasks 
    WHERE user_id = ? 
    ORDER BY status ASC, due_date ASC, created_at DESC
    LIMIT 50
");
$stmt->execute([$user_id]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pending_count = 0;
foreach ($tasks as &$t) {
    if ($t['status'] === 'pending') $pending_count++;
    if ($t['due_date']) {
        $t['due_formatted'] = date('H:i d/m/Y', strtotime($t['due_date']));
    }
}

echo json_encode([
    'tasks' => $tasks,
    'pending_count' => $pending_count
]);
