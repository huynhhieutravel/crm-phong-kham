<?php
// modules/appointments/dashboard.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

$page_title = __('appointment.dashboard.title');
$current_page = 'appointments';
require_once '../../templates/header.php';

$db = getDB();

// Period Filter
$period = isset($_GET['period']) ? $_GET['period'] : 'month';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$range = get_date_range($period, $start_date, $end_date);

$params = [$range['start'], $range['end']];

// 1. Total Appointments
$stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date BETWEEN ? AND ?");
$stmt->execute($params);
$total_apts = $stmt->fetchColumn();

// 2. Status Breakdown
$stmt = $db->prepare("
    SELECT status, COUNT(*) as count 
    FROM appointments 
    WHERE appointment_date BETWEEN ? AND ?
    GROUP BY status
");
$stmt->execute($params);
$status_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$status_map = [
    'scheduled' => ['label' => __('appointment.status.scheduled'), 'color' => '#64748b'],
    'confirmed' => ['label' => __('appointment.status.confirmed'), 'color' => '#3b82f6'],
    'arrived'   => ['label' => __('appointment.status.arrived'), 'color' => '#8b5cf6'],
    'completed' => ['label' => __('appointment.status.completed'), 'color' => '#10b981'],
    'no_show'   => ['label' => __('appointment.status.no_show'), 'color' => '#f59e0b'],
    'cancelled' => ['label' => __('appointment.status.cancelled'), 'color' => '#ef4444']
];

// 3. Type Breakdown
$stmt = $db->prepare("
    SELECT type, COUNT(*) as count 
    FROM appointments 
    WHERE appointment_date BETWEEN ? AND ?
    GROUP BY type
    ORDER BY count DESC
");
$stmt->execute($params);
$type_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$type_map = [
    'consultation' => __('appointment.type.consultation'),
    'treatment'    => __('appointment.type.treatment'),
    're_exam'      => __('appointment.type.re_exam'),
    'adjustment'   => __('appointment.type.adjustment')
];

// 4. Provider Performance
$stmt = $db->prepare("
    SELECT u.full_name, 
           COUNT(*) as total,
           SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM appointments a
    JOIN users u ON a.doctor_id = u.id
    WHERE a.appointment_date BETWEEN ? AND ?
    GROUP BY u.id
    ORDER BY total DESC
");
$stmt->execute($params);
$provider_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Daily Trend
$stmt = $db->prepare("
    SELECT DATE(appointment_date) as date, COUNT(*) as count
    FROM appointments 
    WHERE appointment_date BETWEEN ? AND ?
    GROUP BY DATE(appointment_date)
    ORDER BY date ASC
");
$stmt->execute($params);
$trend_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Rates
$completed_count = 0;
$noshow_count = 0;
foreach($status_data as $s) {
    if($s['status'] === 'completed') $completed_count = $s['count'];
    if($s['status'] === 'no_show') $noshow_count = $s['count'];
}
$completion_rate = $total_apts > 0 ? round(($completed_count / $total_apts) * 100, 1) : 0;
$noshow_rate = $total_apts > 0 ? round(($noshow_count / $total_apts) * 100, 1) : 0;
?>

<style>
.apt-dashboard-outer { width: 100%; max-width: 1400px; }
.apt-grid-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
@media (max-width: 1024px) { .apt-grid-stats { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 768px) { .apt-grid-stats { grid-template-columns: 1fr; } }

.filter-bar-apt {
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;
    margin-bottom: 2.5rem; padding: 1.5rem; background: white; border-radius: 18px; box-shadow: 0 4px 20px rgba(0,0,0,0.03);
}
.period-toggle-apt { display: flex; background: #f1f5f9; padding: 5px; border-radius: 14px; gap: 4px; }
.btn-toggle-apt {
    padding: 0.7rem 1.5rem; border-radius: 11px; font-size: 0.9rem; font-weight: 600; color: #64748b;
    text-decoration: none; transition: all 0.25s ease; border: none; background: transparent; cursor: pointer;
}
.btn-toggle-apt:hover:not(.active) { background: rgba(255, 255, 255, 0.6); color: var(--primary); }
.btn-toggle-apt.active { background: white; color: var(--primary); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); }

.stat-card-apt {
    padding: 2rem; border-radius: 22px; color: white; position: relative; overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
}
.stat-card-apt:hover { transform: translateY(-6px); box-shadow: 0 20px 30px -10px rgba(0,0,0,0.15); }
.stat-card-apt.indigo { background: linear-gradient(135deg, #6366f1 0%, #818cf8 100%); }
.stat-card-apt.emerald { background: linear-gradient(135deg, #10b981 0%, #34d399 100%); }
.stat-card-apt.amber { background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); }

.stat-card-apt i { position: absolute; right: -10px; bottom: -10px; font-size: 5rem; opacity: 0.15; transform: rotate(-10deg); }
.stat-card-apt .stat-tag { font-size: 0.85rem; font-weight: 600; text-transform: uppercase; opacity: 0.85; margin-bottom: 0.5rem; display: block; }
.stat-card-apt .stat-val { font-size: 2.5rem; font-weight: 800; display: block; }
.stat-card-apt .stat-desc { font-size: 0.8rem; margin-top: 0.8rem; opacity: 0.8; display: block; font-weight: 500; }

.dashboard-grid-apt { display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 2.5rem; }
@media (max-width: 1100px) { .dashboard-grid-apt { grid-template-columns: 1fr; } }

.card-apt { background: white; border-radius: 24px; padding: 2rem; box-shadow: 0 4px 24px rgba(0,0,0,0.03); }
.card-title-apt { font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-bottom: 2rem; display: flex; align-items: center; gap: 0.8rem; }
.card-title-apt i { color: var(--primary); }
.chart-container-apt { position: relative; height: 350px; width: 100%; }

.perf-table-apt { width: 100%; border-collapse: separate; border-spacing: 0; }
.perf-table-apt th { padding: 1.25rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase; background: #f8fafc; border-bottom: 2px solid #f1f5f9; }
.perf-table-apt td { padding: 1.25rem 1rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
</style>

<div class="content-body">
    <div class="apt-dashboard-outer">
        <div class="breadcrumb mb-2" style="font-size: 0.75rem; font-weight: 700; letter-spacing: 1px; color: #94a3b8;">
            <?php echo __('appointment.dashboard.breadcrumb'); ?>
        </div>
        <div class="header-section mb-4">
            <h1 class="page-title" style="font-size: 2rem; font-weight: 800; color: #0f172a;"><?php echo __('appointment.dashboard.heading'); ?></h1>
        </div>

        <!-- Filter Hub -->
        <form method="GET" class="filter-bar-apt">
            <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                <div class="period-toggle-apt">
                    <input type="hidden" name="period" id="periodInput" value="<?php echo e($period); ?>">
                    
                    <a href="#" class="btn-toggle-apt <?php echo $period == 'today' ? 'active' : ''; ?>" onclick="setPeriod(event, 'today')"><?php echo __('common.today'); ?></a>
                    <a href="#" class="btn-toggle-apt <?php echo $period == 'week' ? 'active' : ''; ?>" onclick="setPeriod(event, 'week')"><?php echo __('common.week'); ?></a>
                    <a href="#" class="btn-toggle-apt <?php echo $period == 'month' ? 'active' : ''; ?>" onclick="setPeriod(event, 'month')"><?php echo __('common.month'); ?></a>
                    <a href="#" class="btn-toggle-apt <?php echo $period == 'quarter' ? 'active' : ''; ?>" onclick="setPeriod(event, 'quarter')"><?php echo __('common.quarter'); ?></a>
                    <a href="#" class="btn-toggle-apt <?php echo $period == 'year' ? 'active' : ''; ?>" onclick="setPeriod(event, 'year')"><?php echo __('common.year'); ?></a>
                    <a href="#" class="btn-toggle-apt <?php echo $period == 'custom' ? 'active' : ''; ?>" onclick="setPeriod(event, 'custom')"><?php echo __('common.custom'); ?></a>
                </div>

                <?php if($period === 'today' || $period === 'week' || $period === 'month' || $period === 'quarter' || $period === 'year'): ?>
                    <div style="display: flex; gap: 0.25rem; align-items: center; margin-left: -0.5rem;">
                        <?php if($period === 'today'): ?>
                            <input type="date" name="sel_date" style="border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.8rem; padding: 0.3rem 0.5rem; color: var(--text-main); outline: none; width: auto;" value="<?php echo isset($_GET['sel_date']) ? e($_GET['sel_date']) : date('Y-m-d'); ?>" onchange="this.form.submit()">
                        <?php endif; ?>

                        <?php if($period === 'week'): ?>
                            <input type="week" name="sel_week" style="border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.8rem; padding: 0.3rem 0.5rem; color: var(--text-main); outline: none; width: auto;" value="<?php echo isset($_GET['sel_week']) ? e($_GET['sel_week']) : date('Y').'-W'.date('W'); ?>" onchange="this.form.submit()">
                        <?php endif; ?>

                        <?php if($period === 'month'): ?>
                            <select name="sel_month" style="border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.8rem; padding: 0.35rem 0.5rem; color: var(--text-main); outline: none; width: auto;" onchange="this.form.submit()">
                                <?php for($m=1; $m<=12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo (isset($_GET['sel_month']) && $_GET['sel_month'] == $m) || (!isset($_GET['sel_month']) && $m == date('n')) ? 'selected' : ''; ?>>Tháng <?php echo $m; ?></option>
                                <?php endfor; ?>
                            </select>
                        <?php endif; ?>
                        
                        <?php if($period === 'quarter'): ?>
                            <select name="sel_quarter" style="border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.8rem; padding: 0.35rem 0.5rem; color: var(--text-main); outline: none; width: auto;" onchange="this.form.submit()">
                                <?php for($q=1; $q<=4; $q++): ?>
                                    <option value="<?php echo $q; ?>" <?php echo (isset($_GET['sel_quarter']) && $_GET['sel_quarter'] == $q) || (!isset($_GET['sel_quarter']) && $q == ceil(date('n')/3)) ? 'selected' : ''; ?>>Quý <?php echo $q; ?></option>
                                <?php endfor; ?>
                            </select>
                        <?php endif; ?>

                        <?php if($period === 'month' || $period === 'quarter' || $period === 'year'): ?>
                            <select name="sel_year" style="border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.8rem; padding: 0.35rem 0.5rem; color: var(--text-main); outline: none; width: auto;" onchange="this.form.submit()">
                                <?php for($y=date('Y')-2; $y<=date('Y')+1; $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo (isset($_GET['sel_year']) && $_GET['sel_year'] == $y) || (!isset($_GET['sel_year']) && $y == date('Y')) ? 'selected' : ''; ?>>Năm <?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div id="customDates" style="display: <?php echo $period == 'custom' ? 'flex' : 'none'; ?>; align-items: center; gap: 0.8rem;">
                <div style="display: flex; align-items: center; background: #f8fafc; padding: 0.3rem 0.8rem; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <input type="date" name="start_date" id="start_date" value="<?php echo date('Y-m-d', strtotime($range['start'])); ?>" style="border:none; background:transparent; font-size: 0.9rem; font-weight: 600; color: #1e293b; outline:none;">
                    <span style="padding: 0 0.5rem; color: #94a3b8;"><i class="fas fa-arrow-right"></i></span>
                    <input type="date" name="end_date" id="end_date" value="<?php echo date('Y-m-d', strtotime($range['end'])); ?>" style="border:none; background:transparent; font-size: 0.9rem; font-weight: 600; color: #1e293b; outline:none;">
                </div>
                <button type="submit" class="btn btn-primary" style="padding: 0.7rem 1.5rem; border-radius: 12px; font-weight: 700;"><?php echo __('appointment.filter.apply'); ?></button>
            </div>
        </form>

<script>
function setPeriod(event, p) {
    if (event) event.preventDefault();
    document.getElementById('periodInput').value = p;
    if (p !== 'custom') {
        const s = document.getElementById('start_date');
        const e = document.getElementById('end_date');
        if (s) s.value = '';
        if (e) e.value = '';
        event.target.closest('form').submit();
    } else {
        document.getElementById('customDates').style.display = 'flex';
        document.querySelectorAll('.btn-toggle-apt').forEach(btn => btn.classList.remove('active'));
        if (event && event.target) {
            event.target.classList.add('active');
        }
    }
}
</script>

        <!-- KPI Stats -->
        <div class="apt-grid-stats">
            <div class="stat-card-apt indigo">
                <i class="fas fa-calendar-alt"></i>
                <span class="stat-tag"><?php echo __('appointment.dashboard.total_appts'); ?></span>
                <span class="stat-val"><?php echo number_format($total_apts); ?></span>
                <span class="stat-desc"><?php echo __('appointment.dashboard.total_desc'); ?></span>
            </div>

            <div class="stat-card-apt emerald">
                <i class="fas fa-check-circle"></i>
                <span class="stat-tag"><?php echo __('appointment.dashboard.completion_rate'); ?></span>
                <span class="stat-val"><?php echo $completion_rate; ?>%</span>
                <span class="stat-desc"><?php echo number_format($completed_count); ?> <?php echo __('appointment.dashboard.served_desc'); ?></span>
            </div>

            <div class="stat-card-apt amber">
                <i class="fas fa-user-slash"></i>
                <span class="stat-tag"><?php echo __('appointment.dashboard.noshow_rate'); ?></span>
                <span class="stat-val"><?php echo $noshow_rate; ?>%</span>
                <span class="stat-desc"><?php echo number_format($noshow_count); ?> <?php echo __('appointment.dashboard.noshow_desc'); ?></span>
            </div>
        </div>

        <!-- Layer 1: Trends & Distribution -->
        <div class="dashboard-grid-apt">
            <div class="card-apt">
                <h3 class="card-title-apt"><i class="fas fa-chart-line"></i> <?php echo __('appointment.dashboard.trend_title'); ?></h3>
                <div class="chart-container-apt">
                    <canvas id="aptTrendChart"></canvas>
                </div>
            </div>
            <div class="card-apt">
                <h3 class="card-title-apt"><i class="fas fa-chart-pie"></i> <?php echo __('appointment.status'); ?></h3>
                <div class="chart-container-apt">
                    <canvas id="aptStatusChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Layer 2: Types & Performance -->
        <div class="dashboard-grid-apt">
            <div class="card-apt">
                <h3 class="card-title-apt"><i class="fas fa-tags"></i> <?php echo __('appointment.dashboard.type_title'); ?></h3>
                <div class="chart-container-apt" style="height: 300px;">
                    <canvas id="aptTypeChart"></canvas>
                </div>
            </div>
            <div class="card-apt">
                <h3 class="card-title-apt"><i class="fas fa-user-md"></i> <?php echo __('appointment.dashboard.perf_title'); ?></h3>
                <div class="perf-list-apt">
                    <table class="perf-table-apt">
                        <thead>
                            <tr>
                                <th><?php echo __('appointment.doctor'); ?></th>
                                <th><?php echo __('appointment.dashboard.completion_col'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($provider_data as $p): ?>
                                <?php $rate = $p['total'] > 0 ? round(($p['completed'] / $p['total']) * 100, 1) : 0; ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 700; color: #334155;"><?php echo e($p['full_name']); ?></div>
                                        <div style="font-size: 0.75rem; color: #64748b; font-weight: 600;">
                                            <span style="color: var(--primary);"><?php echo $p['completed']; ?></span> / <?php echo $p['total']; ?> <?php echo __('appointment.dashboard.appt_unit'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 0.8rem;">
                                            <div style="flex: 1; height: 8px; background: #f1f5f9; border-radius: 10px; overflow: hidden;">
                                                <div style="height: 100%; background: var(--primary); width: <?php echo $rate; ?>%"></div>
                                            </div>
                                            <span style="font-size: 0.85rem; font-weight: 800; color: #1e293b; min-width: 45px;"><?php echo $rate; ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
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
    new Chart(document.getElementById('aptStatusChart').getContext('2d'), {
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
            cutout: '75%', responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 25, font: { weight: '700', size: 11 } } } }
        }
    });

    // 2. Trend Chart
    const trendCtx = document.getElementById('aptTrendChart').getContext('2d');
    const gradient = trendCtx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(99, 102, 241, 0.2)');
    gradient.addColorStop(1, 'rgba(99, 102, 241, 0)');

    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: [<?php foreach($trend_data as $t) echo "'" . date('d/m', strtotime($t['date'])) . "',"; ?>],
            datasets: [{
                label: '<?php echo __('appointment.dashboard.chart_label'); ?>',
                data: [<?php foreach($trend_data as $t) echo $t['count'] . ","; ?>],
                borderColor: '#6366f1', borderWidth: 4, backgroundColor: gradient, fill: true, tension: 0.4,
                pointRadius: 0, pointHoverRadius: 6, pointHoverBackgroundColor: '#6366f1', pointHoverBorderColor: '#fff', pointHoverBorderWidth: 3
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            interaction: { intersect: false, mode: 'index' },
            scales: {
                y: { beginAtZero: true, grid: { borderDash: [5, 5], color: '#f1f5f9' }, ticks: { stepSize: 1 } },
                x: { grid: { display: false } }
            }
        }
    });

    // 3. Type Chart
    new Chart(document.getElementById('aptTypeChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: [<?php foreach($type_data as $t) echo "'" . (isset($type_map[$t['type']]) ? $type_map[$t['type']] : $t['type']) . "',"; ?>],
            datasets: [{
                data: [<?php foreach($type_data as $t) echo $t['count'] . ","; ?>],
                backgroundColor: '#8b5cf6', borderRadius: 10, barThickness: 25
            }]
        },
        options: {
            indexAxis: 'y', responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, grid: { display: false } },
                y: { grid: { display: false }, ticks: { font: { weight: '600' } } }
            }
        }
    });
});
</script>

<?php require_once '../../templates/footer.php'; ?>
