<?php
// scripts/cross_module_revenue_audit.php
// Automated Cross-Module Reconciliation Audit Script
// Validates that Billing, Daily Revenue, and Dashboard Overview reports match 100%

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDB();

echo "====================================================================\n";
echo "   SIMON CENTER CRM — AUTOMATED CROSS-MODULE RECONCILIATION AUDIT    \n";
echo "====================================================================\n\n";

function calculate_billing_kpis($db, $start, $end) {
    // 1. Invoices
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_invoices,
            COALESCE(SUM(total_amount), 0) as total_amount,
            COALESCE(SUM(cash_amount), 0) as total_cash,
            COALESCE(SUM(transfer_personal_amount), 0) as total_transfer_personal,
            COALESCE(SUM(transfer_company_amount), 0) as total_transfer_company,
            COALESCE(SUM(CASE 
                WHEN transfer_personal_amount = 0 AND transfer_company_amount = 0 THEN transfer_amount 
                ELSE 0 
            END), 0) as total_transfer_other,
            COALESCE(SUM(card_amount), 0) as total_card,
            COALESCE(SUM(debt_amount), 0) as total_debt,
            COALESCE(SUM(package_deduct), 0) as total_package_deduct
        FROM invoices 
        WHERE created_at >= ? AND created_at <= ? AND status != 'cancelled'
    ");
    $stmt->execute([$start, $end]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Package transactions
    $pkg_stmt = $db->prepare("
        SELECT amount, description 
        FROM transactions 
        WHERE category IN ('package', 'package_debt') 
          AND type = 'income' 
          AND transaction_date >= ? AND transaction_date <= ?
    ");
    $pkg_stmt->execute([$start, $end]);
    $pkg_txs = $pkg_stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_package = 0;
    $pkg_cash = 0;
    $pkg_transfer_personal = 0;
    $pkg_transfer_company = 0;
    $pkg_card = 0;

    foreach ($pkg_txs as $ptx) {
        $desc = $ptx['description'] ?? '';
        $amt = (float)$ptx['amount'];
        $total_package += $amt;
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

    $cash = (float)$stats['total_cash'] + $pkg_cash;
    $ck_personal = (float)$stats['total_transfer_personal'] + (float)$stats['total_transfer_other'] + $pkg_transfer_personal;
    $ck_company = (float)$stats['total_transfer_company'] + $pkg_transfer_company;
    $card = (float)$stats['total_card'] + $pkg_card;
    $grand_total = $cash + $ck_personal + $ck_company + $card;

    return [
        'cash' => $cash,
        'ck_personal' => $ck_personal,
        'ck_company' => $ck_company,
        'card' => $card,
        'package_revenue' => $total_package,
        'grand_total' => $grand_total,
        'package_deduct' => (float)$stats['total_package_deduct'],
        'debt' => (float)$stats['total_debt']
    ];
}

function calculate_daily_revenue_kpis($db, $start, $end) {
    // Exact logic from daily_revenue.php
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_invoices,
            COALESCE(SUM(total_amount), 0) as total_invoice_amount,
            COALESCE(SUM(cash_amount), 0) as total_cash,
            COALESCE(SUM(transfer_personal_amount), 0) as total_transfer_personal,
            COALESCE(SUM(transfer_company_amount), 0) as total_transfer_company,
            COALESCE(SUM(CASE 
                WHEN transfer_personal_amount = 0 AND transfer_company_amount = 0 THEN transfer_amount 
                ELSE 0 
            END), 0) as total_transfer_other,
            COALESCE(SUM(card_amount), 0) as total_card,
            COALESCE(SUM(package_deduct), 0) as total_package_deduct,
            COALESCE(SUM(debt_amount), 0) as total_debt
        FROM invoices
        WHERE created_at BETWEEN ? AND ? AND status != 'cancelled'
    ");
    $stmt->execute([$start, $end]);
    $inv_totals = $stmt->fetch(PDO::FETCH_ASSOC);

    $pkg_stmt = $db->prepare("
        SELECT amount, description, transaction_date 
        FROM transactions 
        WHERE category IN ('package', 'package_debt') 
          AND type = 'income' 
          AND transaction_date BETWEEN ? AND ?
    ");
    $pkg_stmt->execute([$start, $end]);
    $pkg_transactions = $pkg_stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_package_revenue = 0;
    $pkg_cash = 0;
    $pkg_transfer_personal = 0;
    $pkg_transfer_company = 0;
    $pkg_card = 0;

    foreach ($pkg_transactions as $ptx) {
        $desc = $ptx['description'] ?? '';
        $amt = (float)$ptx['amount'];
        $total_package_revenue += $amt;
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

    $retail_cash = (float)$inv_totals['total_cash'];
    $retail_transfer_personal = (float)$inv_totals['total_transfer_personal'];
    $retail_transfer_company = (float)$inv_totals['total_transfer_company'];
    $retail_transfer_other = (float)$inv_totals['total_transfer_other'];
    $retail_card = (float)$inv_totals['total_card'];

    $total_cash = $retail_cash + $pkg_cash;
    $total_transfer_personal = $retail_transfer_personal + $retail_transfer_other + $pkg_transfer_personal;
    $total_transfer_company = $retail_transfer_company + $pkg_transfer_company;
    $total_card = $retail_card + $pkg_card;

    $total_revenue = $total_cash + $total_transfer_personal + $total_transfer_company + $total_card;

    return [
        'cash' => $total_cash,
        'ck_personal' => $total_transfer_personal,
        'ck_company' => $total_transfer_company,
        'card' => $total_card,
        'package_revenue' => $total_package_revenue,
        'grand_total' => $total_revenue,
        'package_deduct' => (float)$inv_totals['total_package_deduct'],
        'debt' => (float)$inv_totals['total_debt']
    ];
}

function calculate_dashboard_revenue($db, $start, $end) {
    // Logic from overview_stats.php & reports/index.php
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) 
        FROM transactions 
        WHERE type = 'income' AND transaction_date >= ? AND transaction_date <= ?
    ");
    $stmt->execute([$start, $end]);
    return (float)$stmt->fetchColumn();
}

// Test Suite: 10 test periods
$test_periods = [
    'Tháng 10/2026 (Toàn tháng)' => ['2026-10-01 00:00:00', '2026-10-31 23:59:59'],
    'Ngày 01/10/2026' => ['2026-10-01 00:00:00', '2026-10-01 23:59:59'],
    'Ngày 15/10/2026' => ['2026-10-15 00:00:00', '2026-10-15 23:59:59'],
    'Ngày 25/10/2026' => ['2026-10-25 00:00:00', '2026-10-25 23:59:59'],
    'Ngày 26/10/2026' => ['2026-10-26 00:00:00', '2026-10-26 23:59:59'],
    'Ngày 27/10/2026' => ['2026-10-27 00:00:00', '2026-10-27 23:59:59'],
    'Tháng 09/2026' => ['2026-09-01 00:00:00', '2026-09-30 23:59:59'],
    'Tháng 08/2026' => ['2026-08-01 00:00:00', '2026-08-31 23:59:59'],
    'Tháng 07/2026' => ['2026-07-01 00:00:00', '2026-07-31 23:59:59'],
    'Năm 2026 (YTD)' => ['2026-01-01 00:00:00', '2026-12-31 23:59:59']
];

$all_passed = true;
$passed_count = 0;
$total_count = count($test_periods);

foreach ($test_periods as $label => $range) {
    $start = $range[0];
    $end = $range[1];

    $kpi_billing = calculate_billing_kpis($db, $start, $end);
    $kpi_daily = calculate_daily_revenue_kpis($db, $start, $end);
    $kpi_dash = calculate_dashboard_revenue($db, $start, $end);

    $diff_billing_daily = abs($kpi_billing['grand_total'] - $kpi_daily['grand_total']);
    $diff_billing_dash = abs($kpi_billing['grand_total'] - $kpi_dash);

    $is_billing_daily_match = ($diff_billing_daily < 0.01);
    $is_billing_dash_match = ($diff_billing_dash < 0.01);

    echo "--- KIỂM TRA: $label ---\n";
    echo "  [Billing]       Tổng Thực Thu: " . number_format($kpi_billing['grand_total'], 0, ',', '.') . " đ\n";
    echo "                  (TM: " . number_format($kpi_billing['cash'], 0, ',', '.') . " | CK_CN: " . number_format($kpi_billing['ck_personal'], 0, ',', '.') . " | TK_CT: " . number_format($kpi_billing['ck_company'], 0, ',', '.') . " | Thẻ: " . number_format($kpi_billing['card'], 0, ',', '.') . ")\n";
    echo "  [Daily Revenue] Tổng Thực Thu: " . number_format($kpi_daily['grand_total'], 0, ',', '.') . " đ\n";
    echo "  [Dashboard]     Tổng Thu Quỹ:  " . number_format($kpi_dash, 0, ',', '.') . " đ\n";

    if ($is_billing_daily_match && $is_billing_dash_match) {
        echo "  => KẾT QUẢ: [PASSED] 100% Khớp tuyệt đối (Sai số: 0 đ)\n\n";
        $passed_count++;
    } else {
        echo "  => KẾT QUẢ: [FAILED] Lệch số liệu!\n";
        if (!$is_billing_daily_match) {
            echo "     - Lệch Billing vs Daily Revenue: " . number_format($diff_billing_daily, 0, ',', '.') . " đ\n";
        }
        if (!$is_billing_dash_match) {
            echo "     - Lệch Billing vs Dashboard: " . number_format($diff_billing_dash, 0, ',', '.') . " đ\n";
        }
        echo "\n";
        $all_passed = false;
    }
}

echo "====================================================================\n";
echo "   TỔNG KẾT KIỂM TOÁN: $passed_count / $total_count KỲ ĐẠT CHUẨN 100%   \n";
echo "====================================================================\n";

if ($all_passed) {
    echo ">>> XÁC NHẬN: TẤT CẢ CÁC MODULE ĐÃ ĐỒNG BỘ 100% SINGLE SOURCE OF TRUTH <<<\n";
    exit(0);
} else {
    echo ">>> CẢNH BÁO: CÒN MODULE BỊ LỆCH SỐ LIỆU <<<\n";
    exit(1);
}
