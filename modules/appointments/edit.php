<?php
// modules/appointments/edit.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

$db = getDB();
$id = $_GET['id'] ?? 0;

$stmt = $db->prepare("SELECT * FROM appointments WHERE id = ?");
$stmt->execute([$id]);
$appointment = $stmt->fetch();

if (!$appointment) {
    set_flash('Lịch hẹn không tồn tại!', 'danger');
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $db->prepare("
        UPDATE appointments 
        SET doctor_id = ?, appointment_date = ?, type = ?, notes = ?, status = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $_POST['doctor_id'] ?: null,
        $_POST['appointment_date'] . ' ' . $_POST['appointment_time'],
        $_POST['type'],
        $_POST['notes'],
        $_POST['status'],
        $id
    ]);

    set_flash('Cập nhật lịch hẹn thành công!');
    redirect('index.php');
}

$page_title = 'Chỉnh sửa lịch hẹn';
$current_page = 'appointments';
require_once '../../templates/header.php';

$doctors = $db->query("
    SELECT u.id, u.full_name, r.display_name as role_name 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    WHERE r.name IN ('doctor', 'cskh', 'admin') AND u.status = 'active'
    ORDER BY r.name = 'doctor' DESC, u.full_name ASC
")->fetchAll();
$status_options = ['scheduled', 'confirmed', 'arrived', 'completed', 'no_show', 'cancelled'];
?>

<div class="card" style="max-width: 600px; margin: 0 auto; padding: 2rem;">
    <form method="POST">
        <div class="form-group">
            <label class="form-label">Loại lịch hẹn</label>
            <select name="type" class="form-input">
                <option value="consultation" <?php echo $appointment['type'] === 'consultation' ? 'selected' : ''; ?>>Tư vấn</option>
                <option value="treatment" <?php echo $appointment['type'] === 'treatment' ? 'selected' : ''; ?>>Điều trị</option>
                <option value="re_exam" <?php echo $appointment['type'] === 're_exam' ? 'selected' : ''; ?>>Tái khám</option>
                <option value="adjustment" <?php echo $appointment['type'] === 'adjustment' ? 'selected' : ''; ?>>Hỗ trợ</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Trạng thái</label>
            <select name="status" class="form-input">
                <?php foreach ($status_options as $s): ?>
                    <option value="<?php echo $s; ?>" <?php echo $appointment['status'] === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Bác sĩ / Nhân viên tư vấn</label>
            <select name="doctor_id" class="form-input">
                <option value="">-- Chưa chỉ định --</option>
                <?php foreach ($doctors as $d): ?>
                    <option value="<?php echo $d['id']; ?>" <?php echo (int)$appointment['doctor_id'] === (int)$d['id'] ? 'selected' : ''; ?>><?php echo e($d['full_name']); ?> (<?php echo e($d['role_name']); ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label class="form-label">Ngày hẹn</label>
                <input type="date" name="appointment_date" class="form-input" value="<?php echo date('Y-m-d', strtotime($appointment['appointment_date'])); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Giờ hẹn</label>
                <input type="time" name="appointment_time" class="form-input" value="<?php echo date('H:i', strtotime($appointment['appointment_date'])); ?>" required>
            </div>
        </div>
        
        <div class="form-group" style="margin-top: 1rem;">
            <label class="form-label">Ghi chú</label>
            <textarea name="notes" class="form-input" rows="3"><?php echo e($appointment['notes']); ?></textarea>
        </div>
        
        <div style="margin-top: 2rem; display: flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary" style="flex: 2;">Lưu thay đổi</button>
            <a href="index.php" class="btn" style="flex: 1; text-align: center; text-decoration: none; background: #f1f5f9; color: var(--text-main);">Hủy</a>
        </div>
    </form>
</div>

<?php require_once '../../templates/footer.php'; ?>
