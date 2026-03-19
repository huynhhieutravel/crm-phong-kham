<?php
// modules/appointments/add.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contact_val = $_POST['contact_id'] ?? '';
    list($type, $cid) = explode(':', $contact_val);
    
    $patient_id = ($type === 'patient') ? $cid : null;
    $lead_id = ($type === 'lead') ? $cid : null;

    $stmt = $db->prepare("
        INSERT INTO appointments (patient_id, lead_id, doctor_id, branch_id, appointment_date, type, reexam_rule_id, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $doctor_id = !empty($_POST['doctor_id']) ? $_POST['doctor_id'] : null;
    $rule_id = !empty($_GET['reexam_rule_id']) ? $_GET['reexam_rule_id'] : null;

    $stmt->execute([
        $patient_id,
        $lead_id,
        $doctor_id,
        $_SESSION['branch_id'] ?? 1,
        $_POST['appointment_date'] . ' ' . $_POST['appointment_time'],
        $_POST['type'] ?? 'consultation',
        $rule_id,
        $_POST['notes']
    ]);
    
    // Update booking time for lead if applicable
    if ($lead_id) {
        $db->prepare("UPDATE leads SET appointment_booking_time = NOW(), status = 'scheduled' WHERE id = ?")
           ->execute([$lead_id]);
    }

    set_flash('Đặt lịch hẹn thành công!');
    redirect('index.php');
}

$page_title = 'Đặt lịch hẹn mới';
$current_page = 'appointments';
require_once '../../templates/header.php';

$patients = $db->query("SELECT id, full_name, phone FROM patients ORDER BY full_name ASC")->fetchAll();
$leads = $db->query("SELECT id, full_name, phone FROM leads WHERE status != 'converted' ORDER BY full_name ASC")->fetchAll();
$doctors = $db->query("
    SELECT u.id, u.full_name, r.display_name as role_name 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    WHERE r.name IN ('doctor', 'cskh', 'admin') AND u.status = 'active'
    ORDER BY r.name = 'doctor' DESC, u.full_name ASC
")->fetchAll();

$prefill_lead_id = $_GET['lead_id'] ?? null;
$prefill_patient_id = $_GET['patient_id'] ?? null;
?>

<div class="card" style="max-width: 650px; margin: 0 auto; padding: 2rem;">
    <div style="margin-bottom: 2rem; text-align: center;">
        <h2 style="font-weight: 800; color: var(--text-main); margin: 0;">LÊN LỊCH THÔNG MINH</h2>
        <p style="color: var(--text-muted); font-size: 0.9rem;">Hệ thống tự động kiểm tra xung đột và tải trọng bác sĩ</p>
    </div>

    <form method="POST" id="appointmentForm">
        <div class="form-group">
            <label class="form-label">Loại lịch hẹn <span style="color: red;">*</span></label>
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.5rem;">
                <label class="type-btn">
                    <input type="radio" name="type" value="consultation" checked required>
                    <div class="type-content">
                        <i class="fas fa-comments"></i>
                        <span>Tư vấn</span>
                    </div>
                </label>
                <label class="type-btn">
                    <input type="radio" name="type" value="treatment">
                    <div class="type-content">
                        <i class="fas fa-hand-holding-medical"></i>
                        <span>Điều trị</span>
                    </div>
                </label>
                <label class="type-btn">
                    <input type="radio" name="type" value="re_exam">
                    <div class="type-content">
                        <i class="fas fa-redo"></i>
                        <span>Tái khám</span>
                    </div>
                </label>
                <label class="type-btn">
                    <input type="radio" name="type" value="adjustment">
                    <div class="type-content">
                        <i class="fas fa-tools"></i>
                        <span>Hỗ trợ</span>
                    </div>
                </label>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Khách hàng <span style="color: red;">*</span></label>
            <select name="contact_id" class="form-input" required>
                <option value="">-- Chọn người đặt lịch --</option>
                <optgroup label="Bệnh nhân (Patients)">
                    <?php foreach ($patients as $p): ?>
                        <option value="patient:<?php echo $p['id']; ?>" <?php echo (int)$prefill_patient_id === (int)$p['id'] ? 'selected' : ''; ?>>
                            <?php echo e($p['full_name']); ?> (<?php echo e($p['phone']); ?>)
                        </option>
                    <?php endforeach; ?>
                </optgroup>
                <optgroup label="Leads (Marketing)">
                    <?php foreach ($leads as $l): ?>
                        <option value="lead:<?php echo $l['id']; ?>" <?php echo (int)$prefill_lead_id === (int)$l['id'] ? 'selected' : ''; ?>>
                            <?php echo e($l['full_name']); ?> (<?php echo e($l['phone']); ?>) - Lead
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label">Bác sĩ / Nhân viên tư vấn</label>
            <select name="doctor_id" id="doctor_id" class="form-input">
                <option value="">-- Chọn bác sĩ/nhân viên tư vấn (Không bắt buộc) --</option>
                <?php foreach ($doctors as $d): ?>
                    <option value="<?php echo $d['id']; ?>"><?php echo e($d['full_name']); ?> (<?php echo e($d['role_name']); ?>)</option>
                <?php endforeach; ?>
            </select>
            <div id="doctorConflict" style="display: none; margin-top: 0.5rem; padding: 0.5rem; background: #fee2e2; color: #dc2626; border-radius: 4px; font-size: 0.85rem;">
                <i class="fas fa-exclamation-triangle"></i> Bác sĩ đã có lịch hẹn khác trong khung giờ này!
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label class="form-label">Ngày hẹn <span style="color: red;">*</span></label>
                <input type="date" name="appointment_date" id="appointment_date" class="form-input" required value="<?php echo date('Y-m-d'); ?>">
                <div id="loadIndicator" style="display: none; margin-top: 0.5rem; font-size: 0.8rem; color: #64748b;">
                    <span id="morningLoad" style="margin-right: 1rem;">Sáng: 0</span>
                    <span id="afternoonLoad">Chiều: 0</span>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Giờ hẹn <span style="color: red;">*</span></label>
                <input type="time" name="appointment_time" id="appointment_time" class="form-input" required>
            </div>
        </div>
        
        <div class="form-group" style="margin-top: 1rem;">
            <label class="form-label">Ghi chú</label>
            <textarea name="notes" class="form-input" rows="3" placeholder="Lý do khám, biểu hiện bệnh..."></textarea>
        </div>
        
        <div style="margin-top: 2rem; display: flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary" style="flex: 2; padding: 1rem;">
                <i class="fas fa-check-circle"></i> XÁC NHẬN ĐẶT LỊCH
            </button>
            <a href="index.php" class="btn" style="flex: 1; background: #f1f5f9; color: var(--text-main); display: flex; align-items: center; justify-content: center; text-decoration: none;">Hủy</a>
        </div>
    </form>
</div>

<style>
.type-btn {
    position: relative;
    cursor: pointer;
}
.type-btn input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}
.type-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 0.75rem 0.5rem;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    transition: all 0.2s;
    background: #f8fafc;
}
.type-content i {
    font-size: 1.2rem;
    margin-bottom: 0.25rem;
    color: #64748b;
}
.type-content span {
    font-size: 0.75rem;
    font-weight: 600;
    color: #64748b;
}
.type-btn input:checked + .type-content {
    border-color: var(--primary);
    background: #eff6ff;
}
.type-btn input:checked + .type-content i,
.type-btn input:checked + .type-content span {
    color: var(--primary);
}

.type-btn:hover .type-content {
    border-color: #cbd5e1;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dateInput = document.getElementById('appointment_date');
    const timeInput = document.getElementById('appointment_time');
    const doctorSelect = document.getElementById('doctor_id');
    const loadIndicator = document.getElementById('loadIndicator');
    const morningLoad = document.getElementById('morningLoad');
    const afternoonLoad = document.getElementById('afternoonLoad');
    const doctorConflict = document.getElementById('doctorConflict');

    function checkAvailability() {
        const date = dateInput.value;
        const time = timeInput.value;
        const doctorId = doctorSelect.value;

        if (!date) return;

        fetch(`check_availability.php?date=${date}&time=${time}&doctor_id=${doctorId}`)
            .then(response => response.json())
            .then(data => {
                // Update load indicator
                loadIndicator.style.display = 'block';
                morningLoad.textContent = `Sáng: ${data.morning_count}${data.morning_count > 5 ? ' (Đông)' : ''}`;
                afternoonLoad.textContent = `Chiều: ${data.afternoon_count}${data.afternoon_count > 5 ? ' (Đông)' : ''}`;
                
                // Color coding based on load
                morningLoad.style.color = data.morning_count > 8 ? '#dc2626' : (data.morning_count > 5 ? '#d97706' : '#64748b');
                afternoonLoad.style.color = data.afternoon_count > 8 ? '#dc2626' : (data.afternoon_count > 5 ? '#d97706' : '#64748b');

                // Check doctor busy
                if (data.doctor_busy) {
                    doctorConflict.style.display = 'block';
                } else {
                    doctorConflict.style.display = 'none';
                }
            });
    }

    dateInput.addEventListener('change', checkAvailability);
    timeInput.addEventListener('change', checkAvailability);
    doctorSelect.addEventListener('change', checkAvailability);

    // Initial check
    checkAvailability();
});
</script>

<?php require_once '../../templates/footer.php'; ?>
