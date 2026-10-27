<?php
$_GET['period'] = 'custom';
$_GET['start_date'] = '2026-10-01';
$_GET['end_date'] = '2026-10-31';
$_SESSION['user_id'] = 1;

ob_start();
// Include without header/footer output if possible or just parse calculations
require '/var/www/crm_phong_kham/includes/db.php';
require '/var/www/crm_phong_kham/includes/functions.php';
$db = getDB();

$range = get_date_range('custom', '2026-10-01', '2026-10-31');
$start = $range['start'];
$end = $range['end'];

// Stats (Phiếu tính tiền)
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_count,
        COALESCE(SUM(total_amount), 0) as total_billing_value,
        COALESCE(SUM(package_deduct), 0) as total_package_deduct,
        COALESCE(SUM(cash_amount), 0) as total_cash,
        COALESCE(SUM(transfer_personal_amount), 0) as total_transfer_personal,
        COALESCE(SUM(transfer_company_amount), 0) as total_transfer_company,
        COALESCE(SUM(CASE 
            WHEN transfer_personal_amount = 0 AND transfer_company_amount = 0 THEN transfer_amount 
            ELSE 0 
        END), 0) as total_transfer_other,
        COALESCE(SUM(card_amount), 0) as total_card,
        COALESCE(SUM(debt_amount), 0) as total_debt
    FROM invoices 
    WHERE created_at BETWEEN ? AND ? 
    AND status != 'cancelled'
");
$stmt->execute([$start, $end]);
$stats = $stmt->fetch();

// Stats (Bán gói) - Lấy từ transactions
$stmt = $db->prepare("
    SELECT COALESCE(SUM(t.amount), 0) as total_pkg
    FROM transactions t
    WHERE t.transaction_date BETWEEN ? AND ? AND t.type = 'income' AND t.category IN ('package', 'package_debt')
");
$stmt->execute([$start, $end]);
$pkg_stats = $stmt->fetch();
$total_package_revenue = (float)$pkg_stats['total_pkg'];

// Danh sách giao dịch bán gói trong kỳ
$stmt = $db->prepare("
    SELECT t.amount, t.description, t.transaction_date, t.category, u.full_name as seller_name,
           pt.full_name as patient_name, pp.id as pp_id
    FROM transactions t
    LEFT JOIN users u ON t.created_by = u.id
    LEFT JOIN patient_packages pp ON t.reference_id = pp.id
    LEFT JOIN patients pt ON pp.patient_id = pt.id
    WHERE t.transaction_date BETWEEN ? AND ? AND t.type = 'income' AND t.category IN ('package', 'package_debt')
    ORDER BY t.transaction_date DESC
");
$stmt->execute([$start, $end]);
$pkg_transactions = $stmt->fetchAll();

$pkg_cash = 0;
$pkg_transfer_personal = 0;
$pkg_transfer_company = 0;
$pkg_card = 0;

foreach ($pkg_transactions as $ptx) {
    $desc = $ptx['description'] ?? '';
    $amt = (float)$ptx['amount'];
    if (mb_stripos($desc, 'Tiền mặt') !== false) {
        $pkg_cash += $amt;
    } elseif (mb_stripos($desc, 'TK Công ty') !== false) {
        $pkg_transfer_company += $amt;
    } elseif (mb_stripos($desc, 'Quẹt Thẻ') !== false || mb_stripos($desc, 'Quẹt thẻ') !== false) {
        $pkg_card += $amt;
    } else {
        $pkg_transfer_personal += $amt;
    }
}

$retail_cash = (float)$stats['total_cash'];
$retail_transfer_personal = (float)$stats['total_transfer_personal'];
$retail_transfer_company = (float)$stats['total_transfer_company'];
$retail_transfer_other = (float)$stats['total_transfer_other'];
$retail_card = (float)$stats['total_card'];

$total_cash = $retail_cash + $pkg_cash;
$total_transfer_personal = $retail_transfer_personal + $retail_transfer_other + $pkg_transfer_personal;
$total_transfer_company = $retail_transfer_company + $pkg_transfer_company;
$total_card = $retail_card + $pkg_card;

$grand_total = $total_cash + $total_transfer_personal + $total_transfer_company + $total_card;

echo "--- THỐNG KÊ THÁNG 10/2026 SAU KHI TÍCH HỢP BÁN GÓI VÀO KÊNH THANH TOÁN ---\n";
echo "TỔNG THỰC THU: " . number_format($grand_total, 0, ',', '.') . " đ\n";
echo "BÁN GÓI: " . number_format($total_package_revenue, 0, ',', '.') . " đ (" . count($pkg_transactions) . " giao dịch)\n";
echo "TIỀN MẶT: " . number_format($total_cash, 0, ',', '.') . " đ (Lẻ: " . number_format($retail_cash, 0, ',', '.') . " | Gói: " . number_format($pkg_cash, 0, ',', '.') . ")\n";
echo "CK CÁ NHÂN: " . number_format($total_transfer_personal, 0, ',', '.') . " đ (Lẻ: " . number_format($retail_transfer_personal + $retail_transfer_other, 0, ',', '.') . " | Gói: " . number_format($pkg_transfer_personal, 0, ',', '.') . ")\n";
echo "TK CÔNG TY: " . number_format($total_transfer_company, 0, ',', '.') . " đ\n";
echo "QUẸT THẺ: " . number_format($total_card, 0, ',', '.') . " đ (Lẻ: " . number_format($retail_card, 0, ',', '.') . " | Gói: " . number_format($pkg_card, 0, ',', '.') . ")\n";
echo "CỘNG 4 THẺ THANH TOÁN (Tiền mặt + CK + Thẻ): " . number_format($total_cash + $total_transfer_personal + $total_transfer_company + $total_card, 0, ',', '.') . " đ\n";
