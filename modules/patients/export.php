<?php
// modules/patients/export.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('view_patients');

// M2 FIX: Log errors but don't display
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);


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
    // H3 FIX: Escape LIKE wildcards
    $safe_search = addcslashes($search, '%_');
    $conditions[] = "(p.full_name LIKE ? OR p.phone LIKE ? OR p.customer_id LIKE ?)";
    $params[] = "%$safe_search%";
    $params[] = "%$safe_search%";
    $params[] = "%$safe_search%";
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
    __('patient.export.customer_id'),
    __('patient.export.full_name'),
    __('patient.export.phone'),
    __('patient.export.gender'),
    __('patient.export.birthday'),
    __('patient.export.address'),
    __('patient.export.label'),
    __('patient.export.notes'),
    __('patient.export.created_at')
]);

foreach ($patients as $p) {
    fputcsv($output, [
        $p['customer_id'],
        $p['full_name'],
        $p['phone'],
        $p['gender'] === 'male' ? __('patient.gender.male') : ($p['gender'] === 'female' ? __('patient.gender.female') : __('common.other')),
        $p['birthday'],
        $p['address'],
        $p['label'],
        $p['notes'],
        $p['created_at']
    ]);
}

fclose($output);
exit();
