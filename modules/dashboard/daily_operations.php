<?php
// modules/dashboard/daily_operations.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('view_reports');

$page_title = __('menu.dashboard') . ' / ' . __('daily_ops.title');
$current_page = 'dashboard';
$base_url = '../../';
require_once '../../templates/header.php';

$db = getDB();
$today = date('Y-m-d');
$display_date = date('d/m/Y');

// --- 1. Top Metrics ---
$stmt_app = $db->prepare("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = ?");
$stmt_app->execute([$today]);
$total_appointments = $stmt_app->fetchColumn();

$stmt_checkin = $db->prepare("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = ? AND status IN ('arrived', 'in_progress', 'completed')");
$stmt_checkin->execute([$today]);
$total_checked_in = $stmt_checkin->fetchColumn();

$stmt_rev = $db->prepare("SELECT SUM(amount) FROM transactions WHERE type = 'income' AND DATE(transaction_date) = ?");
$stmt_rev->execute([$today]);
$total_revenue = (float)($stmt_rev->fetchColumn() ?: 0);

// --- 2. Appointments List ---
$apps_stmt = $db->prepare("
    SELECT a.*, p.full_name as patient_name, p.phone, 
           (SELECT COUNT(*) FROM medical_sessions ms WHERE ms.patient_id = p.id) as history_count, 
           u.full_name as doctor_name 
    FROM appointments a 
    JOIN patients p ON a.patient_id = p.id 
    LEFT JOIN users u ON a.doctor_id = u.id 
    WHERE DATE(a.appointment_date) = ?
    ORDER BY a.appointment_date ASC
");
$apps_stmt->execute([$today]);
$today_appointments = $apps_stmt->fetchAll();

// --- 3. Staff Utilization ---
$staff_stmt = $db->prepare("
    SELECT u.full_name, r.name as role_name, COUNT(a.id) as appt_count
    FROM appointments a
    JOIN users u ON a.doctor_id = u.id
    LEFT JOIN roles r ON u.role_id = r.id
    WHERE DATE(a.appointment_date) = ?
    GROUP BY u.id
    ORDER BY appt_count DESC
");
$staff_stmt->execute([$today]);
$staff_utilization = $staff_stmt->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem;">
    <div>
        <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.25rem;"><?php echo __('daily_ops.title'); ?></h2>
        <p style="color: var(--text-muted); font-size: 0.9rem;"><?php echo __('daily_ops.subtitle'); ?> (<?php echo $display_date; ?>).</p>
    </div>
    <a href="/modules/appointments/add.php" class="btn btn-primary">
        <i class="fas fa-plus"></i> <?php echo __('daily_ops.btn_add'); ?>
    </a>
</div>

<!-- Key Metrics -->
<div class="grid-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <div class="card stat-card">
        <div class="stat-icon" style="color: var(--primary); background: rgba(99, 102, 241, 0.1); width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
            <i class="fas fa-calendar-day"></i>
        </div>
        <div class="stat-label" style="color: var(--text-muted); font-size: 0.875rem; font-weight: 500;"><?php echo __('daily_ops.metric_appointments'); ?></div>
        <div class="stat-value" style="font-size: 1.75rem; font-weight: 800; margin-top: 0.25rem;"><?php echo number_format($total_appointments); ?></div>
    </div>
    
    <div class="card stat-card">
        <div class="stat-icon" style="color: #10b981; background: rgba(16, 185, 129, 0.1); width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
            <i class="fas fa-user-check"></i>
        </div>
        <div class="stat-label" style="color: var(--text-muted); font-size: 0.875rem; font-weight: 500;"><?php echo __('daily_ops.metric_checked_in'); ?></div>
        <div class="stat-value" style="font-size: 1.75rem; font-weight: 800; margin-top: 0.25rem;"><?php echo number_format($total_checked_in); ?> <span style="font-size: 0.9rem; color: #94a3b8; font-weight: 500;">/ <?php echo $total_appointments; ?></span></div>
    </div>
    
    <div class="card stat-card">
        <div class="stat-icon" style="color: #f59e0b; background: rgba(245, 158, 11, 0.1); width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
            <i class="fas fa-money-bill-wave"></i>
        </div>
        <div class="stat-label" style="color: var(--text-muted); font-size: 0.875rem; font-weight: 500;"><?php echo __('daily_ops.metric_revenue'); ?></div>
        <div class="stat-value" style="font-size: 1.75rem; font-weight: 800; margin-top: 0.25rem;"><?php echo format_money($total_revenue); ?></div>
    </div>
</div>

<div class="grid-content" style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
    <!-- Today's Schedule -->
    <div class="card">
        <h3 style="font-weight: 700; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);">
            <i class="fas fa-clipboard-list" style="color: var(--primary);"></i> <?php echo __('daily_ops.schedule_title'); ?>
        </h3>
        
        <?php if(empty($today_appointments)): ?>
            <div style="padding: 3rem; text-align: center; color: var(--text-muted);">
                <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                <p><?php echo __('daily_ops.empty_appointments'); ?></p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="table" style="width: 100%;">
                    <thead>
                        <tr style="text-align: left; border-bottom: 1px solid var(--border-color); color: var(--text-muted); font-size: 0.85rem;">
                            <th style="padding: 1rem 0.5rem; white-space: nowrap;"><?php echo __('daily_ops.col_time'); ?></th>
                            <th style="padding: 1rem 0.5rem; white-space: nowrap;"><?php echo __('daily_ops.col_patient'); ?></th>
                            <th style="padding: 1rem 0.5rem; white-space: nowrap;"><?php echo __('daily_ops.col_type'); ?></th>
                            <th style="padding: 1rem 0.5rem; white-space: nowrap;"><?php echo __('daily_ops.col_doctor'); ?></th>
                            <th style="padding: 1rem 0.5rem; white-space: nowrap;"><?php echo __('daily_ops.col_status'); ?></th>
                            <th style="padding: 1rem 0.5rem; text-align: right; white-space: nowrap;"><?php echo __('daily_ops.col_actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($today_appointments as $a): 
                            $status_colors = [
                                'scheduled' => ['bg' => 'rgba(59, 130, 246, 0.1)', 'color' => '#3b82f6'],
                                'arrived' => ['bg' => 'rgba(245, 158, 11, 0.1)', 'color' => '#f59e0b'],
                                'in_progress' => ['bg' => 'rgba(139, 92, 246, 0.1)', 'color' => '#8b5cf6'],
                                'completed' => ['bg' => 'rgba(16, 185, 129, 0.1)', 'color' => '#10b981'],
                                'cancelled' => ['bg' => 'rgba(239, 68, 68, 0.1)', 'color' => '#ef4444']
                            ];
                            $color = $status_colors[$a['status']] ?? $status_colors['scheduled'];
                            
                            $is_past = (strtotime($a['appointment_date']) < time() && $a['status'] == 'scheduled');
                        ?>
                        <tr style="border-bottom: 1px solid #f1f5f9; <?php echo $is_past ? 'opacity: 0.7;' : ''; ?>">
                            <td style="padding: 1rem 0.5rem; vertical-align: middle; font-weight: 600; color: var(--text-main); white-space: nowrap;">
                                <?php echo date('H:i', strtotime($a['appointment_date'])); ?>
                                <?php if(isset($a['duration']) && $a['duration'] > 0): ?>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 500;">(<?php echo $a['duration']; ?>p)</div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 1rem 0.5rem; vertical-align: middle;">
                                <div style="font-weight: 600; color: var(--text-main); font-size: 0.95rem; margin-bottom: 0.25rem;"><?php echo e($a['patient_name']); ?></div>
                                <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                    <span style="font-size: 0.8rem; color: var(--text-muted);"><?php echo e($a['phone']); ?></span>
                                    <?php if($a['history_count'] == 0): ?>
                                        <span style="font-size: 0.65rem; padding: 0.15rem 0.4rem; background: #fee2e2; color: #dc2626; border-radius: 4px; font-weight: 600; white-space: nowrap;"><?php echo __('daily_ops.new_patient_badge'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="padding: 1rem 0.5rem; vertical-align: middle; white-space: nowrap;">
                                <div style="font-size: 0.85rem; color: var(--text-main); font-weight: 500;">
                                    <?php echo ($a['type'] == 're_exam') ? __('daily_ops.type_re_exam') : (($a['type'] == 'treatment') ? __('daily_ops.type_treatment') : __('daily_ops.type_new')); ?>
                                </div>
                            </td>
                            <td style="padding: 1rem 0.5rem; vertical-align: middle; font-weight: 500; color: #475569; font-size: 0.9rem; white-space: nowrap;">
                                <?php if($a['doctor_name']): ?>
                                    <i class="fas fa-user-md" style="color: #94a3b8; margin-right: 0.25rem;"></i> <?php echo e($a['doctor_name']); ?>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-style: italic;"><?php echo __('daily_ops.unassigned_doctor'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 1rem 0.5rem; vertical-align: middle;">
                                <div style="font-size: 0.75rem; padding: 0.35rem 0.75rem; border-radius: 20px; font-weight: 600; background: <?php echo $color['bg']; ?>; color: <?php echo $color['color']; ?>; display: inline-flex; align-items: center; gap: 0.35rem; white-space: nowrap;">
                                    <?php 
                                        if($a['status'] == 'scheduled') echo '<i class="far fa-calendar"></i><span>' . __('daily_ops.status_scheduled') . '</span>';
                                        elseif($a['status'] == 'arrived') echo '<i class="fas fa-walking"></i><span>' . __('daily_ops.status_arrived') . '</span>';
                                        elseif($a['status'] == 'in_progress') echo '<i class="fas fa-spinner fa-spin"></i><span>' . __('daily_ops.status_in_progress') . '</span>';
                                        elseif($a['status'] == 'completed') echo '<i class="fas fa-check"></i><span>' . __('daily_ops.status_completed') . '</span>';
                                        elseif($a['status'] == 'cancelled') echo '<i class="fas fa-times"></i><span>' . __('daily_ops.status_cancelled') . '</span>';
                                    ?>
                                </div>
                                <?php if($is_past): ?>
                                    <div style="font-size: 0.7rem; color: #ef4444; font-weight: 600; margin-top: 6px; white-space: nowrap;"><?php echo __('daily_ops.late_badge'); ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 1rem 0.5rem; vertical-align: middle;">
                                <div style="display: flex; gap: 0.5rem; justify-content: flex-end; align-items: center;">
                                    <?php if($a['status'] == 'scheduled'): ?>
                                        <form action="/modules/appointments/checkin.php" method="POST" style="margin: 0;">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="appointment_id" value="<?php echo $a['id']; ?>">
                                            <button type="submit" class="btn btn-sm" style="background: #10b981; color: white; border-radius: 6px; padding: 0.35rem 0.6rem; border: none; cursor: pointer; display: flex; align-items: center;" title="<?php echo e(__('daily_ops.action_checkin')); ?>">
                                                <i class="fas fa-sign-in-alt"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <a href="/modules/appointments/edit.php?id=<?php echo $a['id']; ?>" class="btn btn-sm btn-icon" style="background: #f8fafc; color: var(--text-main); border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.35rem 0.6rem; display: flex; align-items: center; justify-content: center;" title="<?php echo e(__('daily_ops.action_edit')); ?>">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="/modules/patients/view.php?id=<?php echo $a['patient_id']; ?>" class="btn btn-sm btn-icon" style="background: #f8fafc; color: var(--text-main); border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.35rem 0.6rem; display: flex; align-items: center; justify-content: center;" title="<?php echo e(__('daily_ops.action_profile')); ?>">
                                        <i class="fas fa-user"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right Sidebar Content -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        
        <!-- Staff Utilization -->
        <div class="card">
            <h3 style="font-weight: 700; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);">
                <i class="fas fa-user-md" style="color: #8b5cf6;"></i> <?php echo __('daily_ops.staff_utilization_title'); ?>
            </h3>
            
            <?php if(empty($staff_utilization)): ?>
                <p style="color: var(--text-muted); font-size: 0.9rem; text-align: center; padding: 1rem 0;"><?php echo __('daily_ops.empty_staff'); ?></p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 0;">
                    <?php foreach($staff_utilization as $staff): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.85rem 0.5rem; border-bottom: 1px solid #f1f5f9;">
                        <div style="display: flex; flex-direction: column; gap: 2px;">
                            <span style="font-weight: 600; color: var(--text-main); font-size: 0.95rem;"><?php echo e($staff['full_name']); ?></span>
                            <span style="font-size: 0.75rem; color: var(--text-muted);"><?php echo e($staff['role_name'] ?? __('daily_ops.role_medic')); ?></span>
                        </div>
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; color: var(--primary); width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; flex-shrink: 0;">
                            <?php echo $staff['appt_count']; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <!-- Logic to count unassigned appointments -->
                <?php
                $unassigned_stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = ? AND (doctor_id IS NULL OR doctor_id = 0)");
                $unassigned_stmt->execute([$today]);
                $unassigned = $unassigned_stmt->fetchColumn();
                if ($unassigned > 0):
                ?>
                <div style="margin-top: 1rem; padding: 0.75rem; background: #fff1f2; border: 1px solid #ffe4e6; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                    <div style="color: #be123c; font-weight: 600; font-size: 0.85rem;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo __('daily_ops.unassigned_alert'); ?>
                    </div>
                    <div style="font-weight: 800; color: #be123c;"><?php echo $unassigned; ?></div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
    </div>
</div>

<?php require_once '../../templates/footer.php'; ?>
