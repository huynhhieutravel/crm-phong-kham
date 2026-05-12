<?php
// modules/hr/user_edit.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_users');

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    set_flash('Nhân viên không tồn tại!', 'danger');
    redirect('users.php');
}

// Security: Non-admin cannot edit an Admin account
if ($user['role_id'] == 1 && $_SESSION['role'] !== 'admin') {
    set_flash('Bạn không có quyền chỉnh sửa tài khoản Administrator!', 'danger');
    redirect('users.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $full_name = $_POST['full_name'];
    $username = $_POST['username'];
    $role_id = (int)$_POST['role_id'];
    $branch_id = $_POST['branch_id'] ? (int)$_POST['branch_id'] : null;

    // Security: Non-admin cannot promote someone to Admin
    if ($role_id == 1 && $_SESSION['role'] !== 'admin') {
        set_flash('Bạn không có quyền gán vai trò Administrator!', 'danger');
        redirect('users.php');
    }
    $allowed_statuses = ['active', 'inactive'];
    $status = in_array($_POST['status'] ?? '', $allowed_statuses) ? $_POST['status'] : 'active';
    $salary = isset($_POST['salary_per_patient']) ? (float)$_POST['salary_per_patient'] : 0.00;

    // Update basic info
    $update_sql = "UPDATE users SET full_name = ?, username = ?, role_id = ?, branch_id = ?, status = ?, salary_per_patient = ? WHERE id = ?";
    $update_params = [$full_name, $username, $role_id, $branch_id, $status, $salary, $id];
    
    $stmt = $db->prepare($update_sql);
    $stmt->execute($update_params);

    // Update password if provided
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$password, $id]);
    }

    set_flash('Cập nhật thông tin nhân viên thành công!');
    redirect('users.php');
}

$page_title = 'Chỉnh sửa Nhân viên';
$current_page = 'hr';
require_once '../../templates/header.php';

$roles = $db->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();
$branches = $db->query("SELECT * FROM branches ORDER BY name ASC")->fetchAll();
?>

<div class="card" style="max-width: 600px; margin: 0 auto; padding: 2.5rem;">
    <div style="margin-bottom: 2rem; display: flex; align-items: center; gap: 1rem;">
        <a href="users.php" style="color: var(--text-muted); font-size: 1.25rem;"><i class="fas fa-arrow-left"></i></a>
        <h2 style="margin: 0; font-weight: 800; color: var(--text-main);">CHỈNH SỬA NHÂN VIÊN</h2>
    </div>

    <form method="POST">
        <?php echo csrf_field(); ?>
        <div class="form-group">
            <label class="form-label">Họ và Tên <span style="color: red;">*</span></label>
            <input type="text" name="full_name" class="form-input" value="<?php echo e($user['full_name']); ?>" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label class="form-label">Tên đăng nhập <span style="color: red;">*</span></label>
                <input type="text" name="username" class="form-input" value="<?php echo e($user['username']); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Mật khẩu mới (Để trống nếu không đổi)</label>
                <input type="password" name="password" class="form-input" placeholder="••••••••">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label class="form-label">Vai trò <span style="color: red;">*</span></label>
                <select name="role_id" class="form-input" required>
                    <option value="">-- Chọn vai trò --</option>
                    <?php foreach ($roles as $r): ?>
                        <?php if ($r['id'] == 1 && $_SESSION['role'] !== 'admin') continue; ?>
                        <option value="<?php echo $r['id']; ?>" <?php echo (int)$user['role_id'] === (int)$r['id'] ? 'selected' : ''; ?>><?php echo e($r['display_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Chi nhánh</label>
                <select name="branch_id" class="form-input">
                    <option value="">-- Trụ sở chính --</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?php echo $b['id']; ?>" <?php echo (int)$user['branch_id'] === (int)$b['id'] ? 'selected' : ''; ?>><?php echo e($b['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Trạng thái</label>
            <div style="display: flex; gap: 1.5rem; margin-top: 0.5rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="radio" name="status" value="active" <?php echo $user['status'] === 'active' ? 'checked' : ''; ?>>
                    <span style="font-weight: 600; color: #10b981;">Đang hoạt động</span>
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="radio" name="status" value="inactive" <?php echo $user['status'] === 'inactive' ? 'checked' : ''; ?>>
                    <span style="font-weight: 600; color: #94a3b8;">Ngừng hoạt động</span>
                </label>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Chi phí theo Bệnh nhân (VNĐ) <span style="color: #64748b; font-weight: normal; font-size: 0.8rem;">- Setup khoản cố định/ca cho KPI</span></label>
            <div style="position: relative;">
                <input type="number" name="salary_per_patient" class="form-input" value="<?php echo e(isset($user['salary_per_patient']) ? $user['salary_per_patient'] : 0); ?>" step="1000" min="0" style="padding-right: 3rem;">
                <span style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-weight: 600;">đ</span>
            </div>
        </div>

        <div style="margin-top: 2.5rem; display: flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary" style="flex: 2; padding: 1rem; font-weight: 700;">
                <i class="fas fa-save"></i> LƯU THAY ĐỔI
            </button>
            <a href="users.php" class="btn" style="flex: 1; text-align: center; text-decoration: none; background: #f1f5f9; color: var(--text-main); display: flex; align-items: center; justify-content: center;">Hủy</a>
        </div>
    </form>
</div>

<?php require_once '../../templates/footer.php'; ?>
