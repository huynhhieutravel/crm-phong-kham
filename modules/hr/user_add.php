<?php
// modules/hr/user_add.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'];
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role_id = $_POST['role_id'];
    $branch_id = $_POST['branch_id'] ?: null;
    $status = $_POST['status'] ?? 'active';

    // Check if username exists
    $check = $db->prepare("SELECT id FROM users WHERE username = ?");
    $check->execute([$username]);
    if ($check->fetch()) {
        set_flash('Tên đăng nhập đã tồn tại!', 'danger');
    } else {
        $stmt = $db->prepare("
            INSERT INTO users (full_name, username, password, role_id, branch_id, status)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$full_name, $username, $password, $role_id, $branch_id, $status]);
        set_flash('Thêm nhân viên mới thành công!');
        redirect('users.php');
    }
}

$page_title = 'Thêm Nhân viên mới';
$current_page = 'hr';
require_once '../../templates/header.php';

$roles = $db->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();
$branches = $db->query("SELECT * FROM branches ORDER BY name ASC")->fetchAll();
?>

<div class="card" style="max-width: 600px; margin: 0 auto; padding: 2.5rem;">
    <div style="margin-bottom: 2rem; display: flex; align-items: center; gap: 1rem;">
        <a href="users.php" style="color: var(--text-muted); font-size: 1.25rem;"><i class="fas fa-arrow-left"></i></a>
        <h2 style="margin: 0; font-weight: 800; color: var(--text-main);">THÊM NHÂN VIÊN MỚI</h2>
    </div>

    <form method="POST">
        <div class="form-group">
            <label class="form-label">Họ và Tên <span style="color: red;">*</span></label>
            <input type="text" name="full_name" class="form-input" placeholder="Nguyễn Văn A" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label class="form-label">Tên đăng nhập <span style="color: red;">*</span></label>
                <input type="text" name="username" class="form-input" placeholder="username123" required>
            </div>
            <div class="form-group">
                <label class="form-label">Mật khẩu <span style="color: red;">*</span></label>
                <input type="password" name="password" class="form-input" placeholder="••••••••" required>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label class="form-label">Vai trò <span style="color: red;">*</span></label>
                <select name="role_id" class="form-input" required>
                    <option value="">-- Chọn vai trò --</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?php echo $r['id']; ?>"><?php echo e($r['display_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Chi nhánh</label>
                <select name="branch_id" class="form-input">
                    <option value="">-- Trụ sở chính --</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?php echo $b['id']; ?>"><?php echo e($b['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Trạng thái</label>
            <div style="display: flex; gap: 1.5rem; margin-top: 0.5rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="radio" name="status" value="active" checked>
                    <span style="font-weight: 600; color: #10b981;">Đang hoạt động</span>
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="radio" name="status" value="inactive">
                    <span style="font-weight: 600; color: #94a3b8;">Ngừng hoạt động</span>
                </label>
            </div>
        </div>

        <div style="margin-top: 2.5rem; display: flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary" style="flex: 2; padding: 1rem; font-weight: 700;">
                <i class="fas fa-save"></i> LƯU NHÂN VIÊN
            </button>
            <a href="users.php" class="btn" style="flex: 1; text-align: center; text-decoration: none; background: #f1f5f9; color: var(--text-main); display: flex; align-items: center; justify-content: center;">Hủy</a>
        </div>
    </form>
</div>

<?php require_once '../../templates/footer.php'; ?>
