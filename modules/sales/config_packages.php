<?php
// modules/sales/config_packages.php
require_once '../../includes/db.php';
$page_title = 'Cấu hình Gói dịch vụ';
$current_page = 'sales';
require_once '../../templates/header.php';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $db->prepare("INSERT INTO packages (name, total_sessions, total_price, is_corporate) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $_POST['name'],
        $_POST['total_sessions'],
        $_POST['total_price'],
        isset($_POST['is_corporate']) ? 1 : 0
    ]);
    set_flash('Thêm loại gói thành công!');
}

$packages = $db->query("SELECT * FROM packages ORDER BY id DESC")->fetchAll();
?>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem;">
    <div class="card">
        <h3 style="margin-bottom: 1.5rem;">Thêm loại gói mới</h3>
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Tên gói</label>
                <input type="text" name="name" class="form-input" required placeholder="Gói Chiropractic 10 buổi">
            </div>
            <div class="form-group">
                <label class="form-label">Số buổi</label>
                <input type="number" name="total_sessions" class="form-input" required placeholder="10">
            </div>
            <div class="form-group">
                <label class="form-label">Giá trọn gói</label>
                <input type="number" name="total_price" class="form-input" required placeholder="5000000">
            </div>
            <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem;">
                <input type="checkbox" name="is_corporate" id="is_corporate">
                <label for="is_corporate" class="form-label" style="margin: 0;">Gói công ty/nhóm (Dùng chung)</label>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Lưu loại gói</button>
        </form>
    </div>

    <div class="card">
        <h3 style="margin-bottom: 1.5rem;">Danh sách loại gói hiện có</h3>
        <table class="table" style="width: 100%;">
            <thead>
                <tr style="text-align: left; border-bottom: 1px solid var(--border-color);">
                    <th style="padding: 0.75rem;">Tên gói</th>
                    <th style="padding: 0.75rem;">Số buổi</th>
                    <th style="padding: 0.75rem;">Đơn giá</th>
                    <th style="padding: 0.75rem;">Loại</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($packages as $pkg): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 0.75rem;"><strong><?php echo e($pkg['name']); ?></strong></td>
                        <td style="padding: 0.75rem;"><?php echo $pkg['total_sessions']; ?></td>
                        <td style="padding: 0.75rem;"><?php echo format_money($pkg['total_price']); ?></td>
                        <td style="padding: 0.75rem;">
                            <span style="font-size: 0.75rem; padding: 0.2rem 0.5rem; border-radius: 4px; background: <?php echo $pkg['is_corporate'] ? '#fef3c7; color: #92400e;' : '#dcfce7; color: #166534;'; ?>">
                                <?php echo $pkg['is_corporate'] ? 'Công ty' : 'Cá nhân'; ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../templates/footer.php'; ?>
