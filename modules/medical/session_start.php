<?php
// modules/medical/session_start.php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

$db = getDB();
$patient_id = isset($_GET['patient_id']) ? $_GET['patient_id'] : 0;
$now = date('Y-m-d');

if (!$patient_id) {
    set_flash('Thiếu mã bệnh nhân.', 'error');
    redirect('index.php');
}

// 1. Check for an active session TODAY
$stmt = $db->prepare("
    SELECT id FROM medical_sessions 
    WHERE patient_id = ? AND DATE(session_date) = ? AND status = 'active'
    LIMIT 1
");
$stmt->execute([$patient_id, $now]);
$existing = $stmt->fetch();

if ($existing) {
    // Resume existing session
    redirect("session_view.php?id=" . $existing['id']);
}

// 2. Create New Session
// Default to the current logged-in user as the doctor (can be changed in session_view)
$doctor_id = $_SESSION['user_id'];

// Check if there's an appointment checked-in today to link
$stmt_app = $db->prepare("
    SELECT id FROM appointments 
    WHERE patient_id = ? AND DATE(appointment_date) = ? AND status = 'arrived'
    ORDER BY appointment_date DESC LIMIT 1
");
$stmt_app->execute([$patient_id, $now]);
$app = $stmt_app->fetch();
$appointment_id = $app ? $app['id'] : null;

$stmt = $db->prepare("
    INSERT INTO medical_sessions (patient_id, doctor_id, appointment_id, session_date, status)
    VALUES (?, ?, ?, NOW(), 'active')
");
$stmt->execute([$patient_id, $doctor_id, $appointment_id]);
$new_session_id = $db->lastInsertId();

set_flash('Đã tạo Sổ khám mới cho hôm nay.');
redirect("session_view.php?id=$new_session_id");
