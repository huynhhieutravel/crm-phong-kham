<?php
// modules/appointments/view.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

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

<div class="card" style="max-width: 600px; margin: 0 auto; padding: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem;">
        <div>
            <h2 style="margin: 0; font-weight: 800;"><?php echo e($a['patient_name'] ?: $a['lead_name']); ?></h2>
            <p style="color: var(--text-muted);"><?php echo $a['patient_id'] ? __('appointment.contact_type.patient') : __('appointment.contact_type.lead'); ?></p>
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
                <?php echo strtoupper(__('appointment.status.' . $a['status'])); ?>
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
