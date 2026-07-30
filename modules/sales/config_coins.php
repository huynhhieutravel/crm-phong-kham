<?php
// modules/sales/config_coins.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_services'); // Dùng chung quyền quản lý dịch vụ/gói

$page_title = 'Cấu Hình Ví Coin';
$current_page = 'config_coins';
require_once '../../templates/header.php';

$db = getDB();

// Auto-run migration for coin_tiers
try {
    $db->exec("
    CREATE TABLE IF NOT EXISTS `coin_tiers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `price` DECIMAL(12,2) NOT NULL,
        `coins_amount` DECIMAL(10,2) NOT NULL,
        `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
        `display_order` INT NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
} catch (Exception $e) {
    // Ignore if exists
}

$success = '';
$error = '';

// Xử lý Thêm / Sửa Gói Nạp
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'add_tier') {
            $stmt = $db->prepare("INSERT INTO coin_tiers (name, price, coins_amount, display_order) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_POST['name'], $_POST['price'], $_POST['coins_amount'], $_POST['display_order']]);
            $success = "Thêm gói nạp thành công.";
        } elseif ($_POST['action'] === 'edit_tier') {
            $stmt = $db->prepare("UPDATE coin_tiers SET name = ?, price = ?, coins_amount = ?, status = ?, display_order = ? WHERE id = ?");
            $stmt->execute([$_POST['name'], $_POST['price'], $_POST['coins_amount'], $_POST['status'], $_POST['display_order'], $_POST['tier_id']]);
            $success = "Cập nhật gói nạp thành công.";
        } elseif ($_POST['action'] === 'update_services') {
            // Cập nhật giá Coin hàng loạt cho Dịch vụ
            if (isset($_POST['service_coins']) && is_array($_POST['service_coins'])) {
                $stmt = $db->prepare("UPDATE services SET coin_cost = ? WHERE id = ?");
                foreach ($_POST['service_coins'] as $svc_id => $coin_cost) {
                    $stmt->execute([$coin_cost, $svc_id]);
                }
                $success = "Cập nhật giá Coin dịch vụ thành công.";
            }
        }
    } catch (Exception $e) {
        $error = "Lỗi hệ thống: " . $e->getMessage();
    }
}

// Lấy danh sách Gói nạp
$stmt = $db->query("SELECT * FROM coin_tiers ORDER BY display_order ASC, id ASC");
$tiers = $stmt->fetchAll();

// Lấy danh sách Dịch vụ
$stmt = $db->query("SELECT * FROM services ORDER BY name ASC");
$services = $stmt->fetchAll();

?>

<style>
.config-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}
@media (max-width: 992px) {
    .config-container {
        grid-template-columns: 1fr;
    }
}
.section-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
}
.section-title {
    font-size: 1.1rem;
    font-weight: 800;
    color: #1e293b;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.section-title i {
    color: var(--primary);
}
</style>

<div class="content-body">
    <div style="max-width: 1400px; width: 100%;">
        <div class="breadcrumb mb-2" style="font-size: 0.75rem; font-weight: 700; letter-spacing: 1px; color: #94a3b8;">
            CRM / BÁN HÀNG / CẤU HÌNH VÍ COIN
        </div>
        <h1 style="font-size: 2rem; font-weight: 800; color: #0f172a; margin-bottom: 1.5rem;">
            ⚙️ Cấu Hình Hệ Thống Ví Coin
        </h1>

        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo e($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo e($error); ?></div>
        <?php endif; ?>

        <div class="config-container">
            
            <!-- Phần 1: Gói Nạp Coin -->
            <div class="section-card">
                <div class="section-title">
                    <i class="fas fa-box"></i> Quản Lý Gói Nạp (Coin Tiers)
                </div>
                <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 1rem;">
                    Các gói nạp này sẽ hiển thị ở Phiếu Tính Tiền để Lễ tân chọn nhanh thay vì nhập thủ công.
                </p>

                <!-- Form Thêm mới -->
                <div style="background: #f8fafc; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px dashed #cbd5e1;">
                    <form method="POST" style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; align-items: end;">
                        <input type="hidden" name="action" value="add_tier">
                        <div style="grid-column: span 2;">
                            <label class="form-label" style="font-size: 0.8rem;">Tên Gói (VD: Gói Khởi Động)</label>
                            <input type="text" name="name" class="form-input" required>
                        </div>
                        <div>
                            <label class="form-label" style="font-size: 0.8rem;">Giá Tiền (VNĐ)</label>
                            <input type="number" name="price" class="form-input" required min="0">
                        </div>
                        <div>
                            <label class="form-label" style="font-size: 0.8rem;">Số Coin Nhận Được</label>
                            <input type="number" step="0.1" name="coins_amount" class="form-input" required min="0">
                        </div>
                        <div style="grid-column: span 2;">
                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                <i class="fas fa-plus"></i> Thêm Gói Mới
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Danh sách Gói -->
                <table class="table" style="width: 100%;">
                    <thead>
                        <tr style="text-align: left; background: #f1f5f9;">
                            <th style="padding: 0.5rem; font-size: 0.8rem;">Tên Gói</th>
                            <th style="padding: 0.5rem; font-size: 0.8rem;">Giá</th>
                            <th style="padding: 0.5rem; font-size: 0.8rem;">Coins</th>
                            <th style="padding: 0.5rem; font-size: 0.8rem;">Trạng thái</th>
                            <th style="padding: 0.5rem; font-size: 0.8rem; text-align: center;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tiers as $t): ?>
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 0.5rem; font-weight: 700; font-size: 0.85rem;"><?php echo e($t['name']); ?></td>
                                <td style="padding: 0.5rem; color: #10b981; font-weight: 700; font-size: 0.85rem;"><?php echo format_money($t['price']); ?></td>
                                <td style="padding: 0.5rem; color: #d97706; font-weight: 700; font-size: 0.85rem;">
                                    <i class="fas fa-coins"></i> <?php echo (float)$t['coins_amount']; ?>
                                </td>
                                <td style="padding: 0.5rem;">
                                    <?php if ($t['status'] === 'active'): ?>
                                        <span style="background: #dcfce7; color: #166534; font-size: 0.7rem; padding: 2px 6px; border-radius: 4px;">Hoạt động</span>
                                    <?php else: ?>
                                        <span style="background: #fee2e2; color: #991b1b; font-size: 0.7rem; padding: 2px 6px; border-radius: 4px;">Tạm ẩn</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 0.5rem; text-align: center;">
                                    <a href="#" onclick="editTier(<?php echo htmlspecialchars(json_encode($t)); ?>); return false;" style="color: #64748b; font-size: 0.85rem;"><i class="fas fa-edit"></i> Sửa</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($tiers)): ?>
                            <tr><td colspan="5" style="text-align: center; padding: 1rem; color: #94a3b8;">Chưa có cấu hình gói nào.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Phần 2: Cấu hình giá Coin cho Dịch vụ -->
            <div class="section-card">
                <div class="section-title">
                    <i class="fas fa-stethoscope"></i> Cấu Hình Giá Coin Dịch Vụ
                </div>
                <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 1rem;">
                    Quy định mỗi lần sử dụng dịch vụ sẽ bị trừ bao nhiêu Coin trong Ví của khách.
                </p>

                <form method="POST">
                    <input type="hidden" name="action" value="update_services">
                    <div style="max-height: 500px; overflow-y: auto; padding-right: 10px; margin-bottom: 1rem; border: 1px solid #e2e8f0; border-radius: 8px;">
                        <table class="table" style="width: 100%;">
                            <thead style="position: sticky; top: 0; background: #f8fafc; z-index: 1;">
                                <tr style="text-align: left;">
                                    <th style="padding: 0.75rem; border-bottom: 1px solid #cbd5e1;">Tên Dịch Vụ</th>
                                    <th style="padding: 0.75rem; border-bottom: 1px solid #cbd5e1; width: 150px;">Số Coin Trừ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($services as $s): ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 0.75rem; font-weight: 600; color: #1e293b; font-size: 0.9rem;">
                                            <?php echo e($s['name']); ?>
                                        </td>
                                        <td style="padding: 0.5rem;">
                                            <input type="number" step="0.5" name="service_coins[<?php echo $s['id']; ?>]" value="<?php echo (float)$s['coin_cost']; ?>" class="form-input" style="padding: 0.3rem 0.5rem; font-weight: 700; color: #d97706; text-align: center;" min="0">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-save"></i> Lưu Cấu Hình Giá Dịch Vụ
                    </button>
                </form>
            </div>

        </div>
    </div>
</div>

<!-- Modal Sửa Gói -->
<div id="editTierModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: white; padding: 2rem; border-radius: 16px; width: 400px; max-width: 90%;">
        <h3 style="margin-top: 0; font-weight: 800;"><i class="fas fa-edit"></i> Sửa Gói Nạp</h3>
        <form method="POST">
            <input type="hidden" name="action" value="edit_tier">
            <input type="hidden" name="tier_id" id="edit_tier_id">
            
            <div style="margin-bottom: 1rem;">
                <label class="form-label">Tên Gói</label>
                <input type="text" name="name" id="edit_name" class="form-input" required>
            </div>
            
            <div style="margin-bottom: 1rem;">
                <label class="form-label">Giá Tiền (VNĐ)</label>
                <input type="number" name="price" id="edit_price" class="form-input" required>
            </div>
            
            <div style="margin-bottom: 1rem;">
                <label class="form-label">Số Coin</label>
                <input type="number" step="0.1" name="coins_amount" id="edit_coins" class="form-input" required>
            </div>
            
            <div style="margin-bottom: 1rem;">
                <label class="form-label">Thứ tự hiển thị</label>
                <input type="number" name="display_order" id="edit_order" class="form-input" value="0">
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label class="form-label">Trạng thái</label>
                <select name="status" id="edit_status" class="form-input">
                    <option value="active">Hoạt động</option>
                    <option value="inactive">Tạm ẩn</option>
                </select>
            </div>

            <div style="display: flex; gap: 1rem;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">Lưu Thay Đổi</button>
                <button type="button" class="btn" style="flex: 1; background: #e2e8f0;" onclick="document.getElementById('editTierModal').style.display='none'">Hủy</button>
            </div>
        </form>
    </div>
</div>

<script>
function editTier(tier) {
    document.getElementById('edit_tier_id').value = tier.id;
    document.getElementById('edit_name').value = tier.name;
    document.getElementById('edit_price').value = tier.price;
    document.getElementById('edit_coins').value = tier.coins_amount;
    document.getElementById('edit_order').value = tier.display_order;
    document.getElementById('edit_status').value = tier.status;
    document.getElementById('editTierModal').style.display = 'flex';
}
</script>

<?php require_once '../../templates/footer.php'; ?>
