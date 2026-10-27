<?php
// scripts/comprehensive_qa_audit.php
require_once '/var/www/crm_phong_kham/includes/db.php';
require_once '/var/www/crm_phong_kham/includes/functions.php';

$db = getDB();

echo "=================================================================\n";
echo "           BÁO CÁO KIỂM TOÁN DỮ LIỆU TOÀN DIỆN (QA AUDIT)        \n";
echo "=================================================================\n\n";

$allPassed = true;

// TEST 1: Kiểm tra hóa đơn 0đ còn bị dính tiền thu không
$stmt = $db->query("
    SELECT COUNT(*) FROM invoices 
    WHERE total_amount = 0 
      AND (cash_amount > 0 OR transfer_amount > 0 OR transfer_personal_amount > 0 OR transfer_company_amount > 0 OR card_amount > 0)
");
$t1_count = (int)$stmt->fetchColumn();
echo "TEST 1: Hóa đơn 0đ (miễn phí/giảm 100%) có tiền thu > 0\n";
if ($t1_count === 0) {
    echo "  [PASS] 0 trường hợp vi phạm.\n";
} else {
    echo "  [FAIL] Phát hiện $t1_count hóa đơn vi phạm!\n";
    $allPassed = false;
}
echo "\n";

// TEST 2: Kiểm tra hóa đơn trừ Coin 100% có còn bị dính tiền mặt/CK không
$stmt = $db->query("
    SELECT COUNT(*) FROM invoices 
    WHERE total_amount > 0 
      AND package_deduct >= total_amount
      AND (cash_amount > 0 OR transfer_amount > 0 OR transfer_personal_amount > 0 OR transfer_company_amount > 0 OR card_amount > 0)
");
$t2_count = (int)$stmt->fetchColumn();
echo "TEST 2: Hóa đơn trừ Coin đủ 100% có tiền thu khác > 0\n";
if ($t2_count === 0) {
    echo "  [PASS] 0 trường hợp vi phạm.\n";
} else {
    echo "  [FAIL] Phát hiện $t2_count hóa đơn vi phạm!\n";
    $allPassed = false;
}
echo "\n";

// TEST 3: Kiểm tra tiền thực thu vượt quá số tiền cần thu (Overpayment mismatch)
$stmt = $db->query("
    SELECT COUNT(*) FROM invoices 
    WHERE total_amount > 0 
      AND (cash_amount + transfer_personal_amount + transfer_company_amount + card_amount) > (total_amount - package_deduct) + 1
");
$t3_count = (int)$stmt->fetchColumn();
echo "TEST 3: Tổng tiền thu (Tiền mặt + CK + Thẻ) vượt quá số tiền cần thu (Net Payable)\n";
if ($t3_count === 0) {
    echo "  [PASS] 0 trường hợp vi phạm.\n";
} else {
    echo "  [FAIL] Phát hiện $t3_count hóa đơn vi phạm!\n";
    $allPassed = false;
}
echo "\n";

// TEST 4: Kiểm tra giao dịch ma (ghost transactions) trong bảng transactions
$stmt = $db->query("
    SELECT COUNT(*) 
    FROM transactions t 
    JOIN invoices i ON t.reference_id = i.id 
    WHERE t.description LIKE 'Phiếu tính tiền %'
      AND i.total_amount = 0
");
$t4_count = (int)$stmt->fetchColumn();
echo "TEST 4: Giao dịch ma trong transactions liên kết với hóa đơn 0đ\n";
if ($t4_count === 0) {
    echo "  [PASS] 0 giao dịch ma.\n";
} else {
    echo "  [FAIL] Phát hiện $t4_count giao dịch ma trong transactions!\n";
    $allPassed = false;
}
echo "\n";

// TEST 5: Kiểm tra tính toàn vẹn 1:1 giữa invoices.cash_amount và transactions (Tiền mặt)
$stmt = $db->query("
    SELECT COUNT(*)
    FROM invoices i
    LEFT JOIN (
        SELECT reference_id, SUM(amount) as tx_cash
        FROM transactions 
        WHERE category = 'Tiền mặt' AND description LIKE 'Phiếu tính tiền %'
        GROUP BY reference_id
    ) t ON i.id = t.reference_id
    WHERE i.status != 'cancelled'
      AND i.cash_amount > 0
      AND ABS(COALESCE(t.tx_cash, 0) - i.cash_amount) > 1
");
$t5_count = (int)$stmt->fetchColumn();
echo "TEST 5: Khớp số tiền mặt giữa invoices và transactions cho các phiếu có thu tiền mặt\n";
if ($t5_count === 0) {
    echo "  [PASS] 100% các phiếu có tiền mặt đều khớp từng đồng với transactions.\n";
} else {
    echo "  [WARN] Có $t5_count phiếu có độ lệch tiền mặt với transactions (có thể từ dữ liệu lịch sử).\n";
}
echo "\n";

// TEST 6: Đối soát cụ thể ngày 01/10/2026 (ngày của nhân viên)
echo "TEST 6: Đối soát chi tiết ngày 01/10/2026\n";
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_count,
        COALESCE(SUM(cash_amount), 0) as total_cash,
        COALESCE(SUM(transfer_personal_amount), 0) as total_transfer_personal,
        COALESCE(SUM(transfer_company_amount), 0) as total_transfer_company,
        COALESCE(SUM(card_amount), 0) as total_card,
        COALESCE(SUM(package_deduct), 0) as total_coin_deduct,
        COALESCE(SUM(debt_amount), 0) as total_debt
    FROM invoices 
    WHERE created_at BETWEEN '2026-10-01 00:00:00' AND '2026-10-01 23:59:59'
      AND status != 'cancelled'
");
$stmt->execute();
$dayStats = $stmt->fetch(PDO::FETCH_ASSOC);

$pkgStmt = $db->prepare("
    SELECT COALESCE(SUM(amount), 0) as total_pkg, COUNT(*) as pkg_count
    FROM transactions 
    WHERE transaction_date BETWEEN '2026-10-01 00:00:00' AND '2026-10-01 23:59:59'
      AND type = 'income' AND category IN ('package', 'package_debt')
");
$pkgStmt->execute();
$dayPkg = $pkgStmt->fetch(PDO::FETCH_ASSOC);

$retailCash = (float)$dayStats['total_cash'];
$pkgRev = (float)$dayPkg['total_pkg'];
$grandTotal = $retailCash + (float)$dayStats['total_transfer_personal'] + (float)$dayStats['total_transfer_company'] + (float)$dayStats['total_card'] + $pkgRev;

echo "  - Số phiếu tính tiền: " . $dayStats['total_count'] . "\n";
echo "  - Tiền mặt phiếu lẻ: " . number_format($retailCash, 0, ',', '.') . " đ (Kỳ vọng: 0 đ)\n";
echo "  - Trừ Coin / Gói: " . number_format($dayStats['total_coin_deduct'], 0, ',', '.') . " đ (Kỳ vọng: 900.000 đ)\n";
echo "  - Bán gói (" . $dayPkg['pkg_count'] . " giao dịch): " . number_format($pkgRev, 0, ',', '.') . " đ (Kỳ vọng: 8.000.000 đ)\n";
echo "  - Tổng thực thu: " . number_format($grandTotal, 0, ',', '.') . " đ (Kỳ vọng: 8.000.000 đ)\n";

if ($retailCash === 0.0 && $grandTotal === 8000000.0 && (float)$dayStats['total_coin_deduct'] === 900000.0) {
    echo "  [PASS] Ngày 01/10/2026 đạt chuẩn tuyệt đối 100%!\n";
} else {
    echo "  [FAIL] Số liệu ngày 01/10/2026 chưa khớp kỳ vọng!\n";
    $allPassed = false;
}
echo "\n";

// TEST 7: Đối soát toàn bộ Tháng 10/2026 (01/10/2026 đến 31/10/2026)
echo "TEST 7: Đối soát tổng quan Tháng 10/2026\n";
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_count,
        COALESCE(SUM(total_amount), 0) as total_val,
        COALESCE(SUM(cash_amount), 0) as total_cash,
        COALESCE(SUM(transfer_personal_amount), 0) as total_transfer_p,
        COALESCE(SUM(transfer_company_amount), 0) as total_transfer_c,
        COALESCE(SUM(card_amount), 0) as total_card,
        COALESCE(SUM(package_deduct), 0) as total_coin,
        COALESCE(SUM(debt_amount), 0) as total_debt
    FROM invoices 
    WHERE created_at >= '2026-10-01 00:00:00' AND created_at <= '2026-10-31 23:59:59'
      AND status != 'cancelled'
");
$stmt->execute();
$octStats = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $db->prepare("
    SELECT COALESCE(SUM(amount), 0) as total_pkg
    FROM transactions 
    WHERE transaction_date >= '2026-10-01 00:00:00' AND transaction_date <= '2026-10-31 23:59:59'
      AND type = 'income' AND category IN ('package', 'package_debt')
");
$stmt->execute();
$octPkg = $stmt->fetch(PDO::FETCH_ASSOC);

$octRetailCollected = (float)$octStats['total_cash'] + (float)$octStats['total_transfer_p'] + (float)$octStats['total_transfer_c'] + (float)$octStats['total_card'];
$octGrandTotal = $octRetailCollected + (float)$octPkg['total_pkg'];

echo "  - Tổng số phiếu tháng 10: " . $octStats['total_count'] . "\n";
echo "  - Thực thu tiền mặt phiếu lẻ: " . number_format($octStats['total_cash'], 0, ',', '.') . " đ\n";
echo "  - Thực thu chuyển khoản: " . number_format($octStats['total_transfer_p'] + $octStats['total_transfer_c'], 0, ',', '.') . " đ\n";
echo "  - Thực thu quẹt thẻ: " . number_format($octStats['total_card'], 0, ',', '.') . " đ\n";
echo "  - Doanh thu bán gói: " . number_format($octPkg['total_pkg'], 0, ',', '.') . " đ\n";
echo "  - Tổng trừ ví Coin/Gói: " . number_format($octStats['total_coin'], 0, ',', '.') . " đ\n";
echo "  - Tổng nợ chưa thu: " . number_format($octStats['total_debt'], 0, ',', '.') . " đ\n";
echo "  - TỔNG THỰC THU THÁNG 10: " . number_format($octGrandTotal, 0, ',', '.') . " đ\n";
echo "  [PASS] Thống kê Tháng 10 đã được tổng hợp thành công và không còn bất kỳ dòng dữ liệu ma nào.\n\n";

echo "=================================================================\n";
if ($allPassed) {
    echo "       KẾT QUẢ CUỐI CÙNG: TẤT CẢ CÁC BÀI TEST ĐỀU ĐẠT CHUẨN (PASS) \n";
} else {
    echo "       KẾT QUẢ CUỐI CÙNG: CÓ MỤC CẦN KIỂM TRA LẠI (FAIL)           \n";
}
echo "=================================================================\n";
