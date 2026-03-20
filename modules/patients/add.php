<?php
// modules/patients/add.php

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    die("ERROR: [$errno] $errstr in $errfile on line $errline");
});
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL) {
        die("FATAL ERROR: " . print_r($error, true));
    }
});

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';

$db = getDB();

// Fetch active consultants (CSKH/Admin)
$consultants_stmt = $db->query("
    SELECT u.id, u.full_name 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    WHERE r.name IN ('cskh', 'admin') AND u.status = 'active'
    ORDER BY u.full_name
");
$consultants = $consultants_stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Self-healing database check (with safety wrapper)
    try {
        ensure_patient_columns($db);
    } catch (\Throwable $e) {}
    
    $stmt = $db->prepare("
        INSERT INTO patients (
            customer_id, full_name, gender, birthday, phone, email, address, 
            branch, occupation, source, consultant_id, label, zalo_number, facebook_link, instagram_link, twitter_link,
            guardian_name, guardian_id_card, guardian_phone, guardian_relationship, notes
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $birthday = !empty($_POST['birthday']) ? $_POST['birthday'] : null;
    $consultant_id = $_POST['consultant_id'] ?: null;
    
    $stmt->execute([
        $_POST['customer_id'] ?: null,
        $_POST['full_name'],
        $_POST['gender'],
        $birthday,
        $_POST['phone'],
        $_POST['email'],
        $_POST['address'],
        $_POST['branch'],
        $_POST['occupation'],
        $_POST['source'],
        $consultant_id,
        $_POST['label'],
        $_POST['zalo_number'],
        $_POST['facebook_link'],
        $_POST['instagram_link'],
        $_POST['twitter_link'],
        $_POST['guardian_name'],
        $_POST['guardian_id_card'],
        $_POST['guardian_phone'],
        $_POST['guardian_relationship'],
        $_POST['notes']
    ]);
    
    set_flash('Thêm bệnh nhân thành công!');
    redirect('index.php');
}

$page_title = 'Thêm Bệnh nhân mới';
$current_page = 'patients';
require_once '../../templates/header.php';
?>

<div style="max-width: 1000px; margin: 0 auto;">
    <div style="margin-bottom: 2rem;">
        <h2 style="font-weight: 800; color: var(--text-main); margin: 0;"><i class="fas fa-user-plus" style="color: var(--primary);"></i> Hồ sơ Bệnh nhân</h2>
        <p style="color: var(--text-muted); margin-top: 0.25rem;">Tạo mới thông tin khách hàng đầy đủ</p>
    </div>

    <form method="POST">
        <!-- Section 1: General Info -->
        <div class="card" style="margin-bottom: 1.5rem; padding: 2rem;">
            <h3 style="font-size: 1rem; text-transform: uppercase; color: var(--primary); margin-bottom: 1.5rem; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.5rem;">
                <i class="fas fa-info-circle"></i> Thông tin chung
            </h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">Họ và tên <span style="color: red;">*</span></label>
                    <input type="text" name="full_name" class="form-input" required placeholder="Nguyễn Văn A" style="font-size: 1.1rem; font-weight: 600;">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Số điện thoại <span style="color: red;">*</span></label>
                    <input type="text" name="phone" class="form-input" required placeholder="0912345678">
                </div>
                <div class="form-group">
                    <label class="form-label">Mã khách hàng (Tùy chọn)</label>
                    <input type="text" name="customer_id" class="form-input" placeholder="Ví dụ: BN-1001">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Giới tính</label>
                    <div style="display: flex; gap: 1.5rem; padding: 0.5rem 0;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="radio" name="gender" value="male" checked> Nam
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="radio" name="gender" value="female"> Nữ
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="radio" name="gender" value="other"> Khác
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Ngày sinh</label>
                    <input type="date" name="birthday" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label">Chi nhánh</label>
                    <select name="branch" class="form-input">
                        <option value="Trụ sở chính">Trụ sở chính</option>
                        <option value="Chi nhánh 1">Chi nhánh 1</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Nguồn khách hàng</label>
                    <select name="source" class="form-input">
                        <?php foreach (get_lead_sources() as $key => $label): ?>
                            <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                        <?php endforeach; ?>
                        <option value="Walk-in">Tự đến (Walk-in)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Sale/CSKH</label>
                    <select name="consultant_id" class="form-input">
                        <option value="">-- Chọn tư vấn viên --</option>
                        <?php foreach ($consultants as $con): ?>
                            <option value="<?php echo $con['id']; ?>"><?php echo e($con['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">Phân loại / Nhãn (Ví dụ: VIP, Khách mới...)</label>
                    <input type="text" name="label" class="form-input" placeholder="Nhập nhãn phân loại...">
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">Nghề nghiệp / Công việc</label>
                    <input type="text" name="occupation" class="form-input" placeholder="Ví dụ: Nhân viên văn phòng, Kinh doanh tự do...">
                </div>
            </div>
            
            <div class="form-group" style="margin-top: 1.5rem;">
                <label class="form-label">Địa chỉ</label>
                <input type="text" name="address" class="form-input" placeholder="Số nhà, đường, phường/xã, quận/huyện, tỉnh/thành">
            </div>
        </div>

        <!-- Section 2: Contact & Social -->
        <div class="card" style="margin-bottom: 1.5rem; padding: 2rem;">
            <h3 style="font-size: 1rem; text-transform: uppercase; color: #10b981; margin-bottom: 1.5rem; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.5rem;">
                <i class="fas fa-address-book"></i> Liên hệ & Mạng xã hội
            </h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div class="form-group">
                    <label class="form-label">Số Zalo</label>
                    <input type="text" name="zalo_number" class="form-input" placeholder="Thường mặc định là SĐT">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input" placeholder="example@gmail.com">
                </div>
                
                <div class="form-group">
                    <label class="form-label"><i class="fab fa-facebook" style="color: #1877f2;"></i> Facebook Link</label>
                    <input type="url" name="facebook_link" class="form-input" placeholder="https://facebook.com/username">
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fab fa-instagram" style="color: #e4405f;"></i> Instagram Link</label>
                    <input type="url" name="instagram_link" class="form-input" placeholder="https://instagram.com/username">
                </div>
            </div>
            <input type="hidden" name="twitter_link" value="">
        </div>

        <!-- Section 3: Guardian Info (Conditional Layout) -->
        <div class="card" style="margin-bottom: 1.5rem; padding: 2rem; background: #fffbeb; border: 1px solid #fef3c7;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem;">
                <div>
                    <h3 style="font-size: 1rem; text-transform: uppercase; color: #d97706; margin: 0;">
                        <i class="fas fa-user-shield"></i> Người Giám Hộ
                    </h3>
                    <p style="font-size: 0.8rem; color: #92400e; margin-top: 0.25rem;">(Bắt buộc nếu khách hàng nhỏ hơn 16 tuổi)</p>
                </div>
                <i class="fas fa-child fa-2x" style="color: #f59e0b; opacity: 0.5;"></i>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div class="form-group">
                    <label class="form-label">Họ tên người giám hộ</label>
                    <input type="text" name="guardian_name" class="form-input" placeholder="Nhập tên người giám hộ">
                </div>
                <div class="form-group">
                    <label class="form-label">Số CMND/CCCD</label>
                    <input type="text" name="guardian_id_card" class="form-input" placeholder="Nhập số CMND/CCCD">
                </div>
                <div class="form-group">
                    <label class="form-label">Số điện thoại</label>
                    <input type="text" name="guardian_phone" class="form-input" placeholder="Nhập số điện thoại liên hệ">
                </div>
                <div class="form-group">
                    <label class="form-label">Mối quan hệ</label>
                    <select name="guardian_relationship" class="form-input">
                        <option value="">-- Chọn mối quan hệ --</option>
                        <option value="Cha">Cha</option>
                        <option value="Mẹ">Mẹ</option>
                        <option value="Ông/Bà">Ông/Bà</option>
                        <option value="Anh/Chị">Anh/Chị</option>
                        <option value="Người thân khác">Người thân khác</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom: 2rem; padding: 2rem;">
            <h3 style="font-size: 1rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 1.5rem; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.5rem;">
                <i class="fas fa-sticky-note"></i> Ghi chú & Tiểu sử bệnh
            </h3>
            <div class="form-group">
                <textarea name="notes" class="form-input" rows="4" placeholder="Nhập các lưu ý đặc biệt hoặc tình trạng bệnh sơ bộ..."></textarea>
            </div>
        </div>
        
        <div style="margin-top: 2rem; display: flex; gap: 1rem; justify-content: flex-end; padding-bottom: 4rem;">
            <a href="index.php" class="btn" style="background: #f1f5f9; color: var(--text-main); padding: 1rem 2.5rem;">Hủy bỏ</a>
            <button type="submit" class="btn btn-primary" style="padding: 1rem 3rem; font-weight: 700; font-size: 1.1rem; box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.4);">
                <i class="fas fa-save" style="margin-right: 0.5rem;"></i> LƯU HỒ SƠ
            </button>
        </div>
    </form>
</div>

<?php require_once '../../templates/footer.php'; ?>
