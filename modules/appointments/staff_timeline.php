<?php
// modules/appointments/staff_timeline.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

$page_title = __('menu.appointments.staff_timeline');
$current_page = 'appointments';
$base_url = '../../';
require_once '../../templates/header.php';

$db = getDB();

$date_filter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';

// Fetch relevant staff (Doctors & Technicians)
$staff_query = "
    SELECT u.id, u.full_name, r.name as role_name, r.display_name as role_display
    FROM users u
    JOIN roles r ON u.role_id = r.id
    WHERE r.name IN ('doctor', 'technician') AND u.status = 'active'
";
if ($role_filter) {
    $staff_query .= " AND r.name = :role";
}
$staff_query .= " ORDER BY r.name = 'doctor' DESC, u.full_name ASC";

$staff_stmt = $db->prepare($staff_query);
if ($role_filter) {
    $staff_stmt->bindValue(':role', $role_filter);
}
$staff_stmt->execute();
$staff_members = $staff_stmt->fetchAll();

// Fetch appointments for this day
$appt_query = "
    SELECT 
        a.*, 
        COALESCE(p.full_name, l.full_name) as contact_name,
        COALESCE(p.phone, l.phone) as contact_phone,
        p.label as patient_label,
        CASE WHEN a.patient_id IS NOT NULL THEN 'Patient' ELSE 'Lead' END as contact_type
    FROM appointments a
    LEFT JOIN patients p ON a.patient_id = p.id
    LEFT JOIN leads l ON a.lead_id = l.id
    WHERE DATE(a.appointment_date) = :date
";
$appt_stmt = $db->prepare($appt_query);
$appt_stmt->execute([':date' => $date_filter]);
$appointments = $appt_stmt->fetchAll();

// Map appointments to staff
$staff_appts = [];
foreach ($appointments as $a) {
    $sid = $a['doctor_id'] ?: 0;
    $staff_appts[$sid][] = $a;
}

// Define Time Range (08:00 to 20:00)
$start_hour = 8;
$end_hour = 20;
$time_slots = [];
for ($h = $start_hour; $h < $end_hour; $h++) {
    $time_slots[] = sprintf("%02d:00", $h);
    $time_slots[] = sprintf("%02d:30", $h);
}

// Status Color Mapping
$status_colors = [
    'scheduled' => ['bg' => '#ffffff', 'text' => '#64748b', 'border' => '#e2e8f0', 'indicator' => '#cbd5e1'], // White/Gray (Chưa khám)
    'confirmed' => ['bg' => '#f5f3ff', 'text' => '#7c3aed', 'border' => '#ddd6fe', 'indicator' => '#8b5cf6'],
    'arrived'   => ['bg' => '#f0fdf4', 'text' => '#166534', 'border' => '#bbf7d0', 'indicator' => '#22c55e'], // Light Green
    'treated'   => ['bg' => '#add8e6', 'text' => '#0369a1', 'border' => '#93c5fd', 'indicator' => '#0ea5e9'], // Light Blue
    'completed' => ['bg' => '#1e40af', 'text' => '#ffffff', 'border' => '#1e3a8a', 'indicator' => '#3b82f6'], // Dark Blue
    'no_show'   => ['bg' => '#fef08a', 'text' => '#854d0e', 'border' => '#fde047', 'indicator' => '#eab308'], // Yellow
    'cancelled' => ['bg' => '#fff1f2', 'text' => '#be123c', 'border' => '#fecdd3', 'indicator' => '#f43f5e'],
    'staff_sick'=> ['bg' => '#f87171', 'text' => '#ffffff', 'border' => '#ef4444', 'indicator' => '#dc2626'], // Red
    'staff_busy'=> ['bg' => '#fef3c7', 'text' => '#92400e', 'border' => '#fcd34d', 'indicator' => '#f59e0b']
];

function get_status_style($status) {
    global $status_colors;
    return $status_colors[$status] ?? ['bg' => '#f1f5f9', 'text' => '#475569', 'border' => '#e2e8f0', 'indicator' => '#94a3b8'];
}

?>

<style>
:root {
    --timeline-row-height: 50px; /* More compact like THEORG */
    --staff-col-width: 200px;
}

.timeline-container {
    background: white;
    border-radius: 12px;
    box-shadow: var(--shadow-lg);
    overflow: hidden;
    border: 1px solid #cbd5e1;
    display: flex;
    flex-direction: column;
    height: calc(100vh - 250px);
    min-height: 500px;
}

.timeline-header {
    display: flex;
    background: #f1f5f9;
    border-bottom: 2px solid #94a3b8;
    z-index: 10;
}

.time-col-header {
    width: 60px;
    flex-shrink: 0;
    border-right: 2px solid #94a3b8;
}

.staff-headers {
    display: flex;
    flex: 1;
    overflow-x: auto;
}

.staff-header-cell {
    width: var(--staff-col-width);
    min-width: var(--staff-col-width);
    padding: 0.75rem;
    text-align: center;
    border-right: 1px solid #94a3b8;
    font-weight: 800;
    color: var(--text-main);
    background: #f8fafc;
}

.staff-role-badge {
    display: block;
    font-size: 0.65rem;
    text-transform: uppercase;
    color: var(--text-muted);
    letter-spacing: 0.05em;
    margin-top: 0.15rem;
}

.timeline-body {
    display: flex;
    flex: 1;
    overflow-y: auto;
    position: relative;
}

.time-axis {
    width: 60px;
    flex-shrink: 0;
    background: #f8fafc;
    border-right: 2px solid #94a3b8;
    position: sticky;
    left: 0;
    z-index: 5;
}

.time-slot-label {
    height: var(--timeline-row-height);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: 800;
    color: #475569;
    border-bottom: 1px solid #e2e8f0;
}

.staff-columns-container {
    display: flex;
    flex: 1;
    position: relative;
    background-image: linear-gradient(to bottom, transparent calc(var(--timeline-row-height) - 1px), #e2e8f0 1px);
    background-size: 100% var(--timeline-row-height);
}

.staff-column {
    width: var(--staff-col-width);
    min-width: var(--staff-col-width);
    border-right: 1px solid #cbd5e1;
    position: relative;
}

.appt-block {
    position: absolute;
    left: 4px;
    right: 4px;
    border-radius: 6px;
    padding: 4px 6px 4px 12px; /* Extra left padding for indicator */
    font-size: 0.75rem;
    overflow: hidden;
    border: 1px solid transparent;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    z-index: 2;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

/* Status Indicator Bar like THEORG */
.appt-block::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 6px;
    background: var(--indicator-color);
}

.appt-block:hover {
    transform: scale(1.01);
    z-index: 10;
    box-shadow: var(--shadow-lg);
    filter: brightness(0.95);
}

.appt-time {
    font-weight: 800;
    font-size: 0.7rem;
    display: flex;
    align-items: center;
    gap: 2px;
    margin-bottom: 2px;
}

.appt-name {
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block;
    line-height: 1.1;
}

.appt-status-text {
    font-size: 0.6rem;
    font-weight: 800;
    text-transform: uppercase;
    margin-top: 2px;
    opacity: 0.7;
}

/* Scrollbar Styling */
.timeline-body::-webkit-scrollbar { width: 8px; height: 8px; }
.timeline-body::-webkit-scrollbar-track { background: #f1f5f9; }
.timeline-body::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.timeline-body::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>

<div class="card" style="margin-bottom: 1.5rem; padding: 1.25rem !important; border-radius: 16px;">
    <form method="GET" style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem; background: #f1f5f9; padding: 0.5rem 1rem; border-radius: 12px; border: 1px solid #e2e8f0;">
                <i class="fas fa-calendar-alt" style="color: var(--primary);"></i>
                <input type="date" name="date" class="form-input" value="<?php echo e($date_filter); ?>" style="border: none; background: transparent; padding: 0; outline: none; font-weight: 700; color: var(--text-main);" onchange="this.form.submit()">
            </div>
            
            <div class="btn-group" style="background: #f1f5f9; padding: 0.3rem; border-radius: 12px;">
                <a href="?date=<?php echo $date_filter; ?>&role=" class="btn btn-sm <?php echo $role_filter === '' ? 'btn-white shadow-sm' : ''; ?>" style="border-radius: 9px; padding: 0.4rem 0.8rem; border: none; font-weight: 700; color: <?php echo $role_filter === '' ? 'var(--primary)' : 'var(--text-muted)'; ?>; background: <?php echo $role_filter === '' ? 'white' : 'transparent'; ?>;">
                    <?php echo __('common.all'); ?>
                </a>
                <a href="?date=<?php echo $date_filter; ?>&role=doctor" class="btn btn-sm <?php echo $role_filter === 'doctor' ? 'btn-white shadow-sm' : ''; ?>" style="border-radius: 9px; padding: 0.4rem 0.8rem; border: none; font-weight: 700; color: <?php echo $role_filter === 'doctor' ? 'var(--primary)' : 'var(--text-muted)'; ?>; background: <?php echo $role_filter === 'doctor' ? 'white' : 'transparent'; ?>;">
                    <?php echo __('role.doctor'); ?>
                </a>
                <a href="?date=<?php echo $date_filter; ?>&role=technician" class="btn btn-sm <?php echo $role_filter === 'technician' ? 'btn-white shadow-sm' : ''; ?>" style="border-radius: 9px; padding: 0.4rem 0.8rem; border: none; font-weight: 700; color: <?php echo $role_filter === 'technician' ? 'var(--primary)' : 'var(--text-muted)'; ?>; background: <?php echo $role_filter === 'technician' ? 'white' : 'transparent'; ?>;">
                    <?php echo __('role.technician'); ?>
                </a>
            </div>
        </div>

        <div style="display: flex; gap: 10px; margin-right: 1rem; flex-wrap: wrap;">
            <?php 
            $legend_statuses = ['scheduled', 'arrived', 'treated', 'completed', 'no_show', 'staff_sick'];
            foreach ($legend_statuses as $ls): 
                $l_style = get_status_style($ls);
            ?>
                <div style="display: flex; align-items: center; gap: 4px; font-size: 0.7rem; font-weight: 700;">
                    <span style="width: 12px; height: 12px; border-radius: 3px; background: <?php echo $l_style['bg']; ?>; border: 1px solid <?php echo $l_style['border']; ?>;"></span> 
                    <?php echo __('appointment.status.' . $ls); ?>
                </div>
            <?php endforeach; ?>
        </div>
        <a href="add.php?date=<?php echo $date_filter; ?>" class="btn btn-primary" style="border-radius: 12px; font-weight: 700;">
            <i class="fas fa-plus"></i> <?php echo __('appointment.book_btn'); ?>
        </a>
    </div>
</form>
</div>

<div class="timeline-container">
<div class="timeline-header">
    <div class="time-col-header"></div>
    <div class="staff-headers">
        <?php foreach ($staff_members as $staff): ?>
            <div class="staff-header-cell">
                <?php echo e($staff['full_name']); ?>
                <span class="staff-role-badge"><?php echo e($staff['role_display']); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="timeline-body">
    <div class="time-axis">
        <?php foreach ($time_slots as $slot): ?>
            <div class="time-slot-label"><?php echo $slot; ?></div>
        <?php endforeach; ?>
    </div>

    <div class="staff-columns-container">
        <?php foreach ($staff_members as $staff): ?>
            <div class="staff-column">
                <?php 
                $appts = isset($staff_appts[$staff['id']]) ? $staff_appts[$staff['id']] : [];
                foreach ($appts as $a): 
                    // Calculate position
                    $st = strtotime($a['appointment_date']);
                    $h = (int)date('H', $st);
                    $m = (int)date('i', $st);
                    
                    if ($h < $start_hour || $h >= $end_hour) continue;
                    
                    $offset_minutes = (($h - $start_hour) * 60) + $m;
                    $top = ($offset_minutes / 30) * 50; // 50px per 30 mins (matches --timeline-row-height)
                    
                    // Calculate duration
                    $duration = 30; // default
                    if (!empty($a['appointment_end_time'])) {
                        $et = strtotime(date('Y-m-d', $st) . ' ' . $a['appointment_end_time']);
                        $duration = ($et - $st) / 60;
                    }
                    $height = ($duration / 30) * 50 - 4; // -4 for margins
                    
                    $style = get_status_style($a['status']);
                ?>
                    <div class="appt-block" 
                         style="top: <?php echo $top; ?>px; height: <?php echo $height; ?>px; background: <?php echo $style['bg']; ?>; color: <?php echo $style['text']; ?>; border-color: <?php echo $style['border']; ?>; --indicator-color: <?php echo $style['indicator']; ?>;"
                         onclick="location.href='view.php?id=<?php echo $a['id']; ?>'">
                        <span class="appt-time">
                            <i class="far fa-clock"></i>
                            <?php echo date('H:i', $st); ?>
                        </span>
                        <span class="appt-name"><?php echo e($a['contact_name']); ?></span>
                        <?php if($height > 35): ?>
                            <span class="appt-status-text"><?php echo __('appointment.status.' . $a['status']); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
</div>

<?php require_once '../../templates/footer.php'; ?>
