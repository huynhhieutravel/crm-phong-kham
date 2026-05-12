<?php
// modules/billing/config_products.php — CRUD Sản phẩm bán lẻ
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_billing');
$page_title = __('billing.products.page_title');
$current_page = 'billing';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (isset($_POST['delete_id'])) {
        try {
            $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$_POST['delete_id']]);
            set_flash(__('billing.products.delete_success'));
        } catch (PDOException $e) {
            set_flash(__('common.db_error') . $e->getMessage(), 'error');
        }
    } elseif (!empty($_POST['product_id'])) {
        $stmt = $db->prepare("UPDATE products SET name = ?, price = ?, category = ?, status = ? WHERE id = ?");
        $stmt->execute([
            $_POST['name'],
            $_POST['price'],
            $_POST['category'],
            $_POST['status'] ?? 'active',
            $_POST['product_id']
        ]);
        set_flash(__('billing.products.update_success'));
    } else {
        $stmt = $db->prepare("INSERT INTO products (name, price, category) VALUES (?, ?, ?)");
        $stmt->execute([
            $_POST['name'],
            $_POST['price'],
            $_POST['category'] ?? 'general'
        ]);
        set_flash(__('billing.products.add_success'));
    }
    header('Location: config_products.php');
    exit;
}

$edit_product = null;
if (isset($_GET['edit_id'])) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$_GET['edit_id']]);
    $edit_product = $stmt->fetch();
}



require_once '../../templates/header.php';

$products = $db->query("SELECT * FROM products ORDER BY id DESC")->fetchAll();
$categories = [
    'san_pham_chung' => __('billing.category.general'),
    'san_pham_vat_ly' => __('billing.category.physical_therapy'),
    'thuoc' => __('billing.category.medicine')
];
?>

<style>
.prod-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem; }
@media (max-width: 900px) { .prod-grid { grid-template-columns: 1fr; } }
.prod-form-card {
    background: white; border-radius: 20px; padding: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.04); border: 1px solid #f1f5f9;
}
.prod-form-card h3 { margin: 0 0 1.5rem 0; font-weight: 800; color: #1e293b; display: flex; align-items: center; gap: 0.5rem; }
.cat-badge {
    font-size: 0.7rem; font-weight: 700; padding: 0.2rem 0.5rem;
    border-radius: 6px; background: #f1f5f9; color: #64748b;
}
</style>

<div class="prod-grid">
    <div class="prod-form-card">
        <h3>
            <i class="fas <?php echo $edit_product ? 'fa-edit' : 'fa-plus-circle'; ?>" style="color: var(--primary);"></i>
            <?php echo $edit_product ? __('billing.products.edit_product') : __('billing.products.add_product'); ?>
        </h3>
        <form method="POST" action="config_products.php">
            <?php echo csrf_field(); ?>
            <?php if ($edit_product): ?>
                <input type="hidden" name="product_id" value="<?php echo $edit_product['id']; ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label class="form-label" style="font-weight: 700;"><?php echo __('billing.products.product_name'); ?></label>
                <input type="text" name="name" class="form-input" required placeholder="Đai lưng cao cấp" value="<?php echo $edit_product ? e($edit_product['name']) : ''; ?>">
            </div>
            <div class="form-group">
                <label class="form-label" style="font-weight: 700;"><?php echo __('billing.products.price'); ?></label>
                <input type="text" id="price_display" class="form-input" required placeholder="300.000" value="<?php echo $edit_product ? number_format(round($edit_product['price']), 0, ',', '.') : ''; ?>">
                <input type="hidden" name="price" id="price_real" value="<?php echo $edit_product ? round($edit_product['price']) : ''; ?>">
            </div>
            <div class="form-group">
                <label class="form-label" style="font-weight: 700;"><?php echo __('billing.products.category'); ?></label>
                <select name="category" class="form-input">
                    <?php foreach ($categories as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo ($edit_product && $edit_product['category'] === $key) ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($edit_product): ?>
            <div class="form-group">
                <label class="form-label" style="font-weight: 700;"><?php echo __('billing.products.status'); ?></label>
                <select name="status" class="form-input">
                    <option value="active" <?php echo $edit_product['status'] === 'active' ? 'selected' : ''; ?>><?php echo __('billing.products.status_active'); ?></option>
                    <option value="inactive" <?php echo $edit_product['status'] === 'inactive' ? 'selected' : ''; ?>><?php echo __('billing.products.status_inactive'); ?></option>
                </select>
            </div>
            <?php endif; ?>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem; border-radius: 12px; font-weight: 700; text-transform: uppercase;">
                <i class="fas <?php echo $edit_product ? 'fa-save' : 'fa-plus'; ?>"></i> <?php echo $edit_product ? __('common.update') : __('billing.products.save_btn'); ?>
            </button>
            
            <?php if ($edit_product): ?>
                <a href="config_products.php" class="btn" style="width: 100%; margin-top: 0.5rem; text-align: center; display: block; background: #f1f5f9; color: #475569; border-radius: 12px; font-weight: 600;">
                    <?php echo __('billing.products.cancel_edit'); ?>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <div class="prod-form-card">
        <h3><i class="fas fa-box-open" style="color: #f59e0b;"></i> <?php echo sprintf(__('billing.products.list_title'), count($products)); ?></h3>
        <?php if (empty($products)): ?>
            <div style="text-align: center; padding: 3rem; color: #94a3b8;">
                <i class="fas fa-box-open" style="font-size: 2.5rem; margin-bottom: 1rem;"></i>
                <p><?php echo __('billing.products.no_data'); ?></p>
            </div>
        <?php else: ?>
        <table class="table" style="width: 100%;">
            <thead>
                <tr style="text-align: left; border-bottom: 2px solid var(--border-color); text-transform: uppercase;">
                    <th style="padding: 0.75rem;"><?php echo __('billing.products.name_col'); ?></th>
                    <th style="padding: 0.75rem;"><?php echo __('billing.products.category'); ?></th>
                    <th style="padding: 0.75rem;"><?php echo __('billing.products.price_col'); ?></th>
                    <th style="padding: 0.75rem;"><?php echo __('billing.products.status_col'); ?></th>
                    <th style="padding: 0.75rem; width: 70px; text-align: right;"><?php echo __('common.edit'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9; <?php echo ($edit_product && $edit_product['id'] == $p['id']) ? 'background: #eef2ff;' : ''; ?>">
                        <td style="padding: 0.75rem; font-weight: 700;"><?php echo e($p['name']); ?></td>
                        <td style="padding: 0.75rem;">
                            <span class="cat-badge"><?php echo $categories[$p['category']] ?? $p['category']; ?></span>
                        </td>
                        <td style="padding: 0.75rem; font-weight: 700; color: var(--primary);"><?php echo format_money($p['price']); ?></td>
                        <td style="padding: 0.75rem;">
                            <span style="font-size: 0.7rem; font-weight: 700; padding: 0.15rem 0.4rem; border-radius: 4px; background: <?php echo $p['status'] === 'active' ? '#dcfce7' : '#fee2e2'; ?>; color: <?php echo $p['status'] === 'active' ? '#166534' : '#991b1b'; ?>;">
                                <?php echo $p['status'] === 'active' ? 'ON' : 'OFF'; ?>
                            </span>
                        </td>
                        <td style="padding: 0.75rem; text-align: right; display: flex; gap: 0.5rem; justify-content: flex-end;">
                            <a href="config_products.php?edit_id=<?php echo $p['id']; ?>" class="btn btn-sm" style="background: #e0e7ff; color: #4f46e5; padding: 0.3rem 0.5rem; font-size: 0.75rem; border-radius: 8px;">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button type="button" onclick="confirmAndPost('config_products.php', {delete_id: <?php echo $p['id']; ?>}, '<?php echo __('billing.products.confirm_delete'); ?>')" class="btn btn-sm" style="background: #fee2e2; color: #ef4444; padding: 0.3rem 0.5rem; font-size: 0.75rem; border-radius: 8px; border:none; cursor:pointer;">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    const displayInput = document.getElementById('price_display');
    const realInput = document.getElementById('price_real');

    if (displayInput && realInput) {
        displayInput.addEventListener('input', function(e) {
            let val = this.value.replace(/\D/g, '');
            realInput.value = val;
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
