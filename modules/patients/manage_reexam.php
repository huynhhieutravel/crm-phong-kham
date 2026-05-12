<?php
// modules/patients/manage_reexam.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_patients');

$db = getDB();

// C2 FIX: POST-only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('Yêu cầu không hợp lệ.', 'error');
    redirect('index.php');
}

// C2 FIX: Cast IDs to int
$patient_id = (int)($_POST['patient_id'] ?? 0);

if (!$patient_id) {
    set_flash('Mã bệnh nhân không hợp lệ.', 'error');
    redirect('index.php');
}

// C2 FIX: Verify CSRF
verify_csrf("view.php?id=$patient_id");

$action = $_POST['action'] ?? 'add';

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $db->prepare("DELETE FROM reexam_rules WHERE id = ? AND patient_id = ?");
    $stmt->execute([$id, $patient_id]);
    set_flash(__('patient.msg.reexam_deleted'));
} elseif ($action === 'edit') {
    $id = (int)($_POST['id'] ?? 0);
    $service_name = $_POST['service_name'] ?? '';
    $frequency = (int)($_POST['frequency'] ?? 3);
    $frequency_type = $_POST['frequency_type'] ?? 'months';
    if (!in_array($frequency_type, ['days', 'weeks', 'months'])) { $frequency_type = 'months'; }
    $first_date = $_POST['first_date'] ?? date('Y-m-d');

    $stmt = $db->prepare("
        UPDATE reexam_rules 
        SET service_name = ?, frequency = ?, frequency_type = ?, next_due_at = ?
        WHERE id = ? AND patient_id = ?
    ");
    $stmt->execute([$service_name, $frequency, $frequency_type, $first_date, $id, $patient_id]);
    set_flash(__('common.msg.updated'));
} else {
    $service_name = $_POST['service_name'] ?? '';
    $frequency = (int)($_POST['frequency'] ?? 3);
    $frequency_type = $_POST['frequency_type'] ?? 'months';
    if (!in_array($frequency_type, ['days', 'weeks', 'months'])) { $frequency_type = 'months'; }
    $first_date = $_POST['first_date'] ?? date('Y-m-d');

    $stmt = $db->prepare("
        INSERT INTO reexam_rules (patient_id, service_name, frequency, frequency_type, next_due_at)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$patient_id, $service_name, $frequency, $frequency_type, $first_date]);
    set_flash(__('patient.msg.reexam_added'));
}

redirect("view.php?id=$patient_id");
