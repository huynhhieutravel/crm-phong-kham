<?php
// modules/patients/manage_reexam.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

$db = getDB();
$patient_id = $_POST['patient_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';

    if ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        $stmt = $db->prepare("DELETE FROM reexam_rules WHERE id = ? AND patient_id = ?");
        $stmt->execute([$id, $patient_id]);
        set_flash('Đã xóa quy tắc tái khám.');
    } else {
        $service_name = $_POST['service_name'] ?? '';
        $frequency = $_POST['frequency'] ?? 3;
        $first_date = $_POST['first_date'] ?? date('Y-m-d');

        $stmt = $db->prepare("
            INSERT INTO reexam_rules (patient_id, service_name, frequency, next_due_at)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$patient_id, $service_name, $frequency, $first_date]);
        set_flash('Đã thiết lập lịch tái khám định kỳ.');
    }
}

redirect("view.php?id=$patient_id");
