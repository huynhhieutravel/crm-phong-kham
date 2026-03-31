<?php
// modules/appointments/view.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('view_appointments');

$db = getDB();
$id = isset($_GET['id']) ? $_GET['id'] : 0;

$stmt = $db->prepare("
    SELECT a.*, p.full_name as patient_name, l.full_name as lead_name, u.full_name as doctor_name
    FROM appointments a
    LEFT JOIN patients p ON a.patient_id = p.id
    LEFT JOIN leads l ON a.lead_id = l.id
    LEFT JOIN users u ON a.doctor_id = u.id
    WHERE a.id = ?
");
$stmt->execute([$id]);
$a = $stmt->fetch();

if (!$a) {
    set_flash(__('appointment.msg.not_found'), 'danger');
    redirect('index.php');
}

$page_title = __('appointment.view.title');
$current_page = 'appointments';
require_once '../../templates/header.php';
?>

<div class="card" style="max-width: 900px; margin: 0 auto; padding: 2.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem;">
        <div style="flex: 1;">
            <h2 style="margin: 0; font-weight: 800; margin-bottom: 0.5rem;"><?php echo e($a['patient_name'] ?: $a['lead_name']); ?></h2>
            <div style="display: flex; gap: 1rem; align-items: center;">
                <span style="color: var(--text-muted); font-weight: 600;">
                    <i class="fas <?php echo $a['patient_id'] ? 'fa-user-injured' : 'fa-user-tag'; ?>" style="margin-right: 4px;"></i>
                    <?php echo $a['patient_id'] ? __('appointment.contact_type.patient') : __('appointment.contact_type.lead'); ?>
                </span>
                <?php if($a['patient_id']): ?>
                    <a href="../patients/view.php?id=<?php echo $a['patient_id']; ?>" style="font-size: 0.85rem; background: #eef2ff; color: #4f46e5; font-weight: 800; padding: 0.4rem 0.85rem; border-radius: 8px; text-decoration: none; border: 1px solid #c7d2fe; transition: all 0.2s;" onmouseover="this.style.background='#4f46e5'; this.style.color='white';" onmouseout="this.style.background='#eef2ff'; this.style.color='#4f46e5';">
                        <i class="fas fa-user-circle" style="margin-right: 4px;"></i> Chi tiết bệnh nhân
                    </a>
                    <a href="../medical/session_start.php?patient_id=<?php echo $a['patient_id']; ?>" style="font-size: 0.85rem; background: #ecfdf5; color: #10b981; font-weight: 800; padding: 0.4rem 0.85rem; border-radius: 8px; text-decoration: none; border: 1px solid #a7f3d0; transition: all 0.2s;" onmouseover="this.style.background='#10b981'; this.style.color='white';" onmouseout="this.style.background='#ecfdf5'; this.style.color='#10b981';">
                        <i class="fas fa-notes-medical" style="margin-right: 4px;"></i> Bệnh án
                    </a>
                <?php elseif($a['lead_id']): ?>
                    <a href="../leads/view.php?id=<?php echo $a['lead_id']; ?>" style="font-size: 0.85rem; background: #f8fafc; color: var(--primary); font-weight: 800; padding: 0.4rem 0.85rem; border-radius: 8px; text-decoration: none; border: 1px solid #e2e8f0; transition: all 0.2s;" onmouseover="this.style.background='var(--primary)'; this.style.color='white';" onmouseout="this.style.background='#f8fafc'; this.style.color='var(--primary)';">
                        <i class="fas fa-external-link-alt" style="margin-right: 4px;"></i> Xem hồ sơ Lead
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <span class="badge" style="background: #e0f2fe; color: #0369a1; text-transform: uppercase; font-weight: 800;">
            <?php echo __('appointment.type.' . $a['type']); ?>
        </span>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
        <div>
            <label style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;"><?php echo __('appointment.table.time'); ?></label>
            <div style="font-weight: 600; margin-top: 0.25rem;">
                <?php 
                    $time_display = date('H:i', strtotime($a['appointment_date']));
                    if (!empty($a['appointment_end_time'])) {
                        $time_display .= ' – ' . substr($a['appointment_end_time'], 0, 5);
                    }
                    echo $time_display . ', ' . date('d/m/Y', strtotime($a['appointment_date']));
                ?>
            </div>
        </div>
        <div>
            <label style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;"><?php echo __('appointment.doctor'); ?></label>
            <div style="font-weight: 600; margin-top: 0.25rem;">
                <?php echo e($a['doctor_name'] ?: __('appointment.unassigned_doctor')); ?>
            </div>
        </div>
        <div>
            <label style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;"><?php echo __('appointment.status'); ?></label>
            <div style="font-weight: 800; margin-top: 0.25rem; color: var(--primary);">
                <?php echo mb_strtoupper(__('appointment.status.' . $a['status']), 'UTF-8'); ?>
            </div>
        </div>
    </div>

    <div style="margin-bottom: 2rem;">
        <label style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;"><?php echo __('common.notes'); ?></label>
        <div style="background: #f8fafc; padding: 1rem; border-radius: 12px; margin-top: 0.5rem; font-size: 0.9rem; line-height: 1.5;">
            <?php echo nl2br(e($a['notes'])); ?>
        </div>
    </div>

    <div style="display: flex; gap: 1rem;">
        <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-primary" style="flex: 1; justify-content: center;"><?php echo __('common.edit'); ?></a>
        <a href="index.php" class="btn" style="flex: 1; justify-content: center; background: #f1f5f9; color: var(--text-main);"><?php echo __('common.back'); ?></a>
    </div>
</div>

<?php require_once '../../templates/footer.php'; ?>
