<?php
// modules/hr/change_password.php — Trang đổi mật khẩu cho chính mình
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

// Mọi nhân viên đã đăng nhập đều được đổi mật khẩu của mình
$db = getDB();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('change_password.php');
    
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Lấy mật khẩu hiện tại từ DB
    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($current_password, $user['password'])) {
        $error = 'Mật khẩu hiện tại không đúng.';
    } elseif (strlen($new_password) < 6) {
        $error = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Xác nhận mật khẩu không khớp.';
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $user_id]);
        $success = 'Đổi mật khẩu thành công!';
    }
}

$page_title = 'Đổi mật khẩu';
$current_page = 'change_password';
require_once '../../templates/header.php';
?>

<div class="card" style="max-width: 500px; margin: 0 auto; padding: 2.5rem;">
    <div style="margin-bottom: 2rem; text-align: center;">
        <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
            <i class="fas fa-key" style="color: white; font-size: 1.5rem;"></i>
        </div>
        <h2 style="margin: 0; font-weight: 800; color: var(--text-main);">ĐỔI MẬT KHẨU</h2>
        <p style="color: var(--text-muted); margin-top: 0.5rem; font-size: 0.9rem;">
            <i class="fas fa-user-circle"></i> <?php echo e($_SESSION['full_name'] ?? $_SESSION['username']); ?>
        </p>
    </div>

    <?php if ($error): ?>
        <div style="background: #fee2e2; color: #ef4444; padding: 0.75rem 1rem; border-radius: 10px; margin-bottom: 1.5rem; font-weight: 600; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div style="background: #d1fae5; color: #059669; padding: 0.75rem 1rem; border-radius: 10px; margin-bottom: 1.5rem; font-weight: 600; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <?php echo csrf_field(); ?>
        
        <div class="form-group" style="margin-bottom: 1.25rem;">
            <label class="form-label" style="font-weight: 600; color: var(--text-main);">
                <i class="fas fa-lock" style="color: var(--text-muted); width: 18px;"></i> Mật khẩu hiện tại <span style="color: red;">*</span>
            </label>
            <input type="password" name="current_password" class="form-input" placeholder="Nhập mật khẩu hiện tại" required 
                   style="padding: 0.85rem 1rem; border-radius: 10px;">
        </div>

        <hr style="border: 0; border-top: 1px dashed var(--border-color); margin: 1.5rem 0;">

        <div class="form-group" style="margin-bottom: 1.25rem;">
            <label class="form-label" style="font-weight: 600; color: var(--text-main);">
                <i class="fas fa-key" style="color: var(--text-muted); width: 18px;"></i> Mật khẩu mới <span style="color: red;">*</span>
            </label>
            <input type="password" name="new_password" class="form-input" placeholder="Tối thiểu 6 ký tự" required minlength="6"
                   style="padding: 0.85rem 1rem; border-radius: 10px;">
        </div>

        <div class="form-group" style="margin-bottom: 1.5rem;">
            <label class="form-label" style="font-weight: 600; color: var(--text-main);">
                <i class="fas fa-check-double" style="color: var(--text-muted); width: 18px;"></i> Xác nhận mật khẩu mới <span style="color: red;">*</span>
            </label>
            <input type="password" name="confirm_password" class="form-input" placeholder="Nhập lại mật khẩu mới" required minlength="6"
                   style="padding: 0.85rem 1rem; border-radius: 10px;">
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem; font-weight: 700; justify-content: center; border-radius: 12px; font-size: 1rem;">
            <i class="fas fa-save"></i> ĐỔI MẬT KHẨU
        </button>
    </form>
</div>

<?php require_once '../../templates/footer.php'; ?>
