<?php
// modules/hr/index.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('view_hr');
$page_title = __('hr.index.title');
$current_page = 'hr';
require_once '../../templates/header.php';

$db = getDB();
$today = date('Y-m-d');

// Get recent leave requests
$leave_stmt = $db->query("
    SELECT lr.*, u.full_name as user_name, r.name as role_name_key, r.display_name as role_name
    FROM leave_requests lr
    JOIN users u ON lr.user_id = u.id
    JOIN roles r ON u.role_id = r.id
    ORDER BY lr.created_at DESC
    LIMIT 20
");
$leaves = $leave_stmt->fetchAll();

// KPI Calculations
$uid = (int)$_SESSION['user_id'];

$stmt = $db->prepare("SELECT salary_per_patient FROM users WHERE id = ?");
$stmt->execute([$uid]);
$salary_per_patient = (float)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM medical_sessions WHERE doctor_id = ? AND status = 'completed' AND MONTH(session_date) = MONTH(CURRENT_DATE()) AND YEAR(session_date) = YEAR(CURRENT_DATE())");
$stmt->execute([$uid]);
$monthly_treated = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND status IN ('scheduled', 'confirmed') AND MONTH(appointment_date) = MONTH(CURRENT_DATE()) AND YEAR(appointment_date) = YEAR(CURRENT_DATE())");
$stmt->execute([$uid]);
$upcoming_patients = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM timekeeping WHERE user_id = ? AND MONTH(work_date) = MONTH(CURRENT_DATE()) AND YEAR(work_date) = YEAR(CURRENT_DATE())");
$stmt->execute([$uid]);
$worked_days = (int)$stmt->fetchColumn();

$estimated_salary = $monthly_treated * $salary_per_patient;
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <h2 style="margin: 0; font-weight: 800; color: var(--text-main); text-transform: uppercase;"><?php echo __('hr.index.title'); ?></h2>
    <div style="display: flex; gap: 1rem;">
        <a href="users.php" class="btn btn-primary shadow-sm" style="background: var(--primary); color: white; border-radius: 12px; padding: 0.75rem 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; text-decoration: none;">
            <i class="fas fa-users-cog"></i> <?php echo __('hr.index.manage_staff'); ?>
        </a>
    </div>
</div>

<!-- KPI Dashboard -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <div class="card" style="border-left: 4px solid #6366f1; padding: 1.25rem;">
        <div style="color: #64748b; font-size: 0.8rem; font-weight: 700; text-transform: uppercase;"><?php echo __('hr.index.worked_days'); ?></div>
        <div style="font-size: 1.8rem; font-weight: 800; color: #1e293b; margin-top: 0.5rem;"><?php echo $worked_days; ?> <span style="font-size: 0.9rem; font-weight: 600; color: #94a3b8;"><?php echo __('hr.index.days'); ?></span></div>
    </div>
    <div class="card" style="border-left: 4px solid #10b981; padding: 1.25rem;">
        <div style="color: #64748b; font-size: 0.8rem; font-weight: 700; text-transform: uppercase;"><?php echo __('hr.index.treated_patients'); ?></div>
        <div style="font-size: 1.8rem; font-weight: 800; color: #1e293b; margin-top: 0.5rem;"><?php echo $monthly_treated; ?> <span style="font-size: 0.9rem; font-weight: 600; color: #94a3b8;"><?php echo __('common.patient_unit'); ?></span></div>
    </div>
    <div class="card" style="border-left: 4px solid #f59e0b; padding: 1.25rem;">
        <div style="color: #64748b; font-size: 0.8rem; font-weight: 700; text-transform: uppercase;"><?php echo __('hr.index.upcoming_patients'); ?></div>
        <div style="font-size: 1.8rem; font-weight: 800; color: #1e293b; margin-top: 0.5rem;"><?php echo $upcoming_patients; ?> <span style="font-size: 0.9rem; font-weight: 600; color: #94a3b8;"><?php echo __('hr.index.appointments_unit'); ?></span></div>
    </div>
    <div class="card" style="border-left: 4px solid #ec4899; padding: 1.25rem; position: relative; overflow: hidden;">
        <i class="fas fa-coins" style="position: absolute; right: -15px; bottom: -15px; font-size: 5rem; opacity: 0.05; color: #ec4899;"></i>
        <div style="color: #64748b; font-size: 0.8rem; font-weight: 700; text-transform: uppercase;"><?php echo __('hr.index.estimated_salary'); ?></div>
        <div style="font-size: 1.8rem; font-weight: 800; color: #1e293b; margin-top: 0.5rem;"><?php echo number_format($estimated_salary); ?> <span style="font-size: 0.9rem; font-weight: 600; color: #94a3b8;"><?php echo __('common.currency'); ?></span></div>
        <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem;">(<?php echo __('hr.index.quota'); ?>: <?php echo number_format($salary_per_patient); ?><?php echo __('common.currency'); ?>/<?php echo __('common.patient_unit'); ?>)</div>
    </div>
</div>

<div class="card" style="margin-top: 1.5rem;">
    <h3 style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;"><i class="fas fa-calendar-times text-warning"></i> <?php echo __('hr.index.leave_management'); ?></h3>
    <table class="table" style="width: 100%;">
        <thead>
            <tr style="text-align: left; border-bottom: 2px solid #f1f5f9; color: #64748b; font-size: 0.8rem; text-transform: uppercase;">
                <th style="padding: 1rem;"><?php echo __('common.staff_member'); ?></th>
                <th style="padding: 1rem;"><?php echo __('common.from_date'); ?></th>
                <th style="padding: 1rem;"><?php echo __('common.to_date'); ?></th>
                <th style="padding: 1rem;"><?php echo __('common.reason'); ?></th>
                <th style="padding: 1rem; width: 150px;"><?php echo __('common.status'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($leaves as $l): ?>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 1rem;">
                        <div style="font-weight: 700; color: #1e293b;"><?php echo e($l['user_name']); ?></div>
                        <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.2rem;"><?php echo __('role.' . strtolower($l['role_name_key'])); ?></div>
                    </td>
                    <td style="padding: 1rem; font-weight: 500;"><?php echo date('d/m/Y', strtotime($l['start_date'])); ?></td>
                    <td style="padding: 1rem; font-weight: 500;"><?php echo date('d/m/Y', strtotime($l['end_date'])); ?></td>
                    <td style="padding: 1rem; color: #64748b; font-size: 0.85rem; max-width: 250px;"><?php echo e($l['reason'] ?: '—'); ?></td>
                    <td style="padding: 1rem; width: 1%; white-space: nowrap;">
                        <?php if ($l['status'] === 'pending'): ?>
                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                <span style="background: #fdfce7; color: #ca8a04; padding: 0.25rem 0.5rem; border-radius: 6px; font-size: 0.75rem; font-weight: 700; border: 1px solid #fef08a; white-space: nowrap;"><?php echo __('leave.status.pending'); ?></span>
                                <?php if (isset($_SESSION['role']) && (strtolower($_SESSION['role']) === 'admin' || can('view_hr'))): ?>
                                    <button onclick="handleLeaveAction(<?php echo $l['id']; ?>, 'approve')" class="btn" style="background: #10b981; color: white; padding: 0.25rem 0.5rem; font-size: 0.75rem; border-radius: 6px; border: none; cursor: pointer; font-weight: 600;" title="<?php echo __('leave.action.approve'); ?>"><i class="fas fa-check"></i></button>
                                    <button onclick="handleLeaveAction(<?php echo $l['id']; ?>, 'reject')" class="btn" style="background: #ef4444; color: white; padding: 0.25rem 0.5rem; font-size: 0.75rem; border-radius: 6px; border: none; cursor: pointer; font-weight: 600;" title="<?php echo __('leave.action.reject'); ?>"><i class="fas fa-times"></i></button>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($l['status'] === 'approved'): ?>
                            <span style="background: #dcfce7; color: #16a34a; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 700; border: 1px solid #bbf7d0; white-space: nowrap;"><?php echo __('leave.status.approved'); ?></span>
                        <?php else: ?>
                            <span style="background: #fee2e2; color: #ef4444; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 700; border: 1px solid #fecaca; white-space: nowrap;"><?php echo __('leave.status.rejected'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($leaves)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 3rem; color: #94a3b8;">
                        <i class="fas fa-clipboard-list fa-3x" style="opacity: 0.2; margin-bottom: 1rem; display: block;"></i>
                        <?php echo __('leave.no_data'); ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal moved to global -->
<script>
async function handleLeaveAction(id, action) {
    var actLabel = action === 'approve' ? '<?php echo __('leave.action.approve_label'); ?>' : '<?php echo __('leave.action.reject_label'); ?>';
    var msg = '<?php echo __('leave.confirm_action'); ?>'.replace('{action}', actLabel);
    confirmAndRun(function() { doLeaveAction(id, action); }, msg);
}
async function doLeaveAction(id, action) {
    
    try {
        const fd = new FormData();
        fd.append('action', action);
        fd.append('id', id);
        fd.append('_csrf_token', document.querySelector('meta[name="csrf-token"]').content);
        
        const r = await fetch('../../api/leave_requests.php', { method: 'POST', body: fd });
        const res = await r.json();
        
        if (res.success) {
            window.location.reload();
        } else {
            alert(res.message);
        }
    } catch(err) {
        alert("<?php echo __('common.system_error'); ?>");
    }
}
</script>

<?php require_once '../../templates/footer.php'; ?>
