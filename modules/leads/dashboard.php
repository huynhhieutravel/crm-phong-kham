<?php
// modules/leads/dashboard.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

$page_title = 'Thống kê Lead Marketing';
$current_page = 'leads';
require_once '../../templates/header.php';

$db = getDB();

// Period Filter Logic
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
?>

<div class="leads-dashboard-outer" style="max-width: 1400px; margin: 0 auto; padding: 1.5rem;">
    <!-- Adaptive Filter Hub -->
    <div class="filter-bar-leads" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 2.5rem; padding: 1.5rem; background: white; border-radius: 18px; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
        <div class="period-toggle-leads" style="display: flex; background: #f1f5f9; padding: 5px; border-radius: 14px; gap: 4px;">
            <?php foreach (['today' => 'Hôm nay', 'week' => 'Tuần', 'month' => 'Tháng', 'quarter' => 'Quý', 'year' => 'Năm'] as $p => $label): ?>
                <a href="?period=<?php echo $p; ?>" class="btn-toggle-lead <?php echo $period === $p ? 'active' : ''; ?>" style="padding: 0.7rem 1.5rem; border-radius: 11px; font-size: 0.9rem; font-weight: 600; color: #64748b; text-decoration: none; transition: all 0.25s ease; border: none; background: transparent; cursor: pointer; <?php echo $period === $p ? 'background: white; color: var(--primary); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);' : ''; ?>">
                    <?php echo $label; ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <form method="GET" style="display: flex; align-items: center; gap: 0.8rem;">
            <input type="hidden" name="period" value="custom">
            <div style="display: flex; align-items: center; background: #f8fafc; padding: 0.3rem 0.8rem; border-radius: 12px; border: 1px solid #e2e8f0;">
                <input type="date" name="start_date" value="<?php echo e($range['start_val'] ?? ''); ?>" style="border:none; background:transparent; font-size: 0.9rem; font-weight: 600; color: #1e293b; outline:none;">
                <span style="padding: 0 0.5rem; color: #94a3b8;"><i class="fas fa-arrow-right"></i></span>
                <input type="date" name="end_date" value="<?php echo e($range['end_val'] ?? ''); ?>" style="border:none; background:transparent; font-size: 0.9rem; font-weight: 600; color: #1e293b; outline:none;">
            </div>
            <button type="submit" class="btn btn-primary" style="padding: 0.7rem 1.5rem; border-radius: 12px; font-weight: 700;">Áp dụng</button>
        </form>
    </div>

    <!-- Premium Stats Cards -->
    <div class="leads-grid-stats" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2.5rem;">
        <div class="stat-card" style="padding: 2rem; border-radius: 24px; color: white; background: linear-gradient(135deg, #f43f5e 0%, #fb7185 100%); position: relative; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(244, 63, 94, 0.3);">
            <i class="fas fa-users" style="position: absolute; right: -10px; bottom: -10px; font-size: 5.5rem; opacity: 0.2; transform: rotate(-15deg);"></i>
            <span style="display: block; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.9; margin-bottom: 1rem;">Tiếp cận mới</span>
            <span style="display: block; font-size: 3.5rem; font-weight: 900; line-height: 1;"><?php echo number_format($total_leads); ?></span>
            <span style="display: block; font-size: 0.85rem; margin-top: 1rem; opacity: 0.8; font-weight: 500;">Tổng số Lead thu thập được từ Marketing</span>
        </div>

        <div class="stat-card" style="padding: 2rem; border-radius: 24px; color: white; background: linear-gradient(135deg, #10b981 0%, #34d399 100%); position: relative; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.3);">
            <i class="fas fa-check-circle" style="position: absolute; right: -10px; bottom: -10px; font-size: 5.5rem; opacity: 0.2; transform: rotate(-15deg);"></i>
            <span style="display: block; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.9; margin-bottom: 1rem;">Tỷ lệ Chuyển đổi</span>
            <span style="display: block; font-size: 3.5rem; font-weight: 900; line-height: 1;"><?php echo $conversion_rate; ?><small style="font-size: 1.5rem;">%</small></span>
            <span style="display: block; font-size: 0.85rem; margin-top: 1rem; opacity: 0.8; font-weight: 500;"><?php echo number_format($converted_count); ?> khách hàng đã đăng ký sử dụng dịch vụ</span>
        </div>

        <div class="stat-card" style="padding: 2rem; border-radius: 24px; color: white; background: linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%); position: relative; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.3);">
            <i class="fas fa-headset" style="position: absolute; right: -10px; bottom: -10px; font-size: 5.5rem; opacity: 0.2; transform: rotate(-15deg);"></i>
            <span style="display: block; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.9; margin-bottom: 1rem;">Hiệu suất Chăm sóc</span>
            <span style="display: block; font-size: 3.5rem; font-weight: 900; line-height: 1;"><?php echo $care_rate; ?><small style="font-size: 1.5rem;">%</small></span>
            <span style="display: block; font-size: 0.85rem; margin-top: 1rem; opacity: 0.8; font-weight: 500;"><?php echo number_format($care_count); ?> khách hàng đang trong tiến trình tư vấn</span>
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
