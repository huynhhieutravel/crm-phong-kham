<?php
// index.php
require_once 'includes/db.php';
$page_title = 'Dashboard';
$current_page = 'dashboard';
require_once 'templates/header.php';

$db = getDB();
$stats = [
    'patients' => $db->query("SELECT COUNT(*) FROM patients")->fetchColumn(),
    'appointments' => $db->query("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = CURDATE()")->fetchColumn(),
    'revenue' => $db->query("SELECT SUM(amount) FROM transactions WHERE type = 'income' AND MONTH(transaction_date) = MONTH(CURDATE()) AND YEAR(transaction_date) = YEAR(CURDATE())")->fetchColumn() ?: 0
];
?>

<div class="grid-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <div class="card stat-card">
        <div class="stat-icon" style="color: var(--primary); background: rgba(99, 102, 241, 0.1); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
            <i class="fas fa-users fa-lg"></i>
        </div>
        <div class="stat-label" style="color: var(--text-muted); font-size: 0.875rem; font-weight: 500;">Tổng số Bệnh nhân</div>
        <div class="stat-value" style="font-size: 1.5rem; font-weight: 700; margin-top: 0.25rem;"><?php echo number_format($stats['patients']); ?></div>
    </div>
    <div class="card stat-card">
        <div class="stat-icon" style="color: #10b981; background: rgba(16, 185, 129, 0.1); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
            <i class="fas fa-calendar-check fa-lg"></i>
        </div>
        <div class="stat-label" style="color: var(--text-muted); font-size: 0.875rem; font-weight: 500;">Lịch hẹn hôm nay</div>
        <div class="stat-value" style="font-size: 1.5rem; font-weight: 700; margin-top: 0.25rem;"><?php echo number_format($stats['appointments']); ?></div>
    </div>
    <div class="card stat-card">
        <div class="stat-icon" style="color: #f59e0b; background: rgba(245, 158, 11, 0.1); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
            <i class="fas fa-coins fa-lg"></i>
        </div>
        <div class="stat-label" style="color: var(--text-muted); font-size: 0.875rem; font-weight: 500;">Doanh thu tháng này</div>
        <div class="stat-value" style="font-size: 1.5rem; font-weight: 700; margin-top: 0.25rem;"><?php echo format_money($stats['revenue']); ?></div>
    </div>
</div>

<div class="grid-content" style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-weight: 700;">Lịch hẹn gần đây</h3>
            <a href="/modules/appointments/index.php" style="font-size: 0.85rem; color: var(--primary); font-weight: 600; text-decoration: none;">Xem tất cả <i class="fas fa-arrow-right"></i></a>
        </div>
        <?php
        $recent_stmt = $db->query("
            SELECT a.*, p.full_name as patient_name, u.full_name as doctor_name 
            FROM appointments a 
            JOIN patients p ON a.patient_id = p.id 
            LEFT JOIN users u ON a.doctor_id = u.id 
            ORDER BY a.appointment_date DESC LIMIT 5
        ");
        $recent_appointments = $recent_stmt->fetchAll();
        ?>
        <table class="table" style="width: 100%;">
            <thead>
                <tr style="text-align: left;">
                    <th style="padding: 1rem 0;">Bệnh nhân</th>
                    <th style="padding: 1rem 0;">Bác sĩ</th>
                    <th style="padding: 1rem 0;">Thời gian</th>
                    <th style="padding: 1rem 0;">Trạng thái</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_appointments as $a): ?>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 1rem 0;"><strong><?php echo e($a['patient_name']); ?></strong></td>
                    <td style="padding: 1rem 0;"><?php echo e($a['doctor_name'] ?: '---'); ?></td>
                    <td style="padding: 1rem 0; font-size: 0.9rem; color: var(--text-muted);"><?php echo date('H:i d/m', strtotime($a['appointment_date'])); ?></td>
                    <td style="padding: 1rem 0;">
                        <span style="font-size: 0.75rem; padding: 0.25rem 0.6rem; border-radius: 20px; font-weight: 700; background: rgba(99,102,241,0.1); color: var(--primary);">
                            <?php echo strtoupper($a['status']); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($recent_appointments)): ?>
                    <tr><td colspan="4" style="text-align: center; padding: 2rem; color: var(--text-muted);">Không có lịch hẹn gần đây</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card">
        <h3 style="margin-bottom: 1.5rem; font-weight: 700; color: #d97706;"><i class="fas fa-bell"></i> Nhắc Tái Khám</h3>
        <?php
        $pending_reexams = $db->query("
            SELECT r.*, p.full_name as patient_name, p.phone
            FROM reexam_rules r
            JOIN patients p ON r.patient_id = p.id
            WHERE r.status = 'active' AND r.next_due_at <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            ORDER BY r.next_due_at ASC
            LIMIT 5
        ")->fetchAll();
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
                            <i class="fas fa-phone-alt"></i> Gọi
                        </a>
                        <a href="/modules/appointments/add.php?contact_id=patient:<?php echo $rx['patient_id']; ?>&type=re_exam&reexam_rule_id=<?php echo $rx['id']; ?>" class="btn btn-sm" style="flex: 2; justify-content: center; background: #d97706; color: white; padding: 0.25rem;">
                            <i class="fas fa-calendar-plus"></i> Đặt lịch
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($pending_reexams)): ?>
                <p style="font-size: 0.85rem; color: var(--text-muted); text-align: center; padding: 1rem;">Không có lịch tải khám cần nhắc.</p>
            <?php endif; ?>
        </div>

        <h3 style="margin: 1.5rem 0 1.5rem 0; font-weight: 700;"><?php echo t('quick_actions'); ?></h3>
        <div class="actions-list" style="display: flex; flex-direction: column; gap: 1rem;">
            <a href="/modules/patients/add.php" class="btn btn-primary" style="justify-content: center;">
                <i class="fas fa-user-plus"></i> <?php echo t('add_patient'); ?>
            </a>
            <a href="/modules/appointments/add.php" class="btn" style="background: white; border: 1px solid var(--primary); color: var(--primary); justify-content: center;">
                <i class="fas fa-calendar-plus"></i> <?php echo t('book_appointment'); ?>
            </a>
            <div style="margin-top: 1rem; padding: 1rem; background: rgba(99,102,241,0.05); border-radius: 12px; border: 1px dashed var(--primary);">
                <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.4;">
                    <i class="fas fa-magic"></i> <strong>Tip:</strong> Anh có thể chạm trực tiếp vào dòng bệnh nhân để xem bệnh án nhanh.
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>
