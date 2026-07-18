<?php
// modules/billing/index.php — Danh sách Phiếu Tính Tiền
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('view_billing');

$page_title = __('billing.index.page_title');
$current_page = 'billing';
require_once '../../templates/header.php';

$db = getDB();

// Date filter — uses centralized get_date_range() like Lead module
$period = $_GET['period'] ?? 'today';
$start_date_filter = $_GET['start_date'] ?? '';
$end_date_filter = $_GET['end_date'] ?? '';

$range = get_date_range($period, $start_date_filter, $end_date_filter);
$start = $range['start'];
$end = $range['end'];
$label = $range['label'];
$is_filtered = !empty($period) && $period !== 'today';

// Stats (Phiếu tính tiền)
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_count,
        COALESCE(SUM(total_amount), 0) as total_revenue,
        COALESCE(SUM(cash_amount), 0) as total_cash,
        COALESCE(SUM(transfer_personal_amount), 0) as total_transfer_personal,
        COALESCE(SUM(transfer_company_amount), 0) as total_transfer_company,
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

// Tổng thực thu = Phiếu lẻ + Bán gói
$grand_total = (float)$stats['total_revenue'] + $total_package_revenue;

// Invoices list
$stmt = $db->prepare("
    SELECT i.*, p.full_name as patient_name, p.phone as patient_phone, u.full_name as cashier_name
    FROM invoices i
    JOIN patients p ON i.patient_id = p.id
    JOIN users u ON i.created_by = u.id
    WHERE i.created_at BETWEEN ? AND ?
    ORDER BY i.created_at DESC
");
$stmt->execute([$start, $end]);
$invoices = $stmt->fetchAll();

// Patients for popup
$patients = $db->query("SELECT id, full_name, phone FROM patients ORDER BY full_name")->fetchAll();
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
.bill-outer { max-width: 1400px; width: 100%; }

.kpi-row { display: grid; grid-template-columns: repeat(7, 1fr); gap: 1.25rem; margin-bottom: 2rem; overflow-x: auto; padding-bottom: 0.5rem; }
@media (max-width: 1200px) { .kpi-row { grid-template-columns: repeat(4, 1fr); } }
@media (max-width: 900px) { .kpi-row { grid-template-columns: repeat(2, 1fr); } }
.kpi-card {
    padding: 1.5rem; border-radius: 18px; color: white; position: relative; overflow: hidden;
    box-shadow: 0 8px 20px -5px rgba(0,0,0,0.1);
}
.kpi-card .kpi-icon { position: absolute; right: -8px; bottom: -8px; font-size: 3.5rem; opacity: 0.15; }
.kpi-card .kpi-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; opacity: 0.85; letter-spacing: 0.5px; }
.kpi-card .kpi-val { font-size: 1.6rem; font-weight: 800; margin-top: 0.3rem; }

.inv-table { width: 100%; border-collapse: collapse; }
.inv-table thead th { padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; }
.inv-table tbody td { padding: 0.75rem 1rem; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; }
.inv-table tbody tr:hover { background: #fafbfc; }
.status-badge { font-size: 0.7rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 6px; }

.select2-container--default .select2-selection--single {
    height: 42px; border: 1px solid #e2e8f0; border-radius: 10px;
    display: flex; align-items: center; padding: 0 0.75rem;
    font-size: 0.9rem; font-weight: 500;
}
</style>

<div class="bill-outer">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
        <div>
            <div style="font-size:0.7rem; font-weight:800; letter-spacing:1px; color:#94a3b8; text-transform:uppercase;"><?php echo __('billing.index.breadcrumb'); ?></div>
            <h1 style="font-size:1.8rem; font-weight:800; color:#0f172a; margin:0.25rem 0;">📄 <?php echo __('billing.index.header_prefix'); ?><?php echo $label; ?></h1>
        </div>
        <button onclick="openCreateNew()" class="btn btn-primary" style="border-radius:14px; padding:0.75rem 1.5rem; font-weight:800; font-size:0.9rem; background:linear-gradient(135deg,#4f46e5,#6366f1); border:none; box-shadow:0 4px 12px rgba(99,102,241,0.3);">
            <i class="fas fa-plus-circle"></i> <?php echo __('billing.index.create_btn'); ?>
        </button>
    </div>

    <!-- Filter (Lead-style) -->
    <style>
    .bill-filter-card { padding: 1rem !important; margin-bottom: 1.5rem !important; border-radius: 16px !important; background: white; box-shadow: 0 4px 20px rgba(0,0,0,0.03); }
    .bill-filter-btn-group { display: flex; gap: 0.4rem; flex-wrap: wrap; align-items: center; }
    .bill-filter-btn {
        padding: 0.4rem 0.8rem; border-radius: 8px; background: #f1f5f9; color: #64748b;
        font-size: 0.8rem; font-weight: 600; text-decoration: none; transition: background 0.15s, color 0.15s; border: 1px solid transparent; cursor: pointer;
    }
    .bill-filter-btn:hover { background: #e2e8f0; color: #1e293b; }
    .bill-filter-btn.active { background: var(--primary); color: white; }
    .bill-filter-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.025em; margin-bottom: 0; display: inline; color: #64748b; font-weight: 700; margin-right: 0.25rem; }
    .bill-custom-range-box { display: flex; gap: 0.5rem; align-items: center; background: #f8fafc; padding: 0.4rem 0.75rem; border-radius: 10px; border: 1px solid #e2e8f0; }
    .bill-custom-range-input { border: none; background: transparent; font-size: 0.8rem; color: #1e293b; width: 110px; outline: none; }
    </style>
    <div class="bill-filter-card">
        <form method="GET" id="billingFilterForm">
            <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                <div class="bill-filter-btn-group">
                    <span class="bill-filter-label"><?php echo __('leads.index.filter_time') ?: 'THỜI GIAN:'; ?></span>
                    <input type="hidden" name="period" id="billPeriodInput" value="<?php echo e($period); ?>">
                    <a href="#" class="bill-filter-btn <?php echo $period == 'today' ? 'active' : ''; ?>" onclick="setBillPeriod(event, 'today')"><?php echo __('filter.today') ?: 'Hôm nay'; ?></a>
                    <a href="#" class="bill-filter-btn <?php echo $period == 'week' ? 'active' : ''; ?>" onclick="setBillPeriod(event, 'week')"><?php echo __('filter.week') ?: 'Tuần'; ?></a>
                    <a href="#" class="bill-filter-btn <?php echo $period == 'month' ? 'active' : ''; ?>" onclick="setBillPeriod(event, 'month')"><?php echo __('filter.month') ?: 'Tháng'; ?></a>
                    <a href="#" class="bill-filter-btn <?php echo $period == 'quarter' ? 'active' : ''; ?>" onclick="setBillPeriod(event, 'quarter')"><?php echo __('filter.quarter') ?: 'Quý'; ?></a>
                    <a href="#" class="bill-filter-btn <?php echo $period == 'year' ? 'active' : ''; ?>" onclick="setBillPeriod(event, 'year')"><?php echo __('filter.year') ?: 'Năm'; ?></a>
                    <a href="#" class="bill-filter-btn <?php echo $period == 'custom' ? 'active' : ''; ?>" onclick="setBillPeriod(event, 'custom')"><?php echo __('filter.custom') ?: 'Tùy chọn'; ?></a>
                </div>

                <?php if($period === 'month' || $period === 'quarter' || $period === 'year'): ?>
                    <div style="display: flex; gap: 0.5rem; align-items: center; background: #f8fafc; padding: 0.3rem 0.5rem; border-radius: 10px; border: 1px solid #e2e8f0;">
                        <?php if($period === 'month'): ?>
                            <select name="sel_month" class="bill-custom-range-input" style="width: auto; padding: 0.2rem 0.5rem;" onchange="this.form.submit()">
                                <?php for($m=1; $m<=12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo (isset($_GET['sel_month']) && $_GET['sel_month'] == $m) || (!isset($_GET['sel_month']) && $m == date('n')) ? 'selected' : ''; ?>><?php echo (__('common.month_prefix') ?: 'Tháng ') . $m; ?></option>
                                <?php endfor; ?>
                            </select>
                        <?php endif; ?>
                        
                        <?php if($period === 'quarter'): ?>
                            <select name="sel_quarter" class="bill-custom-range-input" style="width: auto; padding: 0.2rem 0.5rem;" onchange="this.form.submit()">
                                <?php for($q=1; $q<=4; $q++): ?>
                                    <option value="<?php echo $q; ?>" <?php echo (isset($_GET['sel_quarter']) && $_GET['sel_quarter'] == $q) || (!isset($_GET['sel_quarter']) && $q == ceil(date('n')/3)) ? 'selected' : ''; ?>><?php echo (__('common.quarter_prefix') ?: 'Quý ') . $q; ?></option>
                                <?php endfor; ?>
                            </select>
                        <?php endif; ?>

                        <select name="sel_year" class="bill-custom-range-input" style="width: auto; padding: 0.2rem 0.5rem;" onchange="this.form.submit()">
                            <?php for($y=date('Y')-2; $y<=date('Y')+1; $y++): ?>
                                <option value="<?php echo $y; ?>" <?php echo (isset($_GET['sel_year']) && $_GET['sel_year'] == $y) || (!isset($_GET['sel_year']) && $y == date('Y')) ? 'selected' : ''; ?>><?php echo (__('common.year_prefix') ?: 'Năm ') . $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div id="billCustomDates" style="display: <?php echo $period == 'custom' ? 'flex' : 'none'; ?>; gap: 0.5rem; align-items: center;">
                    <div class="bill-custom-range-box">
                        <input type="date" name="start_date" class="bill-custom-range-input" value="<?php echo e($start_date_filter); ?>">
                        <span style="color: #94a3b8; font-size: 0.8rem;">→</span>
                        <input type="date" name="end_date" class="bill-custom-range-input" value="<?php echo e($end_date_filter); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" style="height: 32px; padding: 0 0.75rem; border-radius: 8px;"><?php echo __('common.apply') ?: 'Áp dụng'; ?></button>
                </div>

                <?php if ($is_filtered): ?>
                    <div style="margin-left: auto;">
                        <a href="index.php" style="color: #ef4444; font-size: 0.75rem; font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 0.25rem; background: #fff1f2; padding: 0.4rem 0.8rem; border-radius: 8px; border: 1px solid #fecaca;">
                            <i class="fas fa-trash-alt"></i> <?php echo __('common.clear_filter') ?: 'Xóa lọc'; ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <script>
    function setBillPeriod(evt, p) {
        if (evt && evt.preventDefault) evt.preventDefault();
        document.getElementById('billPeriodInput').value = p;
        if (p !== 'custom') {
            var startEl = document.querySelector('#billingFilterForm input[name="start_date"]');
            var endEl = document.querySelector('#billingFilterForm input[name="end_date"]');
            if (startEl) startEl.value = '';
            if (endEl) endEl.value = '';
            document.getElementById('billingFilterForm').submit();
        } else {
            document.getElementById('billCustomDates').style.display = 'flex';
            document.querySelectorAll('.bill-filter-btn').forEach(function(btn) { btn.classList.remove('active'); });
            if (evt && evt.target) evt.target.classList.add('active');
        }
    }
    </script>

    <!-- KPIs -->
    <div class="kpi-row">
        <div class="kpi-card" style="background:linear-gradient(135deg,#111827,#1e293b); min-width: 140px;">
            <div class="kpi-icon">⭐</div>
            <div class="kpi-label">TỔNG THỰC THU</div>
            <div class="kpi-val" style="font-size:1.3rem;"><?php echo format_money($grand_total); ?></div>
            <div style="font-size:0.7rem; opacity:0.6; margin-top:0.2rem;">Gói + Lẻ</div>
        </div>
        <div class="kpi-card" style="background:linear-gradient(135deg,#7c3aed,#8b5cf6); min-width: 140px;">
            <div class="kpi-icon">📦</div>
            <div class="kpi-label">BÁN GÓI</div>
            <div class="kpi-val" style="font-size:1.3rem;"><?php echo format_money($total_package_revenue); ?></div>
            <div style="font-size:0.7rem; opacity:0.6; margin-top:0.2rem;"><?php echo count($pkg_transactions); ?> giao dịch</div>
        </div>
        <div class="kpi-card" style="background:linear-gradient(135deg,#10b981,#34d399); min-width: 140px;">
            <div class="kpi-icon">💵</div>
            <div class="kpi-label">TIỀN MẶT</div>
            <div class="kpi-val" style="font-size:1.3rem;"><?php echo format_money($stats['total_cash']); ?></div>
            <div style="font-size:0.7rem; opacity:0.6; margin-top:0.2rem;">Phiếu lẻ</div>
        </div>
        <div class="kpi-card" style="background:linear-gradient(135deg,#3b82f6,#60a5fa); min-width: 140px;">
            <div class="kpi-icon">🏦</div>
            <div class="kpi-label">CK CÁ NHÂN</div>
            <div class="kpi-val" style="font-size:1.3rem;"><?php echo format_money($stats['total_transfer_personal']); ?></div>
            <div style="font-size:0.7rem; opacity:0.6; margin-top:0.2rem;">Phiếu lẻ</div>
        </div>
        <div class="kpi-card" style="background:linear-gradient(135deg,#8b5cf6,#a78bfa); min-width: 140px;">
            <div class="kpi-icon">🏢</div>
            <div class="kpi-label">TK CÔNG TY</div>
            <div class="kpi-val" style="font-size:1.3rem;"><?php echo format_money($stats['total_transfer_company']); ?></div>
            <div style="font-size:0.7rem; opacity:0.6; margin-top:0.2rem;">Phiếu lẻ</div>
        </div>
        <div class="kpi-card" style="background:linear-gradient(135deg,#f59e0b,#fbbf24); min-width: 140px;">
            <div class="kpi-icon">💳</div>
            <div class="kpi-label">QUẸT THẺ</div>
            <div class="kpi-val" style="font-size:1.3rem;"><?php echo format_money($stats['total_card']); ?></div>
            <div style="font-size:0.7rem; opacity:0.6; margin-top:0.2rem;">Phiếu lẻ</div>
        </div>
        <div class="kpi-card" style="background:linear-gradient(135deg,#ef4444,#f87171); min-width: 140px;">
            <div class="kpi-icon">📝</div>
            <div class="kpi-label">CÔNG NỢ</div>
            <div class="kpi-val" style="font-size:1.3rem;"><?php echo format_money($stats['total_debt']); ?></div>
            <div style="font-size:0.7rem; opacity:0.6; margin-top:0.2rem;">Chưa thu đủ</div>
        </div>
    </div>

    <!-- Table -->
    <div style="background:white; border-radius:20px; padding:1.5rem; box-shadow:0 4px 20px rgba(0,0,0,0.03);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
            <h3 style="margin:0; font-weight:800; color:#1e293b;"><i class="fas fa-list" style="color:var(--primary);"></i> <?php echo __('billing.index.list_title'); ?></h3>
            <span style="font-size:0.8rem; background:#eef2ff; color:#4f46e5; padding:0.3rem 0.7rem; border-radius:8px; font-weight:700;"><?php echo count($invoices); ?> <?php echo __('billing.index.invoice_unit'); ?></span>
        </div>

        <?php if (empty($invoices)): ?>
            <div style="text-align:center; padding:3rem; color:#94a3b8;">
                <i class="fas fa-file-invoice" style="font-size:3rem; margin-bottom:1rem; opacity:0.3;"></i>
                <p style="font-weight:600;"><?php echo __('billing.index.no_data'); ?></p>
            </div>
        <?php else: ?>
            <div style="overflow-x:auto;">
            <table class="inv-table">
                <thead>
                    <tr>
                        <th><?php echo __('billing.index.invoice_no'); ?></th>
                        <th><?php echo __('common.customer'); ?></th>
                        <th><?php echo __('billing.index.content'); ?></th>
                        <th style="text-align:right;"><?php echo __('billing.index.amount'); ?></th>
                        <th><?php echo __('billing.index.status'); ?></th>
                        <th><?php echo __('billing.index.cashier'); ?></th>
                        <th><?php echo __('billing.index.time'); ?></th>
                        <th style="width:80px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $inv): 
                        $items = json_decode($inv['items'], true);
                        $first_item = $items[0]['name'] ?? '—';
                        $more = count($items) > 1 ? ' +' . (count($items)-1) : '';
                        $status_map = [
                            'paid' => ['label' => __('billing.status.paid'), 'bg' => '#dcfce7', 'color' => '#166534'],
                            'partial' => ['label' => __('billing.status.partial'), 'bg' => '#fef3c7', 'color' => '#92400e'],
                            'debt' => ['label' => __('billing.status.debt'), 'bg' => '#fee2e2', 'color' => '#991b1b'],
                            'cancelled' => ['label' => 'Đã Hủy', 'bg' => '#f1f5f9', 'color' => '#64748b'],
                        ];
                        $st = $status_map[$inv['status']] ?? $status_map['paid'];
                    ?>
                    <tr>
                        <td><span style="font-weight:800; color:var(--primary); font-size:0.85rem;"><?php echo $inv['invoice_no']; ?></span></td>
                        <td>
                            <div style="font-weight:700; <?php echo $inv['status'] === 'cancelled' ? 'text-decoration: line-through; color: #94a3b8;' : ''; ?>"><?php echo e($inv['patient_name']); ?></div>
                            <div style="font-size:0.75rem; color:#94a3b8;"><?php echo e($inv['patient_phone']); ?></div>
                        </td>
                        <td>
                            <span style="font-size:0.85rem; <?php echo $inv['status'] === 'cancelled' ? 'text-decoration: line-through; color: #94a3b8;' : ''; ?>"><?php echo e($first_item . $more); ?></span>
                        </td>
                        <td style="text-align:right; font-weight:800; color:#1e293b; <?php echo $inv['status'] === 'cancelled' ? 'text-decoration: line-through; color: #94a3b8;' : ''; ?>"><?php echo format_money($inv['total_amount']); ?></td>
                        <td>
                            <select onchange="updateInvoiceStatus(<?php echo $inv['id']; ?>, this.value)" style="font-size:0.75rem; font-weight:700; padding:0.2rem 0.5rem; border-radius:6px; border:1px solid #e2e8f0; background:<?php echo $st['bg']; ?>; color:<?php echo $st['color']; ?>; cursor:pointer; outline:none; appearance:none; -webkit-appearance:none; text-align:center;">
                                <?php foreach($status_map as $key => $val): ?>
                                    <option value="<?php echo $key; ?>" <?php echo $inv['status'] === $key ? 'selected' : ''; ?> style="background:white; color:#1e293b;"><?php echo $val['label']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td style="font-size:0.85rem; color:#64748b;"><?php echo e($inv['cashier_name']); ?></td>
                        <td style="font-size:0.8rem; color:#94a3b8;"><?php echo date('H:i', strtotime($inv['created_at'])); ?></td>
                        <td>
                            <button onclick="openInvoiceView(<?php echo $inv['id']; ?>)" class="btn btn-sm" style="background:#eef2ff; color:#4f46e5; border-radius:8px; padding:0.3rem 0.6rem; font-size:0.75rem; font-weight:700;">
                                <i class="fas fa-eye"></i> <?php echo __('common.view_btn'); ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bảng Bán Gói -->
    <?php if (!empty($pkg_transactions)): ?>
    <div style="background:white; border-radius:20px; padding:1.5rem; box-shadow:0 4px 20px rgba(0,0,0,0.03); margin-top:1.5rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
            <h3 style="margin:0; font-weight:800; color:#1e293b;"><i class="fas fa-box-open" style="color:#7c3aed;"></i> Thu từ Bán Gói</h3>
            <span style="font-size:0.8rem; background:#f3e8ff; color:#7c3aed; padding:0.3rem 0.7rem; border-radius:8px; font-weight:700;"><?php echo count($pkg_transactions); ?> giao dịch · <?php echo format_money($total_package_revenue); ?></span>
        </div>
        <div style="overflow-x:auto;">
        <table class="inv-table">
            <thead>
                <tr>
                    <th>THỜI GIAN</th>
                    <th>KHÁCH HÀNG</th>
                    <th>NỘI DUNG</th>
                    <th style="text-align:right;">SỐ TIỀN</th>
                    <th>NGƯỜI BÁN</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pkg_transactions as $pt): ?>
                <tr>
                    <td style="font-size:0.85rem; color:#64748b;">
                        <?php echo date('H:i', strtotime($pt['transaction_date'])); ?>
                        <div style="font-size:0.7rem; color:#94a3b8;"><?php echo date('d/m/Y', strtotime($pt['transaction_date'])); ?></div>
                    </td>
                    <td>
                        <?php if ($pt['pp_id']): ?>
                            <a href="/modules/sales/manage_shared.php?id=<?php echo $pt['pp_id']; ?>" style="font-weight:700; color:#1e293b; text-decoration:none;"><?php echo e($pt['patient_name'] ?? 'Khách'); ?></a>
                        <?php else: ?>
                            <span style="font-weight:700; color:#1e293b;"><?php echo e($pt['patient_name'] ?? 'Khách'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span style="font-size:0.85rem;"><?php echo e($pt['description']); ?></span>
                        <span style="font-size:0.7rem; background:<?php echo $pt['category'] === 'package' ? '#f3e8ff' : '#fef3c7'; ?>; color:<?php echo $pt['category'] === 'package' ? '#7c3aed' : '#92400e'; ?>; padding:0.15rem 0.4rem; border-radius:4px; font-weight:700; margin-left:0.25rem;"><?php echo $pt['category'] === 'package' ? 'Mua mới' : 'Trả nợ'; ?></span>
                    </td>
                    <td style="text-align:right; font-weight:800; color:#10b981;"><?php echo format_money($pt['amount']); ?></td>
                    <td style="font-size:0.85rem; color:#64748b;"><?php echo e($pt['seller_name'] ?? '—'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../../includes/invoice_popup.php'; ?>

<script>
var _billingPatients = [
    <?php foreach ($patients as $p): ?>
    { id: <?php echo $p['id']; ?>, name: <?php echo json_encode($p['full_name']); ?>, phone: <?php echo json_encode($p['phone']); ?> },
    <?php endforeach; ?>
];

function openCreateNew() {
    openInvoicePopup({ patient_id: '', patient_name: '', inline_select: true, patients: _billingPatients });
}

function updateInvoiceStatus(invoiceId, newStatus) {
    if (!confirm('Bạn có chắc chắn muốn thay đổi trạng thái phiếu này?')) {
        window.location.reload();
        return;
    }
    fetch('update_invoice_status_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ invoice_id: invoiceId, status: newStatus })
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            window.location.reload();
        } else {
            alert('Lỗi: ' + (data.error || 'Không thể đổi trạng thái'));
            window.location.reload();
        }
    })
    .catch(err => {
        alert('Lỗi kết nối');
        window.location.reload();
    });
}
</script>

<?php require_once '../../templates/footer.php'; ?>
