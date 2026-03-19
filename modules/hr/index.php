<?php
// modules/hr/index.php
require_once '../../includes/db.php';
$page_title = 'Nhân sự & Chấm công';
$current_page = 'hr';
require_once '../../templates/header.php';

$db = getDB();
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_in'])) {
    $stmt = $db->prepare("INSERT INTO timekeeping (user_id, check_in, work_date) VALUES (?, NOW(), ?)");
    $stmt->execute([$_SESSION['user_id'], $today]);
    set_flash('Dểm danh thành công!');
}

$stmt = $db->query("
    SELECT t.*, u.full_name as user_name, r.display_name as role_name
    FROM timekeeping t
    JOIN users u ON t.user_id = u.id
    JOIN roles r ON u.role_id = r.id
    WHERE t.work_date = '$today'
    ORDER BY t.check_in DESC
");
$attendance = $stmt->fetchAll();

// Check if current user already checked in today
$my_check = $db->query("SELECT id FROM timekeeping WHERE user_id = {$_SESSION['user_id']} AND work_date = '$today'")->fetch();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <h2 style="margin: 0; font-weight: 800; color: var(--text-main);">NHÂN SỰ & CHẤM CÔNG</h2>
    <a href="users.php" class="btn btn-primary shadow-sm" style="background: var(--primary); color: white; border-radius: 12px; padding: 0.75rem 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; text-decoration: none;">
        <i class="fas fa-users-cog"></i> Quản lý Nhân sự
    </a>
</div>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem;">
    <div class="card">
        <h3 style="margin-bottom: 1.5rem;">Điểm danh hôm nay</h3>
        <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Ngày: <strong><?php echo date('d/m/Y'); ?></strong></p>
        
        <?php if ($my_check): ?>
            <div style="background: #dcfce7; color: #166534; padding: 2rem; border-radius: 12px; text-align: center;">
                <i class="fas fa-check-circle fa-3x" style="margin-bottom: 1rem;"></i>
                <p style="font-weight: 600;">Bạn đã điểm danh thành công!</p>
            </div>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="check_in" value="1">
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1.5rem; font-size: 1.25rem;">
                    <i class="fas fa-fingerprint"></i> ĐIỂM DANH NGAY
                </button>
            </form>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3 style="margin-bottom: 1.5rem;">Nhân sự đã có mặt</h3>
        <table class="table" style="width: 100%;">
            <thead>
                <tr style="text-align: left; border-bottom: 2px solid var(--border-color);">
                    <th style="padding: 1rem;">Nhân viên</th>
                    <th style="padding: 1rem;">Vai trò</th>
                    <th style="padding: 1rem;">Giờ vào</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($attendance as $at): ?>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 1rem;"><strong><?php echo e($at['user_name']); ?></strong></td>
                        <td style="padding: 1rem;"><?php echo e($at['role_name']); ?></td>
                        <td style="padding: 1rem;"><?php echo date('H:i', strtotime($at['check_in'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($attendance)): ?>
                    <tr>
                        <td colspan="3" style="text-align: center; padding: 2rem; color: var(--text-muted);">Chưa có ai điểm danh hôm nay.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../templates/footer.php'; ?>
