<?php
// scripts/remediate_billing_revenue.php
// Script chuẩn hóa & làm sạch dữ liệu doanh thu sai lệch lịch sử (ADR 0003)

require_once __DIR__ . '/../includes/db.php';

$db = getDB();

echo "=== BẮT ĐẦU QUÁ TRÌNH LÀM SẠCH DOANH THU LỊCH SỬ ===\n";

$db->beginTransaction();

try {
    // 1. Xử lý các hóa đơn có total_amount = 0 nhưng bị ghi nhận tiền thu / tiền mặt / nợ
    $stmt = $db->query("
        SELECT id, invoice_no, total_amount, discount_amount, package_deduct, cash_amount, 
               transfer_personal_amount, transfer_company_amount, card_amount, debt_amount, created_at
        FROM invoices 
        WHERE total_amount = 0 
          AND (cash_amount > 0 OR transfer_amount > 0 OR transfer_personal_amount > 0 OR transfer_company_amount > 0 OR card_amount > 0 OR debt_amount > 0)
    ");
    $zeroTotalInvoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $countZero = count($zeroTotalInvoices);
    echo "1. Tìm thấy $countZero hóa đơn có total_amount = 0 nhưng có ghi nhận tiền thu > 0.\n";

    $zeroIds = [];
    $totalRemovedFromZero = 0;
    foreach ($zeroTotalInvoices as $inv) {
        $zeroIds[] = (int)$inv['id'];
        $totalRemovedFromZero += (float)$inv['cash_amount'] + (float)$inv['transfer_personal_amount'] + (float)$inv['transfer_company_amount'] + (float)$inv['card_amount'];
    }

    if (!empty($zeroIds)) {
        // Xóa transactions ảo tương ứng
        $inClause = implode(',', $zeroIds);
        $delTxStmt = $db->query("DELETE FROM transactions WHERE reference_id IN ($inClause) AND description LIKE 'Phiếu tính tiền %'");
        $deletedTxCount = $delTxStmt->rowCount();
        echo "   -> Đã xóa $deletedTxCount dòng giao dịch ảo trong bảng transactions (tổng tiền ảo giải phóng: " . number_format($totalRemovedFromZero, 0, ',', '.') . " đ).\n";

        // Cập nhật invoices về 0
        $updInvStmt = $db->query("
            UPDATE invoices 
            SET cash_amount = 0, transfer_amount = 0, transfer_personal_amount = 0, transfer_company_amount = 0, card_amount = 0, debt_amount = 0 
            WHERE id IN ($inClause)
        ");
        echo "   -> Đã cập nhật $countZero hóa đơn về 0đ tiền thu thực tế.\n";
    }

    // 2. Xử lý các hóa đơn có package_deduct >= total_amount (khách trả 100% bằng Coin/Gói) nhưng vẫn lưu tiền mặt
    $stmt = $db->query("
        SELECT id, invoice_no, total_amount, package_deduct, cash_amount, transfer_personal_amount, transfer_company_amount, card_amount
        FROM invoices
        WHERE total_amount > 0 
          AND package_deduct >= total_amount
          AND (cash_amount > 0 OR transfer_personal_amount > 0 OR transfer_company_amount > 0 OR card_amount > 0)
    ");
    $coinCovered = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $countCoinCovered = count($coinCovered);
    echo "2. Tìm thấy $countCoinCovered hóa đơn thanh toán Coin đủ 100% nhưng vẫn ghi nhận tiền mặt/CK.\n";

    $coinCoveredIds = [];
    foreach ($coinCovered as $inv) {
        $coinCoveredIds[] = (int)$inv['id'];
    }
    if (!empty($coinCoveredIds)) {
        $inClause = implode(',', $coinCoveredIds);
        $delTxStmt = $db->query("DELETE FROM transactions WHERE reference_id IN ($inClause) AND description LIKE 'Phiếu tính tiền %'");
        echo "   -> Đã xóa " . $delTxStmt->rowCount() . " dòng giao dịch trong transactions.\n";

        $db->query("
            UPDATE invoices 
            SET cash_amount = 0, transfer_amount = 0, transfer_personal_amount = 0, transfer_company_amount = 0, card_amount = 0, debt_amount = 0 
            WHERE id IN ($inClause)
        ");
        echo "   -> Đã chuẩn hóa $countCoinCovered hóa đơn trừ coin về 0đ tiền thu.\n";
    }

    // 3. Xử lý các hóa đơn có tổng tiền thu vượt quá số tiền cần thu (Overpaid mismatch do auto-fill cash 900k)
    $stmt = $db->query("
        SELECT id, invoice_no, total_amount, package_deduct, cash_amount, 
               transfer_personal_amount, transfer_company_amount, card_amount, debt_amount
        FROM invoices
        WHERE total_amount > 0 
          AND package_deduct < total_amount
          AND (cash_amount + transfer_personal_amount + transfer_company_amount + card_amount) > (total_amount - package_deduct)
    ");
    $overpaidInvoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $countOverpaid = count($overpaidInvoices);
    echo "3. Tìm thấy $countOverpaid hóa đơn có tổng tiền thanh toán vượt quá số tiền cần thu thực tế.\n";

    foreach ($overpaidInvoices as $inv) {
        $invId = (int)$inv['id'];
        $netPayable = max(0, (float)$inv['total_amount'] - (float)$inv['package_deduct']);
        $nonCash = (float)$inv['transfer_personal_amount'] + (float)$inv['transfer_company_amount'] + (float)$inv['card_amount'];
        
        $newCash = max(0, $netPayable - $nonCash);
        
        // Cập nhật invoice
        $upd = $db->prepare("UPDATE invoices SET cash_amount = ?, debt_amount = 0 WHERE id = ?");
        $upd->execute([$newCash, $invId]);

        // Cập nhật hoặc xóa transaction tiền mặt tương ứng
        if ($newCash > 0) {
            $updTx = $db->prepare("UPDATE transactions SET amount = ? WHERE reference_id = ? AND category = 'Tiền mặt' AND description LIKE 'Phiếu tính tiền %'");
            $updTx->execute([$newCash, $invId]);
        } else {
            $delTx = $db->prepare("DELETE FROM transactions WHERE reference_id = ? AND category = 'Tiền mặt' AND description LIKE 'Phiếu tính tiền %'");
            $delTx->execute([$invId]);
        }
        echo "   -> Đã điều chỉnh hóa đơn {$inv['invoice_no']} (ID: $invId): tiền mặt cũ " . number_format($inv['cash_amount'], 0, ',', '.') . " đ -> tiền mặt mới " . number_format($newCash, 0, ',', '.') . " đ (Net payable: " . number_format($netPayable, 0, ',', '.') . " đ).\n";
    }

    $db->commit();
    echo "\n=== HOÀN TẤT DỌN SẠCH & CHUẨN HÓA DỮ LIỆU THÀNH CÔNG ===\n";

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "LỖI: " . $e->getMessage() . "\n";
    exit(1);
}
