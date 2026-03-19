<?php
// modules/patients/index.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

$page_title = 'Danh sách Bệnh nhân';
$current_page = 'patients';
require_once '../../templates/header.php';

$db = getDB();
$search = $_GET['search'] ?? '';

$sql = "SELECT p.*, COUNT(mh.id) as record_count 
        FROM patients p 
        LEFT JOIN medical_history mh ON p.id = mh.patient_id";
$params = [];
if ($search) {
    $sql .= " WHERE p.full_name LIKE ? OR p.phone LIKE ? OR p.customer_id LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
}
$sql .= " GROUP BY p.id ORDER BY p.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h2 style="margin: 0; font-weight: 800; color: var(--text-main);">Danh sách Bệnh nhân</h2>
        <p style="color: var(--text-muted); margin-top: 0.25rem;">Quản lý và theo dõi hồ sơ khách hàng tại phòng khám</p>
    </div>
    <a href="add.php" class="btn btn-primary shadow-sm" style="padding: 0.75rem 1.5rem; font-weight: 700;">
        <i class="fas fa-plus"></i> THÊM BỆNH NHÂN MỚI
    </a>
</div>

<div class="card" style="margin-bottom: 2rem; padding: 1rem;">
    <form method="GET" style="display: flex; gap: 1rem;">
        <div style="flex: 1; position: relative;">
            <i class="fas fa-search" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
            <input type="text" name="search" class="form-input" placeholder="Tìm theo tên, số điện thoại hoặc mã khách hàng..." value="<?php echo e($search); ?>" style="padding-left: 2.5rem;">
        </div>
        <button type="submit" class="btn btn-primary" style="padding: 0 1.5rem;">Tìm kiếm</button>
        <?php if ($search): ?>
            <a href="index.php" class="btn" style="background: #f1f5f9; color: var(--text-main); display: flex; align-items: center;">Xóa lọc</a>
        <?php endif; ?>
    </form>
</div>

<div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--border-color);">
    <table class="table" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f8fafc; text-align: left;">
                <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: var(--text-muted); font-weight: 700; border-bottom: 2px solid var(--border-color);">Mã BN / Họ Tên</th>
                <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: var(--text-muted); font-weight: 700; border-bottom: 2px solid var(--border-color);">Liên hệ</th>
                <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: var(--text-muted); font-weight: 700; border-bottom: 2px solid var(--border-color);">Sinh nhật / GT</th>
                <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: var(--text-muted); font-weight: 700; border-bottom: 2px solid var(--border-color); text-align: center;">Số bệnh án</th>
                <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: var(--text-muted); font-weight: 700; border-bottom: 2px solid var(--border-color); text-align: center;">Thao tác</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($patients as $p): ?>
                <tr class="patient-row" style="border-bottom: 1px solid var(--border-color); transition: background 0.2s;">
                    <td style="padding: 1.25rem 1.5rem;">
                        <span style="font-size: 0.7rem; font-weight: 800; background: #f1f5f9; padding: 0.1rem 0.4rem; border-radius: 4px; color: var(--text-muted); margin-bottom: 0.25rem; display: inline-block;">
                            <?php echo e($p['customer_id'] ?: 'BN-' . $p['id']); ?>
                        </span>
                        <div style="font-weight: 700; color: var(--text-main); font-size: 1.05rem;"><?php echo e($p['full_name']); ?></div>
                    </td>
                    <td style="padding: 1.25rem 1.5rem;">
                        <div style="font-weight: 600; color: var(--primary);"><i class="fas fa-phone-alt" style="font-size: 0.8rem;"></i> <?php echo e($p['phone']); ?></div>
                        <div style="font-size: 0.85rem; color: var(--text-muted);"><?php echo e($p['email'] ?: '—'); ?></div>
                    </td>
                    <td style="padding: 1.25rem 1.5rem;">
                        <div style="font-weight: 500;"><?php echo $p['birthday'] ? date('d/m/Y', strtotime($p['birthday'])) : '—'; ?></div>
                        <div style="font-size: 0.85rem; color: var(--text-muted);">
                            <?php echo $p['gender'] === 'male' ? '<i class="fas fa-mars" style="color: #2563eb;"></i> Nam' : ($p['gender'] === 'female' ? '<i class="fas fa-venus" style="color: #e4405f;"></i> Nữ' : 'Khác'); ?>
                        </div>
                    </td>
                    <td style="padding: 1.25rem 1.5rem; text-align: center;">
                        <span style="display: inline-flex; align-items: center; justify-content: center; min-width: 28px; height: 28px; background: <?php echo $p['record_count'] > 0 ? '#eff6ff' : '#f8fafc'; ?>; color: <?php echo $p['record_count'] > 0 ? '#2563eb' : '#94a3b8'; ?>; border-radius: 8px; font-weight: 700; font-size: 0.85rem; border: 1px solid <?php echo $p['record_count'] > 0 ? '#dbeafe' : '#e2e8f0'; ?>;">
                            <?php echo $p['record_count']; ?>
                        </span>
                    </td>
                    <td style="padding: 1.25rem 1.5rem;">
                        <div style="display: flex; gap: 0.5rem; justify-content: center;">
                            <a href="../appointments/add.php?patient_id=<?php echo $p['id']; ?>" class="btn btn-sm" title="Đặt lịch hẹn" style="background: #fdf2f8; color: #db2777; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; padding: 0; border-radius: 10px;">
                                <i class="fas fa-calendar-plus"></i>
                            </a>
                            <a href="view.php?id=<?php echo $p['id']; ?>" class="btn btn-sm" title="Xem chi tiết" style="background: #eff6ff; color: #2563eb; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; padding: 0; border-radius: 10px;">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="edit.php?id=<?php echo $p['id']; ?>" class="btn btn-sm" title="Chỉnh sửa" style="background: #f1f5f9; color: var(--text-muted); width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; padding: 0; border-radius: 10px;">
                                <i class="fas fa-edit"></i>
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($patients)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 5rem 2rem;">
                         <div style="opacity: 0.1; margin-bottom: 1rem;"><i class="fas fa-users-slash fa-4x"></i></div>
                         <div style="color: var(--text-muted); font-size: 1.1rem; font-weight: 600;">Không tìm thấy bệnh nhân nào.</div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.patient-row:hover { background: #f8fafc; }
</style>

<?php require_once '../../templates/footer.php'; ?>
