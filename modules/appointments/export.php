<?php
// modules/appointments/export.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('view_appointments');

// L2 FIX: Log errors but don't display (outputting CSV binary)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);


$db = getDB();

$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$doctor_filter = $_GET['doctor_id'] ?? '';
$type_filter = $_GET['type'] ?? '';
$period = $_GET['period'] ?? '';
$start_date_param = $_GET['start_date'] ?? '';
$end_date_param = $_GET['end_date'] ?? '';

$sql = "SELECT a.*, 
               COALESCE(p.full_name, l.full_name) as contact_name,
               COALESCE(p.phone, l.phone) as contact_phone,
               u.full_name as doctor_name
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        LEFT JOIN leads l ON a.lead_id = l.id
        LEFT JOIN users u ON a.doctor_id = u.id";

$params = [];
$conditions = [];

if ($search) {
    // H4 FIX: Escape LIKE wildcards
    $safe_search = addcslashes($search, '%_');
    $conditions[] = "(p.full_name LIKE ? OR l.full_name LIKE ? OR p.phone LIKE ? OR l.phone LIKE ?)";
    $params = array_merge($params, ["%$safe_search%", "%$safe_search%", "%$safe_search%", "%$safe_search%"]);
}
if ($status_filter) {
    $conditions[] = "a.status = ?";
    $params[] = $status_filter;
}
if ($doctor_filter) {
    $conditions[] = "a.doctor_id = ?";
    $params[] = $doctor_filter;
}
if ($type_filter) {
    $conditions[] = "a.type = ?";
    $params[] = $type_filter;
}
if ($period) {
    $range = get_date_range($period, $start_date_param, $end_date_param);
    $conditions[] = "a.appointment_date BETWEEN ? AND ?";
    $params[] = $range['start'];
    $params[] = $range['end'];
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}
$sql .= " ORDER BY a.appointment_date ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=appointments_export_' . date('Ymd_His') . '.csv');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($output, [
    'Họ tên',
    'Số điện thoại',
    'Ngày hẹn',
    'Giờ',
    'Bác sĩ',
    'Loại',
    'Trạng thái',
    'Ghi chú'
]);

foreach ($appointments as $a) {
    fputcsv($output, [
        $a['contact_name'],
        $a['contact_phone'],
        date('Y-m-d', strtotime($a['appointment_date'])),
        date('H:i', strtotime($a['appointment_date'])),
        $a['doctor_name'] ?? 'Chưa phân công',
        $a['type'],
        $a['status'],
        $a['notes']
    ]);
}

fclose($output);
exit();
