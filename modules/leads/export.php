<?php
// modules/leads/export.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

error_reporting(0);
ini_set('display_errors', 0);


$db = getDB();

// Fetch leads with optional filtering (consistent with index.php)
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$group_filter = $_GET['medical_group'] ?? '';
$consultant_filter = $_GET['consultant_id'] ?? '';
$period = $_GET['period'] ?? '';
$start_date_filter = $_GET['start_date'] ?? '';
$end_date_filter = $_GET['end_date'] ?? '';

$sql = "SELECT l.*, u.full_name as consultant_name 
        FROM leads l 
        LEFT JOIN users u ON l.consultant_id = u.id";
$params = [];
$conditions = [];

if ($search) {
    $conditions[] = "(l.full_name LIKE ? OR l.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status_filter) {
    $conditions[] = "l.status = ?";
    $params[] = $status_filter;
}
if ($group_filter) {
    $conditions[] = "l.medical_group = ?";
    $params[] = $group_filter;
}
if ($consultant_filter) {
    $conditions[] = "l.consultant_id = ?";
    $params[] = $consultant_filter;
}
if ($period) {
    $range = get_date_range($period, $start_date_filter, $end_date_filter);
    $conditions[] = "l.created_at BETWEEN ? AND ?";
    $params[] = $range['start'];
    $params[] = $range['end'];
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}
$sql .= " ORDER BY l.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=leads_export_' . date('Ymd_His') . '.csv');

// Create file pointer
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel visibility of Vietnamese characters
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Column Headers
fputcsv($output, [
    'ID',
    'Họ và Tên',
    'Số điện thoại',
    'Nguồn',
    'Nhóm chuyên khoa',
    'Người phụ trách',
    'Trạng thái tư vấn',
    'Trạng thái hệ thống',
    'Ngày tạo'
], ",", "\r", "");

// Lead Sources and Groups for translation
$lead_sources = get_lead_sources();
$medical_groups = get_medical_groups();

foreach ($leads as $l) {
    fputcsv($output, [
        $l['id'],
        $l['full_name'],
        $l['phone'],
        $lead_sources[$l['source']] ?? $l['source'],
        $medical_groups[$l['medical_group']] ?? $l['medical_group'],
        $l['consultant_name'] ?? 'Chưa phân công',
        $l['consultation_status'],
        $l['status'],
        $l['created_at']
    ], ",", "\r", "");
}

fclose($output);
exit();
