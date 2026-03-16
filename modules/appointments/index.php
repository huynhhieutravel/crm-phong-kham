<?php
// modules/appointments/index.php
require_once '../../includes/db.php';
$page_title = 'Quản lý Lịch hẹn';
$current_page = 'appointments';
require_once '../../templates/header.php';

$db = getDB();
$stmt = $db->query("
    SELECT 
        a.*, 
        COALESCE(p.full_name, l.full_name) as contact_name,
        COALESCE(p.phone, l.phone) as contact_phone,
        u.full_name as doctor_name,
        CASE WHEN a.patient_id IS NOT NULL THEN 'Patient' ELSE 'Lead' END as contact_type
    FROM appointments a
    LEFT JOIN patients p ON a.patient_id = p.id
    LEFT JOIN leads l ON a.lead_id = l.id
    LEFT JOIN users u ON a.doctor_id = u.id
    ORDER BY a.appointment_date ASC
");
$appointments = $stmt->fetchAll();
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <h3 style="margin: 0;">Danh sách Lịch hẹn</h3>
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Đặt lịch mới
        </a>
    </div>

    <table class="table" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="text-align: left; border-bottom: 2px solid var(--border-color);">
                <th style="padding: 1rem;">Thời gian</th>
                <th style="padding: 1rem;">Bệnh nhân</th>
                <th style="padding: 1rem;">Bác sĩ/Doctor</th>
                <th style="padding: 1rem;">Trạng thái</th>
                <th style="padding: 1rem;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($appointments as $a): ?>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 1rem;">
                        <strong><?php echo date('H:i d/m/Y', strtotime($a['appointment_date'])); ?></strong>
                    </td>
                    <td style="padding: 1rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                             <span style="font-weight: 600; color: var(--text-main);"><?php echo e($a['contact_name']); ?></span>
                             <span style="font-size: 0.65rem; font-weight: 800; padding: 0.1rem 0.4rem; border-radius: 4px; text-transform: uppercase; <?php echo $a['contact_type'] === 'Patient' ? 'background: #e0f2fe; color: #0369a1;' : 'background: #fef3c7; color: #92400e;'; ?>">
                                <?php echo $a['contact_type']; ?>
                             </span>
                        </div>
                        <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo e($a['contact_phone']); ?></div>
                    </td>
                    <td style="padding: 1rem;"><?php echo e($a['doctor_name'] ?: 'Chưa phân'); ?></td>
                    <td style="padding: 1rem;">
                        <?php 
                        $status_colors = [
                            'scheduled' => '#6366f1', 
                            'confirmed' => '#8b5cf6', 
                            'arrived' => '#10b981', 
                            'completed' => '#059669', 
                            'no_show' => '#f59e0b', 
                            'cancelled' => '#ef4444'
                        ];
                        $color = $status_colors[$a['status']] ?? '#64748b';
                        ?>
                        <span style="background: <?php echo $color; ?>1a; color: <?php echo $color; ?>; padding: 0.25rem 0.5rem; border-radius: 6px; font-size: 0.8rem; font-weight: 700; text-transform: uppercase;">
                            <?php echo $a['status']; ?>
                        </span>
                    </td>
                    <td style="padding: 1rem;">
                        <div style="display: flex; gap: 0.5rem;">
                            <?php if ($a['contact_type'] === 'Lead' && $a['status'] !== 'cancelled'): ?>
                                <a href="checkin.php?id=<?php echo $a['id']; ?>" class="btn btn-sm" style="background: #10b981; color: white; font-weight: 700;">
                                    <i class="fas fa-sign-in-alt"></i> CHECK-IN
                                </a>
                            <?php endif; ?>
                            <button class="btn btn-sm" style="background: #f1f5f9;"><i class="fas fa-ellipsis-h"></i></button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($appointments)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-muted);">Không có lịch hẹn nào.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../templates/footer.php'; ?>
