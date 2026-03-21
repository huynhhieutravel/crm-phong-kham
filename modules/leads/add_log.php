<?php
// modules/leads/add_log.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lead_id = $_POST['lead_id'] ?? 0;
    $note = trim($_POST['note'] ?? '');
    $user_id = $_SESSION['user_id'];

    if ($lead_id && $note) {
        $db = getDB();
        try {
            $stmt = $db->prepare("INSERT INTO lead_logs (lead_id, user_id, note) VALUES (?, ?, ?)");
            $stmt->execute([$lead_id, $user_id, $note]);
            
            // Get the inserted log with user name for immediate display
            $stmt = $db->prepare("
                SELECT l.*, u.full_name as user_name 
                FROM lead_logs l 
                JOIN users u ON l.user_id = u.id 
                WHERE l.id = ?
            ");
            $stmt->execute([$db->lastInsertId()]);
            $new_log = $stmt->fetch();

            echo json_encode([
                'success' => true, 
                'log' => [
                    'id' => $new_log['id'],
                    'note' => e($new_log['note']),
                    'user_name' => e($new_log['user_name']),
                    'created_at' => date('H:i d/m/Y', strtotime($new_log['created_at']))
                ]
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Missing data']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
