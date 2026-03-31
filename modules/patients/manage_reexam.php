<?php
// modules/patients/manage_reexam.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_patients');

$db = getDB();
$patient_id = isset($_POST['patient_id']) ? $_POST['patient_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : 'add';

    if ($action === 'delete') {
        $id = isset($_POST['id']) ? $_POST['id'] : 0;
        $stmt = $db->prepare("DELETE FROM reexam_rules WHERE id = ? AND patient_id = ?");
        $stmt->execute([$id, $patient_id]);
        set_flash('Đã xóa quy tắc tái khám.');
    } else {
        $service_name = isset($_POST['service_name']) ? $_POST['service_name'] : '';
        $frequency = isset($_POST['frequency']) ? (int)$_POST['frequency'] : 3;
        $frequency_type = isset($_POST['frequency_type']) ? $_POST['frequency_type'] : 'months';
        if (!in_array($frequency_type, ['days', 'weeks', 'months'])) { $frequency_type = 'months'; }
        $first_date = isset($_POST['first_date']) ? $_POST['first_date'] : date('Y-m-d');

        $stmt = $db->prepare("
            INSERT INTO reexam_rules (patient_id, service_name, frequency, frequency_type, next_due_at)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$patient_id, $service_name, $frequency, $frequency_type, $first_date]);
        set_flash('Đã thiết lập lịch tái khám định kỳ.');
    }
}

redirect("view.php?id=$patient_id");
