<?php
// modules/leads/add.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$db = getDB();

$medical_groups = get_medical_groups();
$lead_sources = get_lead_sources();

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
        INSERT INTO leads (full_name, gender, birthday, phone, email, address, source, medical_group, consultant_id, notes, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $full_name, $gender, $birthday, $phone, $email, $address, 
        $source, $medical_group, $consultant_id, $notes, $status
    ]);
    
    set_flash('Đã thêm Lead mới thành công!');
    redirect('index.php');
}

// Fetch consultants
$consultants_stmt = $db->query("
    SELECT u.id, u.full_name 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    WHERE r.name IN ('cskh', 'admin') AND u.status = 'active'
    ORDER BY u.full_name
");
$consultants = $consultants_stmt->fetchAll();

$page_title = 'Thêm Lead mới';
$current_page = 'leads';
require_once '../../templates/header.php';
?>

<div class="card" style="max-width: 900px; margin: 0 auto; padding: 2.5rem;">
    <div style="margin-bottom: 2rem;">
        <h2 style="font-weight: 800; color: var(--text-main);"><i class="fas fa-plus-circle" style="color: var(--primary);"></i> Tạo Lead Marketing Mới</h2>
        <p style="color: var(--text-muted); font-size: 0.9rem;">Thông tin chi tiết hỗ trợ tư vấn và chuyển đổi thành bệnh nhân.</p>
    </div>

    <form method="POST">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group">
                <label class="form-label">Họ và tên <span style="color: red;">*</span></label>
                <input type="text" name="full_name" class="form-input" required placeholder="Nguyễn Văn A">
            </div>
            <div class="form-group">
                <label class="form-label">Số điện thoại <span style="color: red;">*</span></label>
                <input type="text" name="phone" class="form-input" required placeholder="0912345678">
            </div>
            <div class="form-group">
                <label class="form-label">Giới tính</label>
                <select name="gender" class="form-input">
                    <option value="male">Nam</option>
                    <option value="female">Nữ</option>
                    <option value="other">Khác</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Ngày sinh</label>
                <input type="date" name="birthday" class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-input" placeholder="example@gmail.com">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div class="form-group">
                    <label class="form-label">Nguồn khách hàng</label>
                    <select name="source" class="form-input">
                        <?php foreach ($lead_sources as $key => $label): ?>
                            <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Tư vấn viên (CSKH)</label>
                    <select name="consultant_id" class="form-input">
                        <option value="">-- Chọn tư vấn viên --</option>
                        <?php foreach ($consultants as $con): ?>
                            <option value="<?php echo $con['id']; ?>"><?php echo e($con['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Nhóm bệnh (Tư vấn)</label>
                <select name="medical_group" class="form-input">
                    <option value="">-- Chọn nhóm bệnh --</option>
                    <?php foreach ($medical_groups as $mg): ?>
                        <option value="<?php echo $mg; ?>"><?php echo $mg; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Trạng thái hiện tại</label>
                <select name="status" class="form-input">
                    <option value="new">Mới (New)</option>
                    <option value="contacted">Đã liên hệ (Contacted)</option>
                    <option value="scheduled">Đã đặt lịch (Scheduled)</option>
                </select>
            </div>
        </div>
        
        <div class="form-group" style="margin-top: 1.5rem;">
            <label class="form-label">Địa chỉ</label>
            <textarea name="address" class="form-input" rows="2" placeholder="Số nhà, đường, phường/xã..."></textarea>
        </div>
        
        <div class="form-group" style="margin-top: 1.5rem;">
            <label class="form-label">Ghi chú chi tiết</label>
            <textarea name="notes" class="form-input" rows="3" placeholder="Ghi chú về tình trạng, nhu cầu của khách..."></textarea>
        </div>
        
        <div style="margin-top: 2.5rem; display: flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary" style="padding: 0.8rem 2rem; font-weight: 700;">
                <i class="fas fa-save"></i> Lưu hồ sơ Lead
            </button>
            <a href="index.php" class="btn" style="background: #f1f5f9; color: var(--text-main); padding: 0.8rem 2rem;">Hủy bỏ</a>
        </div>
    </form>
</div>

<?php require_once '../../templates/footer.php'; ?>
