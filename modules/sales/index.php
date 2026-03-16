<?php
// modules/sales/index.php
require_once '../../includes/db.php';
$page_title = 'Gói khách hàng đang sử dụng';
$current_page = 'sales';
require_once '../../templates/header.php';

$db = getDB();
$stmt = $db->query("
    SELECT pp.*, p.name as package_name, p.is_corporate, pt.full_name as patient_name, pt.phone as patient_phone
    FROM patient_packages pp
    JOIN packages p ON pp.package_id = p.id
    JOIN patients pt ON pp.patient_id = pt.id
    ORDER BY pp.purchase_date DESC
");
$patient_packages = $stmt->fetchAll();
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <h3 style="margin: 0;">Theo dõi Gói dịch vụ</h3>
        <div style="display: flex; gap: 0.75rem;">
            <a href="config_packages.php" class="btn" style="background: #f1f5f9; color: var(--text-main);">
                <i class="fas fa-cog"></i> Cấu hình gói
            </a>
            <a href="add_package.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Bán gói cho khách
            </a>
        </div>
    </div>

    <table class="table" style="width: 100%;">
        <thead>
            <tr style="text-align: left; border-bottom: 2px solid var(--border-color);">
                <th style="padding: 1rem;">Khách hàng / Đại diện</th>
                <th style="padding: 1rem;">Gói dịch vụ</th>
                <th style="padding: 1rem;">Số buổi còn lại</th>
                <th style="padding: 1rem;">Trạng thái</th>
                <th style="padding: 1rem;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($patient_packages as $pp): ?>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 1rem;">
                        <strong><?php echo e($pp['patient_name']); ?></strong>
                        <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo e($pp['patient_phone']); ?></div>
                    </td>
                    <td style="padding: 1rem;">
                        <div><?php echo e($pp['package_name']); ?></div>
                        <?php if ($pp['is_corporate']): ?>
                            <span style="font-size: 0.7rem; color: #92400e; background: #fef3c7; padding: 0.1rem 0.4rem; border-radius: 4px;">Shared/Corporate</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 1rem;">
                        <span style="font-size: 1.1rem; font-weight: 700; color: <?php echo $pp['sessions_remaining'] <= 2 ? '#ef4444' : '#10b981'; ?>;">
                            <?php echo $pp['sessions_remaining']; ?>
                        </span>
                        <span style="color: var(--text-muted);">buổi</span>
                    </td>
                    <td style="padding: 1rem;">
                        <span style="padding: 0.25rem 0.5rem; border-radius: 6px; font-size: 0.85rem; font-weight: 600; background: #dcfce7; color: #166534;">
                            ACTIVE
                        </span>
                    </td>
                    <td style="padding: 1rem;">
                        <a href="usage_log.php?id=<?php echo $pp['id']; ?>" class="btn btn-sm" style="background: #f1f5f9;"><i class="fas fa-history"></i> Lịch sử</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($patient_packages)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-muted);">Chưa có gói nào được bán.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../templates/footer.php'; ?>
