<?php
// modules/leads/edit.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$id = $_GET['id'] ?? 0;
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

$medical_groups = [
    'Thoát vị đĩa đệm',
    'Thoái hóa cột sống',
    'Đau thần kinh tọa',
    'Cong vẹo cột sống',
    'Phục hồi chức năng',
    'Cơ xương khớp khác'
];

// Handle form submission before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'];
    $phone = $_POST['phone'];
    $gender = $_POST['gender'];
    $birthday = !empty($_POST['birthday']) ? $_POST['birthday'] : null;
    $email = $_POST['email'];
    $address = $_POST['address'];
    $source = $_POST['source'];
    $medical_group = $_POST['medical_group'];
    $consultant_id = $_POST['consultant_id'] ?: null;
    $status = $_POST['status'];
    $notes = $_POST['notes'];

    $stmt = $db->prepare("
        UPDATE leads 
        SET full_name = ?, phone = ?, gender = ?, birthday = ?, email = ?, address = ?, 
            source = ?, medical_group = ?, consultant_id = ?, status = ?, notes = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $full_name, $phone, $gender, $birthday, $email, $address, 
        $source, $medical_group, $consultant_id, $status, $notes, $id
    ]);
    
    set_flash('Cập nhật lead thành công!');
    redirect('index.php');
}

$page_title = 'Chỉnh sửa Lead';
$current_page = 'leads';
require_once '../../templates/header.php';
?>

<div class="card" style="max-width: 900px; margin: 0 auto; padding: 2.5rem;">
    <div style="margin-bottom: 2rem;">
        <h2 style="font-weight: 800; color: var(--text-main);"><i class="fas fa-edit" style="color: var(--primary);"></i> Chỉnh sửa Hồ sơ Lead</h2>
        <p style="color: var(--text-muted); font-size: 0.9rem;">Cập nhật tiến trình chăm sóc và thông tin tư vấn.</p>
    </div>

    <form method="POST">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group">
                <label class="form-label">Họ và tên <span style="color: red;">*</span></label>
                <input type="text" name="full_name" class="form-input" required value="<?php echo e($lead['full_name']); ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Số điện thoại <span style="color: red;">*</span></label>
                <input type="text" name="phone" class="form-input" required value="<?php echo e($lead['phone']); ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Giới tính</label>
                <select name="gender" class="form-input">
                    <option value="male" <?php echo $lead['gender'] === 'male' ? 'selected' : ''; ?>>Nam</option>
                    <option value="female" <?php echo $lead['gender'] === 'female' ? 'selected' : ''; ?>>Nữ</option>
                    <option value="other" <?php echo $lead['gender'] === 'other' ? 'selected' : ''; ?>>Khác</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Ngày sinh</label>
                <input type="date" name="birthday" class="form-input" value="<?php echo $lead['birthday']; ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-input" value="<?php echo e($lead['email']); ?>">
            </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div class="form-group">
                        <label class="form-label">Nguồn khách hàng</label>
                        <select name="source" class="form-input">
                            <option value="Facebook" <?php echo $lead['source'] === 'Facebook' ? 'selected' : ''; ?>>Facebook</option>
                            <option value="Zalo" <?php echo $lead['source'] === 'Zalo' ? 'selected' : ''; ?>>Zalo</option>
                            <option value="TikTok" <?php echo $lead['source'] === 'TikTok' ? 'selected' : ''; ?>>TikTok</option>
                            <option value="Google" <?php echo $lead['source'] === 'Google' ? 'selected' : ''; ?>>Google</option>
                            <option value="Referral" <?php echo $lead['source'] === 'Referral' ? 'selected' : ''; ?>>Người quen</option>
                            <option value="Other" <?php echo $lead['source'] === 'Other' ? 'selected' : ''; ?>>Khác</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tư vấn viên (CSKH)</label>
                        <select name="consultant_id" class="form-input">
                            <option value="">-- Chọn tư vấn viên --</option>
                            <?php foreach ($consultants as $con): ?>
                                <option value="<?php echo $con['id']; ?>" <?php echo (int)$lead['consultant_id'] === (int)$con['id'] ? 'selected' : ''; ?>><?php echo e($con['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            <div class="form-group">
                <label class="form-label">Nhóm bệnh (Tư vấn)</label>
                <select name="medical_group" class="form-input">
                    <option value="">-- Chọn nhóm bệnh --</option>
                    <?php foreach ($medical_groups as $mg): ?>
                        <option value="<?php echo $mg; ?>" <?php echo $lead['medical_group'] === $mg ? 'selected' : ''; ?>><?php echo $mg; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Trạng thái</label>
                <select name="status" class="form-input">
                    <option value="new" <?php echo $lead['status'] === 'new' ? 'selected' : ''; ?>>Mới (New)</option>
                    <option value="contacted" <?php echo $lead['status'] === 'contacted' ? 'selected' : ''; ?>>Đã liên hệ (Contacted)</option>
                    <option value="scheduled" <?php echo $lead['status'] === 'scheduled' ? 'selected' : ''; ?>>Đã đặt lịch (Scheduled)</option>
                    <option value="converted" <?php echo $lead['status'] === 'converted' ? 'selected' : ''; ?>>Đã chuyển đổi</option>
                    <option value="cancelled" <?php echo $lead['status'] === 'cancelled' ? 'selected' : ''; ?>>Đã hủy</option>
                </select>
            </div>
        </div>
        
        <div class="form-group" style="margin-top: 1.5rem;">
            <label class="form-label">Địa chỉ</label>
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
</div>

<?php require_once '../../templates/footer.php'; ?>
