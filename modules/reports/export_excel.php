<?php
// modules/reports/export_excel.php
require_once '../../includes/db.php';
require_once '../../includes/auth_middleware.php';

$db = getDB();
$month = date('m');
$year = date('Y');

$stmt = $db->query("SELECT * FROM transactions ORDER BY transaction_date DESC");
$transactions = $stmt->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=BaoCao_TaiChinh_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');
// Add UTF-8 BOM for Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($output, ['Thời gian', 'Loại', 'Hạng mục', 'Số tiền', 'Mô tả']);

foreach ($transactions as $tx) {
    fputcsv($output, [
        $tx['transaction_date'],
        $tx['type'] === 'income' ? 'THU' : 'CHI',
        strtoupper($tx['category']),
        (float)$tx['amount'],
        $tx['description']
    ]);
}
fclose($output);
exit;
