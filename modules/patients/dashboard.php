<?php
// modules/patients/dashboard.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

// Cấp quyền: Giả định CEO, Admin, và những người được xem bệnh nhân đều vào được.
require_permission('view_patients');

$page_title = __('patients.dashboard.title');
$current_page = 'patients';
require_once '../../templates/header.php';

$db = getDB();

// Filter
$period = isset($_GET['period']) ? $_GET['period'] : 'month';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$range = get_date_range($period, $start_date, $end_date);

$params = [$range['start'] . ' 00:00:00', $range['end'] . ' 23:59:59'];

$min_age = isset($_GET['min_age']) && $_GET['min_age'] !== '' ? (int)$_GET['min_age'] : null;
$max_age = isset($_GET['max_age']) && $_GET['max_age'] !== '' ? (int)$_GET['max_age'] : null;

$age_condition = "";
$age_params = [];
if ($min_age !== null) {
    $age_condition .= " AND TIMESTAMPDIFF(YEAR, birthday, CURDATE()) >= ?";
    $age_params[] = $min_age;
}
if ($max_age !== null) {
    $age_condition .= " AND TIMESTAMPDIFF(YEAR, birthday, CURDATE()) <= ?";
    $age_params[] = $max_age;
}

$params = [$range['start'] . ' 00:00:00', $range['end'] . ' 23:59:59'];
$base_params = array_merge($params, $age_params);

// 1. Total Patients (All Time but Filtered by Age)
if (empty($age_condition)) {
    $stmt = $db->query("SELECT COUNT(*) FROM patients");
    $total_patients = $stmt->fetchColumn();
} else {
    // 1=1 is needed because $age_condition starts with AND
    $stmt = $db->prepare("SELECT COUNT(*) FROM patients WHERE 1=1 " . $age_condition);
    $stmt->execute($age_params);
    $total_patients = $stmt->fetchColumn();
}

// 2. New Patients in Period
$stmt = $db->prepare("SELECT COUNT(*) FROM patients WHERE created_at BETWEEN ? AND ? " . $age_condition);
$stmt->execute($base_params);
$new_patients = $stmt->fetchColumn();

// Compare with previous period (e.g. if month, compare with last month)
$interval_days = (strtotime($range['end']) - strtotime($range['start'])) / (60*60*24);
if ($interval_days <= 1) $interval_days = 1;
$prev_start = date('Y-m-d H:i:s', strtotime($range['start'] . " - $interval_days days"));
$prev_end = date('Y-m-d H:i:s', strtotime($range['end'] . " - $interval_days days"));

$prev_params = array_merge([$prev_start, $prev_end], $age_params);
$stmt = $db->prepare("SELECT COUNT(*) FROM patients WHERE created_at BETWEEN ? AND ? " . $age_condition);
$stmt->execute($prev_params);
$prev_new_patients = $stmt->fetchColumn();

$growth_percent = 0;
if ($prev_new_patients > 0) {
    $growth_percent = round((($new_patients - $prev_new_patients) / $prev_new_patients) * 100, 1);
} else if ($new_patients > 0) {
    $growth_percent = 100;
}

// 3. Gender Distribution in Period
$stmt = $db->prepare("SELECT gender, COUNT(*) as count FROM patients WHERE created_at BETWEEN ? AND ? " . $age_condition . " GROUP BY gender");
$stmt->execute($base_params);
$gender_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$gender_map = [
    'male' => ['label' => __('patient.gender.male'), 'color' => '#3b82f6'],
    'female' => ['label' => __('patient.gender.female'), 'color' => '#ec4899'],
    'other' => ['label' => __('patient.gender.other'), 'color' => '#94a3b8'],
    '' => ['label' => __('patient.gender.other'), 'color' => '#cbd5e1']
];

// 4. Age Groups in Period
$stmt = $db->prepare("
    SELECT 
        CASE 
            WHEN birthday IS NULL THEN 'Unknown'
            WHEN TIMESTAMPDIFF(YEAR, birthday, CURDATE()) < 5 THEN '< 5'
            WHEN TIMESTAMPDIFF(YEAR, birthday, CURDATE()) BETWEEN 5 AND 11 THEN '5-11'
            WHEN TIMESTAMPDIFF(YEAR, birthday, CURDATE()) BETWEEN 12 AND 18 THEN '12-18'
            WHEN TIMESTAMPDIFF(YEAR, birthday, CURDATE()) BETWEEN 19 AND 35 THEN '19-35'
            WHEN TIMESTAMPDIFF(YEAR, birthday, CURDATE()) BETWEEN 36 AND 50 THEN '36-50'
            ELSE '> 50' 
        END as age_group,
        CASE 
            WHEN birthday IS NULL THEN 99
            WHEN TIMESTAMPDIFF(YEAR, birthday, CURDATE()) < 5 THEN 1
            WHEN TIMESTAMPDIFF(YEAR, birthday, CURDATE()) BETWEEN 5 AND 11 THEN 2
            WHEN TIMESTAMPDIFF(YEAR, birthday, CURDATE()) BETWEEN 12 AND 18 THEN 3
            WHEN TIMESTAMPDIFF(YEAR, birthday, CURDATE()) BETWEEN 19 AND 35 THEN 4
            WHEN TIMESTAMPDIFF(YEAR, birthday, CURDATE()) BETWEEN 36 AND 50 THEN 5
            ELSE 6 
        END as age_sort,
        COUNT(*) as count
    FROM patients
    WHERE created_at BETWEEN ? AND ? " . $age_condition . "
    GROUP BY age_group, age_sort
    ORDER BY age_sort
");
$stmt->execute($base_params);
$age_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Source Distribution in Period
$source_other_label = __('patients.dashboard.source_other');
$stmt = $db->prepare("
    SELECT COALESCE(NULLIF(source, ''), ?) as source, COUNT(*) as count 
    FROM patients 
    WHERE created_at BETWEEN ? AND ? " . $age_condition . "
    GROUP BY source
    ORDER BY count DESC
    LIMIT 6
");
$stmt->execute(array_merge([$source_other_label], $base_params));
$source_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 6. Trend Data (Daily if <= 40 days, otherwise Monthly)
$is_monthly_trend = ($interval_days > 40);

if ($is_monthly_trend) {
    $stmt = $db->prepare("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as date, COUNT(*) as count
        FROM patients 
        WHERE created_at BETWEEN ? AND ? " . $age_condition . "
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY date ASC
    ");
} else {
    $stmt = $db->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM patients 
        WHERE created_at BETWEEN ? AND ? " . $age_condition . "
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
}
$stmt->execute($base_params);
$trend_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 7. Re-exam (Retention) stat
$apts_age_condition = str_replace("birthday", "p.birthday", $age_condition);
$stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM appointments a
    LEFT JOIN patients p ON a.patient_id = p.id
    WHERE a.type = 're_exam' AND a.appointment_date BETWEEN ? AND ? " . $apts_age_condition . "
");
$stmt->execute($base_params);
$reexam_apts = $stmt->fetchColumn();

$stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM appointments a
    LEFT JOIN patients p ON a.patient_id = p.id
    WHERE a.appointment_date BETWEEN ? AND ? " . $apts_age_condition . "
");
$stmt->execute($base_params);
$period_apts = $stmt->fetchColumn();

$reexam_rate = $period_apts > 0 ? round(($reexam_apts / $period_apts) * 100, 1) : 0;
?>

<style>
.pat-dashboard-outer { width: 100%; max-width: 1400px; }
.pat-grid-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
@media (max-width: 1024px) { .pat-grid-stats { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 768px) { .pat-grid-stats { grid-template-columns: 1fr; } }

.filter-bar-pat {
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;
    margin-bottom: 2.5rem; padding: 1.5rem; background: white; border-radius: 18px; box-shadow: 0 4px 20px rgba(0,0,0,0.03);
}
.period-toggle-pat { display: flex; background: #f1f5f9; padding: 5px; border-radius: 14px; gap: 4px; }
.btn-toggle-pat {
    padding: 0.7rem 1.5rem; border-radius: 11px; font-size: 0.9rem; font-weight: 600; color: #64748b;
    text-decoration: none; transition: all 0.25s ease; border: none; background: transparent; cursor: pointer;
}
.btn-toggle-pat:hover:not(.active) { background: rgba(255, 255, 255, 0.6); color: var(--primary); }
.btn-toggle-pat.active { background: white; color: var(--primary); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); }

.stat-card-pat {
    padding: 2rem; border-radius: 22px; color: white; position: relative; overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
}
.stat-card-pat:hover { transform: translateY(-6px); box-shadow: 0 20px 30px -10px rgba(0,0,0,0.15); }
.stat-card-pat.blue { background: linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%); }
.stat-card-pat.emerald { background: linear-gradient(135deg, #10b981 0%, #34d399 100%); }
.stat-card-pat.purple { background: linear-gradient(135deg, #8b5cf6 0%, #a78bfa 100%); }

.stat-card-pat i { position: absolute; right: -10px; bottom: -10px; font-size: 5rem; opacity: 0.15; transform: rotate(-10deg); }
.stat-card-pat .stat-tag { font-size: 0.85rem; font-weight: 600; text-transform: uppercase; opacity: 0.85; margin-bottom: 0.5rem; display: block; letter-spacing: 0.5px; }
.stat-card-pat .stat-val { font-size: 2.8rem; font-weight: 800; display: flex; align-items: baseline; gap: 0.5rem; }
.stat-card-pat .stat-desc { font-size: 0.85rem; margin-top: 0.8rem; opacity: 0.9; display: flex; align-items: center; gap: 0.5rem; font-weight: 500; }

.dashboard-grid-pat { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2.5rem; }
.dashboard-grid-pat.wide-left { grid-template-columns: 2fr 1fr; }
@media (max-width: 1100px) { .dashboard-grid-pat, .dashboard-grid-pat.wide-left { grid-template-columns: 1fr; } }

.card-pat { background: white; border-radius: 24px; padding: 2rem; box-shadow: 0 4px 24px rgba(0,0,0,0.03); }
.card-title-pat { font-size: 1.25rem; font-weight: 800; color: #1e293b; margin-bottom: 2rem; display: flex; align-items: center; gap: 0.8rem; }
.card-title-pat i { color: var(--primary); }
.chart-container-pat { position: relative; width: 100%; }

.growth-badge {
    padding: 0.25rem 0.6rem; border-radius: 20px; font-size: 0.75rem; font-weight: 700; background: rgba(255,255,255,0.25);
}
.growth-badge.positive { color: #f0fdf4; }
.growth-badge.negative { color: #fef2f2; background: rgba(239, 68, 68, 0.4); }
</style>

<div class="content-body">
    <div class="pat-dashboard-outer">
        <div class="breadcrumb mb-2" style="font-size: 0.75rem; font-weight: 700; letter-spacing: 1px; color: #94a3b8;">
            CRM / <?php echo __('menu.patients'); ?> / DASHBOARD CEO
        </div>
        <div class="header-section mb-4" style="display: flex; justify-content: space-between; align-items: center;">
            <h1 class="page-title" style="font-size: 2.2rem; font-weight: 800; color: #0f172a; margin: 0;"><?php echo __('patients.dashboard.title'); ?></h1>
            <a href="index.php" class="btn"><i class="fas fa-list"></i> <?php echo __('patients.dashboard.btn_list'); ?></a>
        </div>

        <!-- Filter Hub -->
        <form method="GET" class="filter-bar-pat">
            <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                <div class="period-toggle-pat">
                    <input type="hidden" name="period" id="periodInput" value="<?php echo e($period); ?>">
                    
                    <a href="#" class="btn-toggle-pat <?php echo $period == 'today' ? 'active' : ''; ?>" onclick="setPeriod(event, 'today')"><?php echo __('common.today'); ?></a>
                    <a href="#" class="btn-toggle-pat <?php echo $period == 'week' ? 'active' : ''; ?>" onclick="setPeriod(event, 'week')"><?php echo __('common.week'); ?></a>
                    <a href="#" class="btn-toggle-pat <?php echo $period == 'month' ? 'active' : ''; ?>" onclick="setPeriod(event, 'month')"><?php echo __('common.month'); ?></a>
                    <a href="#" class="btn-toggle-pat <?php echo $period == 'quarter' ? 'active' : ''; ?>" onclick="setPeriod(event, 'quarter')"><?php echo __('common.quarter'); ?></a>
                    <a href="#" class="btn-toggle-pat <?php echo $period == 'year' ? 'active' : ''; ?>" onclick="setPeriod(event, 'year')"><?php echo __('common.year'); ?></a>
                    <a href="#" class="btn-toggle-pat <?php echo $period == 'custom' ? 'active' : ''; ?>" onclick="setPeriod(event, 'custom')"><?php echo __('common.custom'); ?></a>
                </div>

                <?php if($period === 'today' || $period === 'week' || $period === 'month' || $period === 'quarter' || $period === 'year'): ?>
                    <div style="display: flex; gap: 0.25rem; align-items: center; margin-left: -0.5rem;">
                        <?php if($period === 'month'): ?>
                            <select name="sel_month" style="border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.8rem; padding: 0.35rem 0.5rem; color: var(--text-main); outline: none; width: auto;" onchange="this.form.submit()">
                                <?php for($m=1; $m<=12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo (isset($_GET['sel_month']) && $_GET['sel_month'] == $m) || (!isset($_GET['sel_month']) && $m == date('n')) ? 'selected' : ''; ?>><?php echo __('common.month'); ?> <?php echo $m; ?></option>
                                <?php endfor; ?>
                            </select>
                        <?php endif; ?>
                        
                        <?php if($period === 'month' || $period === 'quarter' || $period === 'year'): ?>
                            <select name="sel_year" style="border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.8rem; padding: 0.35rem 0.5rem; color: var(--text-main); outline: none; width: auto;" onchange="this.form.submit()">
                                <?php for($y=date('Y')-2; $y<=date('Y')+1; $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo (isset($_GET['sel_year']) && $_GET['sel_year'] == $y) || (!isset($_GET['sel_year']) && $y == date('Y')) ? 'selected' : ''; ?>><?php echo __('common.year'); ?> <?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Age Filters -->
                <div style="display: flex; align-items: center; gap: 0.5rem; border-left: 1px solid #e2e8f0; padding-left: 1rem; margin-left: 0.5rem;">
                    <span style="font-size: 0.8rem; font-weight: 700; color: #64748b;">ĐỘ TUỔI:</span>
                    <input type="number" name="min_age" placeholder="Từ" value="<?php echo isset($_GET['min_age']) ? e($_GET['min_age']) : ''; ?>" style="width: 60px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.85rem; padding: 0.4rem 0.5rem; color: var(--text-main); outline: none;" onchange="this.form.submit()" min="0" max="120">
                    <span style="font-size: 0.8rem; color: #94a3b8;">-</span>
                    <input type="number" name="max_age" placeholder="Đến" value="<?php echo isset($_GET['max_age']) ? e($_GET['max_age']) : ''; ?>" style="width: 60px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.85rem; padding: 0.4rem 0.5rem; color: var(--text-main); outline: none;" onchange="this.form.submit()" min="0" max="120">
                    
                    <?php if ((isset($_GET['min_age']) && $_GET['min_age'] !== '') || (isset($_GET['max_age']) && $_GET['max_age'] !== '')): ?>
                        <a href="#" onclick="document.querySelector('input[name=min_age]').value=''; document.querySelector('input[name=max_age]').value=''; document.querySelector('.filter-bar-pat').submit(); return false;" style="color: #ef4444; font-size: 0.85rem; margin-left: 0.25rem; display: flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 50%; background: #fee2e2; text-decoration: none;" title="Xóa bộ lọc độ tuổi"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div id="customDates" style="display: <?php echo $period == 'custom' ? 'flex' : 'none'; ?>; align-items: center; gap: 0.8rem;">
                <div style="display: flex; align-items: center; background: #f8fafc; padding: 0.3rem 0.8rem; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <input type="date" name="start_date" id="start_date" value="<?php echo date('Y-m-d', strtotime($range['start'])); ?>" style="border:none; background:transparent; font-size: 0.9rem; font-weight: 600; color: #1e293b; outline:none;">
                    <span style="padding: 0 0.5rem; color: #94a3b8;"><i class="fas fa-arrow-right"></i></span>
                    <input type="date" name="end_date" id="end_date" value="<?php echo date('Y-m-d', strtotime($range['end'])); ?>" style="border:none; background:transparent; font-size: 0.9rem; font-weight: 600; color: #1e293b; outline:none;">
                </div>
                <button type="submit" class="btn btn-primary" style="padding: 0.7rem 1.5rem; border-radius: 12px; font-weight: 700;"><?php echo __('common.filter'); ?></button>
            </div>
        </form>

<script>
function setPeriod(event, p) {
    if (event) event.preventDefault();
    document.getElementById('periodInput').value = p;
    if (p !== 'custom') {
        event.target.closest('form').submit();
    } else {
        document.getElementById('customDates').style.display = 'flex';
        document.querySelectorAll('.btn-toggle-pat').forEach(btn => btn.classList.remove('active'));
        if (event && event.target) {
            event.target.classList.add('active');
        }
    }
}
</script>

        <!-- KPI Stats -->
        <div class="pat-grid-stats">
            <div class="stat-card-pat blue">
                <i class="fas fa-users"></i>
                <span class="stat-tag"><?php echo __('patients.dashboard.total'); ?> (<?php echo __('patients.dashboard.cumulative'); ?>)</span>
                <span class="stat-val"><?php echo number_format($total_patients); ?> <span style="font-size: 1rem; opacity: 0.7; font-weight: 600;"><?php echo __('patients.dashboard.people'); ?></span></span>
                <span class="stat-desc"><i class="fas fa-database"></i> <?php echo __('patients.dashboard.all_time'); ?></span>
            </div>

            <div class="stat-card-pat emerald">
                <i class="fas fa-user-plus"></i>
                <span class="stat-tag"><?php echo __('patients.dashboard.new_this_month'); ?></span>
                <span class="stat-val"><?php echo number_format($new_patients); ?></span>
                <span class="stat-desc">
                    <?php if ($growth_percent > 0): ?>
                        <span class="growth-badge positive"><i class="fas fa-arrow-up"></i> <?php echo $growth_percent; ?>%</span>
                    <?php elseif ($growth_percent < 0): ?>
                        <span class="growth-badge negative"><i class="fas fa-arrow-down"></i> <?php echo abs($growth_percent); ?>%</span>
                    <?php else: ?>
                        <span class="growth-badge"><i class="fas fa-minus"></i> 0%</span>
                    <?php endif; ?>
                    <span style="opacity: 0.8;"><?php echo __('patients.dashboard.prev_period'); ?></span>
                </span>
            </div>

            <div class="stat-card-pat purple">
                <i class="fas fa-redo"></i>
                <span class="stat-tag"><?php echo __('patients.dashboard.returning_ratio'); ?></span>
                <span class="stat-val"><?php echo $reexam_rate; ?>%</span>
                <span class="stat-desc"><i class="fas fa-info-circle"></i> <?php echo __('patients.dashboard.reexam_total_ratio'); ?></span>
            </div>
        </div>

        <!-- Layer 1: Trends & Distribution -->
        <div class="dashboard-grid-pat wide-left">
            <div class="card-pat">
                <h3 class="card-title-pat"><i class="fas fa-chart-area"></i> <?php echo __('patients.dashboard.growth_trend'); ?></h3>
                <div class="chart-container-pat" style="height: 320px;">
                    <canvas id="patTrendChart"></canvas>
                </div>
            </div>
            <div class="card-pat">
                <h3 class="card-title-pat"><i class="fas fa-venus-mars"></i> <?php echo __('patients.dashboard.gender_ratio'); ?></h3>
                <div class="chart-container-pat" style="height: 300px; display: flex; align-items: center; justify-content: center;">
                    <canvas id="patGenderChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Layer 2: Demographics & Sources -->
        <div class="dashboard-grid-pat">
            <div class="card-pat">
                <h3 class="card-title-pat"><i class="fas fa-birthday-cake"></i> <?php echo __('patients.dashboard.age_groups'); ?></h3>
                <div class="chart-container-pat" style="height: 300px;">
                    <canvas id="patAgeChart"></canvas>
                </div>
            </div>
            <div class="card-pat">
                <h3 class="card-title-pat"><i class="fas fa-bullhorn"></i> <?php echo __('patients.dashboard.sources'); ?></h3>
                <div class="chart-container-pat" style="height: 300px;">
                    <canvas id="patSourceChart"></canvas>
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

    // 1. Gender Doughnut
    new Chart(document.getElementById('patGenderChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: [<?php foreach($gender_data as $g) echo "'" . (isset($gender_map[$g['gender']]['label']) ? $gender_map[$g['gender']]['label'] : $g['gender']) . "',"; ?>],
            datasets: [{
                data: [<?php foreach($gender_data as $g) echo $g['count'] . ","; ?>],
                backgroundColor: [<?php foreach($gender_data as $g) echo "'" . (isset($gender_map[$g['gender']]['color']) ? $gender_map[$g['gender']]['color'] : '#cbd5e1') . "',"; ?>],
                borderWidth: 5, borderColor: '#ffffff', hoverOffset: 12
            }]
        },
        options: {
            cutout: '65%', responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20, font: { weight: '600' } } } }
        }
    });

    // 2. Trend Line Chart
    const trendCtx = document.getElementById('patTrendChart').getContext('2d');
    const gradient = trendCtx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(16, 185, 129, 0.25)'); // Emerald Green
    gradient.addColorStop(1, 'rgba(16, 185, 129, 0)');

    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: [<?php foreach($trend_data as $t) echo "'" . ($is_monthly_trend ? str_replace('-', '/', $t['date']) : date('d/m', strtotime($t['date']))) . "',"; ?>],
            datasets: [{
                label: '<?php echo __('patients.dashboard.chart_new_patients_label'); ?>',
                data: [<?php foreach($trend_data as $t) echo $t['count'] . ","; ?>],
                borderColor: '#10b981', borderWidth: 4, backgroundColor: gradient, fill: true, tension: 0.4,
                pointRadius: 4, pointHoverRadius: 8, pointHoverBackgroundColor: '#10b981', pointHoverBorderColor: '#fff', pointHoverBorderWidth: 3
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            interaction: { intersect: false, mode: 'index' },
            scales: {
                y: { beginAtZero: true, grid: { borderDash: [5, 5], color: '#f1f5f9' }, ticks: { stepSize: 1, font: { weight: '600' } } },
                x: { grid: { display: false }, ticks: { font: { weight: '600' } } }
            }
        }
    });

    // 3. Age Groups - Bar Chart
    new Chart(document.getElementById('patAgeChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: [<?php foreach($age_data as $a) echo "'" . $a['age_group'] . "',"; ?>],
            datasets: [{
                label: '<?php echo __('patients.dashboard.chart_count_label'); ?>',
                data: [<?php foreach($age_data as $a) echo $a['count'] . ","; ?>],
                backgroundColor: '#cbd5e1', hoverBackgroundColor: '#f59e0b', borderRadius: 8, barThickness: 'flex', maxBarThickness: 40
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { font: { weight: '600' } } },
                y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { stepSize: 1 } }
            }
        }
    });

    // 4. Source - Horizontal Bar Chart
    new Chart(document.getElementById('patSourceChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: [<?php foreach($source_data as $s) echo "'" . e($s['source']) . "',"; ?>],
            datasets: [{
                label: '<?php echo __('patients.dashboard.chart_visitors_label'); ?>',
                data: [<?php foreach($source_data as $s) echo $s['count'] . ","; ?>],
                backgroundColor: '#8b5cf6', borderRadius: 6
            }]
        },
        options: {
            indexAxis: 'y', // Horizontal
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, grid: { borderDash: [5, 5] }, ticks: { stepSize: 1 } },
                y: { grid: { display: false }, ticks: { font: { weight: '700', size: 11 }, color: '#334155' } }
            }
        }
    });
});
</script>

<?php require_once '../../templates/footer.php'; ?>
