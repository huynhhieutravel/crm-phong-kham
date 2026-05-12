<?php
// modules/dashboard/overview_stats.php
if (!isset($db)) die('Direct access not permitted');

// Stats for the selected period
$stats = [
    'patients' => 0,
    'appointments' => 0,
    'revenue' => 0,
    'completed' => 0,
    'cancelled' => 0,
    'total_sessions' => 0
];

// Previous period stats for comparison
$prev_stats = [
    'patients' => 0,
    'appointments' => 0,
    'revenue' => 0
];

if ($start_date && $end_date) {
    // Calculate previous period range for comparison
    $start_dt = new DateTime($start_date);
    $end_dt = new DateTime($end_date);
    $interval = $start_dt->diff($end_dt);
    $prev_end_dt = clone $start_dt;
    $prev_end_dt->modify('-1 day');
    $prev_start_dt = clone $prev_end_dt;
    $prev_start_dt->sub($interval);
    $prev_start = $prev_start_dt->format('Y-m-d');
    $prev_end = $prev_end_dt->format('Y-m-d');

    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM patients WHERE created_at >= ? AND created_at <= ?");
        $stmt->execute([$start_date, $end_date]);
        $stats['patients'] = (int)$stmt->fetchColumn();
        
        $stmt_prev = $db->prepare("SELECT COUNT(*) FROM patients WHERE created_at >= ? AND created_at <= ?");
        $stmt_prev->execute([$prev_start . ' 00:00:00', $prev_end . ' 23:59:59']);
        $prev_stats['patients'] = (int)$stmt_prev->fetchColumn();
    } catch (Exception $e) { error_log($e->getMessage()); }

    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date >= ? AND appointment_date <= ?");
        $stmt->execute([$start_date, $end_date]);
        $stats['appointments'] = (int)$stmt->fetchColumn();
        
        $stmt_prev = $db->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date >= ? AND appointment_date <= ?");
        $stmt_prev->execute([$prev_start . ' 00:00:00', $prev_end . ' 23:59:59']);
        $prev_stats['appointments'] = (int)$stmt_prev->fetchColumn();
    } catch (Exception $e) { error_log($e->getMessage()); }

    try {
        $stmt = $db->prepare("SELECT SUM(amount) FROM transactions WHERE type = 'income' AND transaction_date >= ? AND transaction_date <= ?");
        $stmt->execute([$start_date, $end_date]);
        $stats['revenue'] = (float)($stmt->fetchColumn() ?: 0);
        
        $stmt_prev = $db->prepare("SELECT SUM(amount) FROM transactions WHERE type = 'income' AND transaction_date >= ? AND transaction_date <= ?");
        $stmt_prev->execute([$prev_start . ' 00:00:00', $prev_end . ' 23:59:59']);
        $prev_stats['revenue'] = (float)($stmt_prev->fetchColumn() ?: 0);
    } catch (Exception $e) { error_log($e->getMessage()); }

    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE status = 'completed' AND appointment_date >= ? AND appointment_date <= ?");
        $stmt->execute([$start_date, $end_date]);
        $stats['completed'] = (int)$stmt->fetchColumn();
    } catch (Exception $e) { error_log($e->getMessage()); }

    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE status = 'cancelled' AND appointment_date >= ? AND appointment_date <= ?");
        $stmt->execute([$start_date, $end_date]);
        $stats['cancelled'] = (int)$stmt->fetchColumn();
    } catch (Exception $e) { error_log($e->getMessage()); }

    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM medical_sessions WHERE session_date >= ? AND session_date <= ?");
        $stmt->execute([$start_date, $end_date]);
        $stats['total_sessions'] = (int)$stmt->fetchColumn();
    } catch (Exception $e) { error_log($e->getMessage()); }
}

// Today's appointments for the daily operations section
$today = date('Y-m-d');
$today_appointments = [];
try {
    $apps_stmt = $db->prepare("
        SELECT a.*, p.full_name as patient_name, p.phone, 
               u.full_name as doctor_name 
        FROM appointments a 
        JOIN patients p ON a.patient_id = p.id 
        LEFT JOIN users u ON a.doctor_id = u.id 
        WHERE DATE(a.appointment_date) = ?
        ORDER BY a.appointment_date ASC
    ");
    $apps_stmt->execute([$today]);
    $today_appointments = $apps_stmt->fetchAll();
} catch (Exception $e) { error_log($e->getMessage()); }

// Today's quick metrics
$today_total = count($today_appointments);
$today_completed = 0;
$today_pending = 0;
$today_in_progress = 0;
foreach ($today_appointments as $a) {
    if ($a['status'] === 'completed') $today_completed++;
    elseif ($a['status'] === 'in_progress') $today_in_progress++;
    elseif ($a['status'] === 'scheduled' || $a['status'] === 'arrived') $today_pending++;
}

// Helper: calc trend percentage
if (!function_exists('calc_trend')) {
    function calc_trend($current, $previous) {
        if ($previous == 0) return $current > 0 ? 100 : 0;
        return round((($current - $previous) / $previous) * 100, 1);
    }
}

$trend_patients = calc_trend($stats['patients'], $prev_stats['patients']);
$trend_appointments = calc_trend($stats['appointments'], $prev_stats['appointments']);
$trend_revenue = calc_trend($stats['revenue'], $prev_stats['revenue']);

// Completion rate
$completion_rate = $stats['appointments'] > 0 ? round(($stats['completed'] / $stats['appointments']) * 100) : 0;

// Status config (used in multiple sections)
$status_config = [
    'scheduled' => ['bg' => '#eff6ff', 'color' => '#3b82f6', 'icon' => 'far fa-calendar', 'label' => __('appointment.status.scheduled')],
    'arrived' => ['bg' => '#fef3c7', 'color' => '#d97706', 'icon' => 'fas fa-walking', 'label' => __('appointment.status.arrived')],
    'in_progress' => ['bg' => '#ede9fe', 'color' => '#7c3aed', 'icon' => 'fas fa-spinner fa-spin', 'label' => __('appointment.status.treated')],
    'completed' => ['bg' => '#f0fdf4', 'color' => '#16a34a', 'icon' => 'fas fa-check', 'label' => __('appointment.status.completed')],
    'cancelled' => ['bg' => '#fef2f2', 'color' => '#dc2626', 'icon' => 'fas fa-times', 'label' => __('appointment.status.cancelled')]
];
?>

<style>
/* Dashboard Overview Styles */
.dash-section-header {
    display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem;
    padding-bottom: 0.75rem; border-bottom: 2px solid #f1f5f9;
}
.dash-section-header h3 {
    font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 0;
}
.dash-section-header .badge {
    font-size: 0.7rem; padding: 0.2rem 0.6rem; border-radius: 20px; font-weight: 700;
}

/* Today's Progress Ring */
.progress-ring-container {
    display: flex; flex-direction: column; align-items: center; gap: 0.5rem;
}
.progress-ring { position: relative; width: 120px; height: 120px; }
.progress-ring svg { transform: rotate(-90deg); }
.progress-ring .ring-bg { fill: none; stroke: #f1f5f9; stroke-width: 8; }
.progress-ring .ring-fill { fill: none; stroke-width: 8; stroke-linecap: round; transition: stroke-dashoffset 1s ease; }
.progress-ring .ring-center {
    position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
    font-size: 1.5rem; font-weight: 800; color: var(--text-main);
}

/* Appointment timeline */
.appt-timeline-item {
    display: flex; gap: 1rem; padding: 0.85rem 1rem; border-radius: 10px;
    transition: all 0.2s ease; border: 1px solid transparent; position: relative;
}
.appt-timeline-item:hover {
    background: #f8fafc; border-color: #e2e8f0; transform: translateX(4px);
}
.appt-timeline-item .time-col {
    min-width: 50px; font-weight: 700; color: var(--primary); font-size: 0.95rem;
    display: flex; flex-direction: column; align-items: center; gap: 2px;
}
.appt-timeline-item .time-col .meridiem {
    font-size: 0.65rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;
}
.appt-timeline-item .info-col { flex: 1; }
.appt-timeline-item .info-col .patient-name {
    font-weight: 700; color: var(--text-main); font-size: 0.95rem;
    text-decoration: none; transition: color 0.15s;
}
.appt-timeline-item .info-col .patient-name:hover { color: var(--primary); }
.appt-timeline-item .info-col .meta {
    font-size: 0.8rem; color: var(--text-muted); display: flex; gap: 0.75rem; margin-top: 0.25rem; flex-wrap: wrap;
}
.appt-status-badge {
    font-size: 0.7rem; padding: 0.2rem 0.6rem; border-radius: 20px; font-weight: 700;
    display: inline-flex; align-items: center; gap: 0.25rem; white-space: nowrap;
}

/* Trend indicator */
.trend-indicator {
    display: inline-flex; align-items: center; gap: 0.25rem;
    font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 6px;
}
.trend-up { background: #ecfdf5; color: #059669; }
.trend-down { background: #fef2f2; color: #dc2626; }
.trend-neutral { background: #f1f5f9; color: #64748b; }

/* Visual stat card with mini chart */
.visual-stat-card {
    background: white; border-radius: 16px; padding: 1.25rem;
    border: 1px solid var(--border-color); box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    transition: all 0.25s ease; position: relative; overflow: hidden;
}
.visual-stat-card:hover { 
    box-shadow: 0 8px 25px rgba(0,0,0,0.08); transform: translateY(-2px); 
}
.visual-stat-card .stat-accent {
    position: absolute; top: 0; left: 0; right: 0; height: 3px; border-radius: 16px 16px 0 0;
}
.visual-stat-card .stat-header {
    display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;
}
.visual-stat-card .stat-icon-wrap {
    width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
}
.visual-stat-card .stat-label {
    color: var(--text-muted); font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;
}
.visual-stat-card .stat-value {
    font-size: 1.75rem; font-weight: 800; color: var(--text-main); line-height: 1.2;
}
.visual-stat-card .stat-footer {
    display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem;
    padding-top: 0.75rem; border-top: 1px solid #f1f5f9;
}
.visual-stat-card .stat-sub {
    font-size: 0.75rem; color: var(--text-muted); font-weight: 500;
}

/* Mini progress bar */
.mini-progress { 
    height: 6px; background: #f1f5f9; border-radius: 3px; overflow: hidden; margin-top: 0.5rem; 
}
.mini-progress-fill { height: 100%; border-radius: 3px; transition: width 1s ease; }

@keyframes fadeSlideIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.dash-animated { animation: fadeSlideIn 0.4s ease forwards; }
</style>

<!-- ===================================================================== -->
<!-- SECTION 1: HOẠT ĐỘNG HÔM NAY (Daily Operations) - ĐƯA LÊN TRƯỚC -->
<!-- ===================================================================== -->
<div class="card dash-animated" style="margin-bottom: 2rem; padding: 1.5rem;">
    <div class="dash-section-header">
        <div style="width: 36px; height: 36px; background: linear-gradient(135deg, #6366f1, #8b5cf6); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
            <i class="fas fa-bolt" style="color: white; font-size: 0.9rem;"></i>
        </div>
        <div>
            <h3><?php echo __('daily_ops.title'); ?></h3>
            <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 500;">
                <?php echo date('l, d/m/Y'); ?> · <span style="color: var(--primary); font-weight: 700;"><?php echo $today_total; ?></span> <?php echo mb_strtolower(__('menu.appointments')); ?>
            </div>
        </div>
        <div style="margin-left: auto; display: flex; gap: 0.5rem;">
            <a href="/modules/appointments/add.php" class="btn btn-sm" style="background: var(--primary); color: white; border-radius: 8px; font-weight: 700; font-size: 0.8rem; padding: 0.4rem 0.8rem;">
                <i class="fas fa-plus"></i> <?php echo __('daily_ops.btn_add'); ?>
            </a>
            <a href="/modules/appointments/timeline.php" class="btn btn-sm" style="background: #f1f5f9; color: var(--text-main); border-radius: 8px; font-weight: 700; font-size: 0.8rem; padding: 0.4rem 0.8rem;">
                <i class="fas fa-calendar-alt"></i> <?php echo __('common.view_all'); ?>
            </a>
        </div>
    </div>

    <!-- Inline CSKH Package Warning -->
    <?php 
    $exp_stmt = $db->query("
        SELECT pt.full_name, p.name as package_name, pt.id as patient_id,
               pp.sessions_remaining as actual_sessions_remaining
        FROM patient_packages pp
        JOIN patients pt ON pp.patient_id = pt.id
        JOIN packages p ON pp.package_id = p.id
        WHERE pp.status = 'active' AND pp.sessions_remaining <= 2
        ORDER BY pp.sessions_remaining ASC
        LIMIT 20
    ");
    $expiring_pkgs = $exp_stmt->fetchAll();
    if (count($expiring_pkgs) > 0):
    ?>
    <div style="margin-bottom: 1.5rem; background: #fff7ed; border: 1px solid #fed7aa; border-radius: 12px; padding: 1rem; display: flex; align-items: flex-start; gap: 1rem;">
        <div style="width: 38px; height: 38px; background: #ea580c; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; flex-shrink: 0;">
            <i class="fas fa-bell"></i>
        </div>
        <div style="flex: 1;">
            <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 0.5rem;">
                <h4 style="margin: 0; color: #9a3412; font-weight: 800; font-size: 1rem;"><?php echo __('dashboard.pkg_warning_title'); ?></h4>
                <a href="/modules/sales/index.php" style="font-size: 0.8rem; color: #ea580c; text-decoration: none; font-weight: 700;"><?php echo __('dashboard.pkg_manage'); ?> <i class="fas fa-arrow-right"></i></a>
            </div>
            <p style="margin: 0 0 0.75rem 0; font-size: 0.85rem; color: #9a3412;">
                <?php echo str_replace('%s', count($expiring_pkgs), __('dashboard.pkg_warning_desc')); ?>
            </p>
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                <?php foreach(array_slice($expiring_pkgs, 0, 4) as $ep): ?>
                <a href="/modules/patients/view.php?id=<?php echo $ep['patient_id']; ?>" style="text-decoration: none; display: flex; align-items: center; gap: 0.75rem; background: white; padding: 0.4rem 0.85rem; border-radius: 8px; border: 1px solid #fed7aa; transition: 0.2s;" onmouseover="this.style.borderColor='#fb923c'; this.style.boxShadow='0 2px 8px rgba(234,88,12,0.1)';" onmouseout="this.style.borderColor='#fed7aa'; this.style.boxShadow='none';">
                    <span style="font-size: 0.85rem; font-weight: 800; color: #1e293b;"><?php echo e($ep['full_name']); ?></span>
                    <span style="font-size: 0.75rem; color: #64748b;"><i class="fas fa-box" style="font-size: 0.65rem;"></i> <?php echo e($ep['package_name']); ?></span>
                    <span style="font-size: 0.7rem; font-weight: 800; color: #ea580c; background: #ffedd5; padding: 0.2rem 0.5rem; border-radius: 20px;"><?php echo __('dashboard.pkg_remaining_prefix'); ?> <?php echo max(0, $ep['actual_sessions_remaining']); ?> <?php echo __('dashboard.pkg_remaining_suffix'); ?></span>
                </a>
                <?php endforeach; ?>
                <?php if(count($expiring_pkgs) > 4): ?>
                <a href="/modules/sales/index.php" style="text-decoration: none; display: flex; align-items: center; justify-content: center; background: #ffedd5; padding: 0.6rem 1rem; border-radius: 8px; font-weight: 700; font-size: 0.85rem; color: #ea580c; transition: 0.2s;" onmouseover="this.style.background='#ffedcc'" onmouseout="this.style.background='#ffedd5'">
                    +<?php echo count($expiring_pkgs) - 4; ?> <?php echo __('dashboard.pkg_others'); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="dash-grid-main">
        <!-- Left: Today's Appointment Timeline -->
        <div>
            <!-- Quick status pills -->
            <div style="display: flex; gap: 0.75rem; margin-bottom: 1rem; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 0.4rem; padding: 0.35rem 0.75rem; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px;">
                    <i class="fas fa-check-circle" style="color: #22c55e; font-size: 0.75rem;"></i>
                    <span style="font-size: 0.8rem; font-weight: 700; color: #16a34a;"><?php echo $today_completed; ?></span>
                    <span style="font-size: 0.75rem; color: #22c55e;"><?php echo __('appointment.status.completed'); ?></span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.4rem; padding: 0.35rem 0.75rem; background: #fef3c7; border: 1px solid #fde68a; border-radius: 8px;">
                    <i class="fas fa-clock" style="color: #d97706; font-size: 0.75rem;"></i>
                    <span style="font-size: 0.8rem; font-weight: 700; color: #d97706;"><?php echo $today_pending; ?></span>
                    <span style="font-size: 0.75rem; color: #d97706;"><?php echo __('dashboard.pending_exam'); ?></span>
                </div>
                <?php if ($today_in_progress > 0): ?>
                <div style="display: flex; align-items: center; gap: 0.4rem; padding: 0.35rem 0.75rem; background: #ede9fe; border: 1px solid #c4b5fd; border-radius: 8px;">
                    <i class="fas fa-spinner fa-spin" style="color: #7c3aed; font-size: 0.75rem;"></i>
                    <span style="font-size: 0.8rem; font-weight: 700; color: #7c3aed;"><?php echo $today_in_progress; ?></span>
                    <span style="font-size: 0.75rem; color: #7c3aed;"><?php echo __('appointment.status.treated'); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Appointments list -->
            <div style="max-height: 400px; overflow-y: auto; padding-right: 0.5rem;">
                <?php if (empty($today_appointments)): ?>
                    <div style="padding: 3rem 1rem; text-align: center;">
                        <i class="fas fa-calendar-check" style="font-size: 2.5rem; color: #e2e8f0; margin-bottom: 1rem;"></i>
                        <p style="color: var(--text-muted); font-weight: 600;"><?php echo __('dashboard.no_appointments_today'); ?></p>
                        <a href="/modules/appointments/add.php" style="color: var(--primary); font-weight: 700; font-size: 0.85rem; text-decoration: none;">
                            <i class="fas fa-plus-circle"></i> <?php echo __('daily_ops.btn_add'); ?>
                        </a>
                    </div>
                <?php else: ?>
                    <?php 
                    foreach ($today_appointments as $idx => $a): 
                        $status_key = $a['status'] ?: 'scheduled';
                        $sc = $status_config[$status_key] ?? $status_config['scheduled'];
                        $is_past = (strtotime($a['appointment_date']) < time() && $a['status'] == 'scheduled');
                    ?>
                    <div class="appt-timeline-item" style="<?php echo $is_past ? 'opacity: 0.65;' : ''; ?> animation-delay: <?php echo $idx * 0.05; ?>s;">
                        <div class="time-col">
                            <span><?php echo date('H:i', strtotime($a['appointment_date'])); ?></span>
                        </div>
                        <div style="width: 2px; background: <?php echo $a['status'] === 'completed' ? '#22c55e' : ($a['status'] === 'in_progress' ? '#7c3aed' : '#e2e8f0'); ?>; border-radius: 2px; flex-shrink: 0; position: relative;">
                            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 10px; height: 10px; border-radius: 50%; background: <?php echo $sc['color']; ?>; border: 2px solid white; box-shadow: 0 0 0 2px <?php echo $sc['color']; ?>20;"></div>
                        </div>
                        <div class="info-col">
                            <a href="/modules/patients/view.php?id=<?php echo $a['patient_id']; ?>" class="patient-name"><?php echo e($a['patient_name']); ?></a>
                            <div class="meta">
                                <?php if (!empty($a['phone'])): ?>
                                    <span><i class="fas fa-phone-alt" style="font-size: 0.7rem;"></i> <?php echo e($a['phone']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($a['doctor_name'])): ?>
                                    <span><i class="fas fa-user-md" style="font-size: 0.7rem;"></i> <?php echo e($a['doctor_name']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0;">
                            <span class="appt-status-badge" style="background: <?php echo $sc['bg']; ?>; color: <?php echo $sc['color']; ?>;">
                                <i class="<?php echo $sc['icon']; ?>" style="font-size: 0.6rem;"></i> <?php echo $sc['label']; ?>
                            </span>
                            <?php if ($is_past): ?>
                                <span style="font-size: 0.65rem; color: #ef4444; font-weight: 700;"><i class="fas fa-exclamation-triangle"></i> <?php echo __('dashboard.late'); ?></span>
                            <?php endif; ?>
                            <div style="display: flex; gap: 0.25rem;">
                                <?php if($a['status'] == 'scheduled'): ?>
                                <form action="/modules/appointments/checkin.php" method="POST" style="margin: 0;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="appointment_id" value="<?php echo $a['id']; ?>">
                                    <button type="submit" class="btn btn-sm" style="background: #10b981; color: white; border-radius: 6px; padding: 0.3rem 0.5rem; border: none; cursor: pointer; font-size: 0.75rem;" title="<?php echo __('appointment.btn.checkin'); ?>">
                                        <i class="fas fa-sign-in-alt"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <a href="/modules/appointments/edit.php?id=<?php echo $a['id']; ?>" class="btn btn-sm" style="background: #f1f5f9; color: var(--text-muted); border-radius: 6px; padding: 0.3rem 0.5rem; font-size: 0.75rem;" title="<?php echo __('common.edit'); ?>">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Progress Ring + Re-exam Reminders -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            <!-- Today's progress ring -->
            <div style="text-align: center; padding: 1rem; background: linear-gradient(135deg, #f8fafc, #eef2ff); border-radius: 14px; border: 1px solid #e2e8f0;">
                <div class="progress-ring-container">
                    <div class="progress-ring">
                        <?php 
                        $pct = $today_total > 0 ? round(($today_completed / $today_total) * 100) : 0;
                        $circ = 2 * 3.14159 * 48;
                        $offset = $circ - ($pct / 100) * $circ;
                        ?>
                        <svg width="120" height="120" viewBox="0 0 120 120">
                            <circle class="ring-bg" cx="60" cy="60" r="48"/>
                            <circle class="ring-fill" cx="60" cy="60" r="48" 
                                stroke="<?php echo $pct >= 80 ? '#22c55e' : ($pct >= 50 ? '#f59e0b' : '#6366f1'); ?>" 
                                stroke-dasharray="<?php echo $circ; ?>" 
                                stroke-dashoffset="<?php echo $offset; ?>"/>
                        </svg>
                        <div class="ring-center"><?php echo $pct; ?>%</div>
                    </div>
                    <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-main);"><?php echo __('dashboard.progress_today'); ?></div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo $today_completed; ?>/<?php echo $today_total; ?> <?php echo __('dashboard.completed_total'); ?></div>
                </div>
            </div>

            <!-- Re-exam Reminders -->
            <div style="background: #fffbeb; border: 1px solid #fef3c7; border-radius: 14px; padding: 1rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
                    <i class="fas fa-bell" style="color: #d97706;"></i>
                    <span style="font-weight: 700; color: #92400e; font-size: 0.9rem;"><?php echo __('dashboard.reexam_reminders'); ?></span>
                </div>
                <?php
                $pending_reexams = [];
                try {
                    $pend_stmt = $db->query("
                        SELECT r.*, p.full_name as patient_name, p.phone
                        FROM reexam_rules r
                        JOIN patients p ON r.patient_id = p.id
                        WHERE r.next_due_at <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                        ORDER BY r.next_due_at ASC
                        LIMIT 5
                    ");
                    if ($pend_stmt) $pending_reexams = $pend_stmt->fetchAll();
                } catch (Exception $e) {
                    error_log("Re-exam logic error: " . $e->getMessage());
                }
                ?>
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <?php foreach ($pending_reexams as $rx): ?>
                        <div style="padding: 0.6rem; background: white; border-radius: 8px; border: 1px solid #fde68a;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.4rem;">
                                <div style="flex: 1; min-width: 0; padding-right: 0.5rem;">
                                    <a href="/modules/patients/view.php?id=<?php echo $rx['patient_id']; ?>" style="font-size: 0.85rem; font-weight: 700; color: #b45309; text-decoration: none; display: block; margin-bottom: 0.15rem;"><?php echo e($rx['patient_name']); ?></a>
                                    <?php
                                    $f_type = ($rx['frequency_type'] === 'days') ? (__('common.day') ?? 'ngày') : (($rx['frequency_type'] === 'weeks') ? (__('common.week') ?? 'tuần') : (__('common.month') ?? 'tháng'));
                                    $freq_str = $rx['frequency'] . ' ' . $f_type;
                                    ?>
                                    <div style="font-size: 0.7rem; color: #92400e; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo e($rx['service_name']); ?>">
                                        <i class="fas fa-notes-medical" style="opacity: 0.7;"></i> <?php echo e($rx['service_name']); ?>
                                    </div>
                                </div>
                                <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 0.2rem;">
                                    <span style="font-size: 0.7rem; font-weight: 800; color: <?php echo strtotime($rx['next_due_at']) <= time() ? '#dc2626' : '#d97706'; ?>;">
                                        <?php echo date('d/m', strtotime($rx['next_due_at'])); ?>
                                    </span>
                                    <span style="background: #fef3c7; border-radius: 4px; padding: 0.1rem 0.3rem; font-size: 0.6rem; font-weight: 700; color: #b45309;">
                                        <?php echo $freq_str; ?>
                                    </span>
                                </div>
                            </div>
                            <div style="display: flex; gap: 0.35rem;">
                                <a href="tel:<?php echo $rx['phone']; ?>" class="btn btn-sm" style="flex: 1; justify-content: center; background: white; border: 1px solid #fde68a; color: #d97706; padding: 0.2rem; font-size: 0.7rem; border-radius: 6px;">
                                    <i class="fas fa-phone-alt"></i>
                                </a>
                                <a href="/modules/appointments/add.php?contact_id=patient:<?php echo $rx['patient_id']; ?>&type=re_exam&reexam_rule_id=<?php echo $rx['id']; ?>" class="btn btn-sm" style="flex: 2; justify-content: center; background: #d97706; color: white; padding: 0.2rem; font-size: 0.7rem; border-radius: 6px;">
                                    <i class="fas fa-calendar-plus"></i> Đặt lịch
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($pending_reexams)): ?>
                        <p style="font-size: 0.8rem; color: #92400e; text-align: center; padding: 0.5rem; margin: 0;">
                            <i class="fas fa-check-circle"></i> <?php echo __('dashboard.no_reexam_reminders'); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===================================================================== -->
<!-- SECTION 2: TỔNG QUAN THỐNG KÊ (Overview Stats) - ĐƯA XUỐNG SAU    -->
<!-- ===================================================================== -->
<div class="dash-section-header dash-animated" style="animation-delay: 0.1s;">
    <div style="width: 36px; height: 36px; background: linear-gradient(135deg, #10b981, #06b6d4); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
        <i class="fas fa-chart-line" style="color: white; font-size: 0.9rem;"></i>
    </div>
    <div>
        <h3><?php echo __('menu.dashboard'); ?></h3>
        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 500;">
            <i class="fas fa-calendar-day" style="font-size: 0.7rem;"></i> <?php echo $period_label; ?>
        </div>
    </div>
</div>

<div class="grid-stats dash-animated" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2rem; animation-delay: 0.15s;">
    <!-- Bệnh nhân mới -->
    <div class="visual-stat-card">
        <div class="stat-accent" style="background: linear-gradient(90deg, #6366f1, #818cf8);"></div>
        <div class="stat-header">
            <div>
                <div class="stat-label"><?php echo __('dashboard.new_patients_count'); ?></div>
                <div class="stat-value"><?php echo number_format($stats['patients']); ?></div>
            </div>
            <div class="stat-icon-wrap" style="background: rgba(99, 102, 241, 0.1); color: #6366f1;">
                <i class="fas fa-users"></i>
            </div>
        </div>
        <div class="stat-footer">
            <?php if ($trend_patients != 0): ?>
                <span class="trend-indicator <?php echo $trend_patients > 0 ? 'trend-up' : 'trend-down'; ?>">
                    <i class="fas fa-arrow-<?php echo $trend_patients > 0 ? 'up' : 'down'; ?>"></i>
                    <?php echo abs($trend_patients); ?>%
                </span>
            <?php else: ?>
                <span class="trend-indicator trend-neutral"><i class="fas fa-minus"></i> 0%</span>
            <?php endif; ?>
            <span class="stat-sub"><?php echo __('dashboard.vs_prev'); ?></span>
        </div>
    </div>

    <!-- Lịch hẹn -->
    <div class="visual-stat-card">
        <div class="stat-accent" style="background: linear-gradient(90deg, #10b981, #34d399);"></div>
        <div class="stat-header">
            <div>
                <div class="stat-label"><?php echo __('dashboard.appointments'); ?></div>
                <div class="stat-value"><?php echo number_format($stats['appointments']); ?></div>
            </div>
            <div class="stat-icon-wrap" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                <i class="fas fa-calendar-check"></i>
            </div>
        </div>
        <div class="mini-progress">
            <div class="mini-progress-fill" style="width: <?php echo $completion_rate; ?>%; background: linear-gradient(90deg, #10b981, #34d399);"></div>
        </div>
        <div class="stat-footer">
            <span class="stat-sub"><i class="fas fa-check-circle" style="color: #10b981;"></i> <?php echo $stats['completed']; ?> <?php echo __('dashboard.completed_total'); ?> (<?php echo $completion_rate; ?>%)</span>
            <?php if ($trend_appointments != 0): ?>
                <span class="trend-indicator <?php echo $trend_appointments > 0 ? 'trend-up' : 'trend-down'; ?>">
                    <i class="fas fa-arrow-<?php echo $trend_appointments > 0 ? 'up' : 'down'; ?>"></i>
                    <?php echo abs($trend_appointments); ?>%
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Doanh thu -->
    <div class="visual-stat-card">
        <div class="stat-accent" style="background: linear-gradient(90deg, #f59e0b, #fbbf24);"></div>
        <div class="stat-header">
            <div>
                <div class="stat-label"><?php echo __('dashboard.revenue'); ?></div>
                <div class="stat-value" style="font-size: 1.5rem;"><?php echo format_money($stats['revenue']); ?></div>
            </div>
            <div class="stat-icon-wrap" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                <i class="fas fa-coins"></i>
            </div>
        </div>
        <div class="stat-footer">
            <?php if ($trend_revenue != 0): ?>
                <span class="trend-indicator <?php echo $trend_revenue > 0 ? 'trend-up' : 'trend-down'; ?>">
                    <i class="fas fa-arrow-<?php echo $trend_revenue > 0 ? 'up' : 'down'; ?>"></i>
                    <?php echo abs($trend_revenue); ?>%
                </span>
            <?php else: ?>
                <span class="trend-indicator trend-neutral"><i class="fas fa-minus"></i> 0%</span>
            <?php endif; ?>
            <span class="stat-sub"><?php echo __('dashboard.vs_prev'); ?></span>
        </div>
    </div>

    <!-- Buổi khám -->
    <div class="visual-stat-card">
        <div class="stat-accent" style="background: linear-gradient(90deg, #8b5cf6, #a78bfa);"></div>
        <div class="stat-header">
            <div>
                <div class="stat-label"><?php echo __('dashboard.total_sessions'); ?></div>
                <div class="stat-value"><?php echo number_format($stats['total_sessions']); ?></div>
            </div>
            <div class="stat-icon-wrap" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6;">
                <i class="fas fa-stethoscope"></i>
            </div>
        </div>
        <div class="stat-footer">
            <?php if ($stats['cancelled'] > 0): ?>
                <span class="stat-sub"><i class="fas fa-times-circle" style="color: #ef4444;"></i> <?php echo $stats['cancelled']; ?> <?php echo __('appointment.status.cancelled'); ?></span>
            <?php else: ?>
                <span class="stat-sub"><i class="fas fa-check-circle" style="color: #22c55e;"></i> 0 <?php echo __('appointment.status.cancelled'); ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Recent appointments table for the selected period -->
<div class="grid-content dash-animated dash-grid-main" style="animation-delay: 0.2s;">
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-weight: 700;"><i class="fas fa-list" style="color: var(--primary); margin-right: 0.5rem;"></i><?php echo __('dashboard.recent_appointments'); ?></h3>
            <a href="/modules/appointments/timeline.php" style="font-size: 0.85rem; color: var(--primary); font-weight: 600; text-decoration: none;"><?php echo __('common.view_all'); ?> <i class="fas fa-arrow-right"></i></a>
        </div>
        <?php
        $recent_appointments = [];
        try {
            $recent_stmt = $db->prepare("
                SELECT a.*, p.full_name as patient_name, u.full_name as doctor_name 
                FROM appointments a 
                JOIN patients p ON a.patient_id = p.id 
                LEFT JOIN users u ON a.doctor_id = u.id 
                WHERE a.appointment_date >= ? AND a.appointment_date <= ?
                ORDER BY a.appointment_date DESC LIMIT 10
            ");
            $recent_stmt->execute([$start_date, $end_date]);
            $recent_appointments = $recent_stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to fetch recent appointments: " . $e->getMessage());
        }
        ?>
        <div class="responsive-table-wrapper">
            <table class="table" style="width: 100%;">
                <thead>
                    <tr style="text-align: left;">
                        <th style="padding: 1rem 0;"><?php echo __('common.patient'); ?></th>
                        <th style="padding: 1rem 0;"><?php echo __('common.doctor'); ?></th>
                        <th style="padding: 1rem 0;"><?php echo __('common.time'); ?></th>
                        <th style="padding: 1rem 0;"><?php echo __('common.status'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_appointments as $a): ?>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 1rem 0;">
                            <?php if(!empty($a['patient_id'])): ?>
                                <a href="/modules/patients/view.php?id=<?php echo $a['patient_id']; ?>" style="color: var(--text-main); font-weight: 800; text-decoration: none; transition: color 0.15s;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--text-main)'">
                                    <?php echo e($a['patient_name']); ?>
                                </a>
                                <div style="margin-top: 6px; display: flex; gap: 10px; align-items: center;">
                                    <a href="/modules/patients/view.php?id=<?php echo $a['patient_id']; ?>" style="font-size: 0.7rem; color: #6366f1; font-weight: 700; text-decoration: none; background: #eef2ff; padding: 0.2rem 0.5rem; border-radius: 4px; transition: all 0.2s;" onmouseover="this.style.background='#6366f1'; this.style.color='white';" onmouseout="this.style.background='#eef2ff'; this.style.color='#6366f1';"><i class="fas fa-user-circle"></i> <?php echo __('dashboard.patients_detail'); ?></a>
                                    <a href="/modules/medical/session_start.php?patient_id=<?php echo $a['patient_id']; ?>" style="font-size: 0.7rem; color: #10b981; font-weight: 700; text-decoration: none; background: #ecfdf5; padding: 0.2rem 0.5rem; border-radius: 4px; transition: all 0.2s;" onmouseover="this.style.background='#10b981'; this.style.color='white';" onmouseout="this.style.background='#ecfdf5'; this.style.color='#10b981';"><i class="fas fa-notes-medical"></i> <?php echo __('dashboard.patients_records'); ?></a>
                                </div>
                            <?php else: ?>
                                <strong><?php echo e($a['patient_name']); ?></strong>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 1rem 0;"><?php echo e($a['doctor_name'] ?? '---'); ?></td>
                        <td style="padding: 1rem 0; font-size: 0.9rem; color: var(--text-muted);"><?php echo date('H:i d/m', strtotime($a['appointment_date'])); ?></td>
                        <td style="padding: 1rem 0;">
                            <?php 
                            $status_key = $a['status'] ?: 'scheduled';
                            $st_c = $status_config[$status_key] ?? $status_config['scheduled'];
                            ?>
                            <span class="appt-status-badge" style="background: <?php echo $st_c['bg']; ?>; color: <?php echo $st_c['color']; ?>;">
                                <i class="<?php echo $st_c['icon']; ?>" style="font-size: 0.6rem;"></i> <?php echo $st_c['label']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($recent_appointments)): ?>
                        <tr><td colspan="4" style="text-align: center; padding: 2rem; color: var(--text-muted);"><?php echo __('common.no_data_period'); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <div class="card">
            <h3 style="margin-bottom: 1.5rem; font-weight: 700;"><?php echo __('dashboard.quick_actions'); ?></h3>
            <div class="actions-list" style="display: flex; flex-direction: column; gap: 1rem;">
                <a href="/modules/patients/add.php" class="btn btn-primary" style="justify-content: center;">
                    <i class="fas fa-user-plus"></i> <?php echo __('patient.add'); ?>
                </a>
                <a href="/modules/appointments/add.php" class="btn" style="background: white; border: 1px solid var(--primary); color: var(--primary); justify-content: center;">
                    <i class="fas fa-calendar-plus"></i> <?php echo __('appointment.add'); ?>
                </a>
            </div>
        </div>
    </div>
</div>
