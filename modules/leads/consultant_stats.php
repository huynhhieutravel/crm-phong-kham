<?php
// modules/leads/consultant_stats.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

$page_title = 'Thống kê Hiệu suất Tư vấn';
$current_page = 'leads';
require_once '../../templates/header.php';

$db = getDB();

// Period Filter Logic
$period = $_GET['period'] ?? 'month';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$range = get_date_range($period, $start_date, $end_date);
$params = [$range['start'], $range['end']];

// 1. Consultant Performance Data (Users with roles: doctor, cskh, admin)
$stmt = $db->prepare("
    SELECT u.id, u.full_name, 
           COUNT(l.id) as total_assigned,
           SUM(CASE WHEN l.status = 'converted' THEN 1 ELSE 0 END) as successful_conversions,
           SUM(CASE WHEN l.status = 'scheduled' THEN 1 ELSE 0 END) as scheduled_count,
           SUM(CASE WHEN l.status = 'contacted' THEN 1 ELSE 0 END) as contacted_count,
           SUM(CASE WHEN l.status = 'new' THEN 1 ELSE 0 END) as pending_count
    FROM users u
    LEFT JOIN leads l ON l.consultant_id = u.id AND l.created_at BETWEEN ? AND ?
    JOIN roles r ON u.role_id = r.id
    WHERE r.name IN ('doctor', 'cskh', 'admin') AND u.status = 'active'
    GROUP BY u.id
    ORDER BY successful_conversions DESC, total_assigned DESC
");
$stmt->execute($params);
$consultant_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Global KPIs (Total leads regardless of assignment)
$stmt = $db->prepare("
    SELECT COUNT(*) as total,
           SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as total_success
    FROM leads
    WHERE created_at BETWEEN ? AND ?
");
$stmt->execute($params);
$global_raw = $stmt->fetch(PDO::FETCH_ASSOC);
$total_assigned_all = $global_raw['total'];
$total_success_all = $global_raw['total_success'];
$global_conversion_rate = $total_assigned_all > 0 ? round(($total_success_all / $total_assigned_all) * 100, 1) : 0;

// 3. Unassigned Leads Count
$stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM leads 
    WHERE consultant_id IS NULL AND created_at BETWEEN ? AND ?
");
$stmt->execute($params);
$unassigned_count = $stmt->fetchColumn();
// 3. Status map for visual consistency
$status_map = [
    'converted' => ['label' => 'Thành công', 'color' => '#10b981'],
    'scheduled' => ['label' => 'Đã hẹn', 'color' => '#8b5cf6'],
    'contacted' => ['label' => 'Đang xử lý', 'color' => '#f59e0b'],
    'pending' => ['label' => 'Chờ duyệt', 'color' => '#94a3b8']
];
?>

<div class="consultant-stats-container" style="max-width: 1400px; margin: 0 auto; padding: 1.5rem;">
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

    <!-- KPI Display Cards -->
    <div class="kpi-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2.5rem;">
        <div class="kpi-card" style="padding: 2rem; border-radius: 24px; color: white; background: linear-gradient(135deg, #6366f1 0%, #818cf8 100%); position: relative; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.3);">
            <i class="fas fa-user-check" style="position: absolute; right: -10px; bottom: -10px; font-size: 5rem; opacity: 0.2; transform: rotate(-15deg);"></i>
            <span style="display: block; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.9; margin-bottom: 1rem;">Tổng Lead Phân Bổ</span>
            <span style="display: block; font-size: 3rem; font-weight: 900; line-height: 1;"><?php echo number_format($total_assigned_all); ?></span>
            <span style="display: block; font-size: 0.85rem; margin-top: 1rem; opacity: 0.8; font-weight: 500;">Số lượng lead đã được giao cho đội ngũ</span>
        </div>

        <div class="kpi-card" style="padding: 2rem; border-radius: 24px; color: white; background: linear-gradient(135deg, #10b981 0%, #34d399 100%); position: relative; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.3);">
            <i class="fas fa-trophy" style="position: absolute; right: -10px; bottom: -10px; font-size: 5rem; opacity: 0.2; transform: rotate(-15deg);"></i>
            <span style="display: block; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.9; margin-bottom: 1rem;">Chốt Thành Công</span>
            <span style="display: block; font-size: 3rem; font-weight: 900; line-height: 1;"><?php echo number_format($total_success_all); ?></span>
            <span style="display: block; font-size: 0.85rem; margin-top: 1rem; opacity: 0.8; font-weight: 500;">KPI quan trọng nhất của đội ngũ tư vấn</span>
        </div>

        <div class="kpi-card" style="padding: 2rem; border-radius: 24px; color: white; background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); position: relative; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(245, 158, 11, 0.3);">
            <i class="fas fa-chart-line" style="position: absolute; right: -10px; bottom: -10px; font-size: 5rem; opacity: 0.2; transform: rotate(-15deg);"></i>
            <span style="display: block; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.9; margin-bottom: 1rem;">Tỷ Lệ Chốt Tổng</span>
            <span style="display: block; font-size: 3rem; font-weight: 900; line-height: 1;"><?php echo $global_conversion_rate; ?><small style="font-size: 1.5rem;">%</small></span>
            <span style="display: block; font-size: 0.85rem; margin-top: 1rem; opacity: 0.8; font-weight: 500;">Chỉ số hiệu quả trung bình của toàn bộ nhân sự</span>
        </div>
    </div>

    <!-- Ranking Table -->
    <div class="ranking-card" style="background: white; border-radius: 24px; padding: 2.5rem; box-shadow: 0 4px 24px rgba(0,0,0,0.03);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2.5rem;">
            <div>
                <h3 style="font-size: 1.5rem; font-weight: 800; color: #1e293b; margin: 0;">Bảng Xếp Hạng Nhân Sự</h3>
                <p style="color: #64748b; font-size: 0.9rem; margin-top: 0.25rem;">Xếp hạng dựa trên số lượng khách hàng chốt thành công</p>
            </div>
            <button class="btn btn-outline" style="padding: 0.6rem 1.2rem; border-radius: 12px; font-weight: 700; border: 1.5px solid #e2e8f0; color: #64748b;">
                <i class="fas fa-download" style="margin-right: 0.5rem;"></i> Xuất báo cáo
            </button>
        </div>

        <div class="table-responsive">
            <table class="ranking-table" style="width: 100%; border-collapse: separate; border-spacing: 0 0.75rem;">
                <thead>
                    <tr style="text-align: left;">
                        <th style="padding: 0.75rem 1rem; color: #94a3b8; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">Xếp hạng</th>
                        <th style="padding: 0.75rem 1rem; color: #94a3b8; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">Tư vấn viên</th>
                        <th style="padding: 0.75rem 1rem; color: #94a3b8; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; text-align: center;">Tổng Giao</th>
                        <th style="padding: 0.75rem 1rem; color: #94a3b8; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; text-align: center;">Thành công</th>
                        <th style="padding: 0.75rem 1rem; color: #94a3b8; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">Tỷ lệ Chốt</th>
                        <th style="padding: 0.75rem 1rem; color: #94a3b8; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">Hoa hồng dự tính</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($unassigned_count > 0): ?>
                        <tr style="background: #fff1f2; transition: all 0.2s ease;">
                            <td style="padding: 1.5rem 1rem; border-radius: 16px 0 0 16px;">
                                <div style="width: 32px; height: 32px; background: #ffe4e6; color: #f43f5e; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.9rem;">
                                    -
                                </div>
                            </td>
                            <td style="padding: 1.5rem 1rem;">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="width: 40px; height: 40px; background: #fecaca; color: #dc2626; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 800;">
                                        ?
                                    </div>
                                    <div>
                                        <div style="font-weight: 700; color: #991b1b;">Chưa phân bổ</div>
                                        <div style="font-size: 0.75rem; color: #b91c1c; font-weight: 500;">Cần được giao cho nhân sự</div>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 1.5rem 1rem; text-align: center;">
                                <span style="font-weight: 700; color: #991b1b;"><?php echo $unassigned_count; ?></span>
                            </td>
                            <td style="padding: 1.5rem 1rem; text-align: center;">
                                <span style="font-weight: 800; color: #94a3b8;">-</span>
                            </td>
                            <td style="padding: 1.5rem 1rem; width: 220px;">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="flex: 1; height: 8px; background: #fecaca; border-radius: 10px; overflow: hidden;">
                                        <div style="height: 100%; width: 0%; background: #dc2626; border-radius: 10px;"></div>
                                    </div>
                                    <span style="font-weight: 800; color: #991b1b; font-size: 0.85rem;">0%</span>
                                </div>
                            </td>
                            <td style="padding: 1.5rem 1rem; border-radius: 0 16px 16px 0;">
                                <span style="font-weight: 800; color: #94a3b8; background: #f1f5f9; padding: 0.4rem 0.8rem; border-radius: 8px;">0đ</span>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php 
                    $rank = 1;
                    foreach ($consultant_stats as $s): 
                        $rate = $s['total_assigned'] > 0 ? round(($s['successful_conversions'] / $s['total_assigned']) * 100, 1) : 0;
                        $commission = $s['successful_conversions'] * 50000; // Example: 50k per conversion
                    ?>
                        <tr style="background: #f8fafc; transition: all 0.2s ease;">
                            <td style="padding: 1.5rem 1rem; border-radius: 16px 0 0 16px;">
                                <div style="width: 32px; height: 32px; background: <?php echo $rank <= 3 ? '#fffbeb' : '#f1f5f9'; ?>; color: <?php echo $rank <= 3 ? '#fbbf24' : '#64748b'; ?>; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.9rem;">
                                    <?php echo $rank; ?>
                                </div>
                            </td>
                            <td style="padding: 1.5rem 1rem;">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="width: 40px; height: 40px; background: #e0e7ff; color: #4f46e5; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 800;">
                                        <?php echo substr($s['full_name'], 0, 1); ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 700; color: #1e293b;"><?php echo e($s['full_name']); ?></div>
                                        <div style="font-size: 0.75rem; color: #94a3b8; font-weight: 500;">Chuyên viên Tư vấn</div>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 1.5rem 1rem; text-align: center;">
                                <span style="font-weight: 700; color: #475569;"><?php echo $s['total_assigned']; ?></span>
                            </td>
                            <td style="padding: 1.5rem 1rem; text-align: center;">
                                <span style="font-weight: 800; color: #10b981;"><?php echo $s['successful_conversions']; ?></span>
                            </td>
                            <td style="padding: 1.5rem 1rem; width: 220px;">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="flex: 1; height: 8px; background: #e2e8f0; border-radius: 10px; overflow: hidden;">
                                        <div style="height: 100%; width: <?php echo $rate; ?>%; background: linear-gradient(90deg, #10b981, #34d399); border-radius: 10px;"></div>
                                    </div>
                                    <span style="font-weight: 800; color: #1e293b; font-size: 0.85rem;"><?php echo $rate; ?>%</span>
                                </div>
                            </td>
                            <td style="padding: 1.5rem 1rem; border-radius: 0 16px 16px 0;">
                                <span style="font-weight: 800; color: #6366f1; background: rgba(99, 102, 241, 0.1); padding: 0.4rem 0.8rem; border-radius: 8px;">
                                    <?php echo number_format($commission, 0, ',', '.'); ?>đ
                                </span>
                            </td>
                        </tr>
                    <?php 
                        $rank++;
                    endforeach; 
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Additional Insights Grid -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 2.5rem;">
        <div style="background: white; border-radius: 24px; padding: 2rem; box-shadow: 0 4px 24px rgba(0,0,0,0.03);">
            <h3 style="font-size: 1.2rem; font-weight: 800; color: #1e293b; margin-bottom: 2rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-chart-bar" style="color: #6366f1;"></i> Phân bổ Lead theo nhân sự
            </h3>
            <div style="height: 350px;">
                <canvas id="assignmentChart"></canvas>
            </div>
        </div>
        <div style="background: white; border-radius: 24px; padding: 2rem; box-shadow: 0 4px 24px rgba(0,0,0,0.03);">
            <h3 style="font-size: 1.2rem; font-weight: 800; color: #1e293b; margin-bottom: 2rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-medal" style="color: #fbbf24;"></i> Hiệu suất chốt hợp đồng
            </h3>
            <div style="height: 350px;">
                <canvas id="performanceChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#64748b';

    const labels = [<?php foreach ($consultant_stats as $s) if($s['total_assigned'] > 0) echo "'" . addslashes($s['full_name']) . "',"; ?>];
    const totalData = [<?php foreach ($consultant_stats as $s) if($s['total_assigned'] > 0) echo $s['total_assigned'] . ","; ?>];
    const successData = [<?php foreach ($consultant_stats as $s) if($s['total_assigned'] > 0) echo $s['successful_conversions'] . ","; ?>];

    // Assignment Chart
    const assignCtx = document.getElementById('assignmentChart').getContext('2d');
    new Chart(assignCtx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Tổng Lead Giao',
                data: totalData,
                backgroundColor: '#cbd5e1',
                borderRadius: 8,
                barThickness: 20
            }, {
                label: 'Thành Công',
                data: successData,
                backgroundColor: '#6366f1',
                borderRadius: 8,
                barThickness: 20
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20, font: { weight: '700' } } } },
            scales: {
                y: { beginAtZero: true, grid: { borderDash: [5, 5], color: '#f1f5f9' }, ticks: { stepSize: 1 } },
                x: { grid: { display: false } }
            }
        }
    });

    // Performance Chart (Conversion Rate)
    const perfCtx = document.getElementById('performanceChart').getContext('2d');
    new Chart(perfCtx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Tỷ lệ Chốt (%)',
                data: [<?php foreach ($consultant_stats as $s) { if($s['total_assigned'] > 0) echo round(($s['successful_conversions'] / $s['total_assigned']) * 100, 1) . ","; } ?>],
                borderColor: '#10b981',
                borderWidth: 4,
                tension: 0.4,
                fill: true,
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                pointRadius: 6,
                pointBackgroundColor: '#10b981',
                pointBorderColor: '#fff',
                pointBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, max: 100, grid: { borderDash: [5, 5], color: '#f1f5f9' }, ticks: { callback: value => value + '%' } },
                x: { grid: { display: false } }
            }
        }
    });
});
</script>

<?php require_once '../../templates/footer.php'; ?>
