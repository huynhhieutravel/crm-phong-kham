<?php
// modules/inventory/index.php
require_once '../../includes/db.php';
$page_title = 'Quản lý Kho vật tư & Hàng hóa';
$current_page = 'inventory';
require_once '../../templates/header.php';

$db = getDB();
$items = $db->query("SELECT * FROM inventory_items ORDER BY name ASC")->fetchAll();
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <h3 style="margin: 0;">Danh mục Vật tư / Hàng hóa</h3>
        <a href="add_item.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Thêm vật tư mới
        </a>
    </div>

    <table class="table" style="width: 100%;">
        <thead>
            <tr style="text-align: left; border-bottom: 2px solid var(--border-color);">
                <th style="padding: 1rem;">Tên mặt hàng</th>
                <th style="padding: 1rem;">Đơn vị</th>
                <th style="padding: 1rem;">Tồn kho</th>
                <th style="padding: 1rem;">Giá nhập</th>
                <th style="padding: 1rem;">Giá bán</th>
                <th style="padding: 1rem;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $i): ?>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 1rem;"><strong><?php echo e($i['name']); ?></strong></td>
                    <td style="padding: 1rem;"><?php echo e($i['unit']); ?></td>
                    <td style="padding: 1rem;">
                        <span style="font-weight: 700; color: <?php echo $i['stock_quantity'] <= $i['min_stock'] ? '#ef4444' : 'inherit'; ?>;">
                            <?php echo (float)$i['stock_quantity']; ?>
                        </span>
                    </td>
                    <td style="padding: 1rem;"><?php echo format_money($i['base_price']); ?></td>
                    <td style="padding: 1rem;"><?php echo format_money($i['sell_price']); ?></td>
                    <td style="padding: 1rem;">
                        <a href="restock.php?id=<?php echo $i['id']; ?>" class="btn btn-sm" title="Nhập hàng" style="background: #f1f5f9;"><i class="fas fa-plus-circle"></i></a>
                        <a href="sell.php?id=<?php echo $i['id']; ?>" class="btn btn-sm" title="Bán lẻ" style="background: #f1f5f9;"><i class="fas fa-shopping-bag"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($items)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-muted);">Chưa có sản phẩm nào trong kho.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../templates/footer.php'; ?>
