<?php
// modules/medical/demo_v2.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('view_medical');

$page_title = 'Demo Khám V2 (Test Sếp)';
$current_page = 'demo_v2';
require_once '../../templates/header.php';

$db = getDB();

// Fetch last 15 patients for testing
$stmt = $db->query("SELECT id, full_name, phone, customer_id FROM patients ORDER BY created_at DESC LIMIT 15");
$patients = $stmt->fetchAll();

// Add Patient 140 explicitly if not in last 15
$has_140 = false;
foreach ($patients as $p) {
    if ($p['id'] == 140) $has_140 = true;
}
if (!$has_140) {
    $stmt140 = $db->query("SELECT id, full_name, phone, customer_id FROM patients WHERE id = 140");
    if ($p140 = $stmt140->fetch()) {
        array_unshift($patients, $p140);
    }
}
?>

<div class="header-actions" style="margin-bottom: 2rem;">
    <h1 style="font-weight: 800; color: #16a34a;"><i class="fas fa-flask"></i> Khu vực Test Bệnh Án V2 (Demo Sếp)</h1>
    <p style="color: #64748b; font-size: 0.95rem; margin-top: 0.5rem;">Khu vực tách biệt hoàn toàn dành riêng cho Sếp test. Chọn bệnh nhân bên dưới để tạo thử Bệnh án (Anamnese) và Tái khám (Follow-up) theo Mẫu Mới. Các bản test này <strong>không ảnh hưởng</strong> đến giao diện mẫu cũ của bác sĩ trên trang Bệnh nhân.</p>
</div>

<div class="card" style="padding: 0; overflow: hidden; border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); margin-bottom: 2rem;">
    <table class="table" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: transparent; text-align: left;">
                <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: #94a3b8; font-weight: 700; border-bottom: 2px solid #f1f5f9; width: 20%;">ID Bệnh Nhân</th>
                <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: #94a3b8; font-weight: 700; border-bottom: 2px solid #f1f5f9; width: 30%;">Họ và Tên</th>
                <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: #94a3b8; font-weight: 700; border-bottom: 2px solid #f1f5f9; width: 25%;">Số Điện Thoại</th>
                <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: #94a3b8; font-weight: 700; border-bottom: 2px solid #f1f5f9; text-align: right; width: 25%;">Thao tác Test V2</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($patients as $p): ?>
                <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s; <?php echo $p['id'] == 140 ? 'background: #fef9c3;' : ''; ?>" onmouseover="this.style.background='<?php echo $p['id'] == 140 ? '#fef9c3' : '#f8fafc'; ?>';" onmouseout="this.style.background='<?php echo $p['id'] == 140 ? '#fef9c3' : 'transparent'; ?>';">
                    <td style="padding: 1.25rem 1.5rem;">
                        <span style="font-size: 0.75rem; font-weight: 800; background: #f1f5f9; padding: 0.2rem 0.5rem; border-radius: 6px; color: var(--text-muted);">
                            <?php echo e($p['customer_id'] ?: 'BN-'.$p['id']); ?>
                        </span>
                    </td>
                    <td style="padding: 1.25rem 1.5rem; font-weight: 700; color: #1e293b; font-size: 0.95rem;">
                        <?php echo e($p['full_name']); ?>
                    </td>
                    <td style="padding: 1.25rem 1.5rem; color: #64748b; font-size: 0.9rem;">
                        <i class="fas fa-phone-alt" style="font-size: 0.7rem; margin-right: 0.3rem; opacity: 0.6;"></i> <?php echo e($p['phone']); ?>
                    </td>
                    <td style="padding: 1.25rem 1.5rem; text-align: right;">
                        <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                            <a href="chiro_history_v2.php?patient_id=<?php echo $p['id']; ?>" class="btn" style="background: white; color: #16a34a; border: 1px solid #16a34a; border-radius: 50px; font-weight: 700; font-size: 0.8rem; padding: 0.4rem 0.8rem; transition: all 0.2s;" onmouseover="this.style.background='#16a34a'; this.style.color='white';" onmouseout="this.style.background='white'; this.style.color='#16a34a';">
                                <i class="fas fa-file-medical-alt"></i> Anamnese V2
                            </a>
                            <a href="follow_up_v2.php?patient_id=<?php echo $p['id']; ?>" class="btn" style="background: white; color: #1e40af; border: 1px solid #1e40af; border-radius: 50px; font-weight: 700; font-size: 0.8rem; padding: 0.4rem 0.8rem; transition: all 0.2s;" onmouseover="this.style.background='#1e40af'; this.style.color='white';" onmouseout="this.style.background='white'; this.style.color='#1e40af';">
                                <i class="fas fa-stethoscope"></i> Follow-up V2
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../templates/footer.php'; ?>
