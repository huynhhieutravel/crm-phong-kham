<?php
// modules/medical/index.php
require_once '../../includes/db.php';
$page_title = 'Hồ sơ bệnh án';
$current_page = 'medical';
require_once '../../templates/header.php';

$db = getDB();
$search = $_GET['search'] ?? '';

$sql = "SELECT p.*, 
        (SELECT COUNT(*) FROM medical_history WHERE patient_id = p.id) as history_count
        FROM patients p";
$params = [];
if ($search) {
    $sql .= " WHERE p.full_name LIKE ? OR p.phone LIKE ?";
    $params = ["%$search%", "%$search%"];
}
$sql .= " ORDER BY p.full_name ASC";

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
        <p style="color: var(--text-muted);"><?php echo count($patients); ?> bệnh nhân</p>
    </div>

    <table class="table" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="text-align: left; border-bottom: 2px solid var(--border-color);">
                <th style="padding: 1rem;">Họ và tên</th>
                <th style="padding: 1rem;">Số điện thoại</th>
                <th style="padding: 1rem;">Số hồ sơ đã tạo</th>
                <th style="padding: 1rem;">Thao tác nhanh</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($patients as $p): ?>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 1rem;">
                        <strong><?php echo e($p['full_name']); ?></strong>
                    </td>
                    <td style="padding: 1rem;"><?php echo e($p['phone']); ?></td>
                    <td style="padding: 1rem;">
                        <span class="badge" style="background: rgba(99,102,241,0.1); color: var(--primary); padding: 0.25rem 0.75rem; border-radius: 20px; font-weight: 600;">
                            <?php echo $p['history_count']; ?> hồ sơ
                        </span>
                    </td>
                    <td style="padding: 1rem; display: flex; gap: 0.5rem;">
                        <a href="initial_exam.php?patient_id=<?php echo $p['id']; ?>" class="btn btn-sm" style="background: #f5f3ff; color: #7c3aed;" title="Khám tiền Chiro">
                            <i class="fas fa-stethoscope"></i> Tiền Chiro
                        </a>
                        <a href="form.php?patient_id=<?php echo $p['id']; ?>&type=chiropractic" class="btn btn-sm" style="background: #eef2ff; color: #4f46e5;" title="Tạo phiếu Chiropractic">
                            <i class="fas fa-bone"></i> Chiro
                        </a>
                        <a href="form.php?patient_id=<?php echo $p['id']; ?>&type=dong_y" class="btn btn-sm" style="background: #fdf2f2; color: #dc2626;" title="Tạo phiếu Đông Y">
                            <i class="fas fa-leaf"></i> Đông Y
                        </a>
                        <a href="../patients/view.php?id=<?php echo $p['id']; ?>" class="btn btn-sm" style="background: #f1f5f9; color: var(--text-main);" title="Xem tất cả">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($patients)): ?>
                <tr>
                    <td colspan="4" style="text-align: center; padding: 2rem; color: var(--text-muted);">Không tìm thấy dữ liệu.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../templates/footer.php'; ?>
