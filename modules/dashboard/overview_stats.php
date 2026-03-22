<?php
// modules/dashboard/overview_stats.php
if (!isset($db)) die('Direct access not permitted');

// Stats for the selected period
$stats = [
    'patients' => 0,
    'appointments' => 0,
    'revenue' => 0
];

if ($start_date && $end_date) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM patients WHERE created_at BETWEEN ? AND ?");
        $stmt->execute([$start_date, $end_date]);
        $stats['patients'] = (int)$stmt->fetchColumn();
    } catch (Exception $e) {}

    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date BETWEEN ? AND ?");
        $stmt->execute([$start_date, $end_date]);
        $stats['appointments'] = (int)$stmt->fetchColumn();
    } catch (Exception $e) {}

    try {
        $stmt = $db->prepare("SELECT SUM(amount) FROM transactions WHERE type = 'income' AND transaction_date BETWEEN ? AND ?");
        $stmt->execute([$start_date, $end_date]);
        $stats['revenue'] = (float)($stmt->fetchColumn() ?: 0);
    } catch (Exception $e) {}
}
?>

<div class="grid-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <div class="card stat-card">
        <div class="stat-icon" style="color: var(--primary); background: rgba(99, 102, 241, 0.1); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
            <i class="fas fa-users fa-lg"></i>
        </div>
        <div class="stat-label" style="color: var(--text-muted); font-size: 0.875rem; font-weight: 500;"><?php echo __('dashboard.new_patients_count'); ?></div>
        <div class="stat-value" style="font-size: 1.5rem; font-weight: 700; margin-top: 0.25rem;"><?php echo number_format($stats['patients']); ?></div>
    </div>
    <div class="card stat-card">
        <div class="stat-icon" style="color: #10b981; background: rgba(16, 185, 129, 0.1); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
            <i class="fas fa-calendar-check fa-lg"></i>
        </div>
        <div class="stat-label" style="color: var(--text-muted); font-size: 0.875rem; font-weight: 500;"><?php echo __('dashboard.appointments'); ?></div>
        <div class="stat-value" style="font-size: 1.5rem; font-weight: 700; margin-top: 0.25rem;"><?php echo number_format($stats['appointments']); ?></div>
    </div>
    <div class="card stat-card">
        <div class="stat-icon" style="color: #f59e0b; background: rgba(245, 158, 11, 0.1); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
            <i class="fas fa-coins fa-lg"></i>
        </div>
        <div class="stat-label" style="color: var(--text-muted); font-size: 0.875rem; font-weight: 500;"><?php echo __('dashboard.revenue'); ?></div>
        <div class="stat-value" style="font-size: 1.5rem; font-weight: 700; margin-top: 0.25rem;"><?php echo format_money($stats['revenue']); ?></div>
    </div>
</div>

<div class="grid-content" style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-weight: 700;"><?php echo __('dashboard.recent_appointments'); ?></h3>
            <a href="/modules/appointments/index.php" style="font-size: 0.85rem; color: var(--primary); font-weight: 600; text-decoration: none;"><?php echo __('common.view_all'); ?> <i class="fas fa-arrow-right"></i></a>
        </div>
        <?php
        $recent_appointments = [];
        try {
            $recent_stmt = $db->query("
                SELECT a.*, p.full_name as patient_name, u.full_name as doctor_name 
                FROM appointments a 
                JOIN patients p ON a.patient_id = p.id 
                LEFT JOIN users u ON a.doctor_id = u.id 
                WHERE a.appointment_date BETWEEN '$start_date' AND '$end_date'
                ORDER BY a.appointment_date DESC LIMIT 10
            ");
            if ($recent_stmt) $recent_appointments = $recent_stmt->fetchAll();
        } catch (Exception $e) {
            // Simplified fallback if JOIN fails (maybe missing doctor_id or something)
            try {
                $recent_appointments = $db->query("SELECT *, 'System' as patient_name, '' as doctor_name FROM appointments ORDER BY appointment_date DESC LIMIT 5")->fetchAll();
            } catch (Exception $e2) {}
        }
        ?>
        <div style="overflow-x: auto;">
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
                        <td style="padding: 1rem 0;"><strong><?php echo e($a['patient_name']); ?></strong></td>
                        <td style="padding: 1rem 0;"><?php echo e($a['doctor_name'] ?? '---'); ?></td>
                        <td style="padding: 1rem 0; font-size: 0.9rem; color: var(--text-muted);"><?php echo date('H:i d/m', strtotime($a['appointment_date'])); ?></td>
                        <td style="padding: 1rem 0;">
                            <span style="font-size: 0.75rem; padding: 0.25rem 0.6rem; border-radius: 20px; font-weight: 700; background: rgba(99,102,241,0.1); color: var(--primary);">
                                <?php echo strtoupper($a['status']); ?>
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
            <h3 style="margin-bottom: 1.5rem; font-weight: 700; color: #d97706;"><i class="fas fa-bell"></i> <?php echo __('dashboard.reexam_reminders'); ?></h3>
            <?php
            $pending_reexams = [];
            try {
                // Re-exam reminders don't strictly follow the dashboard period, usually looking ahead
                $pend_stmt = $db->query("
                    SELECT r.*, p.full_name as patient_name, p.phone
                    FROM reexam_rules r
                    JOIN patients p ON r.patient_id = p.id
                    WHERE r.status = 'active' AND r.next_due_at <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                    ORDER BY r.next_due_at ASC
                    LIMIT 5
                ");
                if ($pend_stmt) $pending_reexams = $pend_stmt->fetchAll();
            } catch (Exception $e) {
                // If table doesn't exist, just keep empty
            }
            ?>
            <div class="reexam-list" style="display: flex; flex-direction: column; gap: 0.75rem;">
                <?php foreach ($pending_reexams as $rx): ?>
                    <div style="padding: 0.75rem; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 10px;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                            <div>
                                <strong style="display: block; font-size: 0.9rem;"><?php echo e($rx['patient_name']); ?></strong>
                                <small style="color: var(--text-muted);"><?php echo e($rx['service_name']); ?></small>
                            </div>
                            <span style="font-size: 0.75rem; font-weight: 700; color: <?php echo strtotime($rx['next_due_at']) <= time() ? '#dc2626' : '#d97706'; ?>;">
                                <?php echo date('d/m', strtotime($rx['next_due_at'])); ?>
                            </span>
                        </div>
                        <div style="display: flex; gap: 0.5rem;">
                            <a href="tel:<?php echo $rx['phone']; ?>" class="btn btn-sm" style="flex: 1; justify-content: center; background: white; border: 1px solid #fef3c7; color: #d97706; padding: 0.25rem;">
                                <i class="fas fa-phone-alt"></i> <?php echo __('common.call'); ?>
                            </a>
                            <a href="/modules/appointments/add.php?contact_id=patient:<?php echo $rx['patient_id']; ?>&type=re_exam&reexam_rule_id=<?php echo $rx['id']; ?>" class="btn btn-sm" style="flex: 2; justify-content: center; background: #d97706; color: white; padding: 0.25rem;">
                                <i class="fas fa-calendar-plus"></i> <?php echo __('button.book'); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($pending_reexams)): ?>
                    <p style="font-size: 0.85rem; color: var(--text-muted); text-align: center; padding: 1rem;"><?php echo __('dashboard.no_reexam_reminders'); ?></p>
                <?php endif; ?>
            </div>
        </div>

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
