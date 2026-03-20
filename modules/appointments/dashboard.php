<?php
// modules/appointments/dashboard.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

$page_title = 'Thống kê Lịch hẹn';
$current_page = 'appointments';
require_once '../../templates/header.php';

$db = getDB();

// Period Filter
$period = $_GET['period'] ?? 'month';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
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
    'scheduled' => ['label' => 'Đã hẹn', 'color' => '#64748b'],
    'confirmed' => ['label' => 'Đã xác nhận', 'color' => '#3b82f6'],
    'arrived'   => ['label' => 'Đã đến', 'color' => '#8b5cf6'],
    'completed' => ['label' => 'Hoàn thành', 'color' => '#10b981'],
    'no_show'   => ['label' => 'Khách vắng', 'color' => '#f59e0b'],
    'cancelled' => ['label' => 'Đã hủy', 'color' => '#ef4444']
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
    'consultation' => 'Tư vấn',
    'treatment'    => 'Điều trị',
    're_exam'      => 'Tái khám',
    'adjustment'   => 'Nắn chỉnh'
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
            CRM / APPOINTMENTS / THỐNG KÊ
        </div>
        <div class="header-section mb-4">
            <h1 class="page-title" style="font-size: 2rem; font-weight: 800; color: #0f172a;">Phân tích Lịch hẹn</h1>
        </div>

        <!-- Filter Hub -->
        <div class="filter-bar-apt">
            <div class="period-toggle-apt">
                <?php foreach(['today' => 'Hôm nay', 'week' => 'Tuần', 'month' => 'Tháng', 'quarter' => 'Quý', 'year' => 'Năm'] as $p => $l): ?>
                    <a href="?period=<?php echo $p; ?>" class="btn-toggle-apt <?php echo $period === $p ? 'active' : ''; ?>"><?php echo $l; ?></a>
                <?php endforeach; ?>
            </div>
            
            <form method="GET" style="display: flex; align-items: center; gap: 0.8rem;">
                <input type="hidden" name="period" value="custom">
                <div style="display: flex; align-items: center; background: #f8fafc; padding: 0.3rem 0.8rem; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <input type="date" name="start_date" value="<?php echo date('Y-m-d', strtotime($range['start'])); ?>" style="border:none; background:transparent; font-size: 0.9rem; font-weight: 600; color: #1e293b; outline:none;">
                    <span style="padding: 0 0.5rem; color: #94a3b8;"><i class="fas fa-arrow-right"></i></span>
                    <input type="date" name="end_date" value="<?php echo date('Y-m-d', strtotime($range['end'])); ?>" style="border:none; background:transparent; font-size: 0.9rem; font-weight: 600; color: #1e293b; outline:none;">
                </div>
                <button type="submit" class="btn btn-primary" style="padding: 0.7rem 1.5rem; border-radius: 12px; font-weight: 700;">Áp dụng</button>
            </form>
        </div>

        <!-- KPI Stats -->
        <div class="apt-grid-stats">
            <div class="stat-card-apt indigo">
                <i class="fas fa-calendar-alt"></i>
                <span class="stat-tag">Tổng lịch hẹn</span>
                <span class="stat-val"><?php echo number_format($total_apts); ?></span>
                <span class="stat-desc">Tổng số lịch trong khoảng thời gian</span>
            </div>

            <div class="stat-card-apt emerald">
                <i class="fas fa-check-circle"></i>
                <span class="stat-tag">Tỷ lệ Hoàn thành</span>
                <span class="stat-val"><?php echo $completion_rate; ?>%</span>
                <span class="stat-desc"><?php echo number_format($completed_count); ?> khách đã được phục vụ</span>
            </div>

            <div class="stat-card-apt amber">
                <i class="fas fa-user-slash"></i>
                <span class="stat-tag">Tỷ lệ No-show</span>
                <span class="stat-val"><?php echo $noshow_rate; ?>%</span>
                <span class="stat-desc"><?php echo number_format($noshow_count); ?> khách không đến/hủy</span>
            </div>
        </div>

        <!-- Layer 1: Trends & Distribution -->
        <div class="dashboard-grid-apt">
            <div class="card-apt">
                <h3 class="card-title-apt"><i class="fas fa-chart-line"></i> Biến động Lịch hẹn</h3>
                <div class="chart-container-apt">
                    <canvas id="aptTrendChart"></canvas>
                </div>
            </div>
            <div class="card-apt">
                <h3 class="card-title-apt"><i class="fas fa-chart-pie"></i> Trạng thái</h3>
                <div class="chart-container-apt">
                    <canvas id="aptStatusChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Layer 2: Types & Performance -->
        <div class="dashboard-grid-apt">
            <div class="card-apt">
                <h3 class="card-title-apt"><i class="fas fa-tags"></i> Loại hình dịch vụ</h3>
                <div class="chart-container-apt" style="height: 300px;">
                    <canvas id="aptTypeChart"></canvas>
                </div>
            </div>
            <div class="card-apt">
                <h3 class="card-title-apt"><i class="fas fa-user-md"></i> Hiệu suất Bác sĩ</h3>
                <div class="perf-list-apt">
                    <table class="perf-table-apt">
                        <thead>
                            <tr>
                                <th>Bác sĩ</th>
                                <th>Tỷ lệ Xong</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($provider_data as $p): ?>
                                <?php $rate = $p['total'] > 0 ? round(($p['completed'] / $p['total']) * 100, 1) : 0; ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 700; color: #334155;"><?php echo e($p['full_name']); ?></div>
                                        <div style="font-size: 0.75rem; color: #64748b; font-weight: 600;">
                                            <span style="color: var(--primary);"><?php echo $p['completed']; ?></span> / <?php echo $p['total']; ?> lịch
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
            labels: [<?php foreach($status_data as $s) echo "'" . ($status_map[$s['status']]['label'] ?? $s['status']) . "',"; ?>],
            datasets: [{
                data: [<?php foreach($status_data as $s) echo $s['count'] . ","; ?>],
                backgroundColor: [<?php foreach($status_data as $s) echo "'" . ($status_map[$s['status']]['color'] ?? '#cbd5e1') . "',"; ?>],
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
                label: 'Số lịch hẹn',
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
            labels: [<?php foreach($type_data as $t) echo "'" . ($type_map[$t['type']] ?? $t['type']) . "',"; ?>],
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
