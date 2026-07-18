<?php
require_once 'includes/db.php';
$db = getDB();

// Find all invoices that have BOTH new transfer types AND 'Chuyển khoản (Cũ)'
$stmt = $db->query("
    SELECT t1.id 
    FROM transactions t1
    JOIN transactions t2 ON t1.reference_id = t2.reference_id
    WHERE t1.category = 'Chuyển khoản (Cũ)' 
      AND t2.category IN ('CK Cá nhân', 'TK Công ty')
      AND t1.description LIKE 'Phiếu tính tiền %'
      AND t2.description LIKE 'Phiếu tính tiền %'
");

$ids_to_delete = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (count($ids_to_delete) > 0) {
    $placeholders = implode(',', array_fill(0, count($ids_to_delete), '?'));
    $del = $db->prepare("DELETE FROM transactions WHERE id IN ($placeholders)");
    $del->execute($ids_to_delete);
    echo "Đã xóa " . count($ids_to_delete) . " giao dịch bị nhân đôi thành công!";
} else {
    echo "Không tìm thấy giao dịch nào bị nhân đôi.";
}
