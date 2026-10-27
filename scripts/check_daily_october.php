<?php
require_once '/var/www/crm_phong_kham/includes/db.php';
require_once '/var/www/crm_phong_kham/includes/functions.php';

$db = getDB();

echo "=== KIỂM TRA TỪNG NGÀY TRONG THÁNG 10/2026 ===\n";

$days = [];
for ($d = 1; $d <= 31; $d++) {
    $dateStr = sprintf("2026-10-%02d", $d);
    $range = get_date_range("custom", $dateStr, $dateStr);
    
    // Invoices
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_count,
            COALESCE(SUM(total_amount), 0) as total_val,
            COALESCE(SUM(package_deduct), 0) as total_coin,
            COALESCE(SUM(cash_amount), 0) as total_cash,
            COALESCE(SUM(transfer_personal_amount), 0) as total_transfer_p,
            COALESCE(SUM(transfer_company_amount), 0) as total_transfer_c,
            COALESCE(SUM(CASE 
                WHEN transfer_personal_amount = 0 AND transfer_company_amount = 0 THEN transfer_amount 
                ELSE 0 
            END), 0) as total_transfer_other,
            COALESCE(SUM(card_amount), 0) as total_card
        FROM invoices 
        WHERE created_at BETWEEN ? AND ? 
        AND status != 'cancelled'
    ");
    $stmt->execute([$range['start'], $range['end']]);
    $invStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Packages
    $pkgStmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) as total_pkg
        FROM transactions 
        WHERE transaction_date BETWEEN ? AND ? 
          AND type = 'income' AND category IN ('package', 'package_debt')
    ");
    $pkgStmt->execute([$range['start'], $range['end']]);
    $pkgStats = $pkgStmt->fetch(PDO::FETCH_ASSOC);

    $retailActual = (float)$invStats['total_cash'] + (float)$invStats['total_transfer_p'] + (float)$invStats['total_transfer_c'] + (float)$invStats['total_transfer_other'] + (float)$invStats['total_card'];
    $pkgRev = (float)$pkgStats['total_pkg'];
    $dayTotal = $retailActual + $pkgRev;

    if ($invStats['total_count'] > 0 || $pkgRev > 0) {
        printf("%s: Phiếu: %2d | Tiền mặt: %10s | CK: %10s | Thẻ: %10s | Bán gói: %10s | Tổng ngày: %10s\n",
            $dateStr,
            $invStats['total_count'],
            number_format($invStats['total_cash'], 0, ',', '.'),
            number_format($invStats['total_transfer_p'] + $invStats['total_transfer_c'] + $invStats['total_transfer_other'], 0, ',', '.'),
            number_format($invStats['total_card'], 0, ',', '.'),
            number_format($pkgRev, 0, ',', '.'),
            number_format($dayTotal, 0, ',', '.')
        );
    }
}
