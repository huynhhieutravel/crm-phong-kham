<?php
// modules/patients/export.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('view_patients');

error_reporting(0);
ini_set('display_errors', 0);


$db = getDB();

$search = $_GET['search'] ?? '';
$label_filter = $_GET['label'] ?? '';
$gender_filter = $_GET['gender'] ?? '';
$period = $_GET['period'] ?? '';
$start_date_filter = $_GET['start_date'] ?? '';
$end_date_filter = $_GET['end_date'] ?? '';

$sql = "SELECT p.* FROM patients p";
$params = [];
$conditions = [];

if ($search) {
    $conditions[] = "(p.full_name LIKE ? OR p.phone LIKE ? OR p.customer_id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($label_filter) {
    $conditions[] = "p.label = ?";
    $params[] = $label_filter;
}
if ($gender_filter) {
    $conditions[] = "p.gender = ?";
    $params[] = $gender_filter;
}
if ($period) {
    $range = get_date_range($period, $start_date_filter, $end_date_filter);
    $conditions[] = "p.created_at BETWEEN ? AND ?";
    $params[] = $range['start'];
    $params[] = $range['end'];
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}
$sql .= " ORDER BY p.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=patients_export_' . date('Ymd_His') . '.csv');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($output, [
    'Mã BN',
    'Họ và Tên',
    'Số điện thoại',
    'Giới tính',
    'Ngày sinh',
    'Địa chỉ',
    'Nhãn',
    'Ghi chú',
    'Ngày tạo'
], ",", "\r", "");

foreach ($patients as $p) {
    fputcsv($output, [
        $p['customer_id'],
        $p['full_name'],
        $p['phone'],
        $p['gender'] === 'male' ? 'Nam' : ($p['gender'] === 'female' ? 'Nữ' : 'Khác'),
        $p['birthday'],
        $p['address'],
        $p['label'],
        $p['notes'],
        $p['created_at']
    ], ",", "\r", "");
}

fclose($output);
exit();
