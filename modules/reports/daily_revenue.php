<?php
// modules/reports/daily_revenue.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('view_reports');

$page_title = 'Báo Cáo Thực Thu';
$current_page = 'reports';
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

$params = [$start, $end];

// 1. KPIs Tổng quan từ Invoices
$stmt = $db->prepare("
    SELECT 
        SUM(cash_amount) as total_cash,
        SUM(transfer_amount) as total_transfer,
        SUM(package_deduct) as total_package,
        SUM(debt_amount) as total_debt
    FROM invoices
    WHERE created_at BETWEEN ? AND ?
");
$stmt->execute($params);
$inv_totals = $stmt->fetch();

// 2. Thu Nợ (Từ package_payments)
$stmt = $db->prepare("
    SELECT payment_method, SUM(amount) as total
    FROM package_payments
    WHERE paid_at BETWEEN ? AND ?
    GROUP BY payment_method
");
$stmt->execute($params);
$pkg_pays = $stmt->fetchAll();

$total_cash = (float)$inv_totals['total_cash'];
$total_transfer = (float)$inv_totals['total_transfer'];
$total_package = (float)$inv_totals['total_package'];
$total_debt = (float)$inv_totals['total_debt'];

foreach ($pkg_pays as $pp) {
    if ($pp['payment_method'] === 'cash') {
        $total_cash += (float)$pp['total'];
    } elseif (in_array($pp['payment_method'], ['transfer', 'transfer_personal', 'transfer_company', 'card'])) {
        // Cộng gộp tất cả các loại chuyển khoản (và quẹt thẻ nếu có) vào mục Chuyển khoản chung
        $total_transfer += (float)$pp['total'];
    }
}

// Thực thu CHỈ tính Tiền mặt + Chuyển khoản thực tế + Tiền nạp Coin
// Lấy doanh thu nạp ví Coin trong kỳ
$stmt = $db->prepare("
    SELECT SUM(price_paid) as total_topup
    FROM coin_transactions
    WHERE transaction_type = 'topup' AND created_at BETWEEN ? AND ?
");
$stmt->execute($params);
$total_coin_topup = (float)$stmt->fetchColumn();

// Tính giá trị Coin trung bình (Toàn thời gian)
$stmt = $db->query("
    SELECT SUM(price_paid) as total_cash_in, SUM(amount) as total_coins_minted
    FROM coin_transactions
    WHERE transaction_type = 'topup'
");
$coin_stats = $stmt->fetch();
$avg_coin_value = ($coin_stats['total_coins_minted'] > 0) ? ($coin_stats['total_cash_in'] / $coin_stats['total_coins_minted']) : 0;

// Tính số Coin đã tiêu hao trong kỳ
$stmt = $db->prepare("
    SELECT SUM(ABS(amount)) as total_coins_used
    FROM coin_transactions
    WHERE transaction_type = 'usage' AND created_at BETWEEN ? AND ?
");
$stmt->execute($params);
$coins_used_today = (float)$stmt->fetchColumn();

$total_coin_usage_revenue = $coins_used_today * $avg_coin_value;

// Thực thu CHỈ tính Tiền mặt + Chuyển khoản thực tế
// (Tiền Nạp Coin đã được cộng tự động vào Tiền mặt / Chuyển khoản thông qua Phiếu tính tiền)
$total_revenue = $total_cash + $total_transfer; 


// 3. Chi Tiết Nợ Phát Sinh (Invoices có debt_amount > 0)
$stmt = $db->prepare("
    SELECT i.*, p.full_name as patient_name, u.full_name as created_by_name
    FROM invoices i
    JOIN patients p ON i.patient_id = p.id
    LEFT JOIN users u ON i.created_by = u.id
    WHERE i.debt_amount > 0 AND i.created_at BETWEEN ? AND ?
    ORDER BY i.created_at DESC
");
$stmt->execute($params);
$debt_invoices = $stmt->fetchAll();

// 4. Chi Tiết Thu Nợ Cũ (package_payments)
$stmt = $db->prepare("
    SELECT ppay.*, p.full_name as patient_name, pkg.name as package_name, u.full_name as created_by_name
    FROM package_payments ppay
    JOIN patient_packages pp ON ppay.patient_package_id = pp.id
    JOIN patients p ON pp.patient_id = p.id
    JOIN packages pkg ON pp.package_id = pkg.id
    LEFT JOIN users u ON ppay.created_by = u.id
    WHERE ppay.paid_at BETWEEN ? AND ?
    ORDER BY ppay.paid_at DESC
");
$stmt->execute($params);
$debt_payments = $stmt->fetchAll();

// 5. Năng suất Nhân viên (Nối qua treatment_id)
$stmt = $db->prepare("
    SELECT u.full_name, u.id as user_id,
           COUNT(DISTINCT t.id) as total_cases,
           SUM(COALESCE(i.cash_amount, 0) + COALESCE(i.transfer_amount, 0)) as cash_revenue,
           SUM(COALESCE(i.package_deduct, 0)) as package_value,
           SUM(COALESCE(i.debt_amount, 0)) as debt_amount
    FROM treatments t
    JOIN users u ON t.technician_id = u.id
    LEFT JOIN invoices i ON t.id = i.treatment_id
    WHERE t.treatment_date BETWEEN ? AND ?
    GROUP BY u.id
    ORDER BY total_cases DESC
");
$stmt->execute($params);
$staff_data = $stmt->fetchAll();

// 6. Tất cả Giao Dịch Phiếu Tính Tiền (Invoices)
$stmt = $db->prepare("
    SELECT i.*, p.full_name as patient_name, u.full_name as created_by_name
    FROM invoices i
    JOIN patients p ON i.patient_id = p.id
    LEFT JOIN users u ON i.created_by = u.id
    WHERE i.created_at BETWEEN ? AND ?
    ORDER BY i.created_at DESC
");
$stmt->execute($params);
$all_invoices = $stmt->fetchAll();
?>

<style>
.rev-outer { max-width: 1400px; width: 100%; }

.rev-stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
@media (max-width: 1200px) { .rev-stats { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 768px) { .rev-stats { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 480px) { .rev-stats { grid-template-columns: 1fr; } }

.rev-card {
    padding: 2rem; border-radius: 20px; color: white; position: relative; overflow: hidden;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); transition: all 0.3s;
}
.rev-card:hover { transform: translateY(-4px); }
.rev-card .rev-icon { position: absolute; right: -5px; bottom: -5px; font-size: 4rem; opacity: 0.15; }
.rev-card .rev-label { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; opacity: 0.85; letter-spacing: 0.5px; }
.rev-card .rev-val { font-size: 2rem; font-weight: 800; margin-top: 0.5rem; }
.rev-card .rev-sub { font-size: 0.85rem; opacity: 0.8; margin-top: 0.3rem; }

.section-card { background: white; border-radius: 20px; padding: 2rem; margin-bottom: 1.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); }
.section-title { font-size: 1.15rem; font-weight: 800; color: #1e293b; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; gap: 0.6rem; }
.section-title i { color: var(--primary); }
</style>

<div class="content-body">
    <div class="rev-outer">
        <div class="breadcrumb mb-2" style="font-size: 0.75rem; font-weight: 700; letter-spacing: 1px; color: #94a3b8;">
            CRM / BÁO CÁO / THỰC THU
        </div>
        <h1 style="font-size: 2rem; font-weight: 800; color: #0f172a; margin-bottom: 1.5rem;">
            💰 Báo Cáo Thực Thu — <?php echo $label; ?>
        </h1>

        <!-- Filter (Lead-style) -->
        <style>
        .dr-filter-card { padding: 1rem; margin-bottom: 2rem; background: white; border-radius: 18px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); }
        .dr-filter-btn-group { display: flex; gap: 0.4rem; flex-wrap: wrap; align-items: center; }
        .dr-filter-btn {
            padding: 0.4rem 0.8rem; border-radius: 8px; background: #f1f5f9; color: #64748b;
            font-size: 0.8rem; font-weight: 600; text-decoration: none; transition: background 0.15s, color 0.15s; border: 1px solid transparent; cursor: pointer;
        }
        .dr-filter-btn:hover { background: #e2e8f0; color: #1e293b; }
        .dr-filter-btn.active { background: var(--primary); color: white; }
        .dr-filter-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.025em; margin-bottom: 0; display: inline; color: #64748b; font-weight: 700; margin-right: 0.25rem; }
        .dr-custom-range-box { display: flex; gap: 0.5rem; align-items: center; background: #f8fafc; padding: 0.4rem 0.75rem; border-radius: 10px; border: 1px solid #e2e8f0; }
        .dr-custom-range-input { border: none; background: transparent; font-size: 0.8rem; color: #1e293b; width: 110px; outline: none; }
        </style>
        <div class="dr-filter-card">
            <form method="GET" id="drFilterForm">
                <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                    <div class="dr-filter-btn-group">
                        <span class="dr-filter-label">THỜI GIAN:</span>
                        <input type="hidden" name="period" id="drPeriodInput" value="<?php echo e($period); ?>">
                        <a href="#" class="dr-filter-btn <?php echo $period == 'today' ? 'active' : ''; ?>" onclick="setDrPeriod(event, 'today')">Hôm nay</a>
                        <a href="#" class="dr-filter-btn <?php echo $period == 'week' ? 'active' : ''; ?>" onclick="setDrPeriod(event, 'week')">Tuần</a>
                        <a href="#" class="dr-filter-btn <?php echo $period == 'month' ? 'active' : ''; ?>" onclick="setDrPeriod(event, 'month')">Tháng</a>
                        <a href="#" class="dr-filter-btn <?php echo $period == 'quarter' ? 'active' : ''; ?>" onclick="setDrPeriod(event, 'quarter')">Quý</a>
                        <a href="#" class="dr-filter-btn <?php echo $period == 'year' ? 'active' : ''; ?>" onclick="setDrPeriod(event, 'year')">Năm</a>
                        <a href="#" class="dr-filter-btn <?php echo $period == 'custom' ? 'active' : ''; ?>" onclick="setDrPeriod(event, 'custom')">Tùy chọn</a>
                    </div>

                    <?php if($period === 'month' || $period === 'quarter' || $period === 'year'): ?>
                        <div style="display: flex; gap: 0.5rem; align-items: center; background: #f8fafc; padding: 0.3rem 0.5rem; border-radius: 10px; border: 1px solid #e2e8f0;">
                            <?php if($period === 'month'): ?>
                                <select name="sel_month" class="dr-custom-range-input" style="width: auto; padding: 0.2rem 0.5rem;" onchange="this.form.submit()">
                                    <?php for($m=1; $m<=12; $m++): ?>
                                        <option value="<?php echo $m; ?>" <?php echo (isset($_GET['sel_month']) && $_GET['sel_month'] == $m) || (!isset($_GET['sel_month']) && $m == date('n')) ? 'selected' : ''; ?>>Tháng <?php echo $m; ?></option>
                                    <?php endfor; ?>
                                </select>
                            <?php endif; ?>
                            
                            <?php if($period === 'quarter'): ?>
                                <select name="sel_quarter" class="dr-custom-range-input" style="width: auto; padding: 0.2rem 0.5rem;" onchange="this.form.submit()">
                                    <?php for($q=1; $q<=4; $q++): ?>
                                        <option value="<?php echo $q; ?>" <?php echo (isset($_GET['sel_quarter']) && $_GET['sel_quarter'] == $q) || (!isset($_GET['sel_quarter']) && $q == ceil(date('n')/3)) ? 'selected' : ''; ?>>Quý <?php echo $q; ?></option>
                                    <?php endfor; ?>
                                </select>
                            <?php endif; ?>

                            <select name="sel_year" class="dr-custom-range-input" style="width: auto; padding: 0.2rem 0.5rem;" onchange="this.form.submit()">
                                <?php for($y=date('Y')-2; $y<=date('Y')+1; $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo (isset($_GET['sel_year']) && $_GET['sel_year'] == $y) || (!isset($_GET['sel_year']) && $y == date('Y')) ? 'selected' : ''; ?>>Năm <?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div id="drCustomDates" style="display: <?php echo $period == 'custom' ? 'flex' : 'none'; ?>; gap: 0.5rem; align-items: center;">
                        <div class="dr-custom-range-box">
                            <input type="date" name="start_date" class="dr-custom-range-input" value="<?php echo e($start_date_filter); ?>">
                            <span style="color: #94a3b8; font-size: 0.8rem;">→</span>
                            <input type="date" name="end_date" class="dr-custom-range-input" value="<?php echo e($end_date_filter); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm" style="height: 32px; padding: 0 0.75rem; border-radius: 10px;">Áp dụng</button>
                    </div>

                    <?php if ($is_filtered): ?>
                        <div style="margin-left: auto;">
                            <a href="daily_revenue.php" style="color: #ef4444; font-size: 0.75rem; font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 0.25rem; background: #fff1f2; padding: 0.4rem 0.8rem; border-radius: 8px; border: 1px solid #fecaca;">
                                <i class="fas fa-trash-alt"></i> Xóa lọc
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <script>
        function setDrPeriod(evt, p) {
            if (evt && evt.preventDefault) evt.preventDefault();
            document.getElementById('drPeriodInput').value = p;
            if (p !== 'custom') {
                var startEl = document.querySelector('#drFilterForm input[name="start_date"]');
                var endEl = document.querySelector('#drFilterForm input[name="end_date"]');
                if (startEl) startEl.value = '';
                if (endEl) endEl.value = '';
                document.getElementById('drFilterForm').submit();
            } else {
                document.getElementById('drCustomDates').style.display = 'flex';
                document.querySelectorAll('.dr-filter-btn').forEach(function(btn) { btn.classList.remove('active'); });
                if (evt && evt.target) evt.target.classList.add('active');
            }
        }
        </script>

        <!-- Revenue KPIs -->
        <div class="rev-stats">
            <div class="rev-card" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                <div class="rev-icon">🪙</div>
                <div class="rev-label">Nạp Ví Coin</div>
                <div class="rev-val"><?php echo format_money($total_coin_topup); ?></div>
            </div>
            <div class="rev-card" style="background: linear-gradient(135deg, #10b981, #34d399);">
                <div class="rev-icon">💵</div>
                <div class="rev-label">Bán Gói Lẻ (Tiền mặt)</div>
                <div class="rev-val"><?php echo format_money($total_cash); ?></div>
            </div>
            <div class="rev-card" style="background: linear-gradient(135deg, #3b82f6, #60a5fa);">
                <div class="rev-icon">🏦</div>
                <div class="rev-label">Bán Gói Lẻ (Chuyển Khoản)</div>
                <div class="rev-val"><?php echo format_money($total_transfer); ?></div>
            </div>
            <div class="rev-card" style="background: linear-gradient(135deg, #8b5cf6, #a78bfa);">
                <div class="rev-icon">📈</div>
                <div class="rev-label">Doanh Thu Thực Hiện (Coin + Gói)</div>
                <div class="rev-val"><?php echo format_money($total_package + $total_coin_usage_revenue); ?></div>
                <div class="rev-sub">Khách đã dùng <?php echo $coins_used_today; ?> Coins hôm nay</div>
            </div>
            <div class="rev-card" style="background: linear-gradient(135deg, #ef4444, #f87171);">
                <div class="rev-icon">📝</div>
                <div class="rev-label">Nợ Phát Sinh Mới</div>
                <div class="rev-val"><?php echo format_money($total_debt); ?></div>
            </div>
        </div>

        <!-- Total Summary -->
        <div class="section-card" style="background: linear-gradient(135deg, #0f172a, #1e293b); color: white; padding: 2.5rem; display: grid; grid-template-columns: 1fr 1px 1fr; gap: 2rem;">
            <div style="text-align: center;">
                <div style="font-size: 0.95rem; font-weight: 700; text-transform: uppercase; opacity: 0.8; letter-spacing: 1px; color: #10b981;">DOANH THU NHẬN TRƯỚC (THỰC THU)</div>
                <div style="font-size: 3.5rem; font-weight: 800; margin-top: 0.5rem; color: #10b981; text-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);"><?php echo format_money($total_revenue); ?></div>
                <div style="margin-top: 1rem; color: #94a3b8; font-size: 0.85rem;">
                    Tiền nạp Coin + Khách mua dịch vụ trả thẳng (Tiền vào quỹ)
                </div>
            </div>
            <div style="background: rgba(255,255,255,0.1); width: 1px; height: 100%;"></div>
            <div style="text-align: center;">
                <div style="font-size: 0.95rem; font-weight: 700; text-transform: uppercase; opacity: 0.8; letter-spacing: 1px; color: #38bdf8;">DOANH THU THỰC HIỆN</div>
                <div style="font-size: 3.5rem; font-weight: 800; margin-top: 0.5rem; color: #38bdf8; text-shadow: 0 4px 15px rgba(56, 189, 248, 0.4);"><?php echo format_money($total_package + $total_coin_usage_revenue); ?></div>
                <div style="margin-top: 1rem; color: #94a3b8; font-size: 0.85rem;">
                    Giá trị trung bình 1 Coin hiện tại: <strong style="color: white;"><?php echo format_money($avg_coin_value); ?></strong>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <!-- Debt Created (Nợ Phát Sinh) -->
            <div class="section-card">
                <div class="section-title">
                    <span><i class="fas fa-hand-holding-usd" style="color: #ef4444;"></i> Nợ Phát Sinh Hôm Nay</span>
                    <span style="font-size: 0.8rem; background: #fef2f2; color: #ef4444; padding: 0.2rem 0.6rem; border-radius: 50px;"><?php echo count($debt_invoices); ?> khoản</span>
                </div>
                <?php if (empty($debt_invoices)): ?>
                    <div style="text-align: center; padding: 2rem; color: #94a3b8;">Không có nợ phát sinh trong kỳ.</div>
                <?php else: ?>
                    <div style="max-height: 350px; overflow-y: auto;">
                        <table class="table" style="width: 100%;">
                            <thead>
                                <tr style="border-bottom: 2px solid #e2e8f0; text-align: left; position: sticky; top: 0; background: white;">
                                    <th style="padding: 0.6rem;">Bệnh nhân</th>
                                    <th style="padding: 0.6rem;">Mã HĐ</th>
                                    <th style="padding: 0.6rem; text-align: right;">Ghi Nợ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($debt_invoices as $di): ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 0.6rem;">
                                            <a href="../patients/view.php?id=<?php echo $di['patient_id']; ?>" style="font-weight: 700; color: var(--primary); text-decoration: none;">
                                                <?php echo e($di['patient_name']); ?>
                                            </a>
                                        </td>
                                        <td style="padding: 0.6rem; font-size: 0.85rem; color: #64748b;"><?php echo e($di['invoice_no']); ?></td>
                                        <td style="padding: 0.6rem; text-align: right; font-weight: 800; color: #ef4444;"><?php echo format_money($di['debt_amount']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Debt Payments Collected (Thu Nợ Cũ) -->
            <div class="section-card">
                <div class="section-title">
                    <span><i class="fas fa-piggy-bank" style="color: #10b981;"></i> Thu Nợ Cũ Hôm Nay</span>
                    <span style="font-size: 0.8rem; background: #ecfdf5; color: #10b981; padding: 0.2rem 0.6rem; border-radius: 50px;"><?php echo count($debt_payments); ?> khoản</span>
                </div>
                <?php if (empty($debt_payments)): ?>
                    <div style="text-align: center; padding: 2rem; color: #94a3b8;">Không có khoản thu nợ nào trong kỳ.</div>
                <?php else: ?>
                    <div style="max-height: 350px; overflow-y: auto;">
                        <table class="table" style="width: 100%;">
                            <thead>
                                <tr style="border-bottom: 2px solid #e2e8f0; text-align: left; position: sticky; top: 0; background: white;">
                                    <th style="padding: 0.6rem;">Bệnh nhân</th>
                                    <th style="padding: 0.6rem;">Hình thức</th>
                                    <th style="padding: 0.6rem; text-align: right;">Đã thu</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($debt_payments as $dp): ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 0.6rem;">
                                            <div style="font-weight: 700; color: #1e293b;"><?php echo e($dp['patient_name']); ?></div>
                                            <div style="font-size: 0.75rem; color: #64748b;"><?php echo e($dp['package_name']); ?></div>
                                        </td>
                                        <td style="padding: 0.6rem;">
                                            <?php if ($dp['payment_method'] === 'cash'): ?>
                                                <span style="font-size: 0.75rem; background: #d1fae5; color: #059669; padding: 0.2rem 0.5rem; border-radius: 6px; font-weight: 700;"><i class="fas fa-money-bill"></i> Tiền mặt</span>
                                            <?php else: ?>
                                                <span style="font-size: 0.75rem; background: #dbeafe; color: #2563eb; padding: 0.2rem 0.5rem; border-radius: 6px; font-weight: 700;"><i class="fas fa-university"></i> Chuyển khoản</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 0.6rem; text-align: right; font-weight: 800; color: #10b981;">
                                            +<?php echo format_money($dp['amount']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <!-- Staff Productivity -->
            <div class="section-card">
                <div class="section-title"><span><i class="fas fa-user-tie" style="color: #6366f1;"></i> Năng Suất Nhân Viên</span></div>
                <?php if (empty($staff_data)): ?>
                    <div style="text-align: center; padding: 2rem; color: #94a3b8;">Không có dữ liệu trong kỳ.</div>
                <?php else: ?>
                    <table class="table" style="width: 100%;">
                        <thead>
                            <tr style="border-bottom: 2px solid #e2e8f0; text-align: left;">
                                <th style="padding: 0.6rem;">KTV / Bác sĩ</th>
                                <th style="padding: 0.6rem; text-align: center;">Số ca</th>
                                <th style="padding: 0.6rem; text-align: right;">Tiền thu</th>
                                <th style="padding: 0.6rem; text-align: right;">Gói (quy đổi)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($staff_data as $s): ?>
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 0.6rem; font-weight: 700;"><?php echo e($s['full_name']); ?></td>
                                    <td style="padding: 0.6rem; text-align: center;">
                                        <span style="font-size: 1.1rem; font-weight: 800; color: #1e293b;"><?php echo $s['total_cases']; ?></span>
                                        <span style="font-size: 0.8rem; color: #64748b;"> ca</span>
                                    </td>
                                    <td style="padding: 0.6rem; text-align: right; font-weight: 700; color: #10b981;">
                                        <?php echo format_money($s['cash_revenue']); ?>
                                    </td>
                                    <td style="padding: 0.6rem; text-align: right; font-weight: 700; color: #8b5cf6;">
                                        <?php echo format_money($s['package_value']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <div class="section-card">
                <div style="text-align: center; padding: 4rem 2rem; border: 2px dashed #e2e8f0; border-radius: 16px; background: #f8fafc; height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                    <i class="fas fa-chart-line" style="font-size: 4rem; color: #cbd5e1; margin-bottom: 1rem;"></i>
                    <h4 style="margin: 0; color: #64748b; font-weight: 800;">Phân Tích Sắp Tới</h4>
                    <p style="font-size: 0.85rem; color: #94a3b8; margin-top: 0.5rem; max-width: 250px;">Biểu đồ xu hướng và phân tích tỷ trọng dịch vụ sẽ sớm ra mắt.</p>
                </div>
            </div>
        </div>

        <!-- All Transactions -->
        <div class="section-card">
            <div class="section-title"><span><i class="fas fa-receipt" style="color: #64748b;"></i> Tất Cả Phiếu Tính Tiền Mới Phát Sinh (<?php echo count($all_invoices); ?>)</span></div>
            <?php if (empty($all_invoices)): ?>
                <div style="text-align: center; padding: 2rem; color: #94a3b8;">Chưa có phiếu tính tiền nào trong kỳ.</div>
            <?php else: ?>
                <div style="max-height: 500px; overflow-y: auto;">
                    <table class="table" style="width: 100%;">
                        <thead>
                            <tr style="border-bottom: 2px solid #e2e8f0; text-align: left; position: sticky; top: 0; background: white; z-index: 10;">
                                <th style="padding: 0.6rem;">Thời gian</th>
                                <th style="padding: 0.6rem;">Khách hàng</th>
                                <th style="padding: 0.6rem;">Mã Phiếu</th>
                                <th style="padding: 0.6rem;">Hình thức</th>
                                <th style="padding: 0.6rem; text-align: right;">Tổng Hoá Đơn</th>
                                <th style="padding: 0.6rem;">Người tạo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_invoices as $inv): ?>
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 0.6rem;">
                                        <div style="font-weight: 700; font-size: 0.9rem; color: #1e293b;"><?php echo date('H:i', strtotime($inv['created_at'])); ?></div>
                                        <div style="font-size: 0.75rem; color: #64748b;"><?php echo date('d/m/Y', strtotime($inv['created_at'])); ?></div>
                                    </td>
                                    <td style="padding: 0.6rem; font-weight: 700; color: var(--primary);">
                                        <a href="../patients/view.php?id=<?php echo $inv['patient_id']; ?>" style="text-decoration: none; color: inherit;">
                                            <?php echo e($inv['patient_name']); ?>
                                        </a>
                                    </td>
                                    <td style="padding: 0.6rem;">
                                        <a href="#" onclick="openInvoicePreview(<?php echo $inv['id']; ?>); return false;" style="font-size: 0.85rem; font-weight: 700; color: #64748b; text-decoration: underline;">
                                            <?php echo e($inv['invoice_no']); ?>
                                        </a>
                                    </td>
                                    <td style="padding: 0.6rem;">
                                        <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                            <?php if ($inv['cash_amount'] > 0): ?>
                                                <span style="font-size: 0.7rem; background: #d1fae5; color: #059669; padding: 0.1rem 0.4rem; border-radius: 4px; font-weight: 700;">Tiền mặt</span>
                                            <?php endif; ?>
                                            <?php if ($inv['transfer_amount'] > 0): ?>
                                                <span style="font-size: 0.7rem; background: #dbeafe; color: #2563eb; padding: 0.1rem 0.4rem; border-radius: 4px; font-weight: 700;">CK</span>
                                            <?php endif; ?>
                                            <?php if ($inv['package_deduct'] > 0): ?>
                                                <span style="font-size: 0.7rem; background: #f3e8ff; color: #7e22ce; padding: 0.1rem 0.4rem; border-radius: 4px; font-weight: 700;">Trừ Gói</span>
                                            <?php endif; ?>
                                            <?php if ($inv['debt_amount'] > 0): ?>
                                                <span style="font-size: 0.7rem; background: #fee2e2; color: #dc2626; padding: 0.1rem 0.4rem; border-radius: 4px; font-weight: 700;">Ghi Nợ</span>
                                            <?php endif; ?>
                                            <?php if ($inv['total_amount'] == 0 && $inv['package_deduct'] == 0 && $inv['debt_amount'] == 0): ?>
                                                <span style="font-size: 0.7rem; background: #f1f5f9; color: #475569; padding: 0.1rem 0.4rem; border-radius: 4px; font-weight: 700;">0đ</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="padding: 0.6rem; text-align: right; font-weight: 800; color: #1e293b; font-size: 1.05rem;">
                                        <?php echo format_money($inv['total_amount']); ?>
                                    </td>
                                    <td style="padding: 0.6rem; font-size: 0.8rem; color: #64748b;"><?php echo e($inv['created_by_name']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once '../../includes/invoice_popup.php'; ?>

<?php require_once '../../templates/footer.php'; ?>
