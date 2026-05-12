<?php
// modules/sales/checkout.php
// SECURITY: Transaction + Idempotency + Row Lock + Audit Log + Payment Status
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_checkout');

$db = getDB();
$treatment_id = isset($_GET['treatment_id']) ? (int)$_GET['treatment_id'] : 0;

// Get treatment info
$stmt = $db->prepare("
    SELECT t.*, p.full_name as patient_name, p.phone as patient_phone, p.id as patient_id,
           s.name as service_name, u.full_name as technician_name
    FROM treatments t
    JOIN patients p ON t.patient_id = p.id
    LEFT JOIN services s ON t.service_id = s.id
    LEFT JOIN users u ON t.technician_id = u.id
    WHERE t.id = ?
");
$stmt->execute([$treatment_id]);
$treatment = $stmt->fetch();

if (!$treatment) {
    set_flash('Không tìm thấy buổi điều trị.', 'error');
    redirect('../medical/daily.php');
}

// ═══════════════════════════════════════════════════════════════
// GUARD #1: IDEMPOTENCY — Block if already paid
// ═══════════════════════════════════════════════════════════════
if ($treatment['payment_status'] === 'paid') {
    set_flash('Buổi điều trị này đã được thanh toán rồi.', 'warning');
    if ($treatment['session_id']) {
        redirect("../medical/session_view.php?id=" . $treatment['session_id']);
    } else {
        redirect("../patients/view.php?id=" . $treatment['patient_id']);
    }
}

$patient_id = $treatment['patient_id'];

// Get available packages (own + shared)
$stmt = $db->prepare("
    SELECT pp.*, pkg.name as package_name, pkg.total_sessions, pkg.total_price,
           owner.full_name as owner_name, owner.id as owner_id
    FROM patient_packages pp
    JOIN packages pkg ON pp.package_id = pkg.id
    JOIN patients owner ON pp.patient_id = owner.id
    WHERE pp.sessions_remaining > 0
      AND pp.status = 'active'
      AND (
          pp.patient_id = ?
          OR pp.id IN (SELECT patient_package_id FROM package_shared_users WHERE patient_id = ?)
      )
");
$stmt->execute([$patient_id, $patient_id]);
$available_packages = $stmt->fetchAll();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $method = $_POST['method'];
    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
    $patient_package_id = !empty($_POST['patient_package_id']) ? (int)$_POST['patient_package_id'] : null;
    $note = isset($_POST['note']) ? trim($_POST['note']) : '';
    $payment_date_input = isset($_POST['payment_date']) && !empty($_POST['payment_date']) ? $_POST['payment_date'] : date('Y-m-d H:i:s');
    $payment_date = date('Y-m-d H:i:s', strtotime($payment_date_input));

    // ═══════════════════════════════════════════════════════════════
    // GUARD #2: TRANSACTION — All-or-nothing
    // ═══════════════════════════════════════════════════════════════
    $db->beginTransaction();
    try {

        // ═══════════════════════════════════════════════════════════
        // GUARD #3: RE-CHECK IDEMPOTENCY inside transaction
        // (prevents race between 2 concurrent POST requests)
        // ═══════════════════════════════════════════════════════════
        $stmt = $db->prepare("SELECT payment_status FROM treatments WHERE id = ? FOR UPDATE");
        $stmt->execute([$treatment_id]);
        $current_status = $stmt->fetchColumn();
        
        if ($current_status === 'paid') {
            $db->rollBack();
            set_flash('Buổi điều trị này đã được thanh toán bởi người khác.', 'warning');
            redirect("../patients/view.php?id=$patient_id");
        }

        $payer_patient_id = null;

        // If paying by package
        if ($method === 'package' && $patient_package_id) {

            // ═══════════════════════════════════════════════════════
            // GUARD #4: ROW LOCK — SELECT FOR UPDATE on package row
            // Prevents sessions_remaining going negative
            // ═══════════════════════════════════════════════════════
            $stmt = $db->prepare("
                SELECT pp.*, pkg.total_price, pkg.total_sessions, pp.patient_id as owner_id
                FROM patient_packages pp 
                JOIN packages pkg ON pp.package_id = pkg.id 
                WHERE pp.id = ? 
                FOR UPDATE
            ");
            $stmt->execute([$patient_package_id]);
            $pkg = $stmt->fetch();
            
            // Double-check remaining > 0 AFTER lock
            if (!$pkg || $pkg['sessions_remaining'] <= 0) {
                $db->rollBack();
                $error = 'Gói đã hết buổi. Vui lòng chọn phương thức khác.';
                goto render_page;
            }

            $amount = $pkg['total_price'] / $pkg['total_sessions']; // Per-session value
            $payer_patient_id = $pkg['owner_id'];

            // Deduct session (safe after FOR UPDATE lock)
            $db->prepare("UPDATE patient_packages SET sessions_remaining = sessions_remaining - 1 WHERE id = ? AND sessions_remaining > 0")
               ->execute([$patient_package_id]);
            
            // Check if exhausted
            $db->prepare("UPDATE patient_packages SET status = 'exhausted' WHERE id = ? AND sessions_remaining <= 0")
               ->execute([$patient_package_id]);

            // Log usage
            $db->prepare("INSERT INTO package_usage_logs (patient_package_id, patient_id, treatment_id, used_at) VALUES (?, ?, ?, ?)")
               ->execute([$patient_package_id, $patient_id, $treatment_id, $payment_date]);
        }

        // Insert payment record with status
        $payment_db_status = 'completed'; // cash/transfer/package are instant
        if ($method === 'debt') $payment_db_status = 'pending';

        $stmt = $db->prepare("
            INSERT INTO payments (treatment_id, patient_id, method, status, amount, patient_package_id, payer_patient_id, note, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $treatment_id, $patient_id, $method, $payment_db_status, $amount,
            $patient_package_id, $payer_patient_id, $note,
            $_SESSION['user_id'], $payment_date
        ]);
        $payment_id = $db->lastInsertId();

        // Update treatment payment status
        $treatment_payment_status = ($method === 'debt') ? 'debt' : 'paid';
        $db->prepare("UPDATE treatments SET payment_status = ? WHERE id = ?")
           ->execute([$treatment_payment_status, $treatment_id]);

        // ═══════════════════════════════════════════════════════════
        // GUARD #5: AUDIT LOG — Track every payment creation
        // ═══════════════════════════════════════════════════════════
        $audit_data = json_encode([
            'payment_id' => $payment_id,
            'treatment_id' => $treatment_id,
            'patient_id' => $patient_id,
            'method' => $method,
            'status' => $payment_db_status,
            'amount' => $amount,
            'patient_package_id' => $patient_package_id,
            'payer_patient_id' => $payer_patient_id,
            'backdated_to' => $payment_date,
        ], JSON_UNESCAPED_UNICODE);

        $db->prepare("
            INSERT INTO payment_audit_logs (payment_id, patient_package_id, action, new_value, performed_by, ip_address)
            VALUES (?, ?, 'create', ?, ?, ?)
        ")->execute([
            $payment_id,
            $patient_package_id,
            $audit_data,
            $_SESSION['user_id'],
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);

        // Log transaction (for financial reports) — only for completed
        if ($method !== 'debt') {
            $category_map = [
                'cash' => 'Tiền mặt', 
                'transfer' => 'Chuyển khoản (Cũ)', 
                'transfer_personal' => 'CK Cá nhân', 
                'transfer_company' => 'TK Công ty', 
                'package' => 'Gói dịch vụ'
            ];
            $db->prepare("
                INSERT INTO transactions (type, category, amount, reference_id, description, branch_id, created_by, created_at)
                VALUES ('income', ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $category_map[$method] ?? $method,
                $amount,
                $treatment_id,
                "TT buổi điều trị #$treatment_id - " . $treatment['patient_name'],
                $_SESSION['branch_id'] ?? 1,
                $_SESSION['user_id'],
                $payment_date
            ]);
        }

        $db->commit();
        set_flash('Thanh toán thành công!');
        
        if ($treatment['session_id']) {
            redirect("../medical/session_view.php?id=" . $treatment['session_id']);
        } else {
            redirect("../patients/view.php?id=$patient_id");
        }
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Lỗi thanh toán: ' . $e->getMessage();
    }
}

render_page:

$page_title = 'Thanh Toán Sau Điều Trị';
$current_page = 'sales';
require_once '../../templates/header.php';

// Calculate per-session price for display
foreach ($available_packages as &$pkg) {
    $pkg['per_session'] = $pkg['total_sessions'] > 0 ? ($pkg['total_price'] / $pkg['total_sessions']) : 0;
}
unset($pkg);
?>

<style>
.checkout-wrapper {
    max-width: 700px; margin: 0 auto;
}
.checkout-header {
    background: linear-gradient(135deg, #0f172a, #1e293b);
    padding: 2rem; border-radius: 20px; color: white; margin-bottom: 2rem;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
}
.checkout-header h1 { font-size: 1.6rem; font-weight: 800; margin: 0 0 1rem 0; }
.checkout-info { display: grid; grid-template-columns: 1fr 1fr; gap: 0.8rem; }
.checkout-info .info-item { font-size: 0.9rem; }
.checkout-info .info-label { opacity: 0.6; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
.checkout-info .info-val { font-weight: 700; font-size: 1rem; margin-top: 0.15rem; }

.method-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem; }
.method-card {
    border: 2px solid #e2e8f0; border-radius: 16px; padding: 1.5rem; cursor: pointer;
    transition: all 0.25s ease; text-align: center; position: relative;
}
.method-card:hover { border-color: var(--primary); background: #f8fafc; transform: translateY(-2px); }
.method-card.selected { border-color: var(--primary); background: #eef2ff; box-shadow: 0 4px 12px rgba(99,102,241,0.15); }
.method-card input[type="radio"] { position: absolute; opacity: 0; }
.method-card .method-icon { font-size: 2rem; margin-bottom: 0.5rem; }
.method-card .method-name { font-weight: 700; font-size: 0.95rem; color: #1e293b; }
.method-card .method-desc { font-size: 0.75rem; color: #64748b; margin-top: 0.25rem; }

.amount-section, .package-section { display: none; margin-bottom: 1.5rem; }
.amount-section.visible, .package-section.visible { display: block; }

.pkg-option {
    border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem; margin-bottom: 0.75rem;
    cursor: pointer; transition: all 0.2s; display: flex; justify-content: space-between; align-items: center;
}
.pkg-option:hover { border-color: var(--primary); background: #f8fafc; }
.pkg-option.selected { border-color: var(--primary); background: #eef2ff; }
.pkg-option input[type="radio"] { display: none; }
.pkg-sessions { font-size: 0.8rem; color: #10b981; font-weight: 700; }
.pkg-owner { font-size: 0.75rem; color: #f59e0b; font-weight: 600; }

.security-badges {
    display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 1rem;
}
.sec-badge {
    font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
    padding: 0.2rem 0.5rem; border-radius: 4px; background: #dcfce7; color: #166534;
}
</style>

<div class="checkout-wrapper">
    <?php if (isset($error)): ?>
        <div class="alert alert-error" style="margin-bottom: 1rem; padding: 1rem; background: #fef2f2; color: #991b1b; border-radius: 12px; font-weight: 600;">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="checkout-header">
        <h1><i class="fas fa-cash-register"></i> Thanh Toán Sau Điều Trị</h1>
        <div class="checkout-info">
            <div class="info-item">
                <div class="info-label">Bệnh nhân</div>
                <div class="info-val"><?php echo e($treatment['patient_name']); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Điện thoại</div>
                <div class="info-val"><?php echo e($treatment['patient_phone']); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Dịch vụ</div>
                <div class="info-val"><?php echo e($treatment['service_name'] ?: 'Điều trị chung'); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">KTV thực hiện</div>
                <div class="info-val"><?php echo e($treatment['technician_name']); ?></div>
            </div>
        </div>
    </div>

    <div class="card" style="border-radius: 20px; padding: 2rem;">
        <h3 style="margin: 0 0 0.75rem 0; font-weight: 800; color: #1e293b;">Chọn phương thức thanh toán</h3>
        
        <!-- Security indicators -->
        <div class="security-badges">
            <span class="sec-badge"><i class="fas fa-shield-alt"></i> Transaction</span>
            <span class="sec-badge"><i class="fas fa-lock"></i> Idempotent</span>
            <span class="sec-badge"><i class="fas fa-database"></i> Row Lock</span>
            <span class="sec-badge"><i class="fas fa-clipboard-list"></i> Audit Log</span>
        </div>

        <form method="POST" id="checkoutForm" onsubmit="return handleSubmit(this);">
            <?php echo csrf_field(); ?>
            <div class="method-grid">
                <label class="method-card" onclick="selectMethod('cash')">
                    <input type="radio" name="method" value="cash" required>
                    <div class="method-icon">💵</div>
                    <div class="method-name">Tiền mặt</div>
                    <div class="method-desc">Thanh toán trực tiếp</div>
                </label>
                <label class="method-card" onclick="selectMethod('transfer_personal')">
                    <input type="radio" name="method" value="transfer_personal">
                    <div class="method-icon">🏦</div>
                    <div class="method-name">CK Cá nhân</div>
                    <div class="method-desc">Cá nhân (Quản lý)</div>
                </label>
                <label class="method-card" onclick="selectMethod('transfer_company')">
                    <input type="radio" name="method" value="transfer_company">
                    <div class="method-icon">🏢</div>
                    <div class="method-name">TK Công ty</div>
                    <div class="method-desc">Công ty TNHH</div>
                </label>
                <label class="method-card" onclick="selectMethod('package')">
                    <input type="radio" name="method" value="package">
                    <div class="method-icon">📦</div>
                    <div class="method-name">Thanh toán bằng gói</div>
                    <div class="method-desc">Trừ buổi trong gói</div>
                </label>
                <label class="method-card" onclick="selectMethod('debt')">
                    <input type="radio" name="method" value="debt">
                    <div class="method-icon">📝</div>
                    <div class="method-name">Ghi nợ</div>
                    <div class="method-desc">Thanh toán sau</div>
                </label>
            </div>

            <!-- Amount input for cash/transfer/debt -->
            <div class="amount-section" id="amountSection">
                <div class="form-group">
                    <label class="form-label" style="font-weight: 700;">Số tiền (VNĐ)</label>
                    <input type="number" name="amount" class="form-input" id="amountInput" placeholder="0" min="0" step="1000" style="font-size: 1.2rem; font-weight: 700; padding: 1rem;">
                </div>
            </div>

            <!-- Package selection -->
            <div class="package-section" id="packageSection">
                <label class="form-label" style="font-weight: 700; margin-bottom: 0.75rem; display: block;">Chọn gói để sử dụng</label>
                <?php if (empty($available_packages)): ?>
                    <div style="text-align: center; padding: 2rem; color: #94a3b8; background: #f8fafc; border-radius: 12px;">
                        <i class="fas fa-box-open" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                        <div>Không có gói nào khả dụng</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($available_packages as $pkg): ?>
                        <label class="pkg-option" onclick="this.classList.add('selected'); document.querySelectorAll('.pkg-option').forEach(e => { if(e!==this) e.classList.remove('selected');});">
                            <input type="radio" name="patient_package_id" value="<?php echo $pkg['id']; ?>">
                            <div>
                                <div style="font-weight: 700; color: #1e293b;"><?php echo e($pkg['package_name']); ?></div>
                                <?php if ($pkg['owner_id'] != $patient_id): ?>
                                    <div class="pkg-owner"><i class="fas fa-share-alt"></i> Gói của <?php echo e($pkg['owner_name']); ?></div>
                                <?php endif; ?>
                                <div class="pkg-sessions">Còn <?php echo $pkg['sessions_remaining']; ?> buổi · Quy đổi <?php echo format_money($pkg['per_session']); ?>/buổi</div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 1.1rem; font-weight: 800; color: var(--primary);"><?php echo format_money($pkg['per_session']); ?></div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                <!-- Payment Date (Backdate) -->
                <div class="form-group">
                    <label class="form-label" style="font-weight: 700;">Thời gian thực tế <span style="font-size:0.75rem;color:#64748b;font-weight:normal;">(Tuỳ chọn lùi ngày)</span></label>
                    <?php 
                        $default_payment_date = !empty($treatment['treatment_date']) ? date('Y-m-d\TH:i', strtotime($treatment['treatment_date'])) : date('Y-m-d\TH:i');
                    ?>
                    <input type="datetime-local" name="payment_date" class="form-input" value="<?php echo $default_payment_date; ?>" max="<?php echo date('Y-m-d\TH:i'); ?>">
                </div>

                <!-- Note -->
                <div class="form-group">
                    <label class="form-label" style="font-weight: 700;">Ghi chú <span style="font-size:0.75rem;color:#64748b;font-weight:normal;">(Tuỳ chọn)</span></label>
                    <textarea name="note" class="form-input" rows="1" placeholder="Ghi chú thêm..."></textarea>
                </div>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <a href="<?php echo $treatment['session_id'] ? '../medical/session_view.php?id='.$treatment['session_id'] : '../patients/view.php?id='.$patient_id; ?>" class="btn" style="flex: 1; text-align: center; background: #f1f5f9; color: #64748b; padding: 1rem; border-radius: 14px; font-weight: 700;">
                    <i class="fas fa-arrow-left"></i> Bỏ qua
                </a>
                <button type="submit" id="submitBtn" class="btn btn-primary" style="flex: 2; padding: 1rem; border-radius: 14px; font-weight: 800; font-size: 1rem;">
                    <i class="fas fa-check-circle"></i> Xác Nhận Thanh Toán
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// ═══════════════════════════════════════════════════════════════
// GUARD #2 (Frontend): Disable button after first click
// ═══════════════════════════════════════════════════════════════
let isSubmitting = false;
function handleSubmit(form) {
    if (isSubmitting) {
        return false; // Block double-click
    }
    isSubmitting = true;
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xử lý...';
    btn.style.opacity = '0.6';
    return true;
}

function selectMethod(method) {
    document.querySelectorAll('.method-card').forEach(c => c.classList.remove('selected'));
    event.currentTarget.classList.add('selected');
    
    const amountSection = document.getElementById('amountSection');
    const packageSection = document.getElementById('packageSection');
    const amountInput = document.getElementById('amountInput');
    
    if (method === 'package') {
        amountSection.classList.remove('visible');
        packageSection.classList.add('visible');
        amountInput.removeAttribute('required');
    } else {
        amountSection.classList.add('visible');
        packageSection.classList.remove('visible');
        amountInput.setAttribute('required', 'required');
    }
}
</script>

<?php require_once '../../templates/footer.php'; ?>
