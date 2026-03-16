<?php
// modules/patients/index.php
require_once '../../includes/db.php';
$page_title = 'Danh sách Bệnh nhân';
$current_page = 'patients';
require_once '../../templates/header.php';

$db = getDB();
$search = $_GET['search'] ?? '';

$sql = "SELECT * FROM patients";
$params = [];
if ($search) {
    $sql .= " WHERE full_name LIKE ? OR phone LIKE ?";
    $params = ["%$search%", "%$search%"];
}
$sql .= " ORDER BY created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll();
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div class="search-box">
            <form method="GET" style="display: flex; gap: 0.5rem;">
                <input type="text" name="search" class="form-input" placeholder="Tìm tên hoặc số điện thoại..." value="<?php echo e($search); ?>" style="width: 300px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
            </form>
        </div>
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Thêm Bệnh nhân
        </a>
    </div>

    <table class="table" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="text-align: left; border-bottom: 2px solid var(--border-color);">
                <th style="padding: 1rem;">Họ và tên</th>
                <th style="padding: 1rem;">Số điện thoại</th>
                <th style="padding: 1rem;">Ngày sinh</th>
                <th style="padding: 1rem;">Giới tính</th>
                <th style="padding: 1rem;">Thao tác</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($patients as $p): ?>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 1rem;">
                        <strong><?php echo e($p['full_name']); ?></strong>
                    </td>
                    <td style="padding: 1rem;"><?php echo e($p['phone']); ?></td>
                    <td style="padding: 1rem;"><?php echo $p['birthday'] ? date('d/m/Y', strtotime($p['birthday'])) : '—'; ?></td>
                    <td style="padding: 1rem;"><?php echo $p['gender'] === 'male' ? 'Nam' : ($p['gender'] === 'female' ? 'Nữ' : 'Khác'); ?></td>
                    <td style="padding: 1rem;">
                        <a href="view.php?id=<?php echo $p['id']; ?>" class="btn btn-sm" style="background: #f1f5f9; padding: 0.4rem 0.8rem;"><i class="fas fa-eye"></i></a>
                        <a href="edit.php?id=<?php echo $p['id']; ?>" class="btn btn-sm" style="background: #f1f5f9; padding: 0.4rem 0.8rem;"><i class="fas fa-edit"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($patients)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-muted);">Không tìm thấy bệnh nhân nào.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../templates/footer.php'; ?>
