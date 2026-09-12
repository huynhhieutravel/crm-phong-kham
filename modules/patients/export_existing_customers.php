<?php
// modules/patients/export_existing_customers.php
// Xuất Danh Sách Khách Hàng Hiện Hữu — SIMON CENTER
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('view_patients');

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

$sql = "SELECT p.* FROM patients p WHERE p.full_name IS NOT NULL AND TRIM(p.full_name) != ''";
$params = [];

if ($search) {
    $safe_search = addcslashes($search, '%_');
    $sql .= " AND (p.full_name LIKE ? OR p.phone LIKE ? OR p.customer_id LIKE ?)";
    $params[] = "%$safe_search%";
    $params[] = "%$safe_search%";
    $params[] = "%$safe_search%";
}
if ($label_filter) {
    $sql .= " AND p.label = ?";
    $params[] = $label_filter;
}
if ($gender_filter) {
    $sql .= " AND p.gender = ?";
    $params[] = $gender_filter;
}
if ($period) {
    $range = get_date_range($period, $start_date_filter, $end_date_filter);
    $sql .= " AND p.created_at BETWEEN ? AND ?";
    $params[] = $range['start'];
    $params[] = $range['end'];
}

// Sắp xếp A -> Z theo tên khách hàng
$sql .= " ORDER BY TRIM(p.full_name) ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=Danh_Sach_Khach_Hang_Hien_Huu_' . date('Ymd_His') . '.csv');

$output = fopen('php://output', 'w');
// UTF-8 BOM for Microsoft Excel / Google Sheets
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Row 1: Title
fputcsv($output, ['DANH SÁCH KHÁCH HÀNG HIỆN HỮU — SIMON CENTER'], ',', '"', "\\");

// Row 2: Column Headers matching spreadsheet
fputcsv($output, [
    'Họ tên',
    'Nhãn',
    'Số điện thoại',
    'Ngày hẹn đầu tiên',
    'Ngày hẹn cuối cùng',
    'DV Chiro',
    'DV Đông y',
    'Đã mua gói',
    'Chưa mua gói',
    'Số buổi đã hoàn thành (tham khảo)',
    'Tổng lượt hẹn (tham khảo)',
    'Gói còn buổi',
    'Ghi chú',
    'Chăm sóc'
], ',', '"', "\\");

// Prepared statements for querying details
$stmt_appt = $db->prepare("
    SELECT 
        MIN(a.appointment_date) as first_date,
        MAX(a.appointment_date) as last_date,
        COUNT(*) as total_count,
        SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_count,
        GROUP_CONCAT(CONCAT_WS(':', a.type, COALESCE(u.full_name, ''))) as appt_details
    FROM appointments a
    LEFT JOIN users u ON a.doctor_id = u.id
    WHERE a.patient_id = ?
");

$stmt_mh = $db->prepare("SELECT GROUP_CONCAT(DISTINCT type) FROM medical_history WHERE patient_id = ?");

$stmt_own_pkg = $db->prepare("
    SELECT pp.id, pp.sessions_remaining, pp.status, pkg.name as pkg_name
    FROM patient_packages pp
    JOIN packages pkg ON pp.package_id = pkg.id
    WHERE pp.patient_id = ?
");

$stmt_shared_pkg = $db->prepare("
    SELECT psu.id, pp.sessions_remaining, pp.status, owner.full_name as owner_name, pkg.name as pkg_name
    FROM package_shared_users psu
    JOIN patient_packages pp ON psu.patient_package_id = pp.id
    JOIN patients owner ON pp.patient_id = owner.id
    JOIN packages pkg ON pp.package_id = pkg.id
    WHERE psu.patient_id = ?
");

foreach ($patients as $p) {
    $pid = (int)$p['id'];

    // 1. Appointments data
    $stmt_appt->execute([$pid]);
    $appt = $stmt_appt->fetch(PDO::FETCH_ASSOC);

    // 2. Medical history data
    $stmt_mh->execute([$pid]);
    $mh_types = $stmt_mh->fetchColumn() ?: '';

    // 3. Packages (Own)
    $stmt_own_pkg->execute([$pid]);
    $own_pkgs = $stmt_own_pkg->fetchAll(PDO::FETCH_ASSOC);

    // 4. Packages (Shared)
    $stmt_shared_pkg->execute([$pid]);
    $shared_pkgs = $stmt_shared_pkg->fetchAll(PDO::FETCH_ASSOC);

    $all_pkgs = array_merge($own_pkgs, $shared_pkgs);
    $has_bought_pkg = !empty($all_pkgs);

    // Remaining sessions check
    $total_remaining = 0;
    foreach ($own_pkgs as $op) $total_remaining += (int)$op['sessions_remaining'];
    foreach ($shared_pkgs as $sp) $total_remaining += (int)$sp['sessions_remaining'];
    $has_remaining_sessions = ($total_remaining > 0);

    // Analyze service usage
    $details_str = mb_strtolower(($appt['appt_details'] ?? '') . ' ' . $mh_types, 'UTF-8');
    $pkg_names_str = '';
    foreach ($all_pkgs as $pkg) $pkg_names_str .= ' ' . mb_strtolower($pkg['pkg_name'] ?? '', 'UTF-8');

    $is_chiro = (
        strpos($details_str, 'chiro') !== false ||
        strpos($details_str, 'bui huy') !== false ||
        strpos($details_str, 'henrik') !== false ||
        strpos($details_str, 'simon') !== false ||
        strpos($details_str, 'johanna') !== false ||
        strpos($details_str, 'soap_note') !== false ||
        strpos($details_str, 'pathologie') !== false ||
        strpos($pkg_names_str, 'chiro') !== false
    );

    $is_dong_y = (
        strpos($details_str, 'dong_y') !== false ||
        strpos($details_str, 'bùi thị lộc') !== false ||
        strpos($details_str, 'lộc') !== false ||
        strpos($pkg_names_str, 'đông y') !== false ||
        strpos($pkg_names_str, 'dong y') !== false ||
        strpos($pkg_names_str, 'đy') !== false
    );

    if (strpos($pkg_names_str, 'hỗn hợp') !== false) {
        $is_chiro = true;
        $is_dong_y = true;
    }

    $first_date = (!empty($appt['first_date']) && $appt['first_date'] !== '0000-00-00 00:00:00') ? date('d/m/Y', strtotime($appt['first_date'])) : '';
    $last_date = (!empty($appt['last_date']) && $appt['last_date'] !== '0000-00-00 00:00:00') ? date('d/m/Y', strtotime($appt['last_date'])) : '';

    $notes = [];
    if (!empty($p['notes'])) $notes[] = trim($p['notes']);
    foreach ($shared_pkgs as $sp) {
        $notes[] = "Dùng chung gói " . trim($sp['owner_name']);
    }
    $note_str = implode('; ', array_unique($notes));

    fputcsv($output, [
        trim($p['full_name']),
        $p['label'] ?? '',
        $p['phone'] ?? '',
        $first_date,
        $last_date,
        $is_chiro ? 'x' : '',
        $is_dong_y ? 'x' : '',
        $has_bought_pkg ? 'x' : '',
        !$has_bought_pkg ? 'x' : '',
        (int)($appt['completed_count'] ?? 0),
        (int)($appt['total_count'] ?? 0),
        $has_remaining_sessions ? 'x' : '',
        $note_str,
        '' // Cột Chăm sóc ngày...
    ], ',', '"', "\\");
}

fclose($output);
exit();
