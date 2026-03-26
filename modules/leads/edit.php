<?php
// modules/leads/edit.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_leads');

$id = isset($_GET['id']) ? $_GET['id'] : 0;
$db = getDB();

// Fetch lead data
$stmt = $db->prepare("SELECT * FROM leads WHERE id = ?");
$stmt->execute([$id]);
$lead = $stmt->fetch();

if (!$lead) {
    set_flash('Lead không tồn tại!', 'error');
    redirect('index.php');
}

// Fetch consultants for dropdown
$consultants_stmt = $db->query("
    SELECT u.id, u.full_name 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    WHERE r.name IN ('cskh', 'admin') AND u.status = 'active'
    ORDER BY u.full_name
");
$consultants = $consultants_stmt->fetchAll();

$medical_groups = get_medical_groups();
$lead_sources = get_lead_sources();

// Handle form submission before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $optionals = [
        'full_name' => $_POST['full_name'],
        'phone' => $_POST['phone'],
        'gender' => $_POST['gender'],
        'birthday' => !empty($_POST['birthday']) ? $_POST['birthday'] : null,
        'email' => $_POST['email'],
        'address' => $_POST['address'],
        'source' => $_POST['source'],
        'medical_group' => $_POST['medical_group'],
        'consultant_id' => $_POST['consultant_id'] ?: null,
        'status' => $_POST['status'],
        'notes' => $_POST['notes']
    ];

    // Detect available columns
    $available_cols = $db->query("SHOW COLUMNS FROM leads")->fetchAll(PDO::FETCH_COLUMN);
    $data = [];
    foreach ($optionals as $col => $val) {
        if (in_array($col, $available_cols)) {
            $data[$col] = $val;
        }
    }

    if (!empty($data)) {
        $set_parts = [];
        foreach (array_keys($data) as $col) {
            $set_parts[] = "$col = ?";
        }
        $sql = "UPDATE leads SET " . implode(", ", $set_parts) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge(array_values($data), [$id]));
    }
    
    set_flash(__('lead.msg_edit_success'));
    redirect('index.php');
}

$page_title = __('leads.form.edit_title');
$current_page = 'leads';
require_once '../../templates/header.php';
?>

<div class="card" style="max-width: 900px; margin: 0 auto; padding: 2.5rem;">
    <div class="page-header" style="margin-bottom: 2rem;">
    <div>
        <h1 class="page-title"><?php echo __('leads.form.edit_title'); ?></h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;">
            <?php echo __('leads.form.edit_subtitle'); ?>
        </p>
    </div>
    <a href="index.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> <?php echo __('common.back'); ?>
    </a>
</div>

    <form method="POST">
        <!-- ... existing form fields ... -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <!-- [Lines 82-146 are preserved here] -->
            <div class="form-group">
                <label class="form-label"><?php echo __('leads.form.label_full_name'); ?> *</label>
                <input type="text" name="full_name" class="form-input" required value="<?php echo e($lead['full_name']); ?>">
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo __('leads.form.label_phone'); ?> *</label>
                <input type="text" name="phone" class="form-input" required value="<?php echo e($lead['phone']); ?>">
            </div>
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label"><?php echo __('leads.form.label_gender'); ?></label>
                    <select name="gender" class="form-input">
                        <option value="male" <?php echo $lead['gender'] === 'male' ? 'selected' : ''; ?>><?php echo __('patient.gender.male'); ?></option>
                        <option value="female" <?php echo $lead['gender'] === 'female' ? 'selected' : ''; ?>><?php echo __('patient.gender.female'); ?></option>
                        <option value="other" <?php echo $lead['gender'] === 'other' ? 'selected' : ''; ?>><?php echo __('patient.gender.other'); ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('leads.form.label_birthday'); ?></label>
                    <input type="date" name="birthday" class="form-input" value="<?php echo $lead['birthday']; ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo __('leads.form.label_email'); ?></label>
                <input type="email" name="email" class="form-input" value="<?php echo e($lead['email']); ?>">
            </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div class="form-group">
                        <label class="form-label"><?php echo __('leads.form.label_source'); ?></label>
                        <select name="source" class="form-input">
                            <?php foreach ($lead_sources as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo $lead['source'] === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo __('leads.form.label_consultant'); ?></label>
                        <select name="consultant_id" class="form-input">
                            <option value=""><?php echo __('leads.form.label_consultant_placeholder', '-- Chọn tư vấn viên --'); ?></option>
                            <?php foreach ($consultants as $con): ?>
                                <option value="<?php echo $con['id']; ?>" <?php echo (int)$lead['consultant_id'] === (int)$con['id'] ? 'selected' : ''; ?>><?php echo e($con['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            <div class="form-group">
                <label class="form-label"><?php echo __('leads.form.label_medical_group'); ?></label>
                <select name="medical_group" class="form-input">
                    <option value=""><?php echo __('leads.form.label_medical_group_placeholder', '-- Chọn nhóm bệnh --'); ?></option>
                    <?php foreach ($medical_groups as $val => $key): ?>
                        <option value="<?php echo $val; ?>" <?php echo $lead['medical_group'] === $val ? 'selected' : ''; ?>><?php echo __($key); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo __('leads.form.label_status'); ?></label>
                <select name="status" class="form-input">
                    <option value="new" <?php echo $lead['status'] === 'new' ? 'selected' : ''; ?>><?php echo __('leads.status.new'); ?></option>
                    <option value="contacted" <?php echo $lead['status'] === 'contacted' ? 'selected' : ''; ?>><?php echo __('leads.status.contacted'); ?></option>
                    <option value="scheduled" <?php echo $lead['status'] === 'scheduled' ? 'selected' : ''; ?>><?php echo __('leads.status.scheduled'); ?></option>
                    <option value="converted" <?php echo $lead['status'] === 'converted' ? 'selected' : ''; ?>><?php echo __('leads.status.converted'); ?></option>
                    <option value="cancelled" <?php echo $lead['status'] === 'cancelled' ? 'selected' : ''; ?>><?php echo __('leads.status.cancelled'); ?></option>
                </select>
            </div>
        </div>
        
        <div class="form-group" style="margin-top: 1.5rem;">
            <label class="form-label"><?php echo __('leads.form.label_address'); ?></label>
            <textarea name="address" class="form-input" rows="2"><?php echo e($lead['address']); ?></textarea>
        </div>
        
        <div class="form-group" style="margin-top: 1.5rem;">
            <label class="form-label">Ghi chú chi tiết</label>
            <textarea name="notes" class="form-input" rows="3"><?php echo e($lead['notes']); ?></textarea>
        </div>
        
        <div style="margin-top: 2.5rem; display: flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary" style="padding: 0.8rem 2rem; font-weight: 700;">
                <i class="fas fa-save"></i> Cập nhật hồ sơ
            </button>
            <a href="index.php" class="btn" style="background: #f1f5f9; color: var(--text-main); padding: 0.8rem 2rem;">Hủy bỏ</a>
        </div>
    </form>

    <!-- Consultation History Section -->
    <hr style="margin: 3rem 0; border: 0; border-top: 1px solid var(--border-color);">
    
    <div style="margin-bottom: 2rem;">
        <h2 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 0.5rem;">Lịch sử tư vấn & Chăm sóc</h2>
        <p style="color: var(--text-muted); font-size: 0.85rem;">Theo dõi các lần trao đổi và ghi chú tiến trình với khách hàng.</p>
    </div>

    <!-- Add Quick Note Form -->
    <div class="card" style="background: #f8fafc; border: 1px dashed #cbd5e1; padding: 1.5rem; margin-bottom: 2rem; border-radius: 12px;">
        <h3 style="font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--primary); margin-bottom: 1rem;">
            <i class="fas fa-plus-circle"></i> Thêm ghi chú mới
        </h3>
        <form id="addLogForm" onsubmit="submitLog(event)">
            <input type="hidden" name="lead_id" value="<?php echo $id; ?>">
            <div style="display: flex; gap: 1rem;">
                <textarea name="note" class="form-input" rows="2" placeholder="Nhập nội dung tư vấn..." style="background: white; border-radius: 10px;"></textarea>
                <button type="submit" class="btn btn-primary" style="align-self: flex-end; padding: 0.6rem 1.5rem;">
                    <i class="fas fa-paper-plane"></i> Gửi
                </button>
            </div>
        </form>
    </div>

    <!-- Logs Timeline -->
    <?php
    $logs_stmt = $db->prepare("
        SELECT l.*, u.full_name as user_name 
        FROM lead_logs l 
        JOIN users u ON l.user_id = u.id 
        WHERE l.lead_id = ? 
        ORDER BY l.created_at DESC
    ");
    $logs_stmt->execute([$id]);
    $logs = $logs_stmt->fetchAll();
    ?>

    <div id="logsContainer" style="position: relative; padding-left: 2rem;">
        <div style="position: absolute; left: 0.5rem; top: 0; bottom: 0; width: 2px; background: #e2e8f0;"></div>
        
        <?php foreach ($logs as $log): ?>
            <div class="log-item" style="position: relative; margin-bottom: 2rem;">
                <div style="position: absolute; left: -1.9rem; top: 0; width: 12px; height: 12px; border-radius: 50%; background: white; border: 3px solid var(--primary); z-index: 1;"></div>
                <div style="background: white; border: 1px solid #e2e8f0; padding: 1rem; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.8rem;">
                        <span style="font-weight: 800; color: var(--text-main);"><i class="fas fa-user-edit" style="color: #64748b;"></i> <?php echo e($log['user_name']); ?></span>
                        <span style="color: var(--text-muted); font-weight: 600;"><?php echo date('H:i d/m/Y', strtotime($log['created_at'])); ?></span>
                    </div>
                    <div style="color: var(--text-main); font-size: 0.95rem; line-height: 1.5; white-space: pre-wrap;"><?php echo e($log['note']); ?></div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($logs)): ?>
            <p id="noLogsMsg" style="color: var(--text-muted); font-style: italic; font-size: 0.9rem;">Chưa có lịch sử tư vấn nào.</p>
        <?php endif; ?>
    </div>
</div>

<script>
function submitLog(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const textarea = form.querySelector('textarea');
    const btn = form.querySelector('button');

    if (!textarea.value.trim()) return;

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    fetch('add_log.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Gửi';
        
        if (data.success) {
            textarea.value = '';
            
            // Remove "no logs" message if it exists
            const noLogsMsg = document.getElementById('noLogsMsg');
            if (noLogsMsg) noLogsMsg.remove();

            // Append new log to container
            const container = document.getElementById('logsContainer');
            const logHtml = `
                <div class="log-item" style="position: relative; margin-bottom: 2rem; opacity: 0; transform: translateY(10px); transition: all 0.3s;">
                    <div style="position: absolute; left: -1.9rem; top: 0; width: 12px; height: 12px; border-radius: 50%; background: white; border: 3px solid var(--primary); z-index: 1;"></div>
                    <div style="background: white; border: 1px solid #e2e8f0; padding: 1rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.8rem;">
                            <span style="font-weight: 800; color: var(--text-main);"><i class="fas fa-user-edit" style="color: #64748b;"></i> ${data.log.user_name}</span>
                            <span style="color: var(--text-muted); font-weight: 600;">${data.log.created_at}</span>
                        </div>
                        <div style="color: var(--text-main); font-size: 0.95rem; line-height: 1.5; white-space: pre-wrap;">${data.log.note}</div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('afterbegin', logHtml);
            
            // Trigger animation
            setTimeout(() => {
                const firstItem = container.querySelector('.log-item');
                firstItem.style.opacity = '1';
                firstItem.style.transform = 'translateY(0)';
            }, 10);
        } else {
            alert(data.message || 'Lỗi khi lưu ghi chú');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Gửi';
        console.error(err);
        alert('Lỗi kết nối server');
    });
}
</script>
</div>

<?php require_once '../../templates/footer.php'; ?>
