<?php
// modules/appointments/timeline.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('view_appointments');

$page_title = __('menu.appointments.timeline');
$current_page = 'appointments';
$base_url = '../../';
require_once '../../templates/header.php';

$db = getDB();

// Save the current URL with filters so that edit/delete actions can redirect back here
$_SESSION['appointment_list_url'] = $_SERVER['REQUEST_URI'];

$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_filter = get_sticky_appointment_date();
$doctor_filter = isset($_GET['doctor_id']) ? $_GET['doctor_id'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$view = isset($_GET['view']) ? $_GET['view'] : 'timeline'; 

// If "view=list" somehow gets here, redirect back to index.php
if ($view === 'list') {
    header("Location: index.php?" . $_SERVER['QUERY_STRING']);
    exit;
}

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

// Handle Time Filtering for Timeline
if ($date_filter) {
    if ($view === 'timeline_week') {
        $ts = strtotime($date_filter);
        $start = date('Y-m-d 00:00:00', strtotime('monday this week', $ts));
        $end = date('Y-m-d 23:59:59', strtotime('sunday this week', $ts));
        $conditions[] = "a.appointment_date BETWEEN ? AND ?";
        $params[] = $start;
        $params[] = $end;
    } elseif ($view === 'timeline_month') {
        $ts = strtotime($date_filter);
        $start = date('Y-m-01 00:00:00', $ts);
        $end = date('Y-m-t 23:59:59', $ts);
        $conditions[] = "a.appointment_date BETWEEN ? AND ?";
        $params[] = $start;
        $params[] = $end;
    } else {
        $conditions[] = "DATE(a.appointment_date) = ?";
        $params[] = $date_filter;
    }
} else {
    // Default to today if no date filter is provided
    if ($view === 'timeline_week') {
        $start = date('Y-m-d 00:00:00', strtotime('monday this week'));
        $end = date('Y-m-d 23:59:59', strtotime('sunday this week'));
        $conditions[] = "a.appointment_date BETWEEN ? AND ?";
        $params[] = $start;
        $params[] = $end;
    } elseif ($view === 'timeline_month') {
        $start = date('Y-m-01 00:00:00');
        $end = date('Y-m-t 23:59:59');
        $conditions[] = "a.appointment_date BETWEEN ? AND ?";
        $params[] = $start;
        $params[] = $end;
    } else {
        $conditions[] = "DATE(a.appointment_date) = CURDATE()";
    }
}

if ($conditions) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$query .= " ORDER BY a.appointment_date ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

// Fetch staff
$doctors_stmt = $db->query("
    SELECT u.id, u.full_name, r.display_name as role_name 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    WHERE r.name IN ('doctor', 'technician', 'cskh', 'admin') AND u.status = 'active'
    ORDER BY r.name = 'doctor' DESC, u.full_name ASC
");
$doctors = $doctors_stmt->fetchAll();

$status_map = [
    'scheduled' => ['label' => __('appointment.status.scheduled'), 'color' => '#6366f1', 'icon' => 'fa-calendar-alt'],
    'confirmed' => ['label' => __('appointment.status.confirmed'), 'color' => '#8b5cf6', 'icon' => 'fa-check-double'],
    'arrived'   => ['label' => __('appointment.status.arrived'), 'color' => '#10b981', 'icon' => 'fa-walking'],
    'completed' => ['label' => __('appointment.status.completed'), 'color' => '#059669', 'icon' => 'fa-check-circle'],
    'no_show'   => ['label' => __('appointment.status.no_show'), 'color' => '#f59e0b', 'icon' => 'fa-user-slash'],
    'cancelled' => ['label' => __('appointment.status.cancelled'), 'color' => '#ef4444', 'icon' => 'fa-times-circle']
];

$type_map = [
    'consultation' => ['label' => __('appointment.type.consultation'), 'color' => '#3b82f6', 'icon' => 'fa-comments'],
    'dong_y_60'    => ['label' => __('appointment.type.dong_y_60'), 'color' => '#10b981', 'icon' => 'fa-leaf'],
    'dong_y_90'    => ['label' => __('appointment.type.dong_y_90'), 'color' => '#059669', 'icon' => 'fa-seedling'],
    'chiro'        => ['label' => __('appointment.type.chiro'), 'color' => '#f59e0b', 'icon' => 'fa-bone'],
    'support_other'=> ['label' => __('appointment.type.support_other'), 'color' => '#64748b', 'icon' => 'fa-hands-helping'],
    'treatment'    => ['label' => __('appointment.type.treatment'), 'color' => '#10b981', 'icon' => 'fa-hand-holding-medical'],
    're_exam'      => ['label' => __('appointment.type.re_exam'), 'color' => '#8b5cf6', 'icon' => 'fa-redo'],
    'adjustment'   => ['label' => __('appointment.type.adjustment'), 'color' => '#64748b', 'icon' => 'fa-tools']
];

$is_filtered = $search || $status_filter || $doctor_filter || $type_filter || ($date_filter && $date_filter != date('Y-m-d'));
?>

<style>
.filter-card {
    padding: 1rem !important;
    margin-bottom: 1.5rem !important;
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
                <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                    <span class="filter-label" style="margin-bottom: 0; margin-right: 0.25rem;">
                        <?php 
                            if ($view === 'timeline') echo __('appointment.filter_labels.date');
                            elseif ($view === 'timeline_week') echo __('appointment.filter_labels.week');
                            elseif ($view === 'timeline_month') echo __('appointment.filter_labels.month');
                        ?>
                    </span>
                    <?php if ($view === 'timeline'): ?>
                        <input type="date" name="date" style="border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.85rem; padding: 0.4rem 0.75rem; color: var(--text-main); outline: none; background: white;" value="<?php echo e($date_filter ?: date('Y-m-d')); ?>" onchange="this.form.submit()">
                    <?php elseif ($view === 'timeline_week'): ?>
                        <input type="week" name="date" style="border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.85rem; padding: 0.4rem 0.75rem; color: var(--text-main); outline: none; background: white;" value="<?php echo e($date_filter ? date('Y-\WW', strtotime($date_filter)) : date('Y-\WW')); ?>" onchange="this.form.submit()">
                    <?php elseif ($view === 'timeline_month'): ?>
                        <input type="month" name="date" style="border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.85rem; padding: 0.4rem 0.75rem; color: var(--text-main); outline: none; background: white;" value="<?php echo e($date_filter ? date('Y-m', strtotime($date_filter)) : date('Y-m')); ?>" onchange="this.form.submit()">
                    <?php endif; ?>
                </div>

                <?php if ($is_filtered): ?>
                    <div style="margin-left: auto;">
                        <a href="?view=<?php echo $view; ?>" style="color: #ef4444; font-size: 0.8rem; font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 0.25rem;">
                            <i class="fas fa-times-circle"></i> <?php echo __('common.clear_filter'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </form>
        <a href="add.php" class="btn btn-primary shadow-sm" style="padding: 0.6rem 1.2rem; font-weight: 700; font-size: 0.9rem; border-radius: 12px; white-space: nowrap;">
            <i class="fas fa-plus"></i> <?php echo __('appointment.book_btn'); ?>
        </a>
    </div>
</div>

<div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
    <div style="display: flex; gap: 0.5rem;">
        <?php 
        $active_filters = [];
        if ($search) $active_filters[] = __('appointment.filter_labels.search') . ": $search";
        if ($status_filter) $active_filters[] = __('appointment.filter_labels.status') . ": " . (isset($status_map[$status_filter]['label']) ? $status_map[$status_filter]['label'] : $status_filter);
        if ($type_filter) $active_filters[] = __('appointment.filter_labels.type') . ": " . (isset($type_map[$type_filter]['label']) ? $type_map[$type_filter]['label'] : $type_filter);
        
        if ($date_filter) {
            if ($view === 'timeline_month') {
                $active_filters[] = __('appointment.filter_labels.month') . ": " . date('m/Y', strtotime($date_filter));
            } else {
                $active_filters[] = __('appointment.filter_labels.date') . ": " . date('d/m/Y', strtotime($date_filter));
            }
        }
        ?>
        <?php foreach($active_filters as $f): ?>
            <span style="background: #eff6ff; color: #3b82f6; padding: 0.35rem 0.75rem; border-radius: 50px; font-size: 0.75rem; font-weight: 700; border: 1px solid #dbeafe;">
                <?php echo e($f); ?>
            </span>
        <?php endforeach; ?>
    </div>

    <div style="display: flex; background: #f1f5f9; padding: 0.3rem; border-radius: 14px;">
        <?php 
        $view_btn_params = "search=$search&status=$status_filter&date=$date_filter&doctor_id=$doctor_filter&type=$type_filter";
        $cell_btn_params = "search=$search&status=$status_filter&doctor_id=$doctor_filter&type=$type_filter";
        ?>
        <a href="?view=timeline&<?php echo $view_btn_params; ?>" class="btn btn-sm <?php echo $view === 'timeline' ? 'btn-white shadow-sm' : ''; ?>" style="border-radius: 10px; padding: 0.5rem 1rem; border: none; font-weight: 700; color: <?php echo $view === 'timeline' ? 'var(--primary)' : 'var(--text-muted)'; ?>; background: <?php echo $view === 'timeline' ? 'white' : 'transparent'; ?>;">
            <i class="fas fa-clock"></i> <?php echo __('appointment.view.day'); ?>
        </a>
        <a href="?view=timeline_week&<?php echo $view_btn_params; ?>" class="btn btn-sm <?php echo $view === 'timeline_week' ? 'btn-white shadow-sm' : ''; ?>" style="border-radius: 10px; padding: 0.5rem 1rem; border: none; font-weight: 700; color: <?php echo $view === 'timeline_week' ? 'var(--primary)' : 'var(--text-muted)'; ?>; background: <?php echo $view === 'timeline_week' ? 'white' : 'transparent'; ?>;">
            <i class="fas fa-calendar-week"></i> <?php echo __('appointment.view.week'); ?>
        </a>
        <a href="?view=timeline_month&<?php echo $view_btn_params; ?>" class="btn btn-sm <?php echo $view === 'timeline_month' ? 'btn-white shadow-sm' : ''; ?>" style="border-radius: 10px; padding: 0.5rem 1rem; border: none; font-weight: 700; color: <?php echo $view === 'timeline_month' ? 'var(--primary)' : 'var(--text-muted)'; ?>; background: <?php echo $view === 'timeline_month' ? 'white' : 'transparent'; ?>;">
            <i class="fas fa-calendar-alt"></i> <?php echo __('appointment.view.month'); ?>
        </a>
    </div>
</div>

<?php if ($view === 'timeline'): ?>
    <!-- Day Timeline View -->
    <div class="card" style="padding: 1.5rem; overflow-x: auto;">
        <?php
        $target_date = $date_filter ?: date('Y-m-d');
        $doctors_with_unassigned = array_merge([['id' => 0, 'full_name' => __('appointment.unassigned_doctor'), 'role_name' => 'N/A']], $doctors);
        
        // Group appointments by staff for this specific day
        $day_grouped = [];
        foreach ($appointments as $a) {
            $did = $a['doctor_id'] ?: 0;
            $day_grouped[$did][] = $a;
        }
        ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
            <div style="flex: 1; min-width: 150px;"></div>
            <h2 style="margin: 0; font-weight: 800; color: var(--text-main); text-align: center; flex: 2; min-width: 250px;"><?php echo __('appointment.day_schedule.title'); ?> <?php echo date('d/m/Y', strtotime($target_date)); ?></h2>
            <div style="flex: 1; display: flex; align-items: center; gap: 0.5rem; justify-content: flex-end; min-width: 150px;">
                <i class="fas fa-search-minus" style="color: var(--text-muted); font-size: 0.85rem;" title="Thu nhỏ (Nhiều cột)"></i>
                <input type="range" id="zoomSliderDay" min="150" max="450" value="300" style="width: 120px; cursor: pointer; accent-color: var(--primary);" title="Thay đổi kích thước cột">
                <i class="fas fa-search-plus" style="color: var(--text-muted); font-size: 0.85rem;" title="Phóng to (Ít cột)"></i>
            </div>
        </div>

        <div id="dayGridContainer" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;">
            <?php foreach ($doctors_with_unassigned as $doc): ?>
                <?php 
                $staff_appts = isset($day_grouped[$doc['id']]) ? $day_grouped[$doc['id']] : [];
                if (empty($staff_appts) && $doc['id'] !== 0) continue; 
                ?>
                <div class="staff-day-column" style="background: #f8fafc; border-radius: 16px; padding: 0.85rem; border: 1px solid var(--border-color);">
                    <div style="margin-bottom: 1rem; padding-bottom: 0.6rem; border-bottom: 2px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; gap: 5px; flex-wrap: wrap;">
                        <span style="font-weight: 800; color: var(--text-main); font-size: 0.85rem; line-height: 1.3;">
                            <i class="fas fa-user-md" style="color: var(--primary); margin-right: 0.4rem;"></i>
                            <?php echo e($doc['full_name']); ?>
                        </span>
                        <span class="session-counter" style="font-size: 0.65rem; font-weight: 700; background: #e2e8f0; color: var(--text-muted); padding: 0.2rem 0.5rem; border-radius: 6px; white-space: nowrap;">
                            <span class="count-number"><?php echo count($staff_appts); ?></span> <?php echo __('appointment.session_count'); ?>
                        </span>
                    </div>

                    <div class="sortable-list" data-doctor-id="<?php echo $doc['id']; ?>" style="display: flex; flex-direction: column; gap: 0.6rem; min-height: 50px;">
                        <?php if (empty($staff_appts)): ?>
                            <div class="empty-list-placeholder" style="text-align: center; padding: 1.5rem 0; color: var(--text-muted); font-size: 0.8rem; font-style: italic;">
                                <?php echo __('appointment.no_appointments'); ?>
                            </div>
                        <?php else: ?>
                            <?php foreach ($staff_appts as $a): 
                                $status = isset($status_map[$a['status']]) ? $status_map[$a['status']] : ['color' => '#64748b', 'label' => 'Unknown'];
                            ?>
                                <div class="day-event-card" data-appointment-id="<?php echo $a['id']; ?>" style="background: white; border-radius: 12px; padding: 0.75rem; box-shadow: var(--shadow-sm); border-left: 4px solid <?php echo $status['color']; ?>; cursor: grab; transition: transform 0.2s;" onclick="location.href='view.php?id=<?php echo $a['id']; ?>'">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.4rem; flex-wrap: wrap; gap: 4px;">
                                        <span style="font-weight: 800; font-size: 0.8rem; color: var(--text-main); line-height: 1.2;">
                                            <?php 
                                                echo date('H:i', strtotime($a['appointment_date']));
                                                if (!empty($a['appointment_end_time'])) echo ' – ' . substr($a['appointment_end_time'], 0, 5);
                                            ?>
                                        </span>
                                        <span style="font-size: 0.6rem; font-weight: 800; text-transform: uppercase; color: <?php echo $status['color']; ?>; text-align: right; line-height: 1.2; word-break: break-word;"><?php echo $status['label']; ?></span>
                                    </div>
                                    <div style="font-weight: 700; font-size: 0.8rem; color: var(--text-main); margin-bottom: 0.35rem; line-height: 1.3;">
                                        <span style="color: var(--text-muted); font-size: 0.7rem;">[<?php echo $a['contact_type'] === 'Patient' ? __('appointment.contact_type.patient') : __('appointment.contact_type.lead'); ?>]</span> 
                                        <?php echo e($a['contact_name']); ?>
                                        <?php if(!empty($a['patient_label'])): ?>
                                            (<?php echo e(get_patient_label_translation($a['patient_label'])); ?>)
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size: 0.7rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.4rem;">
                                        <i class="fas fa-phone-alt" style="font-size: 0.6rem;"></i> <?php echo e($a['contact_phone']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const zoomSlider = document.getElementById('zoomSliderDay');
            const dayGrid = document.getElementById('dayGridContainer');
            
            if(zoomSlider && dayGrid) {
                // Apply saved preference if exists
                const savedZoom = localStorage.getItem('crmTimelineDayZoom');
                if(savedZoom) {
                    zoomSlider.value = savedZoom;
                    dayGrid.style.gridTemplateColumns = `repeat(auto-fill, minmax(${savedZoom}px, 1fr))`;
                }

                // Smoothly update while dragging
                zoomSlider.addEventListener('input', function() {
                    dayGrid.style.gridTemplateColumns = `repeat(auto-fill, minmax(${this.value}px, 1fr))`;
                });

                // Save to localStorage when released
                zoomSlider.addEventListener('change', function() {
                    localStorage.setItem('crmTimelineDayZoom', this.value);
                });
            }

            // Drag and Drop Logic
            if (typeof Sortable !== 'undefined') {
                const lists = document.querySelectorAll('.sortable-list');
                lists.forEach(function(list) {
                    new Sortable(list, {
                        group: 'shared', 
                        animation: 150,
                        ghostClass: 'sortable-ghost',
                        dragClass: 'sortable-drag',
                        onStart: function(evt) {
                            document.body.style.cursor = 'grabbing';
                        },
                        onEnd: function(evt) {
                            document.body.style.cursor = 'default';
                            
                            const itemEl = evt.item;
                            const fromEl = evt.from;
                            const toEl = evt.to;

                            if (fromEl === toEl) return;

                            const appointmentId = itemEl.getAttribute('data-appointment-id');
                            const newDoctorId = toEl.getAttribute('data-doctor-id');
                            
                            // Remove empty placeholder from target if exists
                            const toPlaceholder = toEl.querySelector('.empty-list-placeholder');
                            if (toPlaceholder) toPlaceholder.style.display = 'none';

                            // Show empty placeholder in source if empty
                            if (fromEl.querySelectorAll('.day-event-card').length === 0) {
                                const fromPlaceholder = fromEl.querySelector('.empty-list-placeholder');
                                if (fromPlaceholder) {
                                    fromPlaceholder.style.display = 'block';
                                } else {
                                    fromEl.insertAdjacentHTML('afterbegin', '<div class="empty-list-placeholder" style="text-align: center; padding: 1.5rem 0; color: var(--text-muted); font-size: 0.8rem; font-style: italic;"><?php echo __('appointment.no_appointments'); ?></div>');
                                }
                            }

                            // Update counters
                            const fromCounter = fromEl.closest('.staff-day-column').querySelector('.count-number');
                            const toCounter = toEl.closest('.staff-day-column').querySelector('.count-number');
                            
                            if (fromCounter) fromCounter.textContent = parseInt(fromCounter.textContent) - 1;
                            if (toCounter) toCounter.textContent = parseInt(toCounter.textContent) + 1;

                            // Call API
                            fetch('api_update_doctor.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    appointment_id: appointmentId,
                                    doctor_id: newDoctorId
                                })
                            })
                            .then(res => res.json())
                            .then(data => {
                                if(!data.success) {
                                    alert('Error: ' + data.message);
                                    window.location.reload();
                                } else {
                                    // Make card blink to show success
                                    itemEl.style.transition = 'background-color 0.3s';
                                    itemEl.style.backgroundColor = '#ecfdf5'; // light emerald
                                    setTimeout(() => itemEl.style.backgroundColor = 'white', 1000);
                                }
                            })
                            .catch(err => {
                                alert('Network error occurred.');
                                window.location.reload();
                            });
                        }
                    });
                });
            }
        });
        </script>
    </div>
<?php elseif ($view === 'timeline_week'): ?>
    <!-- Week Timeline View -->
    <div class="card" style="padding: 1.5rem; overflow-x: auto;">
        <?php
        $start_date = $date_filter ?: date('Y-m-d');
        $ts = strtotime($start_date);
        $start_of_week = date('Y-m-d', strtotime('monday this week', $ts));
        $week_days = [];
        for ($i = 0; $i < 7; $i++) {
            $week_days[] = date('Y-m-d', strtotime("+$i days", strtotime($start_of_week)));
        }
        
        $doctors_with_unassigned = array_merge([['id' => 0, 'full_name' => __('appointment.unassigned_doctor')]], $doctors);
        
        // Group appointments by doctor and day
        $grouped_week = [];
        foreach ($appointments as $a) {
            $did = $a['doctor_id'] ?: 0;
            $day = date('Y-m-d', strtotime($a['appointment_date']));
            $grouped_week[$did][$day][] = $a;
        }
        ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
            <div style="flex: 1; min-width: 150px;"></div>
            <h2 style="margin: 0; font-weight: 800; color: var(--text-main); text-align: center; flex: 2; min-width: 250px;">Tuần từ <?php echo date('d/m/Y', strtotime($start_of_week)); ?></h2>
            <div style="flex: 1; display: flex; align-items: center; gap: 0.5rem; justify-content: flex-end; min-width: 150px;">
                <i class="fas fa-search-minus" style="color: var(--text-muted); font-size: 0.85rem;" title="Thu nhỏ (Nhiều cột)"></i>
                <input type="range" id="zoomSliderWeek" min="600" max="2500" value="1000" style="width: 120px; cursor: pointer; accent-color: var(--primary);" title="Thay đổi kích thước bảng">
                <i class="fas fa-search-plus" style="color: var(--text-muted); font-size: 0.85rem;" title="Phóng to (Ít cột)"></i>
            </div>
        </div>
        
        <div id="weekGridContainer" class="timeline-grid" style="display: grid; grid-template-columns: 150px repeat(7, minmax(80px, 1fr)); min-width: 1000px; transition: min-width 0.1s ease-out;">
            <!-- Header Row -->
            <div style="padding: 1rem; border-bottom: 2px solid #e2e8f0; font-weight: 800; color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase;"><?php echo __('appointment.doctor'); ?></div>
            <?php foreach ($week_days as $day): ?>
                <div style="padding: 1rem; border-bottom: 2px solid #e2e8f0; text-align: center; border-left: 1px solid #f1f5f9; font-weight: 700; background: <?php echo $day === date('Y-m-d') ? '#fffbeb' : 'transparent'; ?>;">
                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;"><?php echo date('D', strtotime($day)); ?></div>
                    <div style="font-size: 1rem; color: var(--text-main);"><?php echo date('d/m', strtotime($day)); ?></div>
                </div>
            <?php endforeach; ?>

            <!-- Doctor Rows -->
            <?php foreach ($doctors_with_unassigned as $doc): ?>
                <div style="padding: 1rem; border-bottom: 1px solid #e2e8f0; font-weight: 700; background: #f8fafc; border-right: 1px solid #e2e8f0; display: flex; align-items: center; font-size: 0.9rem;">
                    <?php echo e($doc['full_name']); ?>
                </div>
                <?php foreach ($week_days as $day): ?>
                    <div style="border-bottom: 1px solid #e2e8f0; border-left: 1px solid #f1f5f9; padding: 0.5rem; min-height: 100px; background: white;">
                        <?php 
                        $day_appts = isset($grouped_week[$doc['id']][$day]) ? $grouped_week[$doc['id']][$day] : [];
                        foreach ($day_appts as $a): 
                            $status = isset($status_map[$a['status']]) ? $status_map[$a['status']] : ['color' => '#64748b'];
                        ?>
                            <div class="week-event" style="background: <?php echo $status['color']; ?>; color: white; padding: 0.35rem 0.6rem; border-radius: 6px; font-size: 0.7rem; margin-bottom: 0.25rem; cursor: pointer; position: relative;" onclick="location.href='view.php?id=<?php echo $a['id']; ?>'">
                                <strong style="display: block;"><?php echo date('H:i', strtotime($a['appointment_date'])); ?><?php if (!empty($a['appointment_end_time'])) echo '–' . substr($a['appointment_end_time'], 0, 5); ?></strong>
                                <span style="display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    [<?php echo $a['contact_type'] === 'Patient' ? __('appointment.contact_type.patient') : __('appointment.contact_type.lead'); ?>] 
                                    <?php echo e($a['contact_name']); ?>
                                    <?php if(!empty($a['patient_label'])): ?>
                                        (<?php echo e(get_patient_label_translation($a['patient_label'])); ?>)
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const zoomSliderW = document.getElementById('zoomSliderWeek');
            const weekGridW = document.getElementById('weekGridContainer');
            
            if(zoomSliderW && weekGridW) {
                const savedZoomW = localStorage.getItem('timeline_week_zoom');
                if(savedZoomW) {
                    zoomSliderW.value = savedZoomW;
                    weekGridW.style.minWidth = savedZoomW + 'px';
                }
                
                zoomSliderW.addEventListener('input', function() {
                    weekGridW.style.minWidth = this.value + 'px';
                });
                
                zoomSliderW.addEventListener('change', function() {
                    localStorage.setItem('timeline_week_zoom', this.value);
                });
            }
        });
        </script>
    </div>
<?php elseif ($view === 'timeline_month'): ?>
    <!-- Month Timeline View -->
    <div class="card" style="padding: 1.5rem; border-radius: 20px; overflow-x: auto;">
        <?php
        $target_date = $date_filter ?: date('Y-m-d');
        $ts = strtotime($target_date);
        $month = date('m', $ts);
        $year = date('Y', $ts);
        
        $first_day_ts = strtotime("$year-$month-01");
        $first_day_of_week = date('N', $first_day_ts) - 1; 
        $days_in_month = date('t', $first_day_ts);
        
        $grouped_month = [];
        foreach ($appointments as $a) {
            $day = (int)date('d', strtotime($a['appointment_date']));
            $grouped_month[$day][] = $a;
        }
        ?>
        <div class="calendar-grid" style="display: grid; grid-template-columns: repeat(7, minmax(130px, 1fr)); gap: 12px; min-width: 900px;">
            <?php foreach ([__('common.day.mon'), __('common.day.tue'), __('common.day.wed'), __('common.day.thu'), __('common.day.fri'), __('common.day.sat'), __('common.day.sun')] as $lbl): ?>
                <div style="text-align: center; font-size: 0.7rem; font-weight: 800; color: #94a3b8; padding: 0.5rem; text-transform: uppercase;"><?php echo $lbl; ?></div>
            <?php endforeach; ?>

            <?php for ($i = 0; $i < $first_day_of_week; $i++): ?>
                <div style="min-height: 130px; background: #f8fafc; border-radius: 16px; border: 1px dashed #e2e8f0;"></div>
            <?php endfor; ?>

            <?php for ($d = 1; $d <= $days_in_month; $d++): 
                $current_date_str = "$year-$month-" . str_pad($d, 2, '0', STR_PAD_LEFT);
                $is_today = ($current_date_str == date('Y-m-d'));
                $day_appts = isset($grouped_month[$d]) ? $grouped_month[$d] : [];
            ?>
                <div style="min-height: 130px; background: white; border-radius: 16px; border: 1px solid <?php echo $is_today ? 'var(--primary)' : '#eef2f6'; ?>; padding: 0.85rem; cursor: pointer;" 
                     class="calendar-day-cell <?php echo $is_today ? 'today' : ''; ?>"
                     onclick="location.href='?view=timeline&date=<?php echo $current_date_str; ?>&<?php echo $cell_btn_params; ?>'">
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem;">
                        <span style="font-weight: 800; font-size: 1.15rem; color: <?php echo $is_today ? 'var(--primary)' : 'var(--text-main)'; ?>;"><?php echo $d; ?></span>
                        <?php if (count($day_appts) > 0): ?>
                            <span style="background: <?php echo $is_today ? 'var(--primary)' : '#f1f5f9'; ?>; color: <?php echo $is_today ? 'white' : '#475569'; ?>; font-size: 0.65rem; font-weight: 800; padding: 0.2rem 0.5rem; border-radius: 6px;">
                                <?php echo count($day_appts); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 5px;">
                        <?php foreach (array_slice($day_appts, 0, 3) as $a): 
                                $status = isset($status_map[$a['status']]) ? $status_map[$a['status']] : ['color' => '#64748b'];
                        ?>
                            <div style="font-size: 0.65rem; padding: 4px 8px; border-radius: 6px; background: <?php echo $status['color']; ?>; color: white; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 700;">
                                <?php echo date('H:i', strtotime($a['appointment_date'])); ?> <?php echo e($a['contact_name']); ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($day_appts) > 3): ?>
                            <div style="font-size: 0.6rem; color: var(--text-muted); text-align: center; font-weight: 700;">+ <?php echo count($day_appts) - 3; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>
<?php endif; ?>

<style>
.btn-white { background: white; color: var(--primary); }
.calendar-day-cell:hover { border-color: var(--primary) !important; transform: translateY(-2px); box-shadow: var(--shadow-md); transition: all 0.2s; }
.day-event-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); transition: all 0.2s; }
.week-event:hover { opacity: 0.9; transform: scale(1.02); transition: all 0.2s; }
</style>

<?php require_once '../../templates/footer.php'; ?>
