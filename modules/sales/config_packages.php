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
            set_flash('Đã xoá/ẩn gói thành công!');
        } catch (PDOException $e) {
            set_flash(__('common.db_error') . $e->getMessage(), 'error');
        }
    } elseif (!empty($_POST['package_id'])) {
        // Fetch old name to sync retail service
        $stmt_old = $db->prepare("SELECT name FROM packages WHERE id = ?");
        $stmt_old->execute([$_POST['package_id']]);
        $old_pkg = $stmt_old->fetch();

        $stmt = $db->prepare("UPDATE packages SET name = ?, total_sessions = ?, total_price = ? WHERE id = ?");
        $stmt->execute([
            $_POST['name'],
            $_POST['total_sessions'],
            $_POST['total_price'],
            $_POST['package_id']
        ]);
        

        
        set_flash(__('sales.config.update_success'));
    } else {
        $stmt = $db->prepare("INSERT INTO packages (name, total_sessions, total_price) VALUES (?, ?, ?)");
        $stmt->execute([
            $_POST['name'],
            $_POST['total_sessions'],
            $_POST['total_price']
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

$packages = $db->query("SELECT p.* FROM packages p WHERE p.status = 'active' OR p.status IS NULL ORDER BY p.id DESC")->fetchAll();
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

                    <th style="padding: 0.75rem; width: 80px; text-align: right;"><?php echo __('common.action'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($packages as $pkg): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9; <?php echo ($edit_package && $edit_package['id'] == $pkg['id']) ? 'background: #f8fafc;' : ''; ?>">
                        <td style="padding: 0.75rem;"><strong><?php echo e($pkg['name']); ?></strong></td>
                        <td style="padding: 0.75rem;"><?php echo $pkg['total_sessions']; ?></td>
                        <td style="padding: 0.75rem;"><?php echo format_money($pkg['total_price']); ?></td>

                        <td style="padding: 0.75rem; text-align: right; display: flex; gap: 0.5rem; justify-content: flex-end;">
                            <a href="config_packages.php?edit_id=<?php echo $pkg['id']; ?>" class="btn btn-sm" style="background: <?php echo ($edit_package && $edit_package['id'] == $pkg['id']) ? '#c7d2fe; color: #4338ca' : '#e0e7ff; color: #4f46e5'; ?>; padding: 0.35rem 0.6rem; font-size: 0.75rem;">
                                <i class="fas fa-edit"></i> <?php echo ($edit_package && $edit_package['id'] == $pkg['id']) ? __('common.editing') : __('common.edit'); ?>
                            </a>
                            <button type="button" onclick="confirmAndPost('config_packages.php', {delete_id: <?php echo $pkg['id']; ?>}, '<?php echo __('sales.config.confirm_delete'); ?>')" class="btn btn-sm" style="background: #fee2e2; color: #ef4444; padding: 0.35rem 0.6rem; font-size: 0.75rem; border:none; cursor:pointer;">
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
