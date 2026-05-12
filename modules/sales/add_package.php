<?php
// modules/sales/add_package.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_sales');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $db->beginTransaction();
    try {
        if (empty($_POST['patient_id']) || empty($_POST['package_id'])) {
            throw new Exception("Vui lòng chọn Hệ Bệnh Nhân và Hợp Đồng Gói Dịch Vụ.");
        }

        // GUARD 1: Idempotency Token
        $token = $_POST['idempotency_token'] ?? '';
        if (!$token) {
            throw new Exception("Yêu cầu không hợp lệ (Missing Access Token).");
        }
        if (isset($_SESSION['last_package_token']) && $_SESSION['last_package_token'] === $token) {
            $db->rollBack();
            set_flash('Đã phát hiện click đúp! Giao dịch trước đó đã được ghi nhận an toàn.', 'warning');
            redirect('add_package.php');
        }

        // Get package info
        $stmt = $db->prepare("SELECT * FROM packages WHERE id = ?");
        $stmt->execute([$_POST['package_id']]);
        $pkg = $stmt->fetch();
        if (!$pkg) {
            throw new Exception("Gói dịch vụ không tồn tại.");
        }
        
        $paid = (float)$_POST['paid_amount'];
        $discount = isset($_POST['discount_amount']) ? (float)$_POST['discount_amount'] : 0;
        $discount_note = $_POST['discount_note'] ?? '';
        $method = $_POST['payment_method'] ?? 'cash';

        $final_total = $pkg['total_price'] - $discount;
        if ($final_total < 0) $final_total = 0;

        // GUARD 2: Logic Constraint
        if ($paid < 0) {
            throw new Exception("Số tiền thanh toán không được nhỏ hơn 0.");
        }
        
        $stmt = $db->prepare("
            INSERT INTO patient_packages (patient_id, package_id, total_amount, discount_amount, discount_note, paid_amount, sessions_remaining)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['patient_id'],
            $_POST['package_id'],
            $final_total,
            $discount,
            $discount_note,
            $paid,
            $pkg['total_sessions']
        ]);
        
        $pp_id = $db->lastInsertId();
        
        // Log transaction with method mapped
        $method_names = [
            'cash' => 'Tiền mặt', 
            'transfer_personal' => 'CK Cá nhân', 
            'transfer_company' => 'TK Công ty', 
            'card' => 'Quẹt Thẻ'
        ];
        $method_str = $method_names[$method] ?? 'Khác';
        $desc = "Mua gói: " . $pkg['name'] . " - TT bằng " . $method_str;
        
        $stmt = $db->prepare("
            INSERT INTO transactions (type, category, amount, reference_id, description, branch_id, created_by)
            VALUES ('income', 'package', ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $paid,
            $pp_id,
            $desc,
            $_SESSION['branch_id'] ?? 1,
            $_SESSION['user_id']
        ]);

        $_SESSION['last_package_token'] = $token;
        $db->commit();
        set_flash('Kích hoạt gói dịch vụ thành công!');
        redirect('add_package.php'); // Redirect to self to see log
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Lỗi hệ thống: ' . $e->getMessage();
    }
}


$page_title = 'Bán gói dịch vụ';
$current_page = 'sales';
require_once '../../templates/header.php';

$patients = $db->query("SELECT id, full_name, phone FROM patients ORDER BY full_name ASC")->fetchAll();
$packages = $db->query("SELECT * FROM packages ORDER BY name ASC")->fetchAll();

// Fetch Changelog (Log Hạch Toán)
$recent_sales = $db->query("
    SELECT pp.id, pt.full_name as patient_name, pkg.name as package_name, 
           pp.purchase_date, t.amount as paid, t.description as tx_desc, u.full_name as seller_name,
           pp.discount_amount, pp.total_amount
    FROM patient_packages pp
    JOIN patients pt ON pp.patient_id = pt.id
    JOIN packages pkg ON pp.package_id = pkg.id
    LEFT JOIN transactions t ON t.reference_id = pp.id AND t.category = 'package' AND t.type = 'income'
    LEFT JOIN users u ON t.created_by = u.id
    ORDER BY pp.id DESC
    LIMIT 10
")->fetchAll();
?>

<!-- Select2 requirements -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
/* Select2 Custom Premium Styling */
.select2-container--default .select2-selection--single {
    height: 48px; border: 1px solid #e2e8f0; border-radius: 12px;
    display: flex; align-items: center; padding: 0 1rem;
    font-size: 0.95rem; font-weight: 500; font-family: 'Inter', sans-serif;
    color: #1e293b; background: #fff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: all 0.2s;
}
.select2-container--default .select2-selection--single:focus,
.select2-container--default.select2-container--open .select2-selection--single {
    border-color: #6366f1; outline: none; box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 48px; right: 10px;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    padding-left: 0; color: #1e293b;
}
.select2-dropdown {
    border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); overflow: hidden;
}
.select2-search__field {
    border-radius: 8px !important; border: 1px solid #e2e8f0 !important; padding: 10px 15px !important;
}

/* Layout */
.pos-wrapper {
    display: grid; grid-template-columns: 1.5fr 1fr; gap: 2rem; max-width: 1200px; margin: 0 auto; padding-bottom: 2rem;
}
@media (max-width: 900px) { .pos-wrapper { grid-template-columns: 1fr; } }

/* Cards */
.pos-card {
    background: white; border-radius: 20px; box-shadow: 0 10px 30px -10px rgba(0,0,0,0.05);
    padding: 2rem; border: 1px solid #f1f5f9;
}
.pos-heading {
    font-size: 1.25rem; font-weight: 800; color: #1e293b; margin-top: 0; margin-bottom: 1.5rem;
    display: flex; align-items: center; gap: 0.75rem;
}

/* Payment Method Radios */
.method-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.5rem; margin-bottom: 1.5rem; }
.method-card {
    border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem; cursor: pointer;
    transition: all 0.2s; text-align: center; position: relative; background: #fff;
}
.method-card input[type="radio"] { position: absolute; opacity: 0; pointer-events: none; }
.method-card:hover { border-color: #6366f1; background: #f8fafc; }
.method-card.selected { border-color: #6366f1; background: #eef2ff; color: #4f46e5; box-shadow: 0 4px 12px rgba(99,102,241,0.1); }
.method-card i { font-size: 1.5rem; margin-bottom: 0.5rem; display: block; }
.method-card span { font-size: 0.8rem; font-weight: 700; display: block; }

/* Bill Summary */
.bill-row { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px dashed #e2e8f0; }
.bill-row:last-child { border-bottom: none; }
.bill-label { color: #64748b; font-weight: 600; font-size: 0.9rem; }
.bill-val { color: #1e293b; font-weight: 800; font-size: 1rem; }
.bill-total-row { background: #f8fafc; border-radius: 12px; padding: 1rem; margin: 1rem 0; display: flex; justify-content: space-between; align-items: center; }
.bill-total-row .bill-label { font-size: 1.1rem; color: #0f172a; text-transform: uppercase; }
.bill-total-row .bill-val { font-size: 1.5rem; color: #4f46e5; }
</style>

<?php if (isset($error)): ?>
    <div class="alert alert-danger" style="max-width: 1200px; margin: 0 auto 1rem auto; border-radius: 12px;">
        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
    </div>
<?php endif; ?>

<form method="POST" id="checkout-form" class="pos-wrapper">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="idempotency_token" value="<?php echo bin2hex(random_bytes(16)); ?>">

    <!-- Lfet Column: Selection -->
    <div class="pos-card">
        <h2 class="pos-heading"><i class="fas fa-user-tag" style="color: #6366f1;"></i> Khởi tạo Hợp đồng Bán Gói</h2>
        
        <div class="form-group" style="margin-bottom: 2rem;">
            <label class="form-label" style="font-weight: 700; color: #334155; margin-bottom: 0.75rem;">1. Tìm & Chọn Bệnh Nhân (Chủ gói)</label>
            <select name="patient_id" class="select2-search" required data-placeholder="-- Gõ Tên hoặc SĐT để tìm --">
                <option value=""></option>
                <?php foreach ($patients as $p): ?>
                    <option value="<?php echo $p['id']; ?>">
                        <?php echo e($p['full_name']); ?> - <?php echo e($p['phone']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group" style="margin-bottom: 2rem;">
            <label class="form-label" style="font-weight: 700; color: #334155; margin-bottom: 0.75rem;">2. Chọn Gói Dịch Vụ Cần Bán</label>
            <select name="package_id" class="select2-search" required id="package_select" data-placeholder="-- Gõ Tên gói để tìm --">
                <option value=""></option>
                <?php foreach ($packages as $pkg): ?>
                    <option value="<?php echo $pkg['id']; ?>" 
                            data-price="<?php echo $pkg['total_price']; ?>"
                            data-name="<?php echo e($pkg['name']); ?>"
                            data-sessions="<?php echo $pkg['total_sessions']; ?>">
                        <?php echo e($pkg['name']); ?> — <?php echo format_money($pkg['total_price']); ?> (<?php echo $pkg['total_sessions']; ?> buổi)
                    </option>
                <?php endforeach; ?>
            </select>
            
            <!-- Virtual Package Card Preview -->
            <div id="pkg-preview" style="display: none; margin-top: 1.5rem; background: linear-gradient(135deg, #1e293b, #0f172a); border-radius: 16px; padding: 1.5rem; color: white; position: relative; overflow: hidden; box-shadow: 0 10px 20px rgba(0,0,0,0.15);">
                <i class="fas fa-gem" style="position: absolute; right: -20px; bottom: -20px; font-size: 8rem; opacity: 0.1; transform: rotate(-15deg);"></i>
                <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 2px; color: #94a3b8; font-weight: 700; margin-bottom: 0.5rem;">Cấp Thẻ V.I.P</div>
                <div id="pv-name" style="font-size: 1.4rem; font-weight: 800; margin-bottom: 1.5rem; line-height: 1.2;">Name</div>
                <div style="display: flex; justify-content: space-between; align-items: flex-end;">
                    <div>
                        <div style="font-size: 0.8rem; color: #94a3b8; margin-bottom: 0.25rem;">Định mức sử dụng</div>
                        <div style="font-size: 1.1rem; font-weight: 700; color: #10b981;" id="pv-sessions">0 buổi</div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 0.8rem; color: #94a3b8; margin-bottom: 0.25rem;">Đơn giá Hợp đồng</div>
                        <div style="font-size: 1.2rem; font-weight: 800; color: #e2e8f0;" id="pv-price">0 đ</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Bill & Checkout -->
    <div class="pos-card" style="background: #fafaf9; border: 1px solid #e5e5e5;">
        <h2 class="pos-heading"><i class="fas fa-receipt" style="color: #f59e0b;"></i> Hóa đơn thanh toán</h2>
        
        <div style="background: white; border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
            <div class="bill-row">
                <span class="bill-label">Tên Gói:</span>
                <span class="bill-val" id="b-name">--</span>
            </div>
            <div class="bill-row">
                <span class="bill-label">Số buổi:</span>
                <span class="bill-val" id="b-sessions">0</span>
            </div>
            <div class="bill-row" id="discount-row" style="display: none; color: #ef4444;">
                <span class="bill-label" style="color: #ef4444;">Chiết khấu:</span>
                <span class="bill-val" id="b-discount" style="color: #ef4444;">0 đ</span>
            </div>
            <div class="bill-total-row">
                <span class="bill-label" style="font-weight: 800;">Tổng Tiền:</span>
                <span class="bill-val" id="b-price" style="font-weight: 900;">0 đ</span>
            </div>
            
            <div style="margin-top: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                <div class="form-group" style="margin: 0;">
                    <label class="form-label" style="font-weight: 700; color: #1e293b;">Chiết khấu (VNĐ)</label>
                    <div style="position: relative;">
                        <input type="text" id="discount_amount_display" class="form-input" style="font-weight: 700; color: #ef4444;" value="0">
                        <input type="hidden" name="discount_amount" id="discount_amount" value="0">
                        <span style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); font-weight: 700; color: #94a3b8;">đ</span>
                    </div>
                </div>
                <div class="form-group" style="margin: 0;">
                    <label class="form-label" style="font-weight: 700; color: #1e293b;">Lý do chiết khấu</label>
                    <input type="text" name="discount_note" class="form-input" placeholder="VD: Khách quen, Sinh nhật...">
                </div>
            </div>
            
            <div class="form-group" style="margin-top: 1.5rem;">
                <label class="form-label" style="font-weight: 700; color: #1e293b;">Số tiền Khách trả (VNĐ)</label>
                <div style="position: relative;">
                    <input type="text" id="paid_amount_display" class="form-input" style="font-size: 1.25rem; font-weight: 800; padding: 1rem; color: #0f172a; border-color: #6366f1; background: #eef2ff;" required value="0">
                    <input type="hidden" name="paid_amount" id="paid_amount" value="0">
                    <span style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); font-weight: 700; color: #64748b;">đ</span>
                </div>
                <div style="margin-top: 0.75rem; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 0.85rem; font-weight: 700; color: #dc2626;">Dư Nợ (Sẽ ghi nợ):</span>
                    <span id="b-debt" style="font-size: 1.1rem; font-weight: 900; color: #dc2626;">0 đ</span>
                </div>
            </div>
        </div>

        <label class="form-label" style="font-weight: 700; color: #1e293b; margin-bottom: 0.75rem; display: block;">Hình thức thanh toán</label>
        <div class="method-grid">
            <label class="method-card selected">
                <input type="radio" name="payment_method" value="cash" checked onchange="updateMethodUI(this)">
                <i class="fas fa-money-bill-wave" style="color: #10b981;"></i>
                <span>Tiền mặt</span>
            </label>
            <label class="method-card">
                <input type="radio" name="payment_method" value="transfer_personal" onchange="updateMethodUI(this)">
                <i class="fas fa-university" style="color: #3b82f6;"></i>
                <span>CK Cá nhân</span>
            </label>
            <label class="method-card">
                <input type="radio" name="payment_method" value="transfer_company" onchange="updateMethodUI(this)">
                <i class="fas fa-building" style="color: #8b5cf6;"></i>
                <span>TK Công ty</span>
            </label>
            <label class="method-card">
                <input type="radio" name="payment_method" value="card" onchange="updateMethodUI(this)">
                <i class="fas fa-credit-card" style="color: #f59e0b;"></i>
                <span>Quẹt thẻ</span>
            </label>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; height: 56px; font-size: 1.1rem; font-weight: 800; border-radius: 14px; background: linear-gradient(135deg, #4f46e5, #6366f1); border: none; box-shadow: 0 10px 25px rgba(99,102,241,0.3); transition: transform 0.2s;">
            <i class="fas fa-check-circle"></i> Xác nhận & Thu tiền
        </button>
    </div>

</form>

<!-- Changelog / Audit Table -->
<div class="pos-card" style="max-width: 1200px; margin: 0 auto 3rem auto;">
    <h3 style="margin-top: 0; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem; color: #1e293b; font-size: 1.2rem;">
        <i class="fas fa-history" style="color: #64748b;"></i> Changelog / Lịch sử bán gói (Audit)
        <span style="margin-left: auto; font-size: 0.8rem; background: #e0e7ff; color: #4338ca; padding: 0.25rem 0.75rem; border-radius: 20px; font-weight: 600;">10 giao dịch gần nhất</span>
    </h3>
    <table class="table" style="width: 100%; margin: 0;">
        <thead>
            <tr style="text-align: left; background: #f8fafc;">
                <th style="padding: 0.75rem 1rem;">Thời gian</th>
                <th style="padding: 0.75rem 1rem;">Người bán (Created By)</th>
                <th style="padding: 0.75rem 1rem;">Khách hàng</th>
                <th style="padding: 0.75rem 1rem;">Gói dịch vụ</th>
                <th style="padding: 0.75rem 1rem; text-align: right;">Giá trị HĐ / Chiết khấu</th>
                <th style="padding: 0.75rem 1rem; text-align: right;">Đã thu / Dư nợ</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($recent_sales)): ?>
                <tr><td colspan="6" style="text-align: center; padding: 2rem; color: #94a3b8;">Chưa có dữ liệu</td></tr>
            <?php else: ?>
                <?php foreach($recent_sales as $rs): 
                    $debt = $rs['total_amount'] - $rs['paid'];
                    if ($debt < 0) $debt = 0;
                ?>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 0.75rem 1rem; color: #64748b; font-size: 0.9rem;">
                        <?php echo date('d/m/Y H:i', strtotime($rs['purchase_date'])); ?>
                    </td>
                    <td style="padding: 0.75rem 1rem;">
                        <span style="font-weight: 700; color: #0f172a;"><i class="fas fa-user-circle" style="color: #cbd5e1;"></i> <?php echo e($rs['seller_name'] ?: 'System'); ?></span>
                    </td>
                    <td style="padding: 0.75rem 1rem;">
                        <strong><?php echo e($rs['patient_name']); ?></strong>
                    </td>
                    <td style="padding: 0.75rem 1rem;">
                        <span style="background: #f1f5f9; padding: 0.25rem 0.5rem; border-radius: 6px; font-size: 0.85rem; color: #334155;">
                            <?php echo e($rs['package_name']); ?>
                        </span>
                        <?php if($rs['tx_desc']): ?>
                            <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem;"><?php echo e($rs['tx_desc']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 0.75rem 1rem; text-align: right;">
                        <div style="font-weight: 700; color: #0f172a; margin-bottom: 0.25rem;"><?php echo format_money($rs['total_amount']); ?></div>
                        <?php if ($rs['discount_amount'] > 0): ?>
                            <div style="font-size: 0.75rem; color: #ef4444;">CK: -<?php echo format_money($rs['discount_amount']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 0.75rem 1rem; text-align: right;">
                        <div style="font-weight: 800; color: #10b981; margin-bottom: 0.25rem;"><?php echo format_money($rs['paid']); ?></div>
                        <?php if ($debt > 0): ?>
                            <div style="font-size: 0.75rem; color: #dc2626; font-weight: 600;">Nợ: <?php echo format_money($debt); ?></div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
// Format Currency helper
const formatVND = (num) => new Intl.NumberFormat('vi-VN').format(num) + ' đ';
const formatRawVND = (num) => new Intl.NumberFormat('vi-VN').format(num);

// ... (existing js setup)
$(document).ready(function() {
    $('.select2-search').select2({
        width: '100%',
        language: {
            noResults: () => "Không tìm thấy kết quả"
        }
    });
});

let pkgPrice = 0;

// Handle Package Selection
$('#package_select').on('change', function() {
    const selected = $(this).find(':selected');
    if(!selected.val()) {
        $('#pkg-preview').slideUp();
        $('#b-name').text('--');
        $('#b-sessions').text('0');
        $('#b-price').text('0 đ');
        $('#discount-row').hide();
        $('#discount_amount_display').val(0);
        $('#discount_amount').val(0);
        $('#paid_amount_display').val(0);
        $('#paid_amount').val(0);
        pkgPrice = 0;
        updateCalculations();
        return;
    }
    
    const price = selected.data('price') || 0;
    const name = selected.data('name');
    const sessions = selected.data('sessions');
    
    pkgPrice = parseFloat(price);
    
    // Update Virtual Card
    $('#pv-name').text(name);
    $('#pv-sessions').text(sessions + ' buổi');
    $('#pv-price').text(formatVND(pkgPrice));
    $('#pkg-preview').slideDown();
    
    // Update Bill Summary
    $('#b-name').text(name.substring(0, 20) + (name.length>20?'...':''));
    $('#b-sessions').text(sessions);
    $('#b-price').text(formatVND(pkgPrice));
    
    // Auto fill paid amount full
    $('#paid_amount_display').val(formatRawVND(pkgPrice));
    $('#paid_amount').val(pkgPrice);
    updateCalculations();
});

// Format debt input on typing
$('#paid_amount_display').on('input', function() {
    let rawStr = $(this).val().replace(/[^\d]/g, '');
    if (!rawStr) rawStr = '0';
    let numericVal = parseInt(rawStr, 10);
    
    $(this).val(formatRawVND(numericVal));
    $('#paid_amount').val(numericVal);
    
    updateCalculations();
});

// Format discount input on typing
$('#discount_amount_display').on('input', function() {
    let rawStr = $(this).val().replace(/[^\d]/g, '');
    if (!rawStr) rawStr = '0';
    let numericVal = parseInt(rawStr, 10);
    
    $(this).val(formatRawVND(numericVal));
    $('#discount_amount').val(numericVal);
    
    updateCalculations();
});

function updateCalculations() {
    let discountVal = $('#discount_amount').val();
    let discount = parseFloat(discountVal) || 0;
    if (discount < 0) { discount = 0; }
    
    if (discount > 0) {
        $('#discount-row').show();
        $('#b-discount').text('- ' + formatVND(discount));
    } else {
        $('#discount-row').hide();
    }
    
    let finalPrice = pkgPrice - discount;
    if (finalPrice < 0) {
        finalPrice = 0;
    }
    $('#b-price').text(formatVND(finalPrice));

    let paidVal = $('#paid_amount').val();
    let paid = parseFloat(paidVal) || 0;
    if (paid < 0) { paid = 0; }
    
    // Auto-cap paid amount if it exceeds the new final price (e.g. after adding discount)
    if (paid > finalPrice && finalPrice > 0) {
        paid = finalPrice;
        $('#paid_amount_display').val(formatRawVND(paid));
        $('#paid_amount').val(paid);
    }
    
    let debt = finalPrice - paid;
    if (debt < 0) debt = 0;
    
    $('#b-debt').text(formatVND(debt));
}

// Payment Method UI Toggle
function updateMethodUI(radio) {
    $('.method-card').removeClass('selected');
    $(radio).closest('.method-card').addClass('selected');
}
</script>

<?php require_once '../../templates/footer.php'; ?>
