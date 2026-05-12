<?php
// modules/inventory/index.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('view_inventory');
$page_title = __('inventory.index.page_title');
$current_page = 'inventory';
require_once '../../templates/header.php';

$db = getDB();
$items = $db->query("SELECT * FROM inventory_items ORDER BY name ASC")->fetchAll();
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <h3 style="margin: 0;"><?php echo __('inventory.index.list_title'); ?></h3>
        <a href="add_item.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> <?php echo __('inventory.index.add_new'); ?>
        </a>
    </div>

    <table class="table" style="width: 100%;">
        <thead>
            <tr style="text-align: left; border-bottom: 2px solid var(--border-color); text-transform: uppercase;">
                <th style="padding: 1rem;"><?php echo __('inventory.index.item_name'); ?></th>
                <th style="padding: 1rem;"><?php echo __('inventory.index.unit'); ?></th>
                <th style="padding: 1rem;"><?php echo __('inventory.index.stock'); ?></th>
                <th style="padding: 1rem;"><?php echo __('inventory.index.import_price'); ?></th>
                <th style="padding: 1rem;"><?php echo __('inventory.index.sell_price'); ?></th>
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
                        <a href="restock.php?id=<?php echo $i['id']; ?>" class="btn btn-sm" title="<?php echo __('inventory.index.restock'); ?>" style="background: #f1f5f9;"><i class="fas fa-plus-circle"></i></a>
                        <a href="sell.php?id=<?php echo $i['id']; ?>" class="btn btn-sm" title="<?php echo __('inventory.index.sell_retail'); ?>" style="background: #f1f5f9;"><i class="fas fa-shopping-bag"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($items)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-muted);"><?php echo __('inventory.index.no_data'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../templates/footer.php'; ?>
