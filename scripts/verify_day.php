<?php
require_once '/var/www/crm_phong_kham/includes/db.php';
require_once '/var/www/crm_phong_kham/includes/functions.php';

$db = getDB();
$range = get_date_range("custom", "2026-10-01", "2026-10-01");
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_count,
        COALESCE(SUM(total_amount), 0) as total_billing_value,
        COALESCE(SUM(package_deduct), 0) as total_package_deduct,
        COALESCE(SUM(cash_amount), 0) as total_cash,
        COALESCE(SUM(transfer_personal_amount), 0) as total_transfer_personal,
        COALESCE(SUM(transfer_company_amount), 0) as total_transfer_company,
        COALESCE(SUM(card_amount), 0) as total_card,
        COALESCE(SUM(debt_amount), 0) as total_debt
    FROM invoices 
    WHERE created_at BETWEEN ? AND ? 
    AND status != 'cancelled'
");
$stmt->execute([$range['start'], $range['end']]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$pkg_stmt = $db->prepare("
    SELECT COALESCE(SUM(t.amount), 0) as total_pkg 
    FROM transactions t 
    WHERE t.transaction_date BETWEEN ? AND ? 
      AND t.type = 'income' 
      AND t.category IN ('package', 'package_debt')
");
$pkg_stmt->execute([$range['start'], $range['end']]);
$pkg = $pkg_stmt->fetch(PDO::FETCH_ASSOC);

echo "--- KẾT QUẢ TÍNH TOÁN NGÀY 01/10/2026 ---\n";
echo "TIỀN MẶT: " . number_format($stats['total_cash'], 0, ',', '.') . " đ\n";
echo "BÁN GÓI: " . number_format($pkg['total_pkg'], 0, ',', '.') . " đ\n";
echo "TRỪ COIN / GÓI: " . number_format($stats['total_package_deduct'], 0, ',', '.') . " đ\n";
echo "TỔNG THỰC THU: " . number_format($stats['total_cash'] + $pkg['total_pkg'], 0, ',', '.') . " đ\n";
