<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
// modules/reports/index.php — Dashboard Báo cáo Doanh thu Tích hợp
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('view_reports');
$page_title = __('reports') . ' — ' . __('dashboard');
$current_page = 'reports';
require_once '../../templates/header.php';

$db = getDB();

// Determine which tab to show by default
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'monthly';

// ==========================================
// PHẦN 1: BÁO CÁO THỰC THU & CÔNG NỢ (NGÀY) — uses get_date_range() like Lead module
// ==========================================
$daily_period = $_GET['daily_period'] ?? 'today';
$daily_start_date = $_GET['daily_start_date'] ?? '';
$daily_end_date = $_GET['daily_end_date'] ?? '';

$daily_range = get_date_range($daily_period, $daily_start_date, $daily_end_date);
$start_day = $daily_range['start'];
$end_day = $daily_range['end'];
$daily_label = $daily_range['label'];
$daily_is_filtered = !empty($daily_period) && $daily_period !== 'today';
$day_params = [$start_day, $end_day];

// 1. Tổng thu từ Transactions (category = 'package', 'package_debt', 'invoice')
$stmt = $db->prepare("
    SELECT category, SUM(amount) as total
    FROM transactions
    WHERE transaction_date BETWEEN ? AND ? AND type = 'income'
    GROUP BY category
");
$stmt->execute($day_params);
$income_totals = $stmt->fetchAll();

$total_package = 0; // Mua mới + Thu nợ cũ
$total_invoice = 0; // Phiếu lẻ
foreach ($income_totals as $row) {
    if (in_array($row['category'], ['package', 'package_debt'])) {
        $total_package += (float)$row['total'];
    } else {
        $total_invoice += (float)$row['total'];
    }
}
$total_revenue = $total_package + $total_invoice;

// 2. Chi Tiết Nợ Phát Sinh (Invoices có debt_amount > 0)
$stmt = $db->prepare("
    SELECT i.*, p.full_name as patient_name, u.full_name as created_by_name
    FROM invoices i
    JOIN patients p ON i.patient_id = p.id
    LEFT JOIN users u ON i.created_by = u.id
    WHERE i.debt_amount > 0 AND i.created_at BETWEEN ? AND ?
    ORDER BY i.created_at DESC
");
$stmt->execute($day_params);
$debt_invoices = $stmt->fetchAll();

$total_debt = 0;
foreach ($debt_invoices as $di) {
    $total_debt += (float)$di['debt_amount'];
}

// 3. Chi Tiết Lịch sử giao dịch Thu Tiền (transactions)
$stmt = $db->prepare("
    SELECT t.*, u.full_name as creator_name,
           p1.full_name as p1_name, p2.full_name as p2_name,
           p1.id as p1_id, p2.id as p2_id,
           i.invoice_no as inv_no, i.created_at as inv_date
    FROM transactions t
    LEFT JOIN users u ON t.created_by = u.id
    LEFT JOIN patient_packages pp ON t.reference_id = pp.id AND t.category IN ('package', 'package_debt')
    LEFT JOIN patients p1 ON pp.patient_id = p1.id
    LEFT JOIN invoices i ON t.reference_id = i.id AND t.category NOT IN ('package', 'package_debt') AND t.description LIKE 'Phiếu tính tiền%'
    LEFT JOIN patients p2 ON i.patient_id = p2.id
    WHERE t.transaction_date BETWEEN ? AND ? AND t.type = 'income'
    ORDER BY t.transaction_date DESC
");
$stmt->execute($day_params);
$daily_transactions = $stmt->fetchAll();

// Normalize patient name & link
foreach ($daily_transactions as &$tx) {
    if ($tx['p1_name']) {
        $tx['patient_name'] = $tx['p1_name'];
        $tx['patient_link'] = "/modules/sales/manage_shared.php?id=" . $tx['reference_id'];
    } elseif ($tx['p2_name']) {
        $tx['patient_name'] = $tx['p2_name'];
        // Link thẳng đến trang Phiếu Tính Tiền lọc theo ngày của phiếu
        $inv_date_str = date('Y-m-d', strtotime($tx['inv_date']));
        $tx['patient_link'] = "/modules/billing/index.php?period=custom&date=" . $inv_date_str;
    } else {
        $tx['patient_name'] = 'Khách lẻ / Khác';
        $tx['patient_link'] = '#';
    }
}
unset($tx);


// ==========================================
// PHẦN 2: DÒNG TIỀN & LỢI NHUẬN (THÁNG)
// ==========================================
$filter_month = $_GET['month'] ?? date('m');
$filter_year = $_GET['year'] ?? date('Y');
$filter_type = $_GET['type'] ?? '';
$monthly_period = $_GET['monthly_period'] ?? 'month';

// Build date range for monthly tab based on monthly_period
if ($monthly_period === 'quarter') {
    $sel_q = (int)($_GET['sel_quarter'] ?? ceil(date('n') / 3));
    $q_start_month = ($sel_q - 1) * 3 + 1;
    $m_start = sprintf("%04d-%02d-01 00:00:00", $filter_year, $q_start_month);
    $m_end = date('Y-m-t 23:59:59', strtotime(sprintf("%04d-%02d-01", $filter_year, $q_start_month + 2)));
    $monthly_label = "Quý $sel_q ($filter_year)";
    $monthly_where = "transaction_date BETWEEN ? AND ?";
    $monthly_params = [$m_start, $m_end];
} elseif ($monthly_period === 'year') {
    $m_start = sprintf("%04d-01-01 00:00:00", $filter_year);
    $m_end = sprintf("%04d-12-31 23:59:59", $filter_year);
    $monthly_label = "Năm $filter_year";
    $monthly_where = "transaction_date BETWEEN ? AND ?";
    $monthly_params = [$m_start, $m_end];
} elseif ($monthly_period === 'custom') {
    $m_sd = $_GET['m_start_date'] ?? date('Y-m-01');
    $m_ed = $_GET['m_end_date'] ?? date('Y-m-d');
    $m_start = $m_sd . ' 00:00:00';
    $m_end = $m_ed . ' 23:59:59';
    $monthly_label = "Từ " . date('d/m/Y', strtotime($m_sd)) . " đến " . date('d/m/Y', strtotime($m_ed));
    $monthly_where = "transaction_date BETWEEN ? AND ?";
    $monthly_params = [$m_start, $m_end];
} else {
    // Default: month
    $monthly_label = "T$filter_month/$filter_year";
    $monthly_where = "MONTH(transaction_date) = ? AND YEAR(transaction_date) = ?";
    $monthly_params = [$filter_month, $filter_year];
}

// 1. Summary stats
$stmt = $db->prepare("SELECT SUM(amount) FROM transactions WHERE $monthly_where AND type = 'income'");
$stmt->execute($monthly_params);
$income = $stmt->fetchColumn() ?: 0;

$stmt = $db->prepare("SELECT SUM(amount) FROM transactions WHERE $monthly_where AND type = 'expense'");
$stmt->execute($monthly_params);
$expense = $stmt->fetchColumn() ?: 0;

$profit = $income - $expense;

// 2. Monthly trend (12 months) — always per-year
$monthly_data = [];
for ($m = 1; $m <= 12; $m++) {
    $stmt = $db->prepare("SELECT COALESCE(SUM(CASE WHEN type='income' THEN amount END),0) as inc, COALESCE(SUM(CASE WHEN type='expense' THEN amount END),0) as exp FROM transactions WHERE MONTH(transaction_date) = ? AND YEAR(transaction_date) = ?");
    $stmt->execute([$m, $filter_year]);
    $row = $stmt->fetch();
    $monthly_data[] = ['month' => $m, 'income' => (float)$row['inc'], 'expense' => (float)$row['exp']];
}

// 3. Category breakdown
$stmt = $db->prepare("SELECT category, type, SUM(amount) as total FROM transactions WHERE $monthly_where GROUP BY category, type ORDER BY total DESC");
$stmt->execute($monthly_params);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Transactions with pagination
$where_parts = [$monthly_where];
$tx_params = $monthly_params;
if ($filter_type) {
    $where_parts[] = "type = ?";
    $tx_params[] = $filter_type;
}
$where_sql = implode(' AND ', $where_parts);

// Count total
$stmt = $db->prepare("SELECT COUNT(*) FROM transactions t WHERE $where_sql");
$stmt->execute($tx_params);
$total_tx = (int)$stmt->fetchColumn();

$per_page = 30;
$total_pages = max(1, ceil($total_tx / $per_page));
$current_page_num = isset($_GET['page']) ? max(1, min((int)$_GET['page'], $total_pages)) : 1;
$offset = ($current_page_num - 1) * $per_page;

$stmt = $db->prepare("SELECT t.*, u.full_name as creator_name,
           p1.full_name as p1_name, p2.full_name as p2_name,
           p1.id as p1_id, p2.id as p2_id,
           i.invoice_no as inv_no, i.created_at as inv_date
    FROM transactions t 
    LEFT JOIN users u ON t.created_by = u.id 
    LEFT JOIN patient_packages pp ON t.reference_id = pp.id AND t.category IN ('package', 'package_debt')
    LEFT JOIN patients p1 ON pp.patient_id = p1.id
    LEFT JOIN invoices i ON t.reference_id = i.id AND t.category NOT IN ('package', 'package_debt') AND t.description LIKE 'Phiếu tính tiền%'
    LEFT JOIN patients p2 ON i.patient_id = p2.id
    WHERE $where_sql ORDER BY transaction_date DESC LIMIT $per_page OFFSET $offset");
$stmt->execute($tx_params);
$transactions = $stmt->fetchAll();

foreach ($transactions as &$tx) {
    if ($tx['p1_name']) {
        $tx['patient_name'] = $tx['p1_name'];
        $tx['patient_link'] = "/modules/sales/manage_shared.php?id=" . $tx['reference_id'];
    } elseif ($tx['p2_name']) {
        $tx['patient_name'] = $tx['p2_name'];
        $inv_date_str = date('Y-m-d', strtotime($tx['inv_date']));
        $tx['patient_link'] = "/modules/billing/index.php?period=custom&date=" . $inv_date_str;
    } else {
        $tx['patient_name'] = 'Xem chi tiết';
        $tx['patient_link'] = '#';
    }
}
unset($tx);
?>

<style>
    :root {
        --color-primary: #111827;
        --color-accent: #6366f1;
        --color-success: #10b981;
        --color-danger: #ef4444;
        --color-info: #3b82f6;
        --bg-light: #f8fafc;
        --border-color: #e2e8f0;
        --text-main: #1e293b;
        --text-muted: #64748b;
        --shadow-sm: 0 1px 2px 0 rgba(0,0,0,0.05);
        --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
        --radius-lg: 16px;
        --radius-xl: 24px;
    }

    body {
        background-color: #f1f5f9;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }

    /* ACTION BAR (Tabs + Filters) */
    .action-bar {
        display: flex; justify-content: space-between; align-items: center; background: white; padding: 1rem 1.5rem; 
        border-radius: var(--radius-lg); margin-bottom: 2rem; box-shadow: var(--shadow-sm); border: 1px solid var(--border-color);
        flex-wrap: wrap; gap: 1rem;
    }
    
    .tabs-group {
        display: flex; background: var(--bg-light); padding: 0.35rem; border-radius: 12px; border: 1px solid var(--border-color);
    }
    .tab-btn {
        padding: 0.6rem 1.25rem; border-radius: 8px; cursor: pointer; font-weight: 700; font-size: 0.9rem; color: var(--text-muted); 
        transition: all 0.2s ease; display: flex; align-items: center; gap: 0.5rem; border: none; background: transparent;
    }
    .tab-btn:hover { color: var(--text-main); }
    .tab-btn.active {
        background: white; color: var(--color-primary); box-shadow: var(--shadow-sm);
    }

    .filter-group {
        display: flex; gap: 0.75rem; align-items: center;
    }
    .filter-input {
        padding: 0.5rem 1rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-light); 
        font-size: 0.85rem; font-weight: 600; color: var(--text-main); outline: none; transition: border 0.2s;
    }
    .filter-input:focus { border-color: var(--color-accent); background: white; }
    
    .btn-action {
        padding: 0.5rem 1.25rem; border-radius: 8px; font-weight: 700; font-size: 0.85rem; border: none; cursor: pointer;
        display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s; background: var(--color-accent); color: white;
    }
    .btn-action:hover { background: #4f46e5; transform: translateY(-1px); box-shadow: var(--shadow-md); }

    /* TAB CONTENT WRAPPERS */
    .tab-content { display: none; animation: slideUp 0.3s ease-out forwards; }
    .tab-content.active { display: block; }
    @keyframes slideUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }

    /* GRID: 4 COLUMNS METRICS */
    .metrics-grid-4 {
        display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 2rem;
    }
    
    .metric-card {
        background: white; border-radius: var(--radius-lg); padding: 1.5rem; position: relative; overflow: hidden;
        border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); transition: transform 0.2s, box-shadow 0.2s;
        display: flex; flex-direction: column; justify-content: center;
    }
    .metric-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
    
    /* Dark Theme Card for Total Revenue */
    .metric-card.dark {
        background: var(--color-primary); color: white; border: none; box-shadow: var(--shadow-md);
    }
    .metric-card.dark .m-icon { color: rgba(255,255,255,0.2); }
    .metric-card.dark .m-lbl { color: #94a3b8; }
    .metric-card.dark .m-val { color: white; }
    
    .m-lbl { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.4rem; z-index: 1; position: relative; }
    .m-val { font-size: 1.75rem; font-weight: 900; color: var(--text-main); letter-spacing: -0.5px; z-index: 1; position: relative; }
    .m-sub { font-size: 0.75rem; font-weight: 600; color: #94a3b8; margin-top: 0.5rem; z-index: 1; position: relative; }
    
    .m-icon { position: absolute; right: 1rem; top: 1rem; font-size: 2.5rem; opacity: 0.05; z-index: 0; }
    .metric-card.success .m-icon, .metric-card.success .m-lbl i { color: var(--color-success); opacity: 1; font-size: 1rem; }
    .metric-card.success .m-val { color: var(--color-success); }
    
    .metric-card.info .m-icon, .metric-card.info .m-lbl i { color: var(--color-info); opacity: 1; font-size: 1rem; }
    .metric-card.info .m-val { color: var(--color-info); }
    
    .metric-card.danger .m-icon, .metric-card.danger .m-lbl i { color: var(--color-danger); opacity: 1; font-size: 1rem; }
    .metric-card.danger .m-val { color: var(--color-danger); }

    /* GRID: 2 COLUMNS SPLIT */
    .grid-split {
        display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; align-items: start;
    }
    
    .data-panel {
        background: white; border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); overflow: hidden;
    }
    .panel-header {
        padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); background: rgba(248, 250, 252, 0.5); display: flex; align-items: center; justify-content: space-between;
    }
    .panel-title { margin: 0; font-size: 1rem; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem; }
    .panel-badge { background: var(--bg-light); color: var(--text-muted); font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.6rem; border-radius: 50px; border: 1px solid var(--border-color); }

    /* MODERN TABLES */
    .premium-table { width: 100%; border-collapse: collapse; }
    .premium-table th { 
        padding: 0.85rem 1.5rem; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; text-align: left; background: var(--bg-light); border-bottom: 1px solid var(--border-color);
    }
    .premium-table td {
        padding: 1rem 1.5rem; font-size: 0.9rem; border-bottom: 1px solid #f1f5f9; color: var(--text-main);
    }
    .premium-table tbody tr { transition: background 0.15s; }
    .premium-table tbody tr:hover { background: #f8fafc; }
    .premium-table tbody tr:last-child td { border-bottom: none; }
    
    .badge-soft {
        padding: 0.3rem 0.6rem; border-radius: 6px; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.3rem;
    }
    .badge-soft.success { background: #d1fae5; color: #065f46; }
    .badge-soft.info { background: #dbeafe; color: #1e40af; }
    .badge-soft.danger { background: #fee2e2; color: #991b1b; }
    .badge-soft.warning { background: #fef3c7; color: #92400e; }

    /* CHART CONTAINER */
    .chart-container { position: relative; height: 320px; width: 100%; padding: 1.5rem; }

    /* PAGINATION */
    .pagination-bar {
        display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem;
        border-top: 1px solid var(--border-color); background: rgba(248, 250, 252, 0.5);
    }
    .pagination-info { font-size: 0.8rem; font-weight: 600; color: var(--text-muted); }
    .pagination-controls { display: flex; gap: 0.35rem; align-items: center; }
    .pg-btn {
        min-width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center;
        border-radius: 8px; border: 1px solid var(--border-color); background: white; color: var(--text-main);
        font-weight: 700; font-size: 0.85rem; text-decoration: none; cursor: pointer; transition: all 0.15s;
    }
    .pg-btn:hover { background: var(--bg-light); border-color: #cbd5e1; }
    .pg-btn.active { background: var(--color-accent); color: white; border-color: var(--color-accent); }
    .pg-btn.disabled { opacity: 0.4; pointer-events: none; }
    /* Lead-style filter for daily tab */
    .rpt-filter-btn-group { display: flex; gap: 0.4rem; flex-wrap: wrap; align-items: center; }
    .rpt-filter-btn {
        padding: 0.4rem 0.8rem; border-radius: 8px; background: #f1f5f9; color: var(--text-muted);
        font-size: 0.8rem; font-weight: 600; text-decoration: none; transition: background 0.15s, color 0.15s; border: 1px solid transparent; cursor: pointer;
    }
    .rpt-filter-btn:hover { background: #e2e8f0; color: var(--text-main); }
    .rpt-filter-btn.active { background: var(--color-accent); color: white; }
    .rpt-filter-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.025em; margin-bottom: 0; display: inline; color: var(--text-muted); font-weight: 700; margin-right: 0.25rem; }
    .rpt-custom-range-box { display: flex; gap: 0.5rem; align-items: center; background: var(--bg-light); padding: 0.4rem 0.75rem; border-radius: 10px; border: 1px solid var(--border-color); }
    .rpt-custom-range-input { border: none; background: transparent; font-size: 0.8rem; color: var(--text-main); width: 110px; outline: none; }
</style>


<!-- ========================================================== -->
<!-- ACTION BAR (TABS + FILTERS) -->
<!-- ========================================================== -->
<div class="action-bar">
    <div class="tabs-group">
        <button type="button" class="tab-btn <?php echo $active_tab === 'monthly' ? 'active' : ''; ?>" onclick="switchTab('monthly')">
            <i class="fas fa-chart-pie"></i> Doanh thu Tháng
        </button>
        <button type="button" class="tab-btn <?php echo $active_tab === 'daily' ? 'active' : ''; ?>" onclick="switchTab('daily')">
            <i class="fas fa-calendar-day"></i> Thực thu Ngày
        </button>
    </div>

    <!-- FILTER FOR DAILY TAB (Lead-style) -->
    <div id="filter-daily" style="display: <?php echo $active_tab === 'daily' ? 'block' : 'none'; ?>; width: 100%;">
        <form method="GET" action="index.php" id="rptDailyFilterForm">
            <input type="hidden" name="tab" value="daily">
            <input type="hidden" name="month" value="<?php echo htmlspecialchars($filter_month); ?>">
            <input type="hidden" name="year" value="<?php echo htmlspecialchars($filter_year); ?>">
            <input type="hidden" name="daily_period" id="rptDailyPeriodInput" value="<?php echo e($daily_period); ?>">
            <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                <div class="rpt-filter-btn-group">
                    <span class="rpt-filter-label">THỜI GIAN:</span>
                    <a href="#" class="rpt-filter-btn <?php echo $daily_period == 'today' ? 'active' : ''; ?>" onclick="setRptDailyPeriod(event, 'today')">Hôm nay</a>
                    <a href="#" class="rpt-filter-btn <?php echo $daily_period == 'week' ? 'active' : ''; ?>" onclick="setRptDailyPeriod(event, 'week')">Tuần</a>
                    <a href="#" class="rpt-filter-btn <?php echo $daily_period == 'month' ? 'active' : ''; ?>" onclick="setRptDailyPeriod(event, 'month')">Tháng</a>
                    <a href="#" class="rpt-filter-btn <?php echo $daily_period == 'quarter' ? 'active' : ''; ?>" onclick="setRptDailyPeriod(event, 'quarter')">Quý</a>
                    <a href="#" class="rpt-filter-btn <?php echo $daily_period == 'year' ? 'active' : ''; ?>" onclick="setRptDailyPeriod(event, 'year')">Năm</a>
                    <a href="#" class="rpt-filter-btn <?php echo $daily_period == 'custom' ? 'active' : ''; ?>" onclick="setRptDailyPeriod(event, 'custom')">Tùy chọn</a>
                </div>

                <?php if($daily_period === 'month' || $daily_period === 'quarter' || $daily_period === 'year'): ?>
                    <div style="display: flex; gap: 0.5rem; align-items: center; background: var(--bg-light); padding: 0.3rem 0.5rem; border-radius: 10px; border: 1px solid var(--border-color);">
                        <?php if($daily_period === 'month'): ?>
                            <select name="sel_month" class="rpt-custom-range-input" style="width: auto; padding: 0.2rem 0.5rem;" onchange="this.form.submit()">
                                <?php for($m=1; $m<=12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo (isset($_GET['sel_month']) && $_GET['sel_month'] == $m) || (!isset($_GET['sel_month']) && $m == date('n')) ? 'selected' : ''; ?>>Tháng <?php echo $m; ?></option>
                                <?php endfor; ?>
                            </select>
                        <?php endif; ?>
                        <?php if($daily_period === 'quarter'): ?>
                            <select name="sel_quarter" class="rpt-custom-range-input" style="width: auto; padding: 0.2rem 0.5rem;" onchange="this.form.submit()">
                                <?php for($q=1; $q<=4; $q++): ?>
                                    <option value="<?php echo $q; ?>" <?php echo (isset($_GET['sel_quarter']) && $_GET['sel_quarter'] == $q) || (!isset($_GET['sel_quarter']) && $q == ceil(date('n')/3)) ? 'selected' : ''; ?>>Quý <?php echo $q; ?></option>
                                <?php endfor; ?>
                            </select>
                        <?php endif; ?>
                        <select name="sel_year" class="rpt-custom-range-input" style="width: auto; padding: 0.2rem 0.5rem;" onchange="this.form.submit()">
                            <?php for($y=date('Y')-2; $y<=date('Y')+1; $y++): ?>
                                <option value="<?php echo $y; ?>" <?php echo (isset($_GET['sel_year']) && $_GET['sel_year'] == $y) || (!isset($_GET['sel_year']) && $y == date('Y')) ? 'selected' : ''; ?>>Năm <?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div id="rptDailyCustomDates" style="display: <?php echo $daily_period == 'custom' ? 'flex' : 'none'; ?>; gap: 0.5rem; align-items: center;">
                    <div class="rpt-custom-range-box">
                        <input type="date" name="daily_start_date" class="rpt-custom-range-input" value="<?php echo e($daily_start_date); ?>">
                        <span style="color: #94a3b8; font-size: 0.8rem;">→</span>
                        <input type="date" name="daily_end_date" class="rpt-custom-range-input" value="<?php echo e($daily_end_date); ?>">
                    </div>
                    <button type="submit" class="btn-action" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;">Áp dụng</button>
                </div>

                <?php if ($daily_is_filtered): ?>
                    <a href="index.php?tab=daily" style="color: #ef4444; font-size: 0.75rem; font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 0.25rem; background: #fff1f2; padding: 0.4rem 0.8rem; border-radius: 8px; border: 1px solid #fecaca; margin-left: auto;">
                        <i class="fas fa-trash-alt"></i> Xóa lọc
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- FILTER FOR MONTHLY TAB (Lead-style) -->
    <div id="filter-monthly" style="display: <?php echo $active_tab === 'monthly' ? 'block' : 'none'; ?>; width: 100%;">
        <form method="GET" action="index.php" id="rptMonthlyFilterForm">
            <input type="hidden" name="tab" value="monthly">
            <input type="hidden" name="daily_period" value="<?php echo e($daily_period); ?>">
            <input type="hidden" name="daily_start_date" value="<?php echo e($daily_start_date); ?>">
            <input type="hidden" name="daily_end_date" value="<?php echo e($daily_end_date); ?>">
            <input type="hidden" name="monthly_period" id="rptMonthlyPeriodInput" value="<?php echo e($_GET['monthly_period'] ?? 'month'); ?>">
            <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                <div class="rpt-filter-btn-group">
                    <span class="rpt-filter-label">THỜI GIAN:</span>
                    <?php $mp = $_GET['monthly_period'] ?? 'month'; ?>
                    <a href="#" class="rpt-filter-btn <?php echo $mp == 'month' ? 'active' : ''; ?>" onclick="setRptMonthlyPeriod(event, 'month')">Tháng</a>
                    <a href="#" class="rpt-filter-btn <?php echo $mp == 'quarter' ? 'active' : ''; ?>" onclick="setRptMonthlyPeriod(event, 'quarter')">Quý</a>
                    <a href="#" class="rpt-filter-btn <?php echo $mp == 'year' ? 'active' : ''; ?>" onclick="setRptMonthlyPeriod(event, 'year')">Năm</a>
                    <a href="#" class="rpt-filter-btn <?php echo $mp == 'custom' ? 'active' : ''; ?>" onclick="setRptMonthlyPeriod(event, 'custom')">Tùy chọn</a>
                </div>

                <div style="display: flex; gap: 0.5rem; align-items: center; background: var(--bg-light); padding: 0.3rem 0.5rem; border-radius: 10px; border: 1px solid var(--border-color);">
                    <?php if($mp === 'month' || $mp === '' ): ?>
                        <select name="month" class="rpt-custom-range-input" style="width: auto; padding: 0.2rem 0.5rem;" onchange="this.form.submit()">
                            <?php for($m=1; $m<=12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $m == $filter_month ? 'selected' : ''; ?>>Tháng <?php echo $m; ?></option>
                            <?php endfor; ?>
                        </select>
                    <?php endif; ?>

                    <?php if($mp === 'quarter'): ?>
                        <?php $sel_q = $_GET['sel_quarter'] ?? ceil(date('n')/3); ?>
                        <select name="sel_quarter" class="rpt-custom-range-input" style="width: auto; padding: 0.2rem 0.5rem;" onchange="this.form.submit()">
                            <?php for($q=1; $q<=4; $q++): ?>
                                <option value="<?php echo $q; ?>" <?php echo $sel_q == $q ? 'selected' : ''; ?>>Quý <?php echo $q; ?></option>
                            <?php endfor; ?>
                        </select>
                    <?php endif; ?>

                    <?php if($mp !== 'custom'): ?>
                        <select name="year" class="rpt-custom-range-input" style="width: auto; padding: 0.2rem 0.5rem;" onchange="this.form.submit()">
                            <?php for($y=date('Y')-2; $y<=date('Y')+1; $y++): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y == $filter_year ? 'selected' : ''; ?>>Năm <?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    <?php endif; ?>
                </div>

                <div id="rptMonthlyCustomDates" style="display: <?php echo $mp == 'custom' ? 'flex' : 'none'; ?>; gap: 0.5rem; align-items: center;">
                    <div class="rpt-custom-range-box">
                        <input type="date" name="m_start_date" class="rpt-custom-range-input" value="<?php echo e($_GET['m_start_date'] ?? ''); ?>">
                        <span style="color: #94a3b8; font-size: 0.8rem;">→</span>
                        <input type="date" name="m_end_date" class="rpt-custom-range-input" value="<?php echo e($_GET['m_end_date'] ?? ''); ?>">
                    </div>
                    <button type="submit" class="btn-action" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;">Áp dụng</button>
                </div>

                <select name="type" class="rpt-custom-range-input" style="width: auto; padding: 0.3rem 0.6rem; background: var(--bg-light); border: 1px solid var(--border-color); border-radius: 8px;" onchange="this.form.submit()">
                    <option value="">Tất cả (Thu/Chi)</option>
                    <option value="income" <?php echo $filter_type === 'income' ? 'selected' : ''; ?>>Chỉ Thu</option>
                    <option value="expense" <?php echo $filter_type === 'expense' ? 'selected' : ''; ?>>Chỉ Chi</option>
                </select>
            </div>
        </form>
    </div>
</div>


<!-- ========================================================== -->
<!-- TAB 2: BÁO CÁO THỰC THU (NGÀY) -->
<!-- ========================================================== -->
<div id="tab-daily" class="tab-content <?php echo $active_tab === 'daily' ? 'active' : ''; ?>">
    
    <!-- 4 METRIC CARDS -->
    <div class="metrics-grid-4">
        <div class="metric-card dark">
            <i class="fas fa-coins m-icon" style="opacity: 0.15;"></i>
            <div class="m-lbl">TỔNG THỰC THU</div>
            <div class="m-val"><?php echo format_money($total_revenue); ?></div>
            <div class="m-sub"><?php echo e($daily_label); ?></div>
        </div>
        
        <div class="metric-card success">
            <div class="m-lbl"><i class="fas fa-box-open"></i> THU BÁN GÓI</div>
            <div class="m-val"><?php echo format_money($total_package); ?></div>
            <div class="m-sub">Mua mới + trả nợ cũ</div>
        </div>
        
        <div class="metric-card info">
            <div class="m-lbl"><i class="fas fa-file-invoice"></i> THU PHIẾU LẺ</div>
            <div class="m-val"><?php echo format_money($total_invoice); ?></div>
            <div class="m-sub">Phiếu tính tiền lẻ</div>
        </div>
        
        <div class="metric-card danger">
            <div class="m-lbl"><i class="fas fa-file-invoice-dollar"></i> NỢ MỚI PHÁT SINH</div>
            <div class="m-val"><?php echo format_money($total_debt); ?></div>
            <div class="m-sub">Chưa thanh toán hết</div>
        </div>
    </div>

    <!-- 2 SPLIT TABLES -->
    <div class="grid-split">
        <!-- Bảng Nợ Mới -->
        <div class="data-panel">
            <div class="panel-header">
                <h4 class="panel-title"><i class="fas fa-exclamation-circle" style="color: var(--color-danger);"></i> Khách nợ mới phát sinh</h4>
                <span class="panel-badge"><?php echo count($debt_invoices); ?> phiếu</span>
            </div>
            <?php if (empty($debt_invoices)): ?>
                <div style="text-align: center; color: var(--text-muted); font-size: 0.85rem; padding: 2.5rem 1rem;">Không có dữ liệu nợ mới.</div>
            <?php else: ?>
                <div style="max-height: 500px; overflow-y: auto;">
                <table class="premium-table">
                    <thead>
                        <tr>
                            <th>KHÁCH HÀNG</th>
                            <th style="text-align: right;">SỐ TIỀN NỢ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($debt_invoices as $di): ?>
                        <tr>
                            <td>
                                <a href="/modules/patients/view.php?id=<?php echo $di['patient_id']; ?>" style="font-weight: 800; color: var(--text-main); text-decoration: none;"><?php echo e($di['patient_name']); ?></a>
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.3rem;"><i class="fas fa-receipt"></i> <?php echo e($di['invoice_no']); ?></div>
                            </td>
                            <td style="text-align: right; font-weight: 800; color: var(--color-danger);">+ <?php echo format_money($di['debt_amount']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Bảng Thu Vào -->
        <div class="data-panel">
            <div class="panel-header">
                <h4 class="panel-title"><i class="fas fa-hand-holding-usd" style="color: var(--color-success);"></i> Danh sách Khoản thu</h4>
                <span class="panel-badge"><?php echo count($daily_transactions); ?> khoản</span>
            </div>
            <?php if (empty($daily_transactions)): ?>
                <div style="text-align: center; color: var(--text-muted); font-size: 0.85rem; padding: 2.5rem 1rem;">Không có dữ liệu thu.</div>
            <?php else: ?>
                <div style="max-height: 500px; overflow-y: auto;">
                <table class="premium-table">
                    <thead>
                        <tr>
                            <th>THỜI GIAN</th>
                            <th>KHÁCH HÀNG / GIAO DỊCH</th>
                            <th style="text-align: right;">THU VÀO</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daily_transactions as $tx): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 700; color: var(--text-main); font-size: 0.85rem;"><?php echo date('H:i', strtotime($tx['transaction_date'])); ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo date('d/m/Y', strtotime($tx['transaction_date'])); ?></div>
                            </td>
                            <td>
                                <a href="<?php echo $tx['patient_link']; ?>" style="font-weight: 800; color: var(--text-main); text-decoration: none;"><?php echo e($tx['patient_name']); ?></a>
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.3rem;">
                                    <?php echo e($tx['description'] ?? '—'); ?>
                                </div>
                            </td>
                            <td style="text-align: right; font-weight: 800; color: var(--color-success);">+ <?php echo format_money($tx['amount']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>


<!-- ========================================================== -->
<!-- TAB 1: BÁO CÁO DÒNG TIỀN (THÁNG) -->
<!-- ========================================================== -->
<div id="tab-monthly" class="tab-content <?php echo $active_tab === 'monthly' ? 'active' : ''; ?>">

    <div class="metrics-grid-4" style="grid-template-columns: repeat(3, 1fr);">
        <div class="metric-card success">
            <div class="m-lbl"><i class="fas fa-arrow-up"></i> TỔNG THU (<?php echo e($monthly_label); ?>)</div>
            <div class="m-val"><?php echo format_money($income); ?></div>
        </div>
        <div class="metric-card danger">
            <div class="m-lbl"><i class="fas fa-arrow-down"></i> TỔNG CHI (<?php echo e($monthly_label); ?>)</div>
            <div class="m-val"><?php echo format_money($expense); ?></div>
        </div>
        <div class="metric-card dark" style="background: <?php echo $profit >= 0 ? 'var(--color-primary)' : 'var(--color-danger)'; ?>;">
            <div class="m-lbl"><i class="fas fa-chart-line" style="color: rgba(255,255,255,0.7);"></i> LỢI NHUẬN <?php echo e($monthly_label); ?></div>
            <div class="m-val"><?php echo format_money($profit); ?></div>
        </div>
    </div>

    <div class="grid-split" style="grid-template-columns: 2fr 1fr;">
        <!-- Biểu đồ -->
        <div class="data-panel">
            <div class="panel-header">
                <h4 class="panel-title"><i class="fas fa-chart-bar" style="color: var(--color-accent);"></i> Xu hướng Thu/Chi (<?php echo $filter_year; ?>)</h4>
            </div>
            <div class="chart-container">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
        
        <!-- Cơ cấu thu chi -->
        <div class="data-panel">
            <div class="panel-header">
                <h4 class="panel-title"><i class="fas fa-tags" style="color: #f59e0b;"></i> Hạng mục <?php echo e($monthly_label); ?></h4>
            </div>
            <div style="padding: 1.5rem;">
                <?php if (empty($categories)): ?>
                <div style="text-align: center; color: var(--text-muted); font-size: 0.85rem; padding: 1rem;">Chưa có hạng mục nào.</div>
                <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <?php foreach ($categories as $cat): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; border-radius: 10px; background: <?php echo $cat['type'] === 'income' ? '#f0fdf4' : '#fef2f2'; ?>; border: 1px solid <?php echo $cat['type'] === 'income' ? '#bbf7d0' : '#fecaca'; ?>;">
                        <span style="font-size: 0.85rem; font-weight: 700; color: <?php echo $cat['type'] === 'income' ? '#166534' : '#991b1b'; ?>;">
                            <i class="fas <?php echo $cat['type'] === 'income' ? 'fa-plus' : 'fa-minus'; ?>" style="opacity: 0.5; margin-right: 0.25rem;"></i> <?php echo e(ucfirst($cat['category'])); ?>
                        </span>
                        <strong style="font-size: 0.95rem; color: <?php echo $cat['type'] === 'income' ? '#166534' : '#991b1b'; ?>;"><?php echo format_money($cat['total']); ?></strong>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bảng Giao Dịch -->
    <div class="data-panel">
        <div class="panel-header">
            <h4 class="panel-title"><i class="fas fa-list-ul" style="color: var(--color-accent);"></i> Lịch sử Giao dịch</h4>
            <span class="panel-badge"><?php echo $total_tx; ?> giao dịch</span>
        </div>
        <?php if (empty($transactions)): ?>
        <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
            <i class="fas fa-receipt" style="font-size: 2.5rem; opacity: 0.3; margin-bottom: 1rem;"></i>
            <p style="font-weight: 600; font-size: 0.9rem;">Không có giao dịch nào trong kỳ.</p>
        </div>
        <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="premium-table">
                <thead>
                    <tr>
                        <th>THỜI GIAN</th>
                        <th>LOẠI</th>
                        <th>HẠNG MỤC</th>
                        <th style="text-align: right;">SỐ TIỀN</th>
                        <th>GHI CHÚ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $tx): ?>
                    <tr>
                        <td>
                            <div style="font-weight: 700; color: var(--text-main); font-size: 0.85rem;"><?php echo date('H:i', strtotime($tx['transaction_date'])); ?></div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo date('d/m/Y', strtotime($tx['transaction_date'])); ?></div>
                        </td>
                        <td>
                            <span class="badge-soft <?php echo $tx['type'] === 'income' ? 'success' : 'danger'; ?>">
                                <i class="fas <?php echo $tx['type'] === 'income' ? 'fa-arrow-up' : 'fa-arrow-down'; ?>"></i>
                                <?php echo $tx['type'] === 'income' ? 'Thu' : 'Chi'; ?>
                            </span>
                        </td>
                        <td style="font-weight: 700; font-size: 0.85rem; color: var(--text-main);"><?php echo e(ucfirst($tx['category'])); ?></td>
                        <td style="font-weight: 800; font-size: 1rem; color: <?php echo $tx['type'] === 'income' ? 'var(--color-success)' : 'var(--color-danger)'; ?>; text-align: right;">
                            <?php echo ($tx['type'] === 'income' ? '+' : '-') . format_money($tx['amount']); ?>
                        </td>
                        <td style="font-size: 0.85rem; color: var(--text-muted); max-width: 250px;">
                            <?php if ($tx['patient_link'] !== '#'): ?>
                                <a href="<?php echo $tx['patient_link']; ?>" style="font-weight: 800; color: var(--color-accent); text-decoration: none; display: block; margin-bottom: 0.3rem;"><i class="fas fa-external-link-alt"></i> Đi đến Giao dịch</a>
                            <?php endif; ?>
                            <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo e($tx['description']); ?>"><?php echo e($tx['description'] ?? '—'); ?></div>
                            <?php if($tx['creator_name']) echo '<div style="font-size:0.7rem; color:#94a3b8; margin-top:3px;"><i class="fas fa-user-edit"></i> ' . e($tx['creator_name']) . '</div>'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <?php if ($total_tx > $per_page): ?>
        <div class="pagination-bar">
            <div class="pagination-info">
                Hiển thị <?php echo $offset + 1; ?>–<?php echo min($offset + $per_page, $total_tx); ?> / <?php echo $total_tx; ?> giao dịch
            </div>
            <div class="pagination-controls">
                <?php
                // Build base URL preserving all filters
                $pg_params = $_GET;
                unset($pg_params['page']);
                $pg_base = 'index.php?' . http_build_query($pg_params) . '&page=';
                ?>
                <a href="<?php echo $pg_base . max(1, $current_page_num - 1); ?>" class="pg-btn <?php echo $current_page_num <= 1 ? 'disabled' : ''; ?>"><i class="fas fa-chevron-left"></i></a>
                <?php
                // Show smart page range
                $start_pg = max(1, $current_page_num - 2);
                $end_pg = min($total_pages, $current_page_num + 2);
                if ($start_pg > 1) { echo '<a href="' . $pg_base . '1" class="pg-btn">1</a>'; if ($start_pg > 2) echo '<span style="color:#94a3b8;font-size:0.8rem;">…</span>'; }
                for ($p = $start_pg; $p <= $end_pg; $p++) {
                    $cls = $p === $current_page_num ? 'pg-btn active' : 'pg-btn';
                    echo '<a href="' . $pg_base . $p . '" class="' . $cls . '">' . $p . '</a>';
                }
                if ($end_pg < $total_pages) { if ($end_pg < $total_pages - 1) echo '<span style="color:#94a3b8;font-size:0.8rem;">…</span>'; echo '<a href="' . $pg_base . $total_pages . '" class="pg-btn">' . $total_pages . '</a>'; }
                ?>
                <a href="<?php echo $pg_base . min($total_pages, $current_page_num + 1); ?>" class="pg-btn <?php echo $current_page_num >= $total_pages ? 'disabled' : ''; ?>"><i class="fas fa-chevron-right"></i></a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>


<script>
function switchTab(tabId) {
    // 1. Update Tab styling
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.querySelector('.tab-btn[onclick="switchTab(\'' + tabId + '\')"]').classList.add('active');
    
    // 2. Update Content visibility
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + tabId).classList.add('active');

    // 3. Update Action bar filters
    document.getElementById('filter-monthly').style.display = (tabId === 'monthly') ? 'block' : 'none';
    document.getElementById('filter-daily').style.display = (tabId === 'daily') ? 'block' : 'none';
    
    // 4. Silently update URL
    if (history.pushState) {
        var searchParams = new URLSearchParams(window.location.search);
        searchParams.set("tab", tabId);
        var newurl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?' + searchParams.toString();
        window.history.pushState({path:newurl}, '', newurl);
    }
}

function setRptDailyPeriod(evt, p) {
    if (evt && evt.preventDefault) evt.preventDefault();
    document.getElementById('rptDailyPeriodInput').value = p;
    if (p !== 'custom') {
        var startEl = document.querySelector('#rptDailyFilterForm input[name="daily_start_date"]');
        var endEl = document.querySelector('#rptDailyFilterForm input[name="daily_end_date"]');
        if (startEl) startEl.value = '';
        if (endEl) endEl.value = '';
        document.getElementById('rptDailyFilterForm').submit();
    } else {
        document.getElementById('rptDailyCustomDates').style.display = 'flex';
        document.querySelectorAll('#filter-daily .rpt-filter-btn').forEach(function(btn) { btn.classList.remove('active'); });
        if (evt && evt.target) evt.target.classList.add('active');
    }
}

function setRptMonthlyPeriod(evt, p) {
    if (evt && evt.preventDefault) evt.preventDefault();
    document.getElementById('rptMonthlyPeriodInput').value = p;
    if (p !== 'custom') {
        var startEl = document.querySelector('#rptMonthlyFilterForm input[name="m_start_date"]');
        var endEl = document.querySelector('#rptMonthlyFilterForm input[name="m_end_date"]');
        if (startEl) startEl.value = '';
        if (endEl) endEl.value = '';
        document.getElementById('rptMonthlyFilterForm').submit();
    } else {
        document.getElementById('rptMonthlyCustomDates').style.display = 'flex';
        document.querySelectorAll('#filter-monthly .rpt-filter-btn').forEach(function(btn) { btn.classList.remove('active'); });
        if (evt && evt.target) evt.target.classList.add('active');
    }
}

// Render chart for monthly tab
var ctx = document.getElementById('revenueChart').getContext('2d');
var monthlyData = <?php echo json_encode($monthly_data); ?>;

new Chart(ctx, {
    type: 'bar',
    data: {
        labels: monthlyData.map(function(d) { return 'T' + d.month; }),
        datasets: [
            {
                label: 'Thu (+)',
                data: monthlyData.map(function(d) { return d.income; }),
                backgroundColor: '#10b981',
                borderRadius: 4,
                barPercentage: 0.6
            },
            {
                label: 'Chi (-)',
                data: monthlyData.map(function(d) { return d.expense; }),
                backgroundColor: '#ef4444',
                borderRadius: 4,
                barPercentage: 0.6
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 8, font: { weight: '700', family: 'Inter, sans-serif' } } }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    font: { weight: '600', size: 11 }, color: '#64748b',
                    callback: function(value) {
                        return value >= 1000000 ? (value / 1000000).toFixed(1) + 'tr' : value >= 1000 ? (value / 1000).toFixed(0) + 'k' : value;
                    }
                },
                grid: { color: 'rgba(0,0,0,0.03)', drawBorder: false }
            },
            x: { 
                grid: { display: false },
                ticks: { font: { weight: '600', size: 11 }, color: '#64748b' }
            }
        }
    }
});
</script>

<?php require_once '../../templates/footer.php'; ?>
