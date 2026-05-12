<?php
// modules/appointments/edit.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_appointments');

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM appointments WHERE id = ?");
$stmt->execute([$id]);
$appointment = $stmt->fetch();

if (!$appointment) {
    set_flash(__('appointment.msg.not_found'), 'danger');
    $redirect_url = $_SESSION['appointment_list_url'] ?? 'index.php';
    redirect($redirect_url);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // H2 FIX: Verify CSRF
    verify_csrf("edit.php?id=$id");
    $optionals = [
        'doctor_id' => $_POST['doctor_id'] ?: null,
        'appointment_date' => $_POST['appointment_date'] . ' ' . $_POST['appointment_time'],
        'appointment_end_time' => !empty($_POST['appointment_end_time']) ? $_POST['appointment_end_time'] : null,
        'type' => $_POST['type'],
        'notes' => $_POST['notes'],
        'status' => $_POST['status']
    ];

    // Detect available columns
    $available_cols = $db->query("SHOW COLUMNS FROM appointments")->fetchAll(PDO::FETCH_COLUMN);
    $data = [];
    foreach ($optionals as $col => $val) {
        if (in_array($col, $available_cols)) {
            $data[$col] = $val;
        }
    }

    if (!empty($data)) {
        // Check if doctor is on approved leave
        if (!empty($data['doctor_id'])) {
            $check_leave = $db->prepare("SELECT COUNT(*) FROM leave_requests WHERE user_id = ? AND status = 'approved' AND ? >= DATE(start_date) AND ? <= DATE(end_date)");
            $check_leave->execute([$data['doctor_id'], $_POST['appointment_date'], $_POST['appointment_date']]);
            if ($check_leave->fetchColumn() > 0) {
                set_flash('Nhân sự đang có lịch nghỉ được duyệt vào ngày này. Không thể thay đổi lịch.', 'danger');
                redirect("edit.php?id=$id");
                exit;
            }
        }

        $set_parts = [];
        foreach (array_keys($data) as $col) {
            $set_parts[] = "$col = ?";
        }
        $sql = "UPDATE appointments SET " . implode(", ", $set_parts) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge(array_values($data), [$id]));
    }

    set_flash(__('appointment.msg.update_success'));
    $redirect_url = $_SESSION['appointment_list_url'] ?? 'index.php';
    redirect($redirect_url);
}

$page_title = __('appointment.edit.title');
$current_page = 'appointments';
require_once '../../templates/header.php';

// M4 FIX: Define $status_options
$status_options = ['scheduled', 'confirmed', 'arrived', 'treated', 'completed', 'no_show', 'cancelled', 'staff_sick', 'staff_busy'];

$doctors = $db->query("
    SELECT u.id, u.full_name, r.display_name as role_name 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    WHERE r.name IN ('doctor', 'technician', 'cskh', 'admin') AND u.status = 'active'
    ORDER BY r.name = 'doctor' DESC, u.full_name ASC
")->fetchAll();
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
/* Flatpickr Premium Styling */
.flatpickr-calendar {
    border-radius: 16px;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    border: 1px solid #e2e8f0;
}
.flatpickr-day.selected {
    background: var(--primary) !important;
    border-color: var(--primary) !important;
}
</style>

<div class="card" style="max-width: 600px; margin: 0 auto; padding: 2rem;">
    <form method="POST">
        <?php echo csrf_field(); ?>
        <div class="form-group">
            <label class="form-label"><?php echo __('appointment.add.type_label'); ?></label>
            <select name="type" class="form-input">
                <option value="consultation" <?php echo $appointment['type'] === 'consultation' ? 'selected' : ''; ?>><?php echo __('appointment.type.consultation'); ?></option>
                <option value="dong_y_60" <?php echo $appointment['type'] === 'dong_y_60' ? 'selected' : ''; ?>><?php echo __('appointment.type.dong_y_60'); ?></option>
                <option value="dong_y_90" <?php echo $appointment['type'] === 'dong_y_90' ? 'selected' : ''; ?>><?php echo __('appointment.type.dong_y_90'); ?></option>
                <option value="chiro" <?php echo $appointment['type'] === 'chiro' ? 'selected' : ''; ?>><?php echo __('appointment.type.chiro'); ?></option>
                <option value="support_other" <?php echo $appointment['type'] === 'support_other' ? 'selected' : ''; ?>><?php echo __('appointment.type.support_other'); ?></option>
                <option value="treatment" <?php echo $appointment['type'] === 'treatment' ? 'selected' : ''; ?> style="display:none;"><?php echo __('appointment.type.treatment'); ?></option>
                <option value="re_exam" <?php echo $appointment['type'] === 're_exam' ? 'selected' : ''; ?> style="display:none;"><?php echo __('appointment.type.re_exam'); ?></option>
                <option value="adjustment" <?php echo $appointment['type'] === 'adjustment' ? 'selected' : ''; ?> style="display:none;"><?php echo __('appointment.type.adjustment'); ?></option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label"><?php echo __('appointment.status'); ?></label>
            <select name="status" class="form-input">
                <?php foreach ($status_options as $s): ?>
                    <option value="<?php echo $s; ?>" <?php echo $appointment['status'] === $s ? 'selected' : ''; ?>><?php echo __('appointment.status.' . $s); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label"><?php echo __('appointment.add.doctor_label'); ?></label>
            <select name="doctor_id" class="form-input">
                <option value=""><?php echo __('appointment.unassigned_doctor'); ?></option>
                <?php foreach ($doctors as $d): ?>
                    <option value="<?php echo $d['id']; ?>" <?php echo (int)$appointment['doctor_id'] === (int)$d['id'] ? 'selected' : ''; ?>><?php echo e($d['full_name']); ?> (<?php echo e($d['role_name']); ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label class="form-label"><?php echo __('appointment.add.date_label'); ?></label>
                <input type="text" name="appointment_date" id="appointment_date" class="form-input" value="<?php echo date('Y-m-d', strtotime($appointment['appointment_date'])); ?>" required placeholder="dd/mm/yyyy">
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo __('appointment.add.time_label'); ?></label>
                <input type="time" name="appointment_time" class="form-input" value="<?php echo date('H:i', strtotime($appointment['appointment_date'])); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo __('appointment.add.end_time_label'); ?></label>
                <input type="time" name="appointment_end_time" class="form-input" value="<?php echo $appointment['appointment_end_time'] ? substr($appointment['appointment_end_time'], 0, 5) : ''; ?>">
                <div style="display: flex; gap: 0.25rem; margin-top: 0.5rem;">
                    <button type="button" class="q-time-btn" onclick="addMinutes(30)">+30p</button>
                    <button type="button" class="q-time-btn" onclick="addMinutes(60)">+60p</button>
                    <button type="button" class="q-time-btn" onclick="addMinutes(90)">+90p</button>
                </div>
            </div>
        </div>
        
        <div class="form-group" style="margin-top: 1rem;">
            <label class="form-label"><?php echo __('common.notes'); ?></label>
            <textarea name="notes" class="form-input" rows="3"><?php echo e($appointment['notes']); ?></textarea>
        </div>
        
        <div style="margin-top: 2rem; display: flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary" style="flex: 2;"><?php echo __('common.save_changes'); ?></button>
            <a href="index.php" class="btn" style="flex: 1; text-align: center; text-decoration: none; background: #f1f5f9; color: var(--text-main);"><?php echo __('common.cancel'); ?></a>
        </div>
    </form>
</div>

<style>
.q-time-btn {
    flex: 1;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.3rem 0.6rem;
    font-size: 0.7rem;
    font-weight: 800;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s ease;
    margin-right: 0.25rem;
}
.q-time-btn:hover {
    background: white;
    color: var(--primary);
    border-color: var(--primary);
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
</style>

<script>
window.addMinutes = function(minutes) {
    const timeInput = document.querySelector('input[name="appointment_time"]');
    const endTimeInput = document.querySelector('input[name="appointment_end_time"]');
    const startTime = timeInput.value;
    if (!startTime) return;
    
    const [hours, mins] = startTime.split(':').map(Number);
    const date = new Date();
    date.setHours(hours);
    date.setMinutes(mins + minutes);
    
    const endHours = String(date.getHours()).padStart(2, '0');
    const endMins = String(date.getMinutes()).padStart(2, '0');
    
    endTimeInput.value = `${endHours}:${endMins}`;
};
</script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/vn.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    flatpickr("#appointment_date", {
        dateFormat: "Y-m-d",
        altInput: true,
        altFormat: "d/m/Y",
        locale: "vn",
        disableMobile: "true"
    });
});
</script>

<?php require_once '../../templates/footer.php'; ?>
