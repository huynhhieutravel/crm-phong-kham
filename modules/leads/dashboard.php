<?php
// modules/leads/dashboard.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

$page_title = 'Thống kê Lead Marketing';
$current_page = 'leads';
require_once '../../templates/header.php';

$db = getDB();

// Period Filter
$period = $_GET['period'] ?? 'month';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
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
    'new' => ['label' => 'Mới', 'color' => '#3b82f6'],
    'contacted' => ['label' => 'Đã liên hệ', 'color' => '#f59e0b'],
    'scheduled' => ['label' => 'Đã hẹn', 'color' => '#8b5cf6'],
    'converted' => ['label' => 'Đã chốt (Sale)', 'color' => '#10b981'],
    'cancelled' => ['label' => 'Hủy/Không nhu cầu', 'color' => '#ef4444']
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

// 4. Consultant Performance
$stmt = $db->prepare("
    SELECT u.full_name, 
           COUNT(*) as total,
           SUM(CASE WHEN l.status = 'converted' THEN 1 ELSE 0 END) as converted
    FROM leads l
    JOIN users u ON l.consultant_id = u.id
    WHERE l.created_at BETWEEN ? AND ?
    GROUP BY u.id
    ORDER BY total DESC
");
$stmt->execute($params);
$consultant_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Daily Trend
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
?>

<style>
/* Custom Premium Grid System for Lead Dashboard */
.leads-dashboard-outer {
    width: 100%;
    max-width: 1400px;
}

.leads-grid-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}

@media (max-width: 1024px) {
    .leads-grid-stats { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
    .leads-grid-stats { grid-template-columns: 1fr; }
}

/* Premium Filter Buttons */
.filter-bar-leads {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 2.5rem;
    padding: 1.5rem;
    background: white;
    border-radius: 18px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.03);
}

.period-toggle-leads {
    display: flex;
    background: #f1f5f9;
    padding: 5px;
    border-radius: 14px;
    gap: 4px;
}

.btn-toggle-lead {
    padding: 0.7rem 1.5rem;
    border-radius: 11px;
    font-size: 0.9rem;
    font-weight: 600;
    color: #64748b;
    text-decoration: none;
    transition: all 0.25s ease;
    border: none;
    background: transparent;
    cursor: pointer;
}

.btn-toggle-lead:hover:not(.active) {
    background: rgba(255, 255, 255, 0.6);
    color: var(--primary);
}

.btn-toggle-lead.active {
    background: white;
    color: var(--primary);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

/* Local Stat Cards */
.stat-card-l {
    padding: 2rem;
    border-radius: 22px;
    color: white;
    position: relative;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
}

.stat-card-l:hover { transform: translateY(-6px); box-shadow: 0 20px 30px -10px rgba(0,0,0,0.15); }

.stat-card-l.pink { background: linear-gradient(135deg, #f43f5e 0%, #fb7185 100%); }
.stat-card-l.green { background: linear-gradient(135deg, #10b981 0%, #34d399 100%); }
.stat-card-l.blue { background: linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%); }

.stat-card-l i {
    position: absolute;
    right: -10px;
    bottom: -10px;
    font-size: 5rem;
    opacity: 0.15;
    transform: rotate(-10deg);
}

.stat-card-l .stat-tag { font-size: 0.85rem; font-weight: 600; text-transform: uppercase; opacity: 0.85; margin-bottom: 0.5rem; display: block; }
.stat-card-l .stat-val { font-size: 2.5rem; font-weight: 800; display: block; }
.stat-card-l .stat-desc { font-size: 0.8rem; margin-top: 0.8rem; opacity: 0.8; display: block; font-weight: 500; }

/* Dashboard Cards Grid */
.dashboard-grid-l {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1.5rem;
    margin-bottom: 2.5rem;
}

@media (max-width: 1100px) {
    .dashboard-grid-l { grid-template-columns: 1fr; }
}

.card-l {
    background: white;
    border-radius: 24px;
    padding: 2rem;
    box-shadow: 0 4px 24px rgba(0,0,0,0.03);
}

.card-title-l {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 0.8rem;
}

.card-title-l i { color: var(--primary); }

.chart-container-l { position: relative; height: 350px; width: 100%; }

/* Performance Table Refined */
.perf-table-l { width: 100%; border-collapse: separate; border-spacing: 0; }
.perf-table-l th { padding: 1.25rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase; background: #f8fafc; border-bottom: 2px solid #f1f5f9; }
.perf-table-l td { padding: 1.25rem 1rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }

.perf-name { font-weight: 700; color: #334155; }
.perf-count { font-weight: 600; color: var(--primary); }
.perf-rate-outer { display: flex; align-items: center; gap: 0.8rem; }
.perf-progress { flex: 1; height: 8px; background: #f1f5f9; border-radius: 10px; overflow: hidden; }
.perf-progress-fill { height: 100%; background: linear-gradient(90deg, var(--primary) 0%, var(--primary-light) 100%); border-radius: 10px; }
.perf-rate-val { font-size: 0.85rem; font-weight: 800; color: #1e293b; min-width: 45px; }
</style>

<div class="content-body">
    <div class="leads-dashboard-outer">
        <div class="breadcrumb mb-2" style="font-size: 0.75rem; font-weight: 700; letter-spacing: 1px; color: #94a3b8;">
            CRM / LEADS / THỐNG KÊ
        </div>
        <div class="header-section mb-4">
            <h1 class="page-title" style="font-size: 2rem; font-weight: 800; color: #0f172a;">Thông số Marketing</h1>
        </div>

        <!-- Adaptive Filter Hub -->
        <div class="filter-bar-leads">
            <div class="period-toggle-leads">
                <a href="?period=today" class="btn-toggle-lead <?php echo $period === 'today' ? 'active' : ''; ?>">Hôm nay</a>
                <a href="?period=week" class="btn-toggle-lead <?php echo $period === 'week' ? 'active' : ''; ?>">Tuần</a>
                <a href="?period=month" class="btn-toggle-lead <?php echo $period === 'month' ? 'active' : ''; ?>">Tháng</a>
                <a href="?period=quarter" class="btn-toggle-lead <?php echo $period === 'quarter' ? 'active' : ''; ?>">Quý</a>
                <a href="?period=year" class="btn-toggle-lead <?php echo $period === 'year' ? 'active' : ''; ?>">Năm</a>
            </div>
            
            <form method="GET" style="display: flex; align-items: center; gap: 0.8rem;">
                <input type="hidden" name="period" value="custom">
                <div style="display: flex; align-items: center; background: #f8fafc; padding: 0.3rem 0.8rem; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>" style="border:none; background:transparent; font-size: 0.9rem; font-weight: 600; color: #1e293b; outline:none;">
                    <span style="padding: 0 0.5rem; color: #94a3b8;"><i class="fas fa-arrow-right"></i></span>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>" style="border:none; background:transparent; font-size: 0.9rem; font-weight: 600; color: #1e293b; outline:none;">
                </div>
                <button type="submit" class="btn btn-primary" style="padding: 0.7rem 1.5rem; border-radius: 12px; font-weight: 700;">Áp dụng</button>
            </form>
        </div>

        <!-- Premium Stats Distribution -->
        <div class="leads-grid-stats">
            <div class="stat-card-l pink">
                <i class="fas fa-users"></i>
                <span class="stat-tag">Tiếp cận mới</span>
                <span class="stat-val"><?php echo number_format($total_leads); ?></span>
                <span class="stat-desc">Tổng số Lead thu thập được</span>
            </div>

            <div class="stat-card-l green">
                <i class="fas fa-check-double"></i>
                <span class="stat-tag">Tỷ lệ Chuyển đổi</span>
                <span class="stat-val"><?php echo $conversion_rate; ?>%</span>
                <span class="stat-desc"><?php echo number_format($converted_count); ?> khách hàng đã đăng ký</span>
            </div>

            <div class="stat-card-l blue">
                <i class="fas fa-headset"></i>
                <span class="stat-tag">Hiệu suất Chăm sóc</span>
                <span class="stat-val"><?php echo $care_rate; ?>%</span>
                <span class="stat-desc"><?php echo number_format($care_count); ?> lead đang được hỗ trợ</span>
            </div>
        </div>

        <!-- Insights Layer 1 -->
        <div class="dashboard-grid-l">
            <div class="card-l">
                <h3 class="card-title-l"><i class="fas fa-chart-line"></i> Tăng trưởng Lead</h3>
                <div class="chart-container-l">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
            <div class="card-l">
                <h3 class="card-title-l"><i class="fas fa-chart-pie"></i> Phân loại trạng thái</h3>
                <div class="chart-container-l">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Insights Layer 2 -->
        <div class="dashboard-grid-l">
            <div class="card-l">
                <h3 class="card-title-l"><i class="fas fa-bullhorn"></i> Phân bổ theo nguồn</h3>
                <div class="chart-container-l" style="height: 300px;">
                    <canvas id="sourceChart"></canvas>
                </div>
            </div>
            <div class="card-l">
                <h3 class="card-title-l"><i class="fas fa-crown"></i> Xếp hạng Tư vấn viên</h3>
                <div class="perf-list-l">
                    <table class="perf-table-l">
                        <thead>
                            <tr>
                                <th>Nhân sự</th>
                                <th>Conversion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($consultant_data as $c): ?>
                                <?php $rate = $c['total'] > 0 ? round(($c['converted'] / $c['total']) * 100, 1) : 0; ?>
                                <tr>
                                    <td>
                                        <div class="perf-name"><?php echo e($c['full_name']); ?></div>
                                        <div style="font-size: 0.75rem; color: #64748b; font-weight: 600;">
                                            <span class="perf-count"><?php echo $c['converted']; ?></span> / <?php echo $c['total']; ?> lead
                                        </div>
                                    </td>
                                    <td>
                                        <div class="perf-rate-outer">
                                            <div class="perf-progress">
                                                <div class="perf-progress-fill" style="width: <?php echo $rate; ?>%"></div>
                                            </div>
                                            <span class="perf-rate-val"><?php echo $rate; ?>%</span>
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
    // Shared Config
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#64748b';

    // 1. Status Chart (Doughnut)
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
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
            cutout: '75%',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, padding: 25, font: { weight: '700', size: 11 } } }
            }
        }
    });

    // 2. Trend Chart (Line)
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

    // 3. Source Chart (Horizontal Bar)
    const sourceCtx = document.getElementById('sourceChart').getContext('2d');
    new Chart(sourceCtx, {
        type: 'bar',
        data: {
            labels: [<?php foreach($source_data as $s) echo "'" . ($s['source'] ?: 'Không rõ') . "',"; ?>],
            datasets: [{
                data: [<?php foreach($source_data as $s) echo $s['count'] . ","; ?>],
                backgroundColor: '#a855f7',
                borderRadius: 10,
                barThickness: 20
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
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
