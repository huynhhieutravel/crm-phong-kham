<?php
require_once __DIR__ . '/includes/db.php';
$db = getDB();

$stmt = $db->query("SELECT * FROM invoices WHERE created_at >= '2026-04-01'");
$invoices = $stmt->fetchAll();

$count = 0;
foreach ($invoices as $inv) {
    $invoice_id = $inv['id'];
    $invoice_no = $inv['invoice_no'];
    $branch_id = 1;
    $creator_id = $inv['created_by'];
    
    // Check if already in transactions
    $check = $db->prepare("SELECT COUNT(*) FROM transactions WHERE reference_id = ? AND description LIKE ?");
    $check->execute([$invoice_id, "%Phiếu tính tiền $invoice_no%"]);
    if ($check->fetchColumn() > 0) continue; // Already synced
    
    if ($inv['cash_amount'] > 0) {
        $db->prepare("INSERT INTO transactions (type, category, amount, reference_id, description, branch_id, created_by, transaction_date) VALUES ('income', 'Tiền mặt', ?, ?, ?, ?, ?, ?)")
           ->execute([$inv['cash_amount'], $invoice_id, "Phiếu tính tiền $invoice_no", $branch_id, $creator_id, $inv['created_at']]);
        $count++;
    }
    if ($inv['transfer_amount'] > 0 && floatval($inv['transfer_personal_amount']) == 0 && floatval($inv['transfer_company_amount']) == 0) {
        $db->prepare("INSERT INTO transactions (type, category, amount, reference_id, description, branch_id, created_by, transaction_date) VALUES ('income', 'Chuyển khoản (Cũ)', ?, ?, ?, ?, ?, ?)")
           ->execute([$inv['transfer_amount'], $invoice_id, "Phiếu tính tiền $invoice_no", $branch_id, $creator_id, $inv['created_at']]);
        $count++;
    }
    if ($inv['transfer_personal_amount'] > 0) {
        $db->prepare("INSERT INTO transactions (type, category, amount, reference_id, description, branch_id, created_by, transaction_date) VALUES ('income', 'CK Cá nhân', ?, ?, ?, ?, ?, ?)")
           ->execute([$inv['transfer_personal_amount'], $invoice_id, "Phiếu tính tiền $invoice_no", $branch_id, $creator_id, $inv['created_at']]);
        $count++;
    }
    if ($inv['transfer_company_amount'] > 0) {
        $db->prepare("INSERT INTO transactions (type, category, amount, reference_id, description, branch_id, created_by, transaction_date) VALUES ('income', 'TK Công ty', ?, ?, ?, ?, ?, ?)")
           ->execute([$inv['transfer_company_amount'], $invoice_id, "Phiếu tính tiền $invoice_no", $branch_id, $creator_id, $inv['created_at']]);
        $count++;
    }
}
echo "Đã đồng bộ thành công $count giao dịch thu tiền từ Phiếu tính tiền vào Báo cáo tài chính.";
