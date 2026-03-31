<?php
// modules/leads/consultant_stats.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('view_leads');

$page_title = __('leads.consultant.title');
$current_page = 'leads_consultant_stats';
require_once '../../templates/header.php';

$db = getDB();

// Period Filter Logic
$period = isset($_GET['period']) ? $_GET['period'] : 'month';
$start_date_filter = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date_filter = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$range = get_date_range($period, $start_date_filter, $end_date_filter);
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
$total_assigned_all = $global_raw['total'] ? (float)$global_raw['total'] : 0;
$total_success_all = $global_raw['total_success'] ? (float)$global_raw['total_success'] : 0;
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
    'converted' => ['label' => __('leads.status.converted'), 'color' => '#10b981'],
    'scheduled' => ['label' => __('leads.status.scheduled'), 'color' => '#8b5cf6'],
    'contacted' => ['label' => __('leads.status.contacted'), 'color' => '#f59e0b'],
    'pending' => ['label' => __('leads.status.pending'), 'color' => '#94a3b8']
];
?>

<div class="consultant-stats-container" style="max-width: 1400px; margin: 0 auto; padding: 1.5rem;">
    <div class="page-header" style="margin-bottom: 2rem;">
        <div>
            <h1 class="page-title"><?php echo __('leads.consultant.title'); ?></h1>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;">
                <?php echo __('leads.consultant.subtitle'); ?>
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-chart-bar"></i> <?php echo __('leads.consultant.btn_dashboard'); ?>
            </a>
        </div>
    </div>

    <!-- Premium Filter Hub -->
    <style>
    .premium-filter-container {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1.5rem;
        margin-bottom: 2.5rem;
        padding: 1.25rem 2rem;
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.5);
        border-radius: 24px;
        box-shadow: 0 10px 30px -10px rgba(0,0,0,0.05);
    }
    .filter-group-main {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    .filter-label-fancy {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
    }
    .modern-period-group {
        display: flex;
        background: #f1f5f9;
        padding: 0.35rem;
        border-radius: 14px;
        gap: 0.25rem;
    }
    .modern-filter-btn {
        padding: 0.5rem 1.25rem;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 700;
        font-size: 0.85rem;
        color: #64748b;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        gap: 0.4rem;
        border: none;
        background: transparent;
    }
    .modern-filter-btn:hover {
        color: #1e293b;
        background: rgba(255,255,255,0.5);
    }
    .modern-filter-btn.active {
        color: white;
        background: var(--primary);
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
    }
    .custom-range-card {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        background: white;
        padding: 0.4rem 0.8rem;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    .custom-date-input {
        border: none;
        background: transparent;
        font-size: 0.85rem;
        font-weight: 600;
        color: #1e293b;
        outline: none;
        width: 130px;
    }
    .filter-apply-btn {
        background: #0f172a;
        color: white;
        width: 32px;
        height: 32px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        border: none;
    }
    .filter-apply-btn:hover {
        background: #1e293b;
        transform: scale(1.05);
    }
    </style>

    <form method="GET" class="premium-filter-container" id="filterForm">
        <div class="filter-group-main">
            <div class="filter-label-fancy">
                <i class="fas fa-calendar-alt" style="color: var(--primary);"></i>
                <span><?php echo __('leads.index.filter_time'); ?></span>
            </div>
            
            <div class="modern-period-group">
                <input type="hidden" name="period" id="periodInput" value="<?php echo e($period); ?>">
                <a href="#" class="modern-filter-btn <?php echo $period == '' ? 'active' : ''; ?>" onclick="setPeriod(event, '')"><?php echo __('common.all'); ?></a>
                <a href="#" class="modern-filter-btn <?php echo $period == 'today' ? 'active' : ''; ?>" onclick="setPeriod(event, 'today')"><?php echo __('filter.today'); ?></a>
                <a href="#" class="modern-filter-btn <?php echo $period == 'week' ? 'active' : ''; ?>" onclick="setPeriod(event, 'week')"><?php echo __('filter.week'); ?></a>
                <a href="#" class="modern-filter-btn <?php echo $period == 'month' ? 'active' : ''; ?>" onclick="setPeriod(event, 'month')"><?php echo __('filter.month'); ?></a>
                <a href="#" class="modern-filter-btn <?php echo $period == 'quarter' ? 'active' : ''; ?>" onclick="setPeriod(event, 'quarter')"><?php echo __('filter.quarter'); ?></a>
                <a href="#" class="modern-filter-btn <?php echo $period == 'year' ? 'active' : ''; ?>" onclick="setPeriod(event, 'year')"><?php echo __('filter.year'); ?></a>
                <a href="#" class="modern-filter-btn <?php echo $period == 'custom' ? 'active' : ''; ?>" onclick="setPeriod(event, 'custom')">
                    <i class="fas fa-sliders-h"></i> <?php echo __('filter.custom'); ?>
                </a>
            </div>

            <?php if($period === 'month' || $period === 'quarter' || $period === 'year'): ?>
                <div class="custom-range-card" style="border-color: var(--primary-light);">
                    <?php if($period === 'month'): ?>
                        <select name="sel_month" class="custom-date-input" style="width: auto;" onchange="this.form.submit()">
                            <?php for($m=1; $m<=12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo (isset($_GET['sel_month']) && $_GET['sel_month'] == $m) || (!isset($_GET['sel_month']) && $m == date('n')) ? 'selected' : ''; ?>><?php echo __('common.month'); ?> <?php echo $m; ?></option>
                            <?php endfor; ?>
                        </select>
                    <?php endif; ?>
                    
                    <?php if($period === 'quarter'): ?>
                        <select name="sel_quarter" class="custom-date-input" style="width: auto;" onchange="this.form.submit()">
                            <?php for($q=1; $q<=4; $q++): ?>
                                <option value="<?php echo $q; ?>" <?php echo (isset($_GET['sel_quarter']) && $_GET['sel_quarter'] == $q) || (!isset($_GET['sel_quarter']) && $q == ceil(date('n')/3)) ? 'selected' : ''; ?>><?php echo __('common.quarter'); ?> <?php echo $q; ?></option>
                            <?php endfor; ?>
                        </select>
                    <?php endif; ?>

                    <select name="sel_year" class="custom-date-input" style="width: auto;" onchange="this.form.submit()">
                        <?php for($y=date('Y')-2; $y<=date('Y')+1; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo (isset($_GET['sel_year']) && $_GET['sel_year'] == $y) || (!isset($_GET['sel_year']) && $y == date('Y')) ? 'selected' : ''; ?>><?php echo __('common.year'); ?> <?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div id="customDates" style="display: <?php echo $period == 'custom' ? 'flex' : 'none'; ?>; align-items: center; gap: 0.75rem;">
                <div class="custom-range-card">
                    <input type="date" name="start_date" class="custom-date-input" value="<?php echo e($start_date_filter); ?>">
                    <span style="color: #cbd5e1; font-weight: 800;">→</span>
                    <input type="date" name="end_date" class="custom-date-input" value="<?php echo e($end_date_filter); ?>">
                </div>
                <button type="submit" class="filter-apply-btn" title="<?php echo __('common.apply'); ?>">
                    <i class="fas fa-check"></i>
                </button>
            </div>
        </div>
        
        <div style="font-size: 0.8rem; color: #94a3b8; font-weight: 700;">
            <i class="fas fa-info-circle"></i> <?php echo __('leads.stats.time_filter_desc'); ?>
        </div>
    </form>

    <!-- KPI Display Cards -->
    <div class="kpi-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2.5rem;">
        <div class="kpi-card" style="padding: 2rem; border-radius: 24px; color: white; background: linear-gradient(135deg, #6366f1 0%, #818cf8 100%); position: relative; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.3);">
            <i class="fas fa-user-check" style="position: absolute; right: -10px; bottom: -10px; font-size: 5rem; opacity: 0.2; transform: rotate(-15deg);"></i>
            <span style="display: block; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.9; margin-bottom: 1rem;"><?php echo __('leads.consultant.kpi_total_assigned'); ?></span>
            <span style="display: block; font-size: 3rem; font-weight: 900; line-height: 1;"><?php echo number_format($total_assigned_all); ?></span>
            <span style="display: block; font-size: 0.85rem; margin-top: 1rem; opacity: 0.8; font-weight: 500;"><?php echo __('leads.consultant.kpi_total_assigned_desc'); ?></span>
        </div>

        <div class="kpi-card" style="padding: 2rem; border-radius: 24px; color: white; background: linear-gradient(135deg, #10b981 0%, #34d399 100%); position: relative; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.3);">
            <i class="fas fa-trophy" style="position: absolute; right: -10px; bottom: -10px; font-size: 5rem; opacity: 0.2; transform: rotate(-15deg);"></i>
            <span style="display: block; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.9; margin-bottom: 1rem;"><?php echo __('leads.consultant.kpi_successful_conversions'); ?></span>
            <span style="display: block; font-size: 3rem; font-weight: 900; line-height: 1;"><?php echo number_format($total_success_all); ?></span>
            <span style="display: block; font-size: 0.85rem; margin-top: 1rem; opacity: 0.8; font-weight: 500;"><?php echo __('leads.consultant.kpi_successful_conversions_desc'); ?></span>
        </div>

        <div class="kpi-card" style="padding: 2rem; border-radius: 24px; color: white; background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); position: relative; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(245, 158, 11, 0.3);">
            <i class="fas fa-chart-line" style="position: absolute; right: -10px; bottom: -10px; font-size: 5rem; opacity: 0.2; transform: rotate(-15deg);"></i>
            <span style="display: block; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.9; margin-bottom: 1rem;"><?php echo __('leads.consultant.kpi_conversion_rate'); ?></span>
            <span style="display: block; font-size: 3rem; font-weight: 900; line-height: 1;"><?php echo $global_conversion_rate; ?><small style="font-size: 1.5rem;">%</small></span>
            <span style="display: block; font-size: 0.85rem; margin-top: 1rem; opacity: 0.8; font-weight: 500;"><?php echo __('leads.consultant.kpi_conversion_rate_desc'); ?></span>
        </div>
    </div>

    <!-- Ranking Table -->

<div class="card" style="padding: 1.5rem; overflow-x: auto;">
    <table class="table" style="width: 100%; border-collapse: separate; border-spacing: 0;">
        <thead>
            <tr style="text-align: left; background: #f8fafc;">
                <th style="padding: 1rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;"><?php echo __('leads.consultant.table_name'); ?></th>
                <th style="padding: 1rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;"><?php echo __('leads.consultant.table_total'); ?></th>
                <th style="padding: 1rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;"><?php echo __('leads.consultant.table_contacted'); ?></th>
                <th style="padding: 1rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;"><?php echo __('leads.consultant.table_scheduled'); ?></th>
                <th style="padding: 1rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;"><?php echo __('leads.consultant.table_converted'); ?></th>
                <th style="padding: 1rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;"><?php echo __('leads.consultant.table_rate'); ?></th>
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
                                        <div style="font-weight: 700; color: #991b1b;"><?php echo __('leads.consultant.unassigned_title'); ?></div>
                                        <div style="font-size: 0.75rem; color: #b91c1c; font-weight: 500;"><?php echo __('leads.consultant.unassigned_desc'); ?></div>
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
                        </tr>
                    <?php endif; ?>

                    <?php 
                    $rank = 1;
                    foreach ($consultant_stats as $s): 
                        $rate = $s['total_assigned'] > 0 ? round(($s['successful_conversions'] / $s['total_assigned']) * 100, 1) : 0;
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
                                        <div style="font-size: 0.75rem; color: #94a3b8; font-weight: 500;"><?php echo __('leads.stats.consultant'); ?></div>
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
                <i class="fas fa-chart-bar" style="color: #6366f1;"></i> <?php echo __('leads.stats.lead_distribution'); ?>
            </h3>
            <div style="height: 350px;">
                <canvas id="assignmentChart"></canvas>
            </div>
        </div>
        <div style="background: white; border-radius: 24px; padding: 2rem; box-shadow: 0 4px 24px rgba(0,0,0,0.03);">
            <h3 style="font-size: 1.2rem; font-weight: 800; color: #1e293b; margin-bottom: 2rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-medal" style="color: #fbbf24;"></i> <?php echo __('leads.stats.conversion_performance'); ?>
            </h3>
            <div style="height: 350px;">
                <canvas id="performanceChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
function setPeriod(event, p) {
    if(event) event.preventDefault();
    document.getElementById('periodInput').value = p;
    if (p !== 'custom') {
        const s = document.querySelector('input[name="start_date"]');
        const e = document.querySelector('input[name="end_date"]');
        if (s) s.value = '';
        if (e) e.value = '';
        const form = document.getElementById('filterForm');
        if(form) form.submit();
    } else {
        document.getElementById('customDates').style.display = 'flex';
        document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
        if(event && event.target) event.target.classList.add('active');
    }
}
</script>

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
                label: '<?php echo __('leads.stats.assigned_leads'); ?>',
                data: totalData,
                backgroundColor: '#cbd5e1',
                borderRadius: 8,
                barThickness: 20
            }, {
                label: '<?php echo __('leads.stats.success'); ?>',
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
                label: '<?php echo __('leads.stats.conversion_rate'); ?>',
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
