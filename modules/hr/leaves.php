<?php
// modules/hr/leaves.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

$page_title = __('leave.page_title');
$current_page = 'leaves';
require_once '../../templates/header.php';

$db = getDB();
$uid = (int)$_SESSION['user_id'];
$role = strtolower($_SESSION['role'] ?? '');
$is_admin = $role === 'admin' || can('view_hr');

$filter_status = $_GET['status'] ?? '';
$filter_month = $_GET['month'] ?? date('Y-m');

// Build query
$where = [];
$params = [];

if (!$is_admin) {
    // Normal users only see their own requests
    $where[] = "lr.user_id = ?";
    $params[] = $uid;
}
if ($filter_status !== '') {
    $where[] = "lr.status = ?";
    $params[] = $filter_status;
}
if ($filter_month !== '') {
    $where[] = "DATE_FORMAT(lr.start_date, '%Y-%m') = ?";
    $params[] = $filter_month;
}

$where_sql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("
    SELECT lr.*, u.full_name as user_name, r.display_name as role_name
    FROM leave_requests lr
    JOIN users u ON lr.user_id = u.id
    JOIN roles r ON u.role_id = r.id
    $where_sql
    ORDER BY lr.created_at DESC
");
$stmt->execute($params);
$leaves = $stmt->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <h2 style="margin: 0; font-weight: 800; color: var(--text-main); text-transform: uppercase;"><?php echo __('leave.my_leaves'); ?></h2>
    <div style="display: flex; gap: 1rem;">
        <button onclick="document.getElementById('leaveModal').style.display='flex'" class="btn btn-primary shadow-sm" style="background: var(--primary); color: white; border-radius: 12px; padding: 0.75rem 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; border: none; cursor: pointer;">
            <i class="fas fa-plus"></i> <?php echo __('leave.create'); ?>
        </button>
    </div>
</div>

<div class="card" style="margin-bottom: 1.5rem;">
    <form method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
        <div class="form-group" style="margin: 0; min-width: 200px;">
            <label class="form-label" style="font-size: 0.8rem; text-transform: uppercase;"><?php echo __('common.status'); ?></label>
            <select name="status" class="form-input" style="padding: 0.6rem 1rem;">
                <option value=""><?php echo __('leave.all_status'); ?></option>
                <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>><?php echo __('leave.status.pending'); ?></option>
                <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>><?php echo __('leave.status.approved'); ?></option>
                <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>><?php echo __('leave.status.rejected'); ?></option>
            </select>
        </div>
        <div class="form-group" style="margin: 0; min-width: 200px;">
            <label class="form-label" style="font-size: 0.8rem; text-transform: uppercase;"><?php echo __('leave.filter_month'); ?></label>
            <input type="month" name="month" class="form-input" style="padding: 0.6rem 1rem;" value="<?php echo htmlspecialchars($filter_month); ?>">
        </div>
        <button type="submit" class="btn" style="background: #f1f5f9; color: #475569; padding: 0.6rem 1.5rem;"><i class="fas fa-filter"></i> <?php echo __('common.filter_btn'); ?></button>
        <?php if ($filter_status !== '' || $filter_month !== date('Y-m')): ?>
            <a href="leaves.php" class="btn" style="background: none; color: #94a3b8; text-decoration: underline; padding: 0.6rem 1rem;"><?php echo __('common.clear_filter'); ?></a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <table class="table" style="width: 100%;">
        <thead>
            <tr style="text-align: left; border-bottom: 2px solid #f1f5f9; color: #64748b; font-size: 0.8rem; text-transform: uppercase;">
                <th style="padding: 1rem;"><?php echo __('common.staff_member'); ?></th>
                <th style="padding: 1rem;"><?php echo __('common.from_date'); ?></th>
                <th style="padding: 1rem;"><?php echo __('common.to_date'); ?></th>
                <th style="padding: 1rem;"><?php echo __('common.reason'); ?></th>
                <th style="padding: 1rem;"><?php echo __('leave.submit_date'); ?></th>
                <th style="padding: 1rem; width: 150px;"><?php echo __('common.status'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($leaves as $l): ?>
                <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                    <td style="padding: 1rem;">
                        <div style="font-weight: 700; color: #1e293b;"><?php echo e($l['user_name']); ?></div>
                        <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.2rem;"><?php echo __('role.' . strtolower($l['role_name_key'] ?? $l['role_name'])); ?></div>
                    </td>
                    <td style="padding: 1rem; font-weight: 500;">
                        <?php echo date('d/m/Y', strtotime($l['start_date'])); ?>
                        <?php if ($l['leave_type'] === 'hourly' && $l['start_time']): ?>
                            <span style="font-size: 0.7rem; color: #f59e0b; font-weight: 700;"><?php echo substr($l['start_time'], 0, 5); ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 1rem; font-weight: 500;">
                        <?php if ($l['leave_type'] === 'hourly'): ?>
                            <span style="background: #fefce8; color: #92400e; padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.75rem; font-weight: 700; border: 1px solid #fde68a;">
                                <i class="fas fa-clock"></i> <?php echo substr($l['start_time'] ?? '08:00', 0, 5); ?> → <?php echo substr($l['end_time'] ?? '17:00', 0, 5); ?>
                            </span>
                        <?php else: ?>
                            <?php echo date('d/m/Y', strtotime($l['end_date'])); ?>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 1rem; color: #64748b; font-size: 0.85rem; max-width: 200px;"><?php echo e($l['reason'] ?: '—'); ?></td>
                    <td style="padding: 1rem; color: #94a3b8; font-size: 0.85rem;"><?php echo date('d/m/Y H:i', strtotime($l['created_at'])); ?></td>
                    <td style="padding: 1rem; width: 1%; white-space: nowrap;">
                        <div style="display: flex; gap: 0.5rem; justify-content: flex-end; align-items: center;">
                            <?php if ($l['status'] === 'pending'): ?>
                                <span style="background: #fdfce7; color: #ca8a04; padding: 0.35rem 0.75rem; border-radius: 6px; font-size: 0.75rem; font-weight: 700; border: 1px solid #fef08a; white-space: nowrap;"><?php echo __('leave.status.pending'); ?></span>
                                <?php if ($is_admin): ?>
                                    <button onclick="handleLeaveAction(<?php echo $l['id']; ?>, 'approve')" class="btn" style="background: #10b981; color: white; padding: 0.35rem 0.6rem; font-size: 0.75rem; border-radius: 6px; border: none; cursor: pointer; font-weight: 600;" title="<?php echo __('leave.action.approve'); ?>"><i class="fas fa-check"></i></button>
                                    <button onclick="handleLeaveAction(<?php echo $l['id']; ?>, 'reject')" class="btn" style="background: #ef4444; color: white; padding: 0.35rem 0.6rem; font-size: 0.75rem; border-radius: 6px; border: none; cursor: pointer; font-weight: 600;" title="<?php echo __('leave.action.reject'); ?>"><i class="fas fa-times"></i></button>
                                <?php endif; ?>
                            <?php elseif ($l['status'] === 'approved'): ?>
                                <span style="background: #dcfce7; color: #16a34a; padding: 0.35rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 700; border: 1px solid #bbf7d0; white-space: nowrap;"><?php echo __('leave.status.approved'); ?></span>
                            <?php else: ?>
                                <span style="background: #fee2e2; color: #ef4444; padding: 0.35rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 700; border: 1px solid #fecaca; white-space: nowrap;"><?php echo __('leave.status.rejected'); ?></span>
                            <?php endif; ?>
                            
                            <?php if ($l['status'] === 'pending' || $is_admin): ?>
                                <button onclick='openEditModal(<?php echo json_encode($l); ?>)' class="btn" style="background: #f1f5f9; color: #64748b; padding: 0.35rem 0.6rem; font-size: 0.75rem; border-radius: 6px; border: none; cursor: pointer;" title="<?php echo __('common.edit'); ?>"><i class="fas fa-pen"></i></button>
                                <button onclick="handleLeaveAction(<?php echo $l['id']; ?>, 'delete')" class="btn" style="background: #f1f5f9; color: #ef4444; padding: 0.35rem 0.6rem; font-size: 0.75rem; border-radius: 6px; border: none; cursor: pointer;" title="<?php echo __('common.delete'); ?>"><i class="fas fa-trash"></i></button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($leaves)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 4rem; color: #94a3b8;">
                        <i class="fas fa-calendar-times fa-3x" style="opacity: 0.2; margin-bottom: 1rem; display: block;"></i>
                        <?php echo __('leave.no_data'); ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="editLeaveModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1050; align-items: center; justify-content: center;">
    <div class="card" style="width: 100%; max-width: 500px; padding: 2rem;">
        <h3 style="margin-top: 0; margin-bottom: 1.5rem; color: #1e293b;"><?php echo __('leave.edit_title'); ?></h3>
        <form id="editLeaveForm" onsubmit="submitEditLeave(event)">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" id="edit_leave_id" name="id">
            
            <div class="form-group" style="margin-bottom: 1rem;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #475569;">Loại nghỉ</label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                    <label style="border: 2px solid #6366f1; border-radius: 10px; padding: 0.6rem; cursor: pointer; text-align: center; background: #eef2ff;" id="edit_lbl_full_day">
                        <input type="radio" name="leave_type" value="full_day" checked style="display: none;" onchange="toggleEditLeaveType(this.value)">
                        <i class="fas fa-calendar-day" style="color: #6366f1;"></i> <span style="font-size: 0.8rem; font-weight: 700;">Cả ngày</span>
                    </label>
                    <label style="border: 2px solid #e2e8f0; border-radius: 10px; padding: 0.6rem; cursor: pointer; text-align: center; background: white;" id="edit_lbl_hourly">
                        <input type="radio" name="leave_type" value="hourly" style="display: none;" onchange="toggleEditLeaveType(this.value)">
                        <i class="fas fa-clock" style="color: #94a3b8;"></i> <span style="font-size: 0.8rem; font-weight: 700;">Theo giờ</span>
                    </label>
                </div>
            </div>
            
            <div class="form-group" style="margin-bottom: 1rem;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #475569;"><?php echo __('common.from_date'); ?></label>
                <input type="date" id="edit_start_date" name="start_date" class="form-input" required style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px;">
            </div>
            <div class="form-group" id="edit_grp_end_date" style="margin-bottom: 1rem;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #475569;"><?php echo __('common.to_date'); ?></label>
                <input type="date" id="edit_end_date" name="end_date" class="form-input" required style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px;">
            </div>
            <div id="edit_hourlyTimeRow" style="display: none; margin-bottom: 1rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600;"><i class="fas fa-sign-in-alt" style="color: #f59e0b;"></i> Từ giờ</label>
                        <input type="time" id="edit_start_time" name="start_time" class="form-input" style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px;">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600;"><i class="fas fa-sign-out-alt" style="color: #ef4444;"></i> Đến giờ</label>
                        <input type="time" id="edit_end_time" name="end_time" class="form-input" style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px;">
                    </div>
                </div>
            </div>
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #475569;"><?php echo __('common.reason'); ?></label>
                <textarea id="edit_reason" name="reason" class="form-input" required rows="3" style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit;"></textarea>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" onclick="document.getElementById('editLeaveModal').style.display='none'" class="btn" style="background: #f1f5f9; color: #475569; padding: 0.75rem 1.5rem; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;"><?php echo __('common.cancel'); ?></button>
                <button type="submit" class="btn" style="background: var(--primary); color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;"><?php echo __('common.save_changes'); ?></button>
            </div>
        </form>
    </div>
</div>

<script>
function handleLeaveAction(id, action) {
    let confirmMsg = 'Bạn có chắc chắn muốn thao tác đơn này?';
    if (action === 'approve') confirmMsg = 'Bạn chắc chắn muốn duyệt đơn này?';
    if (action === 'reject') confirmMsg = 'Bạn chắc chắn muốn từ chối đơn này?';
    if (action === 'delete') confirmMsg = 'Bạn chắc chắn muốn XÓA đơn này? Hành động không thể hoàn tác.';
    
    if (typeof confirmAndRun === 'function') {
        confirmAndRun(async function() {
            executeLeaveAction(id, action);
        }, confirmMsg);
    } else {
        if (!confirm(confirmMsg)) return;
        executeLeaveAction(id, action);
    }
}

async function executeLeaveAction(id, action) {
    try {
        const fd = new FormData();
        fd.append('action', action);
        fd.append('id', id);
        fd.append('_csrf_token', document.querySelector('meta[name="csrf-token"]').content);
        const r = await fetch('../../api/leave_requests.php', { method: 'POST', body: fd });
        const res = await r.json();
        if (res.success) { window.location.reload(); } 
        else { 
            typeof confirmAndRun === 'function' ? confirmAndRun(null, res.message) : alert(res.message); 
        }
    } catch(err) { 
        typeof confirmAndRun === 'function' ? confirmAndRun(null, "Lỗi hệ thống.") : alert("Lỗi hệ thống."); 
    }
}

function openEditModal(leaveData) {
    document.getElementById('edit_leave_id').value = leaveData.id;
    document.getElementById('edit_start_date').value = leaveData.start_date.substring(0, 10);
    document.getElementById('edit_end_date').value = leaveData.end_date.substring(0, 10);
    document.getElementById('edit_reason').value = leaveData.reason || '';
    
    // Set leave type
    var lt = leaveData.leave_type || 'full_day';
    var radios = document.querySelectorAll('#editLeaveModal input[name="leave_type"]');
    radios.forEach(function(r) { r.checked = (r.value === lt); });
    toggleEditLeaveType(lt);
    
    if (lt === 'hourly') {
        document.getElementById('edit_start_time').value = (leaveData.start_time || '08:00').substring(0, 5);
        document.getElementById('edit_end_time').value = (leaveData.end_time || '17:00').substring(0, 5);
    }
    
    document.getElementById('editLeaveModal').style.display = 'flex';
}

function toggleEditLeaveType(type) {
    var timeRow = document.getElementById('edit_hourlyTimeRow');
    var endDateGrp = document.getElementById('edit_grp_end_date');
    var lblFull = document.getElementById('edit_lbl_full_day');
    var lblHour = document.getElementById('edit_lbl_hourly');
    if (type === 'hourly') {
        timeRow.style.display = 'block';
        endDateGrp.style.display = 'none';
        lblFull.style.borderColor = '#e2e8f0'; lblFull.style.background = 'white';
        lblHour.style.borderColor = '#f59e0b'; lblHour.style.background = '#fefce8';
    } else {
        timeRow.style.display = 'none';
        endDateGrp.style.display = 'block';
        lblFull.style.borderColor = '#6366f1'; lblFull.style.background = '#eef2ff';
        lblHour.style.borderColor = '#e2e8f0'; lblHour.style.background = 'white';
    }
}

async function submitEditLeave(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const oldText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang lưu...';
    btn.disabled = true;

    try {
        const fd = new FormData(e.target);
        fd.append('_csrf_token', document.querySelector('meta[name="csrf-token"]').content);
        const r = await fetch('../../api/leave_requests.php', { method: 'POST', body: fd });
        const res = await r.json();
        if (res.success) {
            window.location.reload();
        } else {
            typeof confirmAndRun === 'function' ? confirmAndRun(null, res.message || "Lỗi lưu thay đổi.") : alert(res.message || "Lỗi lưu thay đổi.");
            btn.innerHTML = oldText;
            btn.disabled = false;
        }
    } catch (err) {
        typeof confirmAndRun === 'function' ? confirmAndRun(null, "Lỗi hệ thống.") : alert("Lỗi hệ thống.");
        btn.innerHTML = oldText;
        btn.disabled = false;
    }
}
</script>

<?php require_once '../../templates/footer.php'; ?>
