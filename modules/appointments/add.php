<?php
// modules/appointments/add.php
require_once '../../includes/db.php';
$page_title = 'Đặt lịch hẹn mới';
$current_page = 'appointments';
require_once '../../templates/header.php';

$db = getDB();
$patients = $db->query("SELECT id, full_name, phone FROM patients ORDER BY full_name ASC")->fetchAll();
$leads = $db->query("SELECT id, full_name, phone FROM leads WHERE status != 'converted' ORDER BY full_name ASC")->fetchAll();
$doctors = $db->query("SELECT u.id, u.full_name FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name IN ('doctor', 'admin')")->fetchAll();

$prefill_lead_id = $_GET['lead_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contact_val = $_POST['contact_id'] ?? '';
    list($type, $cid) = explode(':', $contact_val);
    
    $patient_id = ($type === 'patient') ? $cid : null;
    $lead_id = ($type === 'lead') ? $cid : null;

    $stmt = $db->prepare("
        INSERT INTO appointments (patient_id, lead_id, doctor_id, branch_id, appointment_date, notes)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $doctor_id = !empty($_POST['doctor_id']) ? $_POST['doctor_id'] : null;

    $stmt->execute([
        $patient_id,
        $lead_id,
        $doctor_id,
        $_SESSION['branch_id'] ?? 1,
        $_POST['appointment_date'] . ' ' . $_POST['appointment_time'],
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
?>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <form method="POST">
        <div class="form-group">
            <label class="form-label">Khách hàng (Lead hoặc Bệnh nhân) <span style="color: red;">*</span></label>
            <select name="contact_id" class="form-input" required>
                <option value="">-- Chọn người đặt lịch --</option>
                <optgroup label="Bệnh nhân (Patients)">
                    <?php foreach ($patients as $p): ?>
                        <option value="patient:<?php echo $p['id']; ?>"><?php echo e($p['full_name']); ?> (<?php echo e($p['phone']); ?>)</option>
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
            <label class="form-label">Bác sĩ phụ trách</label>
            <select name="doctor_id" class="form-input">
                <option value="">-- Chọn bác sĩ (Không bắt buộc) --</option>
                <?php foreach ($doctors as $d): ?>
                    <option value="<?php echo $d['id']; ?>"><?php echo e($d['full_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label class="form-label">Ngày hẹn <span style="color: red;">*</span></label>
                <input type="date" name="appointment_date" class="form-input" required value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Giờ hẹn <span style="color: red;">*</span></label>
                <input type="time" name="appointment_time" class="form-input" required>
            </div>
        </div>
        
        <div class="form-group" style="margin-top: 1rem;">
            <label class="form-label">Ghi chú</label>
            <textarea name="notes" class="form-input" rows="3"></textarea>
        </div>
        
        <div style="margin-top: 2rem; display: flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary">Xác nhận đặt lịch</button>
            <a href="index.php" class="btn" style="background: #f1f5f9; color: var(--text-main);">Hủy</a>
        </div>
    </form>
</div>

<?php require_once '../../templates/footer.php'; ?>
