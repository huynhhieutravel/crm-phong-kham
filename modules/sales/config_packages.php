<?php
// modules/sales/config_packages.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_sales');
$page_title = __('sales.config.page_title');
$current_page = 'sales';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (isset($_POST['delete_id'])) {
        try {
            $stmt = $db->prepare("UPDATE packages SET status = 'hidden' WHERE id = ?");
            $stmt->execute([$_POST['delete_id']]);
            set_flash('Đã ẩn gói thành công!');
        } catch (PDOException $e) {
            set_flash(__('common.db_error') . $e->getMessage(), 'error');
        }
    } elseif (isset($_POST['hard_delete_id'])) {
        try {
            $stmt = $db->prepare("DELETE FROM packages WHERE id = ?");
            $stmt->execute([$_POST['hard_delete_id']]);
            set_flash('Đã xoá vĩnh viễn gói thành công!');
        } catch (PDOException $e) {
            if ($e->getCode() == 23000 || $e->getCode() == 1451) {
                set_flash('Không thể xoá vĩnh viễn vì gói này đã được bệnh nhân mua. Vui lòng sử dụng tính năng Ẩn.', 'error');
            } else {
                set_flash(__('common.db_error') . $e->getMessage(), 'error');
            }
        }
    } elseif (!empty($_POST['package_id'])) {
        // Fetch old name to sync retail service
        $stmt_old = $db->prepare("SELECT name FROM packages WHERE id = ?");
        $stmt_old->execute([$_POST['package_id']]);
        $old_pkg = $stmt_old->fetch();

        $stmt = $db->prepare("UPDATE packages SET name = ?, total_sessions = ?, total_price = ?, coin_cost = ? WHERE id = ?");
        $stmt->execute([
            $_POST['name'],
            $_POST['total_sessions'],
            $_POST['total_price'],
            $_POST['coin_cost'],
            $_POST['package_id']
        ]);
        

        
        set_flash(__('sales.config.update_success'));
    } else {
        $stmt = $db->prepare("INSERT INTO packages (name, total_sessions, total_price, coin_cost) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_POST['name'],
            $_POST['total_sessions'],
            $_POST['total_price'],
            $_POST['coin_cost']
        ]);
        set_flash(__('sales.config.add_success'));
    }
    header('Location: config_packages.php');
    exit;
}

$edit_package = null;
if (isset($_GET['edit_id'])) {
    $stmt = $db->prepare("SELECT * FROM packages WHERE id = ?");
    $stmt->execute([$_GET['edit_id']]);
    $edit_package = $stmt->fetch();
}


require_once '../../templates/header.php';

$packages = $db->query("
    SELECT pkg.*, 
           COUNT(pp.id) as active_patients, 
           SUM(pp.sessions_remaining) as total_sessions_left
    FROM packages pkg
    LEFT JOIN patient_packages pp ON pkg.id = pp.package_id AND pp.sessions_remaining > 0 AND pp.status = 'active'
    WHERE pkg.status = 'active' OR pkg.status IS NULL
    GROUP BY pkg.id
    ORDER BY pkg.id DESC
")->fetchAll();
?>


<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem;">
    <div class="card">
        <h3 style="margin-bottom: 1.5rem;"><?php echo $edit_package ? __('sales.config.edit_package') : __('sales.config.add_package'); ?></h3>
        <form method="POST" action="config_packages.php">
            <?php echo csrf_field(); ?>
            <?php if ($edit_package): ?>
                <input type="hidden" name="package_id" value="<?php echo $edit_package['id']; ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label class="form-label"><?php echo __('sales.config.package_name'); ?></label>
                <input type="text" name="name" class="form-input" required placeholder="Gói Chiropractic 10 buổi" value="<?php echo $edit_package ? e($edit_package['name']) : ''; ?>">
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo __('sales.config.total_sessions'); ?></label>
                <input type="number" name="total_sessions" class="form-input" required placeholder="10" value="<?php echo $edit_package ? $edit_package['total_sessions'] : ''; ?>">
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo __('sales.config.total_price'); ?></label>
                <input type="text" id="total_price_display" class="form-input" required placeholder="5.000.000" value="<?php echo $edit_package ? number_format(round($edit_package['total_price']), 0, ',', '.') : ''; ?>">
                <input type="hidden" name="total_price" id="total_price_real" value="<?php echo $edit_package ? round($edit_package['total_price']) : ''; ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Quy đổi Coin (Số Coin tương đương 1 buổi của gói này)</label>
                <input type="number" step="0.5" name="coin_cost" class="form-input" required placeholder="Ví dụ: 1.5 hoặc 3" value="<?php echo $edit_package ? (isset($edit_package['coin_cost']) ? $edit_package['coin_cost'] : '2') : '2'; ?>">
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                <i class="fas <?php echo $edit_package ? 'fa-save' : 'fa-plus'; ?>"></i> <?php echo $edit_package ? __('sales.config.update_btn') : __('sales.config.save_btn'); ?>
            </button>
            
            <?php if ($edit_package): ?>
                <a href="config_packages.php" class="btn" style="width: 100%; margin-top: 0.5rem; text-align: center; display: block; box-sizing: border-box; text-decoration: none; background: #f1f5f9; color: #475569;">
                    <?php echo __('sales.config.cancel_edit'); ?>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <h3 style="margin-bottom: 1.5rem;"><?php echo __('sales.config.list_title'); ?></h3>
        <table class="table" style="width: 100%;">
            <thead>
                <tr style="text-align: left; border-bottom: 1px solid var(--border-color); text-transform: uppercase;">
                    <th style="padding: 0.75rem;"><?php echo __('sales.config.package_name'); ?></th>
                    <th style="padding: 0.75rem;"><?php echo __('sales.config.total_sessions'); ?></th>
                    <th style="padding: 0.75rem;"><?php echo __('sales.config.total_price'); ?></th>
                    <th style="padding: 0.75rem;">Quy đổi Coin/Buổi</th>
                    <th style="padding: 0.75rem;">Đang sử dụng</th>
                    <th style="padding: 0.75rem; text-align: center;">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($packages as $pkg): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9; <?php echo ($edit_package && $edit_package['id'] == $pkg['id']) ? 'background: #f8fafc;' : ''; ?>">
                        <td style="padding: 0.75rem;"><strong><?php echo e($pkg['name']); ?></strong></td>
                        <td style="padding: 0.75rem;"><?php echo $pkg['total_sessions']; ?></td>
                        <td style="padding: 0.75rem;"><?php echo format_money($pkg['total_price']); ?></td>
                        <td style="padding: 0.75rem;"><span style="color: #d97706; font-weight: 700;"><i class="fas fa-coins"></i> <?php echo isset($pkg['coin_cost']) ? $pkg['coin_cost'] : 2; ?> Coins</span></td>
                        <td style="padding: 0.75rem;">
                            <?php if ($pkg['active_patients'] > 0): ?>
                                <span style="background: #e0f2fe; color: #0284c7; padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.8rem; font-weight: 700;">
                                    <i class="fas fa-users"></i> <?php echo $pkg['active_patients']; ?> người (<?php echo $pkg['total_sessions_left']; ?> buổi)
                                </span>
                            <?php else: ?>
                                <span style="background: #f1f5f9; color: #64748b; padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.8rem; font-weight: 700;">
                                    Không có
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 0.75rem; text-align: center; white-space: nowrap;">
                            <a href="config_packages.php?edit_id=<?php echo $pkg['id']; ?>" class="btn btn-sm" style="background: <?php echo ($edit_package && $edit_package['id'] == $pkg['id']) ? '#c7d2fe; color: #4338ca' : '#e0e7ff; color: #4f46e5'; ?>; padding: 0.35rem 0.6rem; font-size: 0.75rem;" title="Sửa">
                                <i class="fas fa-edit"></i> <?php echo ($edit_package && $edit_package['id'] == $pkg['id']) ? __('common.editing') : __('common.edit'); ?>
                            </a>
                            <button type="button" onclick="confirmAndPost('config_packages.php', {delete_id: <?php echo $pkg['id']; ?>}, 'Bạn có chắc chắn muốn ẨN gói này?')" class="btn btn-sm" style="background: #fef3c7; color: #d97706; padding: 0.35rem 0.6rem; font-size: 0.75rem; border:none; cursor:pointer;" title="Ẩn gói">
                                <i class="fas fa-eye-slash"></i>
                            </button>
                            <button type="button" onclick="confirmAndPost('config_packages.php', {hard_delete_id: <?php echo $pkg['id']; ?>}, 'Bạn có chắc chắn muốn XOÁ VĨNH VIỄN gói này không? Thao tác không thể hoàn tác!')" class="btn btn-sm" style="background: #fee2e2; color: #ef4444; padding: 0.35rem 0.6rem; font-size: 0.75rem; border:none; cursor:pointer;" title="Xoá vĩnh viễn">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const displayInput = document.getElementById('total_price_display');
    const realInput = document.getElementById('total_price_real');

    if (displayInput && realInput) {
        displayInput.addEventListener('input', function(e) {
            // Lọc bỏ tất cả ký tự không phải là số
            let val = this.value.replace(/\D/g, '');
            
            // Cập nhật giá trị thực vào thẻ hidden
            realInput.value = val;
            
            // Hiển thị lại số có dấu chấm ngăn cách 3 số
            if (val !== '') {
                this.value = parseInt(val, 10).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            } else {
                this.value = '';
            }
        });
    }
});
</script>

<?php require_once '../../templates/footer.php'; ?>
