<?php
// modules/sales/add_package.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_sales');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get package info
    $stmt = $db->prepare("SELECT * FROM packages WHERE id = ?");
    $stmt->execute([$_POST['package_id']]);
    $pkg = $stmt->fetch();
    
    $stmt = $db->prepare("
        INSERT INTO patient_packages (patient_id, package_id, total_amount, paid_amount, sessions_remaining)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $_POST['patient_id'],
        $_POST['package_id'],
        $pkg['total_price'],
        $_POST['paid_amount'],
        $pkg['total_sessions']
    ]);
    
    // Log as income transaction
    $pp_id = $db->lastInsertId();
    $stmt = $db->prepare("
        INSERT INTO transactions (type, category, amount, reference_id, description, branch_id, created_by)
        VALUES ('income', 'package', ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $_POST['paid_amount'],
        $pp_id,
        "Khách mua gói: " . $pkg['name'],
        $_SESSION['branch_id'] ?? 1,
        $_SESSION['user_id']
    ]);

    set_flash('Bán gói thành công!');
    redirect('index.php');
}

$page_title = 'Bán gói dịch vụ';
$current_page = 'sales';
require_once '../../templates/header.php';

$patients = $db->query("SELECT id, full_name, phone FROM patients ORDER BY full_name ASC")->fetchAll();
$packages = $db->query("SELECT * FROM packages ORDER BY name ASC")->fetchAll();
?>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <form method="POST">
        <div class="form-group">
            <label class="form-label">Chọn khách hàng (Chủ gói)</label>
            <select name="patient_id" class="form-input" required>
                <option value="">-- Chọn khách hàng --</option>
                <?php foreach ($patients as $p): ?>
                    <option value="<?php echo $p['id']; ?>"><?php echo e($p['full_name']); ?> (<?php echo e($p['phone']); ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label">Chọn gói dịch vụ</label>
            <select name="package_id" class="form-input" required id="package_select">
                <option value="">-- Chọn gói --</option>
                <?php foreach ($packages as $pkg): ?>
                    <option value="<?php echo $pkg['id']; ?>" data-price="<?php echo $pkg['total_price']; ?>">
                        <?php echo e($pkg['name']); ?> - <?php echo format_money($pkg['total_price']); ?> (<?php echo $pkg['total_sessions']; ?> buổi)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Số tiền khách đã thanh toán</label>
            <input type="number" name="paid_amount" class="form-input" required id="paid_amount">
        </div>
        
        <script>
            document.getElementById('package_select').addEventListener('change', function() {
                const selected = this.options[this.selectedIndex];
                const price = selected.getAttribute('data-price');
                if (price) {
                    document.getElementById('paid_amount').value = price;
                }
            });
        </script>
        
        <div style="margin-top: 2rem; display: flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary" style="width: 100%;">Hoàn tất thanh toán & Kích hoạt gói</button>
        </div>
    </form>
</div>

<?php require_once '../../templates/footer.php'; ?>
