<?php
// modules/appointments/index.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('view_appointments');

$page_title = __('appointment.list.title');
$current_page = 'appointments';
$base_url = '../../';
require_once '../../templates/header.php';

$db = getDB();

$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';
$doctor_filter = $_GET['doctor_id'] ?? '';
$type_filter = $_GET['type'] ?? '';
$period = $_GET['period'] ?? (empty($_GET) ? 'today' : '');
$start_date_param = $_GET['start_date'] ?? '';
$end_date_param = $_GET['end_date'] ?? '';
$view = 'list'; // Strictly List view for index.php

$query = "
    SELECT 
        a.*, 
        COALESCE(p.full_name, l.full_name) as contact_name,
        COALESCE(p.phone, l.phone) as contact_phone,
        p.label as patient_label,
        u.full_name as doctor_name,
        CASE WHEN a.patient_id IS NOT NULL THEN 'Patient' ELSE 'Lead' END as contact_type
    FROM appointments a
    LEFT JOIN patients p ON a.patient_id = p.id
    LEFT JOIN leads l ON a.lead_id = l.id
    LEFT JOIN users u ON a.doctor_id = u.id
";

$conditions = [];
$params = [];

if ($search) {
    $conditions[] = "(p.full_name LIKE ? OR l.full_name LIKE ? OR p.phone LIKE ? OR l.phone LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
}

if ($status_filter) {
    $conditions[] = "a.status = ?";
    $params[] = $status_filter;
}

if ($doctor_filter) {
    $conditions[] = "a.doctor_id = ?";
    $params[] = $doctor_filter;
}

if ($type_filter) {
    $conditions[] = "a.type = ?";
    $params[] = $type_filter;
}

// Handle Time Filtering
if ($period) {
    $range = get_date_range($period, $start_date_param, $end_date_param);
    $conditions[] = "a.appointment_date BETWEEN ? AND ?";
    $params[] = $range['start'];
    $params[] = $range['end'];
}

if ($conditions) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$query .= " ORDER BY a.appointment_date ASC";

// Pagination Logic (Only for List view)
$limit = 20;
$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;
$total_count = 0;

if ($view === 'list') {
    $count_query = "
        SELECT COUNT(*) 
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        LEFT JOIN leads l ON a.lead_id = l.id
    ";
    if ($conditions) {
        $count_query .= " WHERE " . implode(" AND ", $conditions);
    }
    try {
        $c_stmt = $db->prepare($count_query);
        $c_stmt->execute($params);
        $total_count = $c_stmt->fetchColumn();
    } catch (Exception $e) {
        $total_count = 0;
    }
    
    $query .= get_sql_limit($limit, $page);
}

try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();
} catch (Exception $e) {
    // Fallback: simple query if JOIN or new columns like label failed
    try {
        $fallback_query = "SELECT *, 'Patient/Lead' as contact_name, '' as contact_phone FROM appointments ORDER BY appointment_date ASC " . get_sql_limit($limit, $page);
        $appointments = $db->query($fallback_query)->fetchAll();
    } catch (Exception $e2) {
        $appointments = [];
    }
}


// Fetch staff for assignment (Doctors, CSKH, Admins)
try {
    $role_cols = $db->query("SHOW COLUMNS FROM roles")->fetchAll(PDO::FETCH_COLUMN);
    $role_label_col = in_array('display_name', $role_cols) ? 'display_name' : 'name';
    
    $doctors_stmt = $db->query("
        SELECT u.id, u.full_name, r.$role_label_col as role_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE r.name IN ('doctor', 'technician', 'cskh', 'admin') AND u.status = 'active'
        ORDER BY r.name = 'doctor' DESC, u.full_name ASC
    ");
    $doctors = $doctors_stmt->fetchAll();
} catch (Exception $e) {
    $doctors = [];
}

$status_map = [
    'scheduled' => ['label' => __('appointment.status.scheduled'), 'color' => '#64748b', 'icon' => 'fa-calendar-alt'],
    'confirmed' => ['label' => __('appointment.status.confirmed'), 'color' => '#8b5cf6', 'icon' => 'fa-check-double'],
    'arrived'   => ['label' => __('appointment.status.arrived'), 'color' => '#166534', 'icon' => 'fa-walking'],
    'treated'   => ['label' => __('appointment.status.treated'), 'color' => '#0ea5e9', 'icon' => 'fa-hand-holding-medical'],
    'completed' => ['label' => __('appointment.status.completed'), 'color' => '#1e40af', 'icon' => 'fa-check-circle'],
    'no_show'   => ['label' => __('appointment.status.no_show'), 'color' => '#eab308', 'icon' => 'fa-user-slash'],
    'cancelled' => ['label' => __('appointment.status.cancelled'), 'color' => '#f43f5e', 'icon' => 'fa-times-circle'],
    'staff_sick'=> ['label' => __('appointment.status.staff_sick'), 'color' => '#dc2626', 'icon' => 'fa-user-md-slash'],
    'staff_busy'=> ['label' => __('appointment.status.staff_busy'), 'color' => '#f59e0b', 'icon' => 'fa-clock']
];

$type_map = [
    'consultation' => ['label' => __('appointment.type.consultation'), 'color' => '#3b82f6', 'icon' => 'fa-comments'],
    'treatment'    => ['label' => __('appointment.type.treatment'), 'color' => '#10b981', 'icon' => 'fa-hand-holding-medical'],
    're_exam'      => ['label' => __('appointment.type.re_exam'), 'color' => '#8b5cf6', 'icon' => 'fa-redo'],
    'adjustment'   => ['label' => __('appointment.type.adjustment'), 'color' => '#64748b', 'icon' => 'fa-tools']
];

$is_filtered = $search || $status_filter || $doctor_filter || $type_filter || $period;
?>

<style>
.filter-card {
    padding: 1rem !important;
    margin-bottom: 0.4rem !important;
    border-radius: 16px !important;
}
.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}
.filter-group { margin-bottom: 0; }
.filter-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.025em;
    margin-bottom: 0.25rem;
    display: block;
    color: var(--text-muted);
    font-weight: 700;
}
.filter-input {
    height: 38px !important;
    font-size: 0.85rem !important;
    padding: 0.5rem 0.75rem !important;
    border-radius: 10px !important;
}
.filter-btn-group {
    display: flex;
    gap: 0.4rem;
    flex-wrap: wrap;
    align-items: center;
}
.filter-btn {
    padding: 0.4rem 0.8rem;
    border-radius: 8px;
    background: #f1f5f9;
    color: var(--text-muted);
    font-size: 0.8rem;
    font-weight: 600;
    text-decoration: none;
    transition: background 0.15s, color 0.15s;
    border: 1px solid transparent;
}
.filter-btn:hover { background: #e2e8f0; color: var(--text-main); }
.filter-btn.active {
    background: var(--primary);
    color: white;
}
.custom-range-box {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    background: #f8fafc;
    padding: 0.4rem 0.75rem;
    border-radius: 10px;
    border: 1px solid var(--border-color);
}
.custom-range-input {
    border: none;
    background: transparent;
    font-size: 0.8rem;
    color: var(--text-main);
    width: 110px;
    outline: none;
}
</style>

<div class="card filter-card">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap;">
        <form method="GET" id="filterForm" style="flex: 1;">
            <input type="hidden" name="view" value="<?php echo e($view); ?>">
            
            <div class="filter-grid">
                <!-- Search -->
                <div class="filter-group">
                    <label class="filter-label"><?php echo __('appointment.filter_labels.search'); ?></label>
                    <div style="position: relative;">
                        <input type="text" name="search" class="form-input filter-input" placeholder="<?php echo __('appointment.search.placeholder'); ?>" value="<?php echo e($search); ?>" style="padding-left: 2.2rem !important;">
                        <i class="fas fa-search" style="position: absolute; left: 0.8rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.75rem;"></i>
                    </div>
                </div>

                <!-- Doctor -->
                <div class="filter-group">
                    <label class="filter-label"><?php echo __('appointment.doctor'); ?></label>
                    <select name="doctor_id" class="form-input filter-input" onchange="this.form.submit()">
                        <option value=""><?php echo __('appointment.filter.all_doctors'); ?></option>
                        <option value="0" <?php echo $doctor_filter === '0' ? 'selected' : ''; ?>><?php echo __('appointment.filter.unassigned'); ?></option>
                        <?php foreach ($doctors as $doc): ?>
                            <option value="<?php echo $doc['id']; ?>" <?php echo (int)$doctor_filter === (int)$doc['id'] ? 'selected' : ''; ?>><?php echo e($doc['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Status -->
                <div class="filter-group">
                    <label class="filter-label"><?php echo __('appointment.filter_labels.status'); ?></label>
                    <select name="status" class="form-input filter-input" onchange="this.form.submit()">
                        <option value=""><?php echo __('appointment.filter.all_statuses'); ?></option>
                        <?php foreach ($status_map as $key => $info): ?>
                            <option value="<?php echo $key; ?>" <?php echo $status_filter === $key ? 'selected' : ''; ?>><?php echo $info['label']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Type -->
                <div class="filter-group">
                    <label class="filter-label"><?php echo __('appointment.filter_labels.type'); ?></label>
                    <select name="type" class="form-input filter-input" onchange="this.form.submit()">
                        <option value=""><?php echo __('appointment.filter.all_types'); ?></option>
                        <?php foreach ($type_map as $key => $info): ?>
                            <option value="<?php echo $key; ?>" <?php echo $type_filter === $key ? 'selected' : ''; ?>><?php echo $info['label']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                    <div class="filter-btn-group">
                        <span class="filter-label" style="margin-bottom: 0; margin-right: 0.25rem;"><?php echo __('appointment.time_filter'); ?></span>
                        <input type="hidden" name="period" id="periodInput" value="<?php echo e($period); ?>">
                        <input type="hidden" name="date" id="dateInput" value="<?php echo e($date_filter); ?>">
                        
                        <a href="#" class="filter-btn <?php echo $period == '' ? 'active' : ''; ?>" onclick="setPeriod(event, '')"><?php echo __('filter.all'); ?></a>
                        <a href="#" class="filter-btn <?php echo $period == 'today' ? 'active' : ''; ?>" onclick="setPeriod(event, 'today')"><?php echo __('filter.today'); ?></a>
                        <a href="#" class="filter-btn <?php echo $period == 'week' ? 'active' : ''; ?>" onclick="setPeriod(event, 'week')"><?php echo __('filter.week'); ?></a>
                        <a href="#" class="filter-btn <?php echo $period == 'month' ? 'active' : ''; ?>" onclick="setPeriod(event, 'month')"><?php echo __('filter.month'); ?></a>
                        <a href="#" class="filter-btn <?php echo $period == 'quarter' ? 'active' : ''; ?>" onclick="setPeriod(event, 'quarter')"><?php echo __('filter.quarter'); ?></a>
                        <a href="#" class="filter-btn <?php echo $period == 'year' ? 'active' : ''; ?>" onclick="setPeriod(event, 'year')"><?php echo __('filter.year'); ?></a>
                        <a href="#" class="filter-btn <?php echo $period == 'custom' ? 'active' : ''; ?>" onclick="setPeriod(event, 'custom')"><?php echo __('filter.custom'); ?></a>
                    </div>

                    <?php if($period === 'today' || $period === 'week' || $period === 'month' || $period === 'quarter' || $period === 'year'): ?>
                        <div style="display: flex; gap: 0.25rem; align-items: center; margin-left: -0.5rem;">
                            <?php if($period === 'today'): ?>
                                <input type="date" name="sel_date" style="border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.8rem; padding: 0.3rem 0.5rem; color: var(--text-main); outline: none;" value="<?php echo isset($_GET['sel_date']) ? e($_GET['sel_date']) : date('Y-m-d'); ?>" onchange="this.form.submit()">
                            <?php endif; ?>

                            <?php if($period === 'week'): ?>
                                <input type="week" name="sel_week" style="border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.8rem; padding: 0.3rem 0.5rem; color: var(--text-main); outline: none;" value="<?php echo isset($_GET['sel_week']) ? e($_GET['sel_week']) : date('Y').'-W'.date('W'); ?>" onchange="this.form.submit()">
                            <?php endif; ?>

                            <?php if($period === 'month'): ?>
                                <select name="sel_month" style="border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.8rem; padding: 0.35rem 0.5rem; color: var(--text-main); outline: none;" onchange="this.form.submit()">
                                    <?php for($m=1; $m<=12; $m++): ?>
                                        <option value="<?php echo $m; ?>" <?php echo (isset($_GET['sel_month']) && $_GET['sel_month'] == $m) || (!isset($_GET['sel_month']) && $m == date('n')) ? 'selected' : ''; ?>><?php echo __('common.month_prefix') . $m . __('common.month_suffix'); ?></option>
                                    <?php endfor; ?>
                                </select>
                            <?php endif; ?>
                            
                            <?php if($period === 'quarter'): ?>
                                <select name="sel_quarter" style="border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.8rem; padding: 0.35rem 0.5rem; color: var(--text-main); outline: none;" onchange="this.form.submit()">
                                    <?php for($q=1; $q<=4; $q++): ?>
                                        <option value="<?php echo $q; ?>" <?php echo (isset($_GET['sel_quarter']) && $_GET['sel_quarter'] == $q) || (!isset($_GET['sel_quarter']) && $q == ceil(date('n')/3)) ? 'selected' : ''; ?>><?php echo __('common.quarter_prefix') . $q . __('common.quarter_suffix'); ?></option>
                                    <?php endfor; ?>
                                </select>
                            <?php endif; ?>

                            <?php if($period === 'month' || $period === 'quarter' || $period === 'year'): ?>
                                <select name="sel_year" style="border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.8rem; padding: 0.35rem 0.5rem; color: var(--text-main); outline: none;" onchange="this.form.submit()">
                                    <?php for($y=date('Y')-2; $y<=date('Y')+1; $y++): ?>
                                        <option value="<?php echo $y; ?>" <?php echo (isset($_GET['sel_year']) && $_GET['sel_year'] == $y) || (!isset($_GET['sel_year']) && $y == date('Y')) ? 'selected' : ''; ?>><?php echo __('common.year_prefix') . $y . __('common.year_suffix'); ?></option>
                                    <?php endfor; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div id="customDates" style="display: <?php echo $period == 'custom' ? 'flex' : 'none'; ?>; gap: 0.5rem; align-items: center;">
                        <div class="custom-range-box">
                            <input type="date" name="start_date" class="custom-range-input" value="<?php echo e($start_date_param); ?>">
                            <span style="color: #94a3b8; font-size: 0.8rem;">→</span>
                            <input type="date" name="end_date" class="custom-range-input" value="<?php echo e($end_date_param); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm" style="height: 32px; padding: 0 0.75rem; border-radius: 8px;"><?php echo __('common.apply'); ?></button>
                    </div>
                </div>

            </div>
            <div style="margin-top: 0.5rem; border-top: 1px solid #f1f5f9; padding-top: 0.75rem; display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <?php 
                    $active_filters = [];
                    if ($search) $active_filters[] = __('appointment.filter_labels.search') . ": $search";
                    if ($status_filter) $active_filters[] = __('appointment.filter_labels.status') . ": " . (isset($status_map[$status_filter]['label']) ? $status_map[$status_filter]['label'] : $status_filter);
                    if ($type_filter) $active_filters[] = __('appointment.filter_labels.type') . ": " . (isset($type_map[$type_filter]['label']) ? $type_map[$type_filter]['label'] : $type_filter);
                    
                    if ($period) {
                        $range = get_date_range($period, $start_date_param, $end_date_param);
                        $active_filters[] = $range['label'];
                    }
                    ?>
                    <?php foreach($active_filters as $f): ?>
                        <span style="background: #eff6ff; color: #3b82f6; padding: 0.3rem 0.6rem; border-radius: 50px; font-size: 0.7rem; font-weight: 700; border: 1px solid #dbeafe;">
                            <?php echo e($f); ?>
                        </span>
                    <?php endforeach; ?>
                </div>

                <?php if ($is_filtered): ?>
                    <div style="margin-left: auto; display: flex; align-items: center;">
                        <a href="?view=<?php echo e($view); ?>" style="color: #ef4444; font-size: 0.75rem; font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 0.25rem; background: #fff1f2; padding: 0.4rem 0.8rem; border-radius: 8px; border: 1px solid #fecaca;">
                            <i class="fas fa-trash-alt"></i> <?php echo __('common.clear_filter'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </form>

        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
            <a href="add.php" class="btn btn-primary shadow-sm" style="padding: 0.6rem 1.2rem; font-weight: 700; font-size: 0.9rem; border-radius: 12px; white-space: nowrap;">
                <i class="fas fa-plus"></i> <?php echo __('appointment.book_btn'); ?>
            </a>
            <div style="display: flex; gap: 0.4rem;">
                <a href="export.php?<?php echo http_build_query($_GET); ?>" class="btn btn-outline-primary" style="padding: 0.4rem; flex: 1; font-size: 0.75rem; border-radius: 8px; font-weight: 700;" title="Xuất CSV">
                    <i class="fas fa-file-export"></i> Xuất
                </a>
                <a href="import.php" class="btn btn-outline-success" style="padding: 0.4rem; flex: 1; font-size: 0.75rem; border-radius: 8px; font-weight: 700;" title="Nhập CSV">
                    <i class="fas fa-file-import"></i> Nhập
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function setPeriod(event, p) {
    if (event) event.preventDefault();
    document.getElementById('periodInput').value = p;
    // Clear specific date when selecting a period
    if (p !== 'custom') {
        const di = document.getElementById('dateInput');
        if (di) di.value = '';
        const form = document.getElementById('filterForm');
        if (form) form.submit();
    } else {
        document.getElementById('customDates').style.display = 'flex';
        document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
        if (event && event.target) {
            event.target.classList.add('active');
        }
    }
}
</script>



<?php if ($view === 'list'): ?>
    <div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--border-color);">
        <div style="overflow-x: auto;">
            <table class="table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8fafc; text-align: left;">
                        <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: var(--text-muted); font-weight: 700; border-bottom: 2px solid var(--border-color);"><?php echo __('appointment.table.time'); ?></th>
                        <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: var(--text-muted); font-weight: 700; border-bottom: 2px solid var(--border-color);"><?php echo __('appointment.table.patient'); ?></th>
                        <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: var(--text-muted); font-weight: 700; border-bottom: 2px solid var(--border-color);"><?php echo __('appointment.table.doctor'); ?></th>
                        <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: var(--text-muted); font-weight: 700; border-bottom: 2px solid var(--border-color);"><?php echo __('appointment.table.status'); ?></th>
                        <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: var(--text-muted); font-weight: 700; border-bottom: 2px solid var(--border-color); text-align: center;"><?php echo __('common.actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $a): ?>
                        <tr class="appointment-row" style="border-bottom: 1px solid var(--border-color); transition: background 0.2s;">
                            <td style="padding: 1.25rem 1.5rem;">
                                <div style="font-weight: 700; color: var(--text-main); font-size: 1rem;">
                                    <?php 
                                        echo date('H:i', strtotime($a['appointment_date']));
                                        if (!empty($a['appointment_end_time'])) {
                                            echo ' – ' . substr($a['appointment_end_time'], 0, 5);
                                        }
                                    ?>
                                </div>
                                <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo date('d/m/Y', strtotime($a['appointment_date'])); ?></div>
                            </td>
                            <td style="padding: 1.25rem 1.5rem;">
                                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.35rem; flex-wrap: wrap;">
                                    <span style="font-weight: 700; color: var(--text-main);"><?php echo e($a['contact_name']); ?></span>
                                    
                                    <div style="display: flex; gap: 0.25rem; align-items: center;">
                                        <span style="font-size: 0.6rem; font-weight: 800; padding: 0.15rem 0.4rem; border-radius: 4px; text-transform: uppercase; <?php echo $a['contact_type'] === 'Patient' ? 'background: #e0f2fe; color: #0369a1;' : 'background: #fef3c7; color: #92400e;'; ?>">
                                            <?php echo $a['contact_type'] === 'Patient' ? __('appointment.contact_type.patient') : __('appointment.contact_type.lead'); ?>
                                        </span>
                                        
                                        <?php if (!empty($a['patient_label'])): ?>
                                            <span style="font-size: 0.6rem; font-weight: 800; padding: 0.15rem 0.4rem; border-radius: 4px; background: #fee2e2; color: #b91c1c; text-transform: uppercase;">
                                                <i class="fas fa-tag"></i> <?php echo e(get_patient_label_translation($a['patient_label'])); ?>
                                            </span>
                                        <?php endif; ?>

                                        <?php if (!empty($a['notes'])): ?>
                                            <div class="note-tooltip-container">
                                                <i class="fas fa-sticky-note note-icon"></i>
                                                <div class="note-tooltip"><?php echo nl2br(e($a['notes'])); ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    <div style="font-size: 0.8rem; color: var(--primary); font-weight: 600;"><i class="fas fa-phone-alt" style="font-size: 0.7rem;"></i> <?php echo e($a['contact_phone']); ?></div>
                                    
                                    <?php 
                                    $t = $type_map[$a['type']] ?? array('label' => $a['type'], 'color' => '#64748b', 'icon' => 'fa-calendar');
                                    ?>
                                    <span style="font-size: 0.65rem; font-weight: 700; color: <?php echo $t['color']; ?>; background: <?php echo $t['color']; ?>1a; padding: 0.1rem 0.6rem; border-radius: 50px; border: 1px solid <?php echo $t['color']; ?>33;">
                                        <i class="fas <?php echo $t['icon']; ?>" style="font-size: 0.6rem;"></i> <?php echo $t['label']; ?>
                                    </span>
                                </div>
                            </td>
                            <td style="padding: 1.25rem 1.5rem; width: 220px;">
                                <form action="update_appointment.php" method="POST" class="quick-status-form">
                                    <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                    <select name="doctor_id" class="form-input" style="padding: 0.4rem; font-size: 0.9rem; border: 1px solid transparent; background: transparent; font-weight: 600; cursor: pointer;" onchange="updateAppointment(this)">
                                        <option value="">-- <?php echo __('appointment.unassigned_doctor'); ?> --</option>
                                        <?php foreach ($doctors as $doc): ?>
                                            <option value="<?php echo $doc['id']; ?>" <?php echo (int)$a['doctor_id'] === (int)$doc['id'] ? 'selected' : ''; ?>><?php echo e($doc['full_name']); ?> (<?php echo e($doc['role_name']); ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </td>
                            <td style="padding: 1.25rem 1.5rem;">
                                <?php 
                                $status = $status_map[$a['status']] ?? array('label' => $a['status'], 'color' => '#64748b', 'icon' => 'fa-question-circle');
                                ?>
                                <form action="update_appointment.php" method="POST">
                                    <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                    <select name="status" class="form-input" style="width: auto; padding: 0.35rem 0.75rem; border-radius: 50px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; border: 1px solid <?php echo $status['color']; ?>; color: <?php echo $status['color']; ?>; background: <?php echo $status['color']; ?>0d;" onchange="updateAppointment(this)">
                                        <?php foreach ($status_map as $key => $info): ?>
                                            <option value="<?php echo $key; ?>" <?php echo $a['status'] === $key ? 'selected' : ''; ?>><?php echo $info['label']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </td>
                            <td style="padding: 1.25rem 1.5rem;">
                                <div style="display: flex; gap: 0.5rem; justify-content: center; align-items: center;">
                                    <?php 
                                    $appt_date = date('Y-m-d', strtotime($a['appointment_date']));
                                    $is_today = $appt_date === date('Y-m-d');
                                    if ($a['contact_type'] === 'Lead' && $a['status'] !== 'cancelled' && $a['status'] !== 'arrived' && $is_today): 
                                    ?>
                                        <a href="checkin.php?id=<?php echo $a['id']; ?>" class="btn btn-sm" style="background: #10b981; color: white; padding: 0.5rem 1rem; font-weight: 700; border-radius: 10px; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2);">
                                            <i class="fas fa-sign-in-alt"></i> <?php echo __('appointment.btn.checkin'); ?>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <div class="dropdown-action" style="position: relative;">
                                        <button onclick="toggleAction(<?php echo $a['id']; ?>, event)" class="btn btn-sm" style="background: #f1f5f9; color: var(--text-muted); width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; padding: 0; border-radius: 10px;">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <div id="action-menu-<?php echo $a['id']; ?>" class="action-menu" style="display: none; position: absolute; right: 0; top: 100%; min-width: 220px; width: max-content; background: white; border-radius: 12px; box-shadow: var(--shadow-lg); z-index: 1100; padding: 0.5rem; border: 1px solid var(--border-color); margin-top: 0.5rem;">
                                            <a href="view.php?id=<?php echo $a['id']; ?>" class="action-item"><i class="fas fa-eye"></i> <?php echo __('common.view_details'); ?></a>
                                            <a href="edit.php?id=<?php echo $a['id']; ?>" class="action-item"><i class="fas fa-edit"></i> <?php echo __('common.edit'); ?></a>
                                            <?php if ($a['patient_id']): ?>
                                                <a href="../patients/view.php?id=<?php echo $a['patient_id']; ?>" class="action-item"><i class="fas fa-user"></i> <?php echo __('appointment.action.patient_profile'); ?></a>
                                            <?php endif; ?>
                                            
                                            <?php if ($a['status'] === 'arrived' && !empty($a['lead_id'])): ?>
                                                <a href="revert_checkin.php?id=<?php echo $a['id']; ?>" class="action-item" style="color: #f59e0b;" onclick="return confirm('<?php echo __('appointment.confirm.revert'); ?>')">
                                                    <i class="fas fa-undo"></i> <?php echo __('appointment.action.revert_checkin'); ?>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <form action="delete.php" method="POST" style="display:inline" onsubmit="return confirm('<?php echo __('appointment.confirm.delete'); ?>')">
                                                <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                                <button type="submit" class="action-item" style="color: #ef4444; background: none; border: none; cursor: pointer; width: 100%; text-align: left; padding: 0.6rem 0.75rem; font-size: 0.85rem; font-weight: 600; display: flex; align-items: center; gap: 0.75rem; border-radius: 8px;"><i class="fas fa-trash-alt" style="width: 16px; text-align: center; font-size: 0.9rem;"></i> <?php echo __('appointment.action.delete'); ?></button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php echo render_pagination($total_count, $limit, $page); ?>
<?php endif; ?>

<style>
.appointment-row:hover { background: #f1f5f9; }
.appointment-row select:hover { background: white !important; border-color: #e2e8f0 !important; }
.text-xs { font-size: 0.7rem; }
.filter-label {
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
    color: #94a3b8;
    margin-bottom: 0.5rem;
    display: block;
    letter-spacing: 0.025em;
}
.filter-input {
    height: 42px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.2s;
}
.filter-input:focus {
    background: white;
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
}
.btn-white {
    background: white;
    color: var(--primary);
}
.action-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.6rem 0.75rem;
    color: var(--text-main);
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 600;
    border-radius: 8px;
    white-space: nowrap;
    transition: all 0.2s;
}
.action-item:hover {
    background: #f8fafc;
    color: var(--primary);
}
.action-item i {
    width: 16px;
    text-align: center;
    font-size: 0.9rem;
    color: #94a3b8;
}
.action-item:hover i {
    color: var(--primary);
}

/* Tooltip styles */
.note-tooltip-container {
    position: relative;
    display: inline-block;
    margin-left: 0.5rem;
    cursor: help;
}
.note-icon {
    color: #f59e0b;
    font-size: 0.85rem;
    transition: transform 0.2s;
}
.note-tooltip-container:hover .note-icon {
    transform: scale(1.2);
}
.note-tooltip {
    visibility: hidden;
    width: 250px;
    background-color: #1e293b;
    color: #fff;
    text-align: left;
    border-radius: 12px;
    padding: 1rem;
    position: absolute;
    z-index: 1001;
    bottom: 125%;
    left: 50%;
    margin-left: -125px;
    opacity: 0;
    transition: opacity 0.3s;
    font-size: 0.75rem;
    line-height: 1.5;
    font-weight: 500;
    box-shadow: var(--shadow-lg);
    border: 1px solid rgba(255,255,255,0.1);
}
.note-tooltip::after {
    content: "";
    position: absolute;
    top: 100%;
    left: 50%;
    margin-left: -5px;
    border-width: 5px;
    border-style: solid;
    border-color: #1e293b transparent transparent transparent;
}
.note-tooltip-container:hover .note-tooltip {
    visibility: visible;
    opacity: 1;
}
</style>

<div id="quick-toast" style="position: fixed; bottom: 2rem; right: 2rem; background: #0f172a; color: white; padding: 0.8rem 1.5rem; border-radius: 12px; box-shadow: var(--shadow-lg); display: none; z-index: 9999; font-weight: 600; font-size: 0.9rem;">
    <i class="fas fa-check-circle" style="color: #10b981; margin-right: 0.5rem;"></i> <span id="toast-msg"></span>
</div>

<script>
function toggleAction(id, event) {
    event.stopPropagation();
    const menu = document.getElementById('action-menu-' + id);
    const allMenus = document.querySelectorAll('.action-menu');
    
    allMenus.forEach(m => {
        if (m.id !== 'action-menu-' + id) m.style.display = 'none';
    });
    
    if (menu.style.display === 'none' || menu.style.display === '') {
        menu.style.display = 'block';
        // Auto-flip if near bottom of viewport
        const rect = menu.getBoundingClientRect();
        if (rect.bottom > window.innerHeight) {
            menu.style.top = 'auto';
            menu.style.bottom = '100%';
            menu.style.marginTop = '0';
            menu.style.marginBottom = '0.5rem';
        } else {
            menu.style.top = '100%';
            menu.style.bottom = 'auto';
            menu.style.marginTop = '0.5rem';
            menu.style.marginBottom = '0';
        }
    } else {
        menu.style.display = 'none';
    }
}

document.addEventListener('click', function() {
    document.querySelectorAll('.action-menu').forEach(m => m.style.display = 'none');
});

function showToast(msg) {
    const toast = document.getElementById('quick-toast');
    document.getElementById('toast-msg').innerText = msg;
    toast.style.display = 'block';
    setTimeout(() => { toast.style.display = 'none'; }, 2000);
}

function updateAppointment(select) {
    const form = select.closest('form');
    const formData = new FormData(form);
    const selectName = select.name;

    select.style.opacity = '0.5';

    fetch('update_appointment.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        select.style.opacity = '1';
        if (data.success) {
            showToast(selectName === 'doctor_id' ? '<?php echo __('appointment.toast.saved_doctor'); ?>' : '<?php echo __('appointment.toast.saved_status'); ?>');
            if (selectName === 'status') {
                // Refresh to update colors if not doing it via CSS/JS dynamically
                setTimeout(() => location.reload(), 500);
            }
        } else {
            alert('<?php echo __('appointment.toast.save_error'); ?>');
            location.reload();
        }
    })
    .catch(error => {
        select.style.opacity = '1';
        console.error('Error:', error);
        location.reload();
    });
}
</script>

<?php require_once '../../templates/footer.php'; ?>
