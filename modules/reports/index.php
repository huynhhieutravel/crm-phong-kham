<?php
// modules/reports/index.php — Dashboard Báo cáo Doanh thu
require_once '../../includes/functions.php';
$page_title = __('reports') . ' — ' . __('dashboard');
$current_page = 'reports';
require_once '../../templates/header.php';

$db = getDB();

// Filter params
$filter_month = $_GET['month'] ?? date('m');
$filter_year = $_GET['year'] ?? date('Y');
$filter_type = $_GET['type'] ?? '';

// 1. Summary stats for selected month
$params = [$filter_month, $filter_year];
$type_clause = '';
if ($filter_type) {
    $type_clause = " AND type = ?";
    $params[] = $filter_type;
}

$stmt = $db->prepare("SELECT SUM(amount) FROM transactions WHERE MONTH(transaction_date) = ? AND YEAR(transaction_date) = ? AND type = 'income'" . ($filter_type === 'income' ? '' : ''));
$stmt->execute([$filter_month, $filter_year]);
$income = $stmt->fetchColumn() ?: 0;

$stmt = $db->prepare("SELECT SUM(amount) FROM transactions WHERE MONTH(transaction_date) = ? AND YEAR(transaction_date) = ? AND type = 'expense'");
$stmt->execute([$filter_month, $filter_year]);
$expense = $stmt->fetchColumn() ?: 0;

$profit = $income - $expense;

// 2. Monthly trend (12 months)
$monthly_data = [];
for ($m = 1; $m <= 12; $m++) {
    $stmt = $db->prepare("SELECT COALESCE(SUM(CASE WHEN type='income' THEN amount END),0) as inc, COALESCE(SUM(CASE WHEN type='expense' THEN amount END),0) as exp FROM transactions WHERE MONTH(transaction_date) = ? AND YEAR(transaction_date) = ?");
    $stmt->execute([$m, $filter_year]);
    $row = $stmt->fetch();
    $monthly_data[] = ['month' => $m, 'income' => (float)$row['inc'], 'expense' => (float)$row['exp']];
}

// 3. Category breakdown
$stmt = $db->prepare("SELECT category, type, SUM(amount) as total FROM transactions WHERE MONTH(transaction_date) = ? AND YEAR(transaction_date) = ? GROUP BY category, type ORDER BY total DESC");
$stmt->execute([$filter_month, $filter_year]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Recent transactions
$where_parts = ["MONTH(transaction_date) = ?", "YEAR(transaction_date) = ?"];
$tx_params = [$filter_month, $filter_year];
if ($filter_type) {
    $where_parts[] = "type = ?";
    $tx_params[] = $filter_type;
}
$where_sql = implode(' AND ', $where_parts);

$stmt = $db->prepare("SELECT t.*, u.full_name as creator_name FROM transactions t LEFT JOIN users u ON t.created_by = u.id WHERE $where_sql ORDER BY transaction_date DESC LIMIT 100");
$stmt->execute($tx_params);
$transactions = $stmt->fetchAll();
?>

<style>
    .stat-card { background: white; border-radius: 16px; padding: 1.5rem; border: 1px solid #e2e8f0; position: relative; overflow: hidden; }
    .stat-card::after { content: ''; position: absolute; top: 0; right: 0; width: 80px; height: 80px; border-radius: 0 0 0 80px; opacity: 0.1; }
    .stat-card.income { border-left: 4px solid #10b981; }
    .stat-card.income::after { background: #10b981; }
    .stat-card.expense { border-left: 4px solid #ef4444; }
    .stat-card.expense::after { background: #ef4444; }
    .stat-card.profit { border-left: 4px solid #6366f1; }
    .stat-card.profit::after { background: #6366f1; }
    .stat-value { font-size: 1.8rem; font-weight: 800; margin: 0.5rem 0; }
    .stat-label { font-size: 0.8rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; color: #64748b; }
    .filter-bar { display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap; align-items: center; }
    .filter-bar select, .filter-bar input { padding: 0.6rem 1rem; border-radius: 10px; border: 1px solid #e2e8f0; font-size: 0.9rem; background: white; font-weight: 600; }
    .category-chip { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 50px; font-size: 0.8rem; font-weight: 700; margin: 0.25rem; }
</style>

<!-- Filter Bar -->
<form class="filter-bar" method="GET">
    <select name="month">
        <?php for ($m = 1; $m <= 12; $m++): ?>
        <option value="<?php echo $m; ?>" <?php echo $m == $filter_month ? 'selected' : ''; ?>><?php echo __('reports.month_prefix') . $m; ?></option>
        <?php endfor; ?>
    </select>
    <select name="year">
        <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
        <option value="<?php echo $y; ?>" <?php echo $y == $filter_year ? 'selected' : ''; ?>><?php echo $y; ?></option>
        <?php endfor; ?>
    </select>
    <select name="type">
        <option value=""><?php echo __('reports.all'); ?></option>
        <option value="income" <?php echo $filter_type === 'income' ? 'selected' : ''; ?>><?php echo __('reports.income_label'); ?></option>
        <option value="expense" <?php echo $filter_type === 'expense' ? 'selected' : ''; ?>><?php echo __('reports.expense_label'); ?></option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm" style="border-radius: 50px;"><i class="fas fa-filter"></i> <?php echo __('filter'); ?></button>
</form>

<!-- Stats Cards -->
<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
    <div class="stat-card income">
        <div class="stat-label"><i class="fas fa-arrow-up" style="color: #10b981;"></i> <?php echo __('reports.income'); ?></div>
        <div class="stat-value" style="color: #10b981;"><?php echo format_money($income); ?></div>
    </div>
    <div class="stat-card expense">
        <div class="stat-label"><i class="fas fa-arrow-down" style="color: #ef4444;"></i> <?php echo __('reports.expense'); ?></div>
        <div class="stat-value" style="color: #ef4444;"><?php echo format_money($expense); ?></div>
    </div>
    <div class="stat-card profit">
        <div class="stat-label"><i class="fas fa-chart-line" style="color: #6366f1;"></i> <?php echo __('reports.profit'); ?></div>
        <div class="stat-value" style="color: <?php echo $profit >= 0 ? '#10b981' : '#ef4444'; ?>;"><?php echo format_money($profit); ?></div>
    </div>
</div>

<!-- Chart + Category Breakdown -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
    <div class="card">
        <h4 style="margin: 0 0 1rem 0;"><i class="fas fa-chart-bar" style="color: var(--primary);"></i> <?php echo __('reports.chart_title'); ?> (<?php echo $filter_year; ?>)</h4>
        <canvas id="revenueChart" style="height: 300px;"></canvas>
    </div>
    <div class="card">
        <h4 style="margin: 0 0 1rem 0;"><i class="fas fa-tags" style="color: #f59e0b;"></i> <?php echo __('reports.breakdown'); ?> T<?php echo $filter_month; ?></h4>
        <?php if (empty($categories)): ?>
        <div style="text-align: center; padding: 2rem; color: #94a3b8;">
            <i class="fas fa-inbox" style="font-size: 2rem;"></i>
            <p><?php echo __('reports.no_data'); ?></p>
        </div>
        <?php else: ?>
        <?php foreach ($categories as $cat): ?>
        <div class="category-chip" style="background: <?php echo $cat['type'] === 'income' ? '#ecfdf5' : '#fef2f2'; ?>; color: <?php echo $cat['type'] === 'income' ? '#059669' : '#dc2626'; ?>; width: 100%; justify-content: space-between; box-sizing: border-box;">
            <span><i class="fas <?php echo $cat['type'] === 'income' ? 'fa-plus-circle' : 'fa-minus-circle'; ?>"></i> <?php echo e(ucfirst($cat['category'])); ?></span>
            <strong><?php echo format_money($cat['total']); ?></strong>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Transactions Table -->
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h3 style="margin: 0;"><i class="fas fa-list-alt"></i> <?php echo __('reports.history'); ?> (<?php echo count($transactions); ?> <?php echo __('reports.records'); ?>)</h3>
    </div>
    <?php if (empty($transactions)): ?>
    <div style="text-align: center; padding: 3rem; color: #94a3b8;">
        <i class="fas fa-receipt" style="font-size: 3rem; margin-bottom: 1rem;"></i>
        <p style="font-weight: 700;"><?php echo __('reports.no_data'); ?></p>
    </div>
    <?php else: ?>
    <table class="table" style="width: 100%;">
        <thead>
            <tr style="text-align: left; border-bottom: 2px solid var(--border-color);">
                <th style="padding: 0.75rem;"><?php echo __('reports.time'); ?></th>
                <th style="padding: 0.75rem;"><?php echo __('reports.type'); ?></th>
                <th style="padding: 0.75rem;"><?php echo __('reports.category'); ?></th>
                <th style="padding: 0.75rem;"><?php echo __('reports.amount'); ?></th>
                <th style="padding: 0.75rem;"><?php echo __('reports.creator'); ?></th>
                <th style="padding: 0.75rem;"><?php echo __('reports.description'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($transactions as $tx): ?>
            <tr style="border-bottom: 1px solid var(--border-color);">
                <td style="padding: 0.75rem; white-space: nowrap; font-size: 0.85rem;"><?php echo date('d/m/Y H:i', strtotime($tx['transaction_date'])); ?></td>
                <td style="padding: 0.75rem;">
                    <span style="display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.2rem 0.6rem; border-radius: 50px; font-size: 0.75rem; font-weight: 800; background: <?php echo $tx['type'] === 'income' ? '#ecfdf5' : '#fef2f2'; ?>; color: <?php echo $tx['type'] === 'income' ? '#059669' : '#dc2626'; ?>;">
                        <i class="fas <?php echo $tx['type'] === 'income' ? 'fa-arrow-up' : 'fa-arrow-down'; ?>"></i>
                        <?php echo $tx['type'] === 'income' ? __('reports.income_label') : __('reports.expense_label'); ?>
                    </span>
                </td>
                <td style="padding: 0.75rem; font-weight: 600;"><?php echo e(ucfirst($tx['category'])); ?></td>
                <td style="padding: 0.75rem; font-weight: 800; color: <?php echo $tx['type'] === 'income' ? '#059669' : '#dc2626'; ?>;">
                    <?php echo ($tx['type'] === 'income' ? '+' : '-') . format_money($tx['amount']); ?>
                </td>
                <td style="padding: 0.75rem; font-size: 0.85rem; color: var(--text-muted);"><?php echo e($tx['creator_name'] ?? '—'); ?></td>
                <td style="padding: 0.75rem; font-size: 0.85rem; color: var(--text-muted); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo e($tx['description'] ?? ''); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<script>
// Revenue Chart
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
                backgroundColor: 'rgba(16, 185, 129, 0.7)',
                borderColor: '#10b981',
                borderWidth: 2,
                borderRadius: 6
            },
            {
                label: 'Chi (-)',
                data: monthlyData.map(function(d) { return d.expense; }),
                backgroundColor: 'rgba(239, 68, 68, 0.7)',
                borderColor: '#ef4444',
                borderWidth: 2,
                borderRadius: 6
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
                labels: { usePointStyle: true, padding: 20, font: { weight: '700' } }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value >= 1000000 ? (value / 1000000).toFixed(1) + 'tr' : value >= 1000 ? (value / 1000).toFixed(0) + 'k' : value;
                    }
                },
                grid: { color: 'rgba(0,0,0,0.03)' }
            },
            x: { grid: { display: false } }
        }
    }
});
</script>

<?php require_once '../../templates/footer.php'; ?>
