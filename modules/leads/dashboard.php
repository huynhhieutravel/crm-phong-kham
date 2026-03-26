<?php
// modules/leads/dashboard.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('view_leads');

$page_title = __('leads.dashboard.title');
$current_page = 'leads_dashboard';
require_once '../../templates/header.php';

$db = getDB();

// Period Filter Logic
$period = isset($_GET['period']) ? $_GET['period'] : 'month';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$range = get_date_range($period, $start_date, $end_date);
$params = [$range['start'], $range['end']];

// 1. Total Leads
$stmt = $db->prepare("SELECT COUNT(*) FROM leads WHERE created_at BETWEEN ? AND ?");
$stmt->execute($params);
$total_leads = $stmt->fetchColumn();

// 2. Status Breakdown
$stmt = $db->prepare("
    SELECT status, COUNT(*) as count 
    FROM leads 
    WHERE created_at BETWEEN ? AND ?
    GROUP BY status
");
$stmt->execute($params);
$status_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$status_map = [
    'new' => ['label' => 'Tiếp cận mới', 'color' => '#f43f5e'],
    'contacted' => ['label' => 'Đang chăm sóc', 'color' => '#3b82f6'],
    'scheduled' => ['label' => 'Đã đặt lịch', 'color' => '#8b5cf6'],
    'converted' => ['label' => 'Đã chốt xong', 'color' => '#10b981'],
    'cancelled' => ['label' => 'Tạm ngưng', 'color' => '#94a3b8']
];

// 3. Source Breakdown
$stmt = $db->prepare("
    SELECT source, COUNT(*) as count 
    FROM leads 
    WHERE created_at BETWEEN ? AND ?
    GROUP BY source
    ORDER BY count DESC
");
$stmt->execute($params);
$source_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Daily Trend
$stmt = $db->prepare("
    SELECT DATE(created_at) as date, COUNT(*) as count
    FROM leads 
    WHERE created_at BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$stmt->execute($params);
$trend_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Conversion Rate
$converted_count = 0;
foreach($status_data as $s) if($s['status'] === 'converted') $converted_count = $s['count'];
$conversion_rate = $total_leads > 0 ? round(($converted_count / $total_leads) * 100, 1) : 0;

// Calculate Care Rate (Contacted + Scheduled + Converted)
$care_count = 0;
foreach($status_data as $s) if(in_array($s['status'], ['contacted', 'scheduled', 'converted'])) $care_count += $s['count'];
$care_rate = $total_leads > 0 ? round(($care_count / $total_leads) * 100, 1) : 0;

$cancelled_count = 0;
foreach($status_data as $s) if($s['status'] === 'cancelled') $cancelled_count = $s['count'];
?>

<style>
.grid { display: grid; gap: 1.5rem; }
.grid-4 { grid-template-columns: repeat(4, 1fr); }
.kpi-card { background: white; border-radius: 20px; padding: 1.5rem; display: flex; flex-direction: column; align-items: flex-start; box-shadow: 0 4px 20px rgba(0,0,0,0.03); transition: transform 0.2s; position: relative; overflow: hidden; }
.kpi-card:hover { transform: translateY(-5px); }
.kpi-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 1rem; }
.kpi-label { font-size: 0.85rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.05em; }
.kpi-value { font-size: 2rem; font-weight: 900; color: #1e293b; line-height: 1; }

.filter-btn-group { display: flex; align-items: center; gap: 0.5rem; background: #f8fafc; padding: 0.5rem; border-radius: 12px; }
.filter-label { font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase; margin-right: 0.5rem; }
.filter-btn { padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.85rem; font-weight: 600; color: #64748b; text-decoration: none; transition: all 0.2s; white-space: nowrap; }
.filter-btn:hover { background: #e2e8f0; color: #1e293b; }
.filter-btn.active { background: #6366f1; color: white; box-shadow: 0 4px 10px rgba(99,102,241,0.3); }

.custom-range-box { display: flex; align-items: center; gap: 0.5rem; background: #f8fafc; padding: 0.5rem; border-radius: 12px; }
.custom-range-input { border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.5rem; font-size: 0.85rem; outline: none; background: white; color: #1e293b; font-weight: 500; }

@media (max-width: 1024px) { 
    .grid-4 { grid-template-columns: repeat(2, 1fr); } 
}
@media (max-width: 768px) { 
    .grid-4 { grid-template-columns: 1fr; } 
    div[style*="1.8fr 1.2fr"] { grid-template-columns: 1fr !important; } 
}
</style>

<div class="leads-dashboard-outer" style="max-width: 1400px; margin: 0 auto; padding: 1.5rem;">
    <div class="page-header" style="margin-bottom: 2rem;">
    <div>
        <h1 class="page-title"><?php echo __('leads.dashboard.title'); ?></h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;">
            <?php echo __('leads.dashboard.subtitle'); ?>
        </p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-list"></i> <?php echo __('leads.dashboard.btn_list'); ?>
        </a>
        <a href="consultant_stats.php" class="btn btn-primary" style="background: linear-gradient(135deg, #4f46e5, #6366f1); border: none;">
            <i class="fas fa-user-tie"></i> <?php echo __('leads.dashboard.btn_consultant_stats'); ?>
        </a>
    </div>
</div>
    <!-- Adaptive Filter Hub -->
    <form method="GET" class="filter-bar-leads" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 2.5rem; padding: 1.5rem; background: white; border-radius: 18px; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
        <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
        <div class="filter-btn-group">
            <span class="filter-label" style="margin-bottom: 0; margin-right: 0.25rem;"><?php echo __('leads.index.filter_time'); ?></span>
            <input type="hidden" name="period" id="periodInput" value="<?php echo e($period); ?>">
            <a href="#" class="filter-btn <?php echo $period == '' ? 'active' : ''; ?>" onclick="setPeriod(event, '')"><?php echo __('common.all'); ?></a>
            <a href="#" class="filter-btn <?php echo $period == 'today' ? 'active' : ''; ?>" onclick="setPeriod(event, 'today')"><?php echo __('filter.today'); ?></a>
            <a href="#" class="filter-btn <?php echo $period == 'week' ? 'active' : ''; ?>" onclick="setPeriod(event, 'week')"><?php echo __('filter.week'); ?></a>
            <a href="#" class="filter-btn <?php echo $period == 'month' ? 'active' : ''; ?>" onclick="setPeriod(event, 'month')"><?php echo __('filter.month'); ?></a>
            <a href="#" class="filter-btn <?php echo $period == 'quarter' ? 'active' : ''; ?>" onclick="setPeriod(event, 'quarter')"><?php echo __('filter.quarter'); ?></a>
            <a href="#" class="filter-btn <?php echo $period == 'year' ? 'active' : ''; ?>" onclick="setPeriod(event, 'year')"><?php echo __('filter.year'); ?></a>
            <a href="#" class="filter-btn <?php echo $period == 'custom' ? 'active' : ''; ?>" onclick="setPeriod(event, 'custom')"><?php echo __('filter.custom'); ?></a>
        </div>

        <?php if($period === 'month' || $period === 'quarter' || $period === 'year'): ?>
            <div style="display: flex; gap: 0.5rem; align-items: center; background: #f8fafc; padding: 0.3rem 0.5rem; border-radius: 10px; border: 1px solid #e2e8f0;">
                <?php if($period === 'month'): ?>
                    <select name="sel_month" class="custom-range-input" style="width: auto; padding: 0.2rem 0.5rem;" onchange="this.form.submit()">
                        <?php for($m=1; $m<=12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo (isset($_GET['sel_month']) && $_GET['sel_month'] == $m) || (!isset($_GET['sel_month']) && $m == date('n')) ? 'selected' : ''; ?>>Tháng <?php echo $m; ?></option>
                        <?php endfor; ?>
                    </select>
                <?php endif; ?>
                
                <?php if($period === 'quarter'): ?>
                    <select name="sel_quarter" class="custom-range-input" style="width: auto; padding: 0.2rem 0.5rem;" onchange="this.form.submit()">
                        <?php for($q=1; $q<=4; $q++): ?>
                            <option value="<?php echo $q; ?>" <?php echo (isset($_GET['sel_quarter']) && $_GET['sel_quarter'] == $q) || (!isset($_GET['sel_quarter']) && $q == ceil(date('n')/3)) ? 'selected' : ''; ?>>Quý <?php echo $q; ?></option>
                        <?php endfor; ?>
                    </select>
                <?php endif; ?>

                <select name="sel_year" class="custom-range-input" style="width: auto; padding: 0.2rem 0.5rem;" onchange="this.form.submit()">
                    <?php for($y=date('Y')-2; $y<=date('Y')+1; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo (isset($_GET['sel_year']) && $_GET['sel_year'] == $y) || (!isset($_GET['sel_year']) && $y == date('Y')) ? 'selected' : ''; ?>>Năm <?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        <?php endif; ?>

        <div id="customDates" style="display: <?php echo $period == 'custom' ? 'flex' : 'none'; ?>; gap: 0.5rem; align-items: center;">
            <div class="custom-range-box">
                <input type="date" name="start_date" class="custom-range-input" value="<?php echo e($start_date); ?>">
                <span style="color: #94a3b8; font-size: 0.8rem;">→</span>
                <input type="date" name="end_date" class="custom-range-input" value="<?php echo e($end_date); ?>">
            </div>
            <button type="submit" class="btn btn-primary btn-sm" style="height: 32px; padding: 0 0.75rem; border-radius: 8px;"><?php echo __('common.apply'); ?></button>
        </div>
    </div>
    </form>
    
    <script>
    function setPeriod(event, p) {
        event.preventDefault();
        document.getElementById('periodInput').value = p;
        if (p !== 'custom') {
            const s = document.querySelector('input[name="start_date"]');
            const e = document.querySelector('input[name="end_date"]');
            if (s) s.value = '';
            if (e) e.value = '';
            event.target.closest('form').submit();
        } else {
            document.getElementById('customDates').style.display = 'flex';
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
        }
    }
    </script>

<div class="grid grid-4" style="margin-bottom: 2rem;">
    <!-- KPI Cards -->
    <div class="card kpi-card">
        <div class="kpi-icon" style="background: rgba(79, 70, 229, 0.1); color: #4f46e5;"><i class="fas fa-users"></i></div>
        <div class="kpi-label"><?php echo __('leads.dashboard.kpi_total'); ?></div>
        <div class="kpi-value"><?php echo $total_leads; ?></div>
    </div>
    <div class="card kpi-card">
        <div class="kpi-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;"><i class="fas fa-user-check"></i></div>
        <div class="kpi-label"><?php echo __('leads.dashboard.kpi_converted'); ?></div>
        <div class="kpi-value" style="color: #10b981;"><?php echo $converted_count; ?></div>
    </div>
    <div class="card kpi-card">
        <div class="kpi-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;"><i class="fas fa-percentage"></i></div>
        <div class="kpi-label"><?php echo __('leads.dashboard.kpi_rate'); ?></div>
        <div class="kpi-value" style="color: #f59e0b;"><?php echo $conversion_rate; ?>%</div>
    </div>
    <div class="card kpi-card">
        <div class="kpi-icon" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;"><i class="fas fa-user-slash"></i></div>
        <div class="kpi-label"><?php echo __('leads.dashboard.kpi_cancelled'); ?></div>
        <div class="kpi-value" style="color: #ef4444;"><?php echo $cancelled_count; ?></div>
    </div>
</div>
    </div>

    <!-- Charts Layout -->
    <div style="display: grid; grid-template-columns: 1.8fr 1.2fr; gap: 1.5rem; margin-bottom: 2.5rem;">
        <div style="background: white; border-radius: 24px; padding: 2rem; box-shadow: 0 4px 24px rgba(0,0,0,0.03);">
            <h3 style="font-size: 1.2rem; font-weight: 800; color: #1e293b; margin-bottom: 2rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-chart-line" style="color: #6366f1;"></i> Tăng trưởng Lead Marketing
            </h3>
            <div style="height: 380px;">
                <canvas id="trendChart"></canvas>
            </div>
        </div>
        <div style="background: white; border-radius: 24px; padding: 2rem; box-shadow: 0 4px 24px rgba(0,0,0,0.03);">
            <h3 style="font-size: 1.2rem; font-weight: 800; color: #1e293b; margin-bottom: 2rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-chart-pie" style="color: #f43f5e;"></i> Phân loại trạng thái Lead
            </h3>
            <div style="height: 380px;">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr; gap: 1.5rem;">
        <div style="background: white; border-radius: 24px; padding: 2rem; box-shadow: 0 4px 24px rgba(0,0,0,0.03);">
            <h3 style="font-size: 1.2rem; font-weight: 800; color: #1e293b; margin-bottom: 2rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-bullhorn" style="color: #a855f7;"></i> Phân bổ theo nguồn thu thập
            </h3>
            <div style="height: 300px;">
                <canvas id="sourceChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#64748b';

    // 1. Status Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: [<?php foreach($status_data as $s) echo "'" . (isset($status_map[$s['status']]['label']) ? $status_map[$s['status']]['label'] : $s['status']) . "',"; ?>],
            datasets: [{
                data: [<?php foreach($status_data as $s) echo $s['count'] . ","; ?>],
                backgroundColor: [<?php foreach($status_data as $s) echo "'" . (isset($status_map[$s['status']]['color']) ? $status_map[$s['status']]['color'] : '#cbd5e1') . "',"; ?>],
                borderWidth: 6,
                borderColor: '#ffffff',
                hoverOffset: 15
            }]
        },
        options: {
            cutout: '75%',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 25, font: { weight: '700', size: 11 } } } }
        }
    });

    // 2. Trend Chart
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    const gradient = trendCtx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(99, 102, 241, 0.2)');
    gradient.addColorStop(1, 'rgba(99, 102, 241, 0)');

    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: [<?php foreach($trend_data as $t) echo "'" . date('d/m', strtotime($t['date'])) . "',"; ?>],
            datasets: [{
                label: 'Lead mới',
                data: [<?php foreach($trend_data as $t) echo $t['count'] . ","; ?>],
                borderColor: '#6366f1',
                borderWidth: 4,
                backgroundColor: gradient,
                fill: true,
                tension: 0.4,
                pointRadius: 0,
                pointHoverRadius: 6,
                pointHoverBackgroundColor: '#6366f1',
                pointHoverBorderColor: '#fff',
                pointHoverBorderWidth: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            interaction: { intersect: false, mode: 'index' },
            scales: {
                y: { beginAtZero: true, grid: { borderDash: [5, 5], color: '#f1f5f9' }, ticks: { stepSize: 1 } },
                x: { grid: { display: false } }
            }
        }
    });

    // 3. Source Chart
    const sourceCtx = document.getElementById('sourceChart').getContext('2d');
    new Chart(sourceCtx, {
        type: 'bar',
        data: {
            labels: [<?php foreach($source_data as $s) echo "'" . ($s['source'] ?: 'Không rõ') . "',"; ?>],
            datasets: [{
                data: [<?php foreach($source_data as $s) echo $s['count'] . ","; ?>],
                backgroundColor: '#a855f7',
                borderRadius: 10,
                barThickness: 25
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, grid: { display: false } },
                y: { grid: { display: false }, ticks: { font: { weight: '700' } } }
            }
        }
    });
});
</script>

<?php require_once '../../templates/footer.php'; ?>
