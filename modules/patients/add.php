<?php
// modules/patients/add.php
require_once '../../includes/db.php';
$page_title = 'Thêm Bệnh nhân mới';
$current_page = 'patients';
require_once '../../templates/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO patients (full_name, gender, birthday, phone, email, address, source, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $birthday = !empty($_POST['birthday']) ? $_POST['birthday'] : null;
    
    $stmt->execute([
        $_POST['full_name'],
        $_POST['gender'],
        $birthday,
        $_POST['phone'],
        $_POST['email'],
        $_POST['address'],
        $_POST['source'],
        $_POST['notes']
    ]);
    
    set_flash('Thêm bệnh nhân thành công!');
    redirect('index.php');
}
?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
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
            <div class="form-group">
                <label class="form-label">Nguồn khách</label>
                <input type="text" name="source" class="form-input" placeholder="Facebook, Zalo, Người quen...">
            </div>
        </div>
        
        <div class="form-group" style="margin-top: 1.5rem;">
            <label class="form-label">Địa chỉ</label>
            <textarea name="address" class="form-input" rows="2"></textarea>
        </div>
        
        <div class="form-group" style="margin-top: 1.5rem;">
            <label class="form-label">Ghi chú</label>
            <textarea name="notes" class="form-input" rows="3"></textarea>
        </div>
        
        <div style="margin-top: 2rem; display: flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary">Lưu thông tin</button>
            <a href="index.php" class="btn" style="background: #f1f5f9; color: var(--text-main);">Hủy</a>
        </div>
    </form>
</div>

<?php require_once '../../templates/footer.php'; ?>
