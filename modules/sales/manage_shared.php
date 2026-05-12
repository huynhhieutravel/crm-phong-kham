<?php
// modules/sales/manage_shared.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_sales');

$db = getDB();
$pp_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle add shared user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf();
    if ($_POST['action'] === 'add_user') {
        try {
            if (empty($_POST['patient_id'])) throw new Exception('Vui lòng chọn bệnh nhân hợp lệ.');
            $stmt = $db->prepare("INSERT IGNORE INTO package_shared_users (patient_package_id, patient_id, added_by) VALUES (?, ?, ?)");
            $stmt->execute([$pp_id, $_POST['patient_id'], $_SESSION['user_id']]);
            set_flash('Đã thêm người dùng chung thành công!');
        } catch (Exception $e) {
            set_flash('Lỗi: ' . $e->getMessage(), 'error');
        }
    } elseif ($_POST['action'] === 'remove_user') {
        try {
            $stmt = $db->prepare("DELETE FROM package_shared_users WHERE id = ? AND patient_package_id = ?");
            $stmt->execute([$_POST['share_id'], $pp_id]);
            set_flash('Đã xóa người dùng chung.');
        } catch (Exception $e) {
            set_flash('Lỗi: ' . $e->getMessage(), 'error');
        }
    } elseif ($_POST['action'] === 'delete_package') {
        if ($_POST['confirm_text'] !== 'XOA') {
            set_flash('Xác nhận không hợp lệ.', 'error');
            redirect("manage_shared.php?id=$pp_id");
        }
        $db->beginTransaction();
        try {
            // Fetch package details for audit log before deletion
            $stmt = $db->prepare("SELECT * FROM patient_packages WHERE id = ?");
            $stmt->execute([$pp_id]);
            $old_pkg = $stmt->fetch(PDO::FETCH_ASSOC);

            $db->prepare("DELETE FROM package_shared_users WHERE patient_package_id = ?")->execute([$pp_id]);
            $db->prepare("DELETE FROM package_usage_logs WHERE patient_package_id = ?")->execute([$pp_id]);
            $db->prepare("DELETE FROM package_payments WHERE patient_package_id = ?")->execute([$pp_id]);
            $db->prepare("UPDATE payments SET patient_package_id = NULL WHERE patient_package_id = ?")->execute([$pp_id]);
            // Xoá tất cả transactions liên quan đến gói này (doanh thu mua gói + thu nợ)
            $db->prepare("DELETE FROM transactions WHERE reference_id = ? AND category IN ('package', 'package_debt')")->execute([$pp_id]);
            $db->prepare("DELETE FROM patient_packages WHERE id = ?")->execute([$pp_id]);
            
            // Log the deletion
            if ($old_pkg) {
                log_audit($_SESSION['user_id'], 'delete', 'patient_packages', $pp_id, json_encode($old_pkg, JSON_UNESCAPED_UNICODE), null);
            }
            
            $db->commit();
            set_flash('Đã xóa gói khách hàng và dữ liệu sử dụng.');
            redirect('index.php');
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('Lỗi khi xóa: ' . $e->getMessage(), 'error');
            redirect("manage_shared.php?id=$pp_id");
        }
    } elseif ($_POST['action'] === 'manual_deduct') {
        $raw_used_at = !empty($_POST['used_at']) ? $_POST['used_at'] : 'now';
        $used_at = date('Y-m-d H:i:s', strtotime($raw_used_at));
        $used_by = (int)$_POST['used_by'];
        
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT sessions_remaining FROM patient_packages WHERE id = ? FOR UPDATE");
            $stmt->execute([$pp_id]);
            $remaining = $stmt->fetchColumn();

            if ($remaining > 0) {
                $db->prepare("UPDATE patient_packages SET sessions_remaining = sessions_remaining - 1 WHERE id = ?")->execute([$pp_id]);
                $db->prepare("UPDATE patient_packages SET status = 'exhausted' WHERE id = ? AND sessions_remaining <= 0")->execute([$pp_id]);
                $technician_id = !empty($_POST['technician_id']) ? (int)$_POST['technician_id'] : null;
                $appointment_id = !empty($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : null;
                $db->prepare("INSERT INTO package_usage_logs (patient_package_id, patient_id, treatment_id, appointment_id, used_at, technician_id) VALUES (?, ?, NULL, ?, ?, ?)")->execute([$pp_id, $used_by, $appointment_id, $used_at, $technician_id]);
                $db->commit();
                set_flash('Đã thêm lịch sử sử dụng gói thành công!');
            } else {
                $db->rollBack();
                set_flash('Gói đã hết buổi, không thể trừ thêm.', 'error');
            }
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('Lỗi hệ thống: ' . $e->getMessage(), 'error');
        }
    } elseif ($_POST['action'] === 'edit_manual_deduct') {
        $log_id = (int)$_POST['log_id'];
        $raw_used_at = !empty($_POST['used_at']) ? $_POST['used_at'] : 'now';
        $used_at = date('Y-m-d H:i:s', strtotime($raw_used_at));
        $used_by = (int)$_POST['used_by'];
        $technician_id = !empty($_POST['technician_id']) ? (int)$_POST['technician_id'] : null;
        $appointment_id = !empty($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : null;
        try {
            $db->prepare("UPDATE package_usage_logs SET used_at = ?, patient_id = ?, technician_id = ?, appointment_id = ? WHERE id = ? AND treatment_id IS NULL AND patient_package_id = ?")->execute([$used_at, $used_by, $technician_id, $appointment_id, $log_id, $pp_id]);
            set_flash('Đã cập nhật thời gian sử dụng gói thành công!');
        } catch (Exception $e) {
            set_flash('Lỗi khi cập nhật thời gian: ' . $e->getMessage(), 'error');
        }
    } elseif ($_POST['action'] === 'delete_manual_deduct') {
        $log_id = (int)$_POST['log_id'];
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("DELETE FROM package_usage_logs WHERE id = ? AND treatment_id IS NULL AND patient_package_id = ?");
            $stmt->execute([$log_id, $pp_id]);
            if ($stmt->rowCount() > 0) {
                $db->prepare("UPDATE patient_packages SET sessions_remaining = sessions_remaining + 1 WHERE id = ?")->execute([$pp_id]);
                $db->prepare("UPDATE patient_packages SET status = 'active' WHERE id = ? AND sessions_remaining > 0 AND status = 'exhausted'")->execute([$pp_id]);
                $db->commit();
                set_flash('Đã xoá lịch sử và hoàn lại 1 buổi thành công!');
            } else {
                $db->rollBack();
                set_flash('Không thể xoá lịch sử này.', 'error');
            }
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('Lỗi khi xoá lịch sử: ' . $e->getMessage(), 'error');
        }
    } elseif ($_POST['action'] === 'collect_debt') {
        $collect_amount = (float)$_POST['collect_amount'];
        $collect_method = $_POST['collect_method'] ?? 'cash';
        $collect_note = trim($_POST['collect_note'] ?? '');
        $collect_date = !empty($_POST['collect_date']) ? $_POST['collect_date'] : date('Y-m-d H:i:s');
        
        $db->beginTransaction();
        try {
            // Lấy thông tin gói mới nhất
            $stmt = $db->prepare("SELECT pp.total_amount, pp.paid_amount, pkg.name as package_name FROM patient_packages pp JOIN packages pkg ON pp.package_id = pkg.id WHERE pp.id = ? FOR UPDATE");
            $stmt->execute([$pp_id]);
            $pkg_info = $stmt->fetch();
            
            $debt = $pkg_info['total_amount'] - $pkg_info['paid_amount'];
            if ($collect_amount <= 0) throw new Exception('Số tiền phải lớn hơn 0.');
            if ($collect_amount > $debt) throw new Exception('Số tiền thu vượt quá công nợ (' . format_money($debt) . ').');
            
            // Insert vào package_payments
            $db->prepare("INSERT INTO package_payments (patient_package_id, amount, payment_method, note, created_by, paid_at) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$pp_id, $collect_amount, $collect_method, $collect_note ?: 'Thu nợ', $_SESSION['user_id'], $collect_date]);
            
            // Cập nhật paid_amount
            $db->prepare("UPDATE patient_packages SET paid_amount = paid_amount + ? WHERE id = ?")
                ->execute([$collect_amount, $pp_id]);
            
            // Log transaction
            $method_names = ['cash' => 'Tiền mặt', 'transfer_personal' => 'CK Cá nhân', 'transfer_company' => 'TK Công ty', 'card' => 'Quẹt Thẻ'];
            $method_str = $method_names[$collect_method] ?? 'Khác';
            $desc = "Thu nợ gói: " . $pkg_info['package_name'] . " - " . $method_str;
            
            $db->prepare("INSERT INTO transactions (type, category, amount, reference_id, description, branch_id, created_by) VALUES ('income', 'package_debt', ?, ?, ?, ?, ?)")
                ->execute([$collect_amount, $pp_id, $desc, $_SESSION['branch_id'] ?? 1, $_SESSION['user_id']]);
            
            $db->commit();
            set_flash('Thu nợ thành công: ' . format_money($collect_amount));
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('Lỗi: ' . $e->getMessage(), 'error');
        }
    } elseif ($_POST['action'] === 'delete_payment') {
        $payment_id = (int)$_POST['payment_id'];
        $db->beginTransaction();
        try {
            // Lấy thông tin thanh toán
            $stmt = $db->prepare("SELECT * FROM package_payments WHERE id = ? AND patient_package_id = ? FOR UPDATE");
            $stmt->execute([$payment_id, $pp_id]);
            $payment = $stmt->fetch();
            if (!$payment) throw new Exception('Không tìm thấy giao dịch thanh toán.');

            $del_amount = (float)$payment['amount'];

            // Xoá thanh toán
            $db->prepare("DELETE FROM package_payments WHERE id = ?")->execute([$payment_id]);

            // Trừ ngược lại số tiền đã thu trong gói
            $db->prepare("UPDATE patient_packages SET paid_amount = paid_amount - ? WHERE id = ?")->execute([$del_amount, $pp_id]);

            // Xóa giao dịch trong bảng transactions có cùng số tiền (LIMIT 1 để chỉ xóa đúng 1 giao dịch)
            $db->prepare("DELETE FROM transactions WHERE reference_id = ? AND amount = ? AND category IN ('package', 'package_debt') LIMIT 1")->execute([$pp_id, $del_amount]);

            $db->commit();
            set_flash('Đã xóa khoản thanh toán và cập nhật lại công nợ.');
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('Lỗi khi xóa: ' . $e->getMessage(), 'error');
        }
    }
    redirect("manage_shared.php?id=$pp_id");
}

// Get package info
$stmt = $db->prepare("
    SELECT pp.*, pkg.name as package_name, pkg.total_sessions, pkg.total_price,
           pt.full_name as owner_name, pt.phone as owner_phone
    FROM patient_packages pp
    JOIN packages pkg ON pp.package_id = pkg.id
    JOIN patients pt ON pp.patient_id = pt.id
    WHERE pp.id = ?
");
$stmt->execute([$pp_id]);
$package = $stmt->fetch();

if (!$package) {
    set_flash('Không tìm thấy gói.', 'error');
    redirect('index.php');
}

// Get shared users
$stmt = $db->prepare("
    SELECT psu.*, p.full_name, p.phone, u.full_name as added_by_name
    FROM package_shared_users psu
    JOIN patients p ON psu.patient_id = p.id
    LEFT JOIN users u ON psu.added_by = u.id
    WHERE psu.patient_package_id = ?
    ORDER BY psu.added_at DESC
");
$stmt->execute([$pp_id]);
$shared_users = $stmt->fetchAll();

// Get usage history
$stmt = $db->prepare("
    SELECT pul.*, p.full_name as used_by_name, t.treatment_date, t.session_data,
           u.full_name as technician_name, pay.method as payment_method, pay.amount as payment_amount,
           a.id as appt_id
    FROM package_usage_logs pul
    JOIN patients p ON pul.patient_id = p.id
    LEFT JOIN treatments t ON pul.treatment_id = t.id
    LEFT JOIN appointments a ON pul.appointment_id = a.id
    LEFT JOIN users u ON COALESCE(t.technician_id, pul.technician_id) = u.id
    LEFT JOIN payments pay ON pay.treatment_id = t.id AND pay.patient_package_id = pul.patient_package_id
    WHERE pul.patient_package_id = ?
    ORDER BY pul.used_at DESC
");
$stmt->execute([$pp_id]);
$usage_logs = $stmt->fetchAll();

// Get all patients for the add dropdown
$patients = $db->query("SELECT id, full_name, phone FROM patients ORDER BY full_name ASC")->fetchAll();

try {
    $role_cols = $db->query("SHOW COLUMNS FROM roles")->fetchAll(PDO::FETCH_COLUMN);
    $role_label_col = in_array('display_name', $role_cols) ? 'display_name' : 'name';
    $technicians = $db->query("
        SELECT u.id, u.full_name, r.$role_label_col as role_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE r.name IN ('doctor', 'technician') AND u.status = 'active'
        ORDER BY u.full_name ASC
    ")->fetchAll();
} catch (Exception $e) {
    $technicians = [];
}

// Lấy lịch hẹn gần đây của các bệnh nhân trong gói này
$all_pids = [$package['patient_id']];
foreach ($shared_users as $su) {
    $all_pids[] = $su['patient_id'];
}
$placeholders = str_repeat('?,', count($all_pids) - 1) . '?';
$stmtAppts = $db->prepare("SELECT a.id, a.appointment_date, a.doctor_id, p.full_name as patient_name FROM appointments a JOIN patients p ON a.patient_id = p.id WHERE a.patient_id IN ($placeholders) AND a.status != 'cancelled' ORDER BY a.appointment_date DESC LIMIT 30");
$stmtAppts->execute($all_pids);
$recent_appts = $stmtAppts->fetchAll();

// Lấy lịch sử thanh toán
$payment_history = [];
try {
    $stmtPay = $db->prepare("
        SELECT pp.*, u.full_name as collector_name 
        FROM package_payments pp 
        LEFT JOIN users u ON pp.created_by = u.id 
        WHERE pp.patient_package_id = ? 
        ORDER BY pp.paid_at ASC
    ");
    $stmtPay->execute([$pp_id]);
    $payment_history = $stmtPay->fetchAll();
} catch (Exception $e) {
    // Bảng chưa tồn tại → bỏ qua
}

// Tính công nợ
$total_paid = (float)$package['paid_amount'];
$total_amount = (float)$package['total_amount'];
$debt_amount = max(0, $total_amount - $total_paid);
$has_debt = $debt_amount > 0;

// Tính số ngày nợ: từ ngày mua đến hôm nay (nếu còn nợ)
$debt_days = 0;
if ($has_debt) {
    $purchase_dt = new DateTime($package['purchase_date']);
    $now_dt = new DateTime();
    $debt_days = $now_dt->diff($purchase_dt)->days;
}

$page_title = 'Quản Lý Gói: ' . $package['package_name'];
$current_page = 'sales';
require_once '../../templates/header.php';

$per_session = $package['total_sessions'] > 0 ? ($package['total_price'] / $package['total_sessions']) : 0;
// Lấy số liệu thực tế từ lịch sử sử dụng
$sessions_used = count($usage_logs);
$expected_remaining = max(0, $package['total_sessions'] - $sessions_used);

// Tự động đồng bộ số buổi còn lại nếu bị lệch (do đổi thông số gói cha hoặc sửa/xoá lỗi)
if ($package['sessions_remaining'] != $expected_remaining) {
    $db->prepare("UPDATE patient_packages SET sessions_remaining = ? WHERE id = ?")->execute([$expected_remaining, $pp_id]);
    $package['sessions_remaining'] = $expected_remaining;
}

// Đồng bộ cả trạng thái một cách độc lập
if ($expected_remaining > 0 && $package['status'] === 'exhausted') {
    $db->prepare("UPDATE patient_packages SET status = 'active' WHERE id = ?")->execute([$pp_id]);
    $package['status'] = 'active';
} elseif ($expected_remaining <= 0 && $package['status'] === 'active') {
    $db->prepare("UPDATE patient_packages SET status = 'exhausted' WHERE id = ?")->execute([$pp_id]);
    $package['status'] = 'exhausted';
}
?>

<style>
.pkg-hero {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    padding: 2rem; border-radius: 20px; color: white; margin-bottom: 2rem;
    box-shadow: 0 10px 30px rgba(99,102,241,0.3);
}
.pkg-hero h1 { font-size: 1.5rem; font-weight: 800; margin: 0 0 1rem 0; }
.pkg-stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 1rem; }
.pkg-stat { text-align: center; }
.pkg-stat .val { font-size: 1.8rem; font-weight: 800; }
.pkg-stat .lbl { font-size: 0.75rem; opacity: 0.8; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }

.section-card { background: white; border-radius: 20px; padding: 2rem; margin-bottom: 1.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); }
.section-title { font-size: 1.15rem; font-weight: 800; color: #1e293b; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.6rem; }
.section-title i { color: var(--primary); }

.shared-badge {
    display: inline-flex; align-items: center; gap: 0.5rem;
    background: #f1f5f9; padding: 0.5rem 1rem; border-radius: 10px; margin: 0.3rem;
    font-weight: 600; font-size: 0.9rem; color: #334155;
}
.shared-badge .remove-btn {
    color: #ef4444; cursor: pointer; font-size: 0.8rem; margin-left: 0.5rem;
    background: #fee2e2; width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
    border: none; transition: all 0.2s;
}
.shared-badge .remove-btn:hover { background: #fca5a5; }

@media (max-width: 768px) { .pkg-stats { grid-template-columns: repeat(2, 1fr); } }
</style>

<div style="max-width: 900px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <a href="index.php" style="display: inline-flex; align-items: center; gap: 0.5rem; color: var(--primary); font-weight: 700; font-size: 0.9rem; text-decoration: none;">
            <i class="fas fa-arrow-left"></i> Quay lại danh sách gói
        </a>
        <button type="button" onclick="document.getElementById('deletePkgModal').style.display='flex'" class="btn" style="background: #fee2e2; color: #ef4444; border: none; font-size: 0.85rem; padding: 0.5rem 1rem; border-radius: 10px;">
            <i class="fas fa-trash-alt"></i> Xóa toàn bộ gói này
        </button>
    </div>

    <!-- Package Hero -->
    <div class="pkg-hero">
        <h1><i class="fas fa-box-open"></i> <?php echo e($package['package_name']); ?></h1>
        <div style="margin-bottom: 1rem; opacity: 0.9;">
            <i class="fas fa-user"></i> Chủ gói: <strong><?php echo e($package['owner_name']); ?></strong>
            (<?php echo e($package['owner_phone']); ?>)
            · Mua ngày: <?php echo date('d/m/Y', strtotime($package['purchase_date'])); ?>
        </div>
        <div class="pkg-stats">
            <div class="pkg-stat">
                <div class="val"><?php echo $package['total_sessions']; ?></div>
                <div class="lbl">Tổng số buổi</div>
            </div>
            <div class="pkg-stat">
                <div class="val"><?php echo $package['sessions_remaining']; ?></div>
                <div class="lbl">Buổi còn lại</div>
            </div>
            <div class="pkg-stat">
                <div class="val"><?php echo $sessions_used; ?></div>
                <div class="lbl">Đã sử dụng</div>
            </div>
            <div class="pkg-stat">
                <div class="val"><?php echo format_money($per_session); ?></div>
                <div class="lbl">Giá / buổi</div>
            </div>
            <div class="pkg-stat">
                <div class="val" style="color: <?php echo $package['status'] === 'active' ? '#4ade80' : '#fbbf24'; ?>;">
                    <?php echo strtoupper($package['status']); ?>
                </div>
                <div class="lbl">Trạng thái</div>
            </div>
        </div>
    </div>

    <!-- Financial Info Bar -->
    <div class="section-card" style="background: <?php echo $has_debt ? 'linear-gradient(135deg, #fef2f2 0%, #fff1f2 100%)' : 'linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%)'; ?>; border: 1px solid <?php echo $has_debt ? '#fecaca' : '#bbf7d0'; ?>;">
        <div class="section-title"><i class="fas fa-wallet" style="color: <?php echo $has_debt ? '#dc2626' : '#16a34a'; ?>;"></i> Tài chính Hợp đồng</div>
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: <?php echo $has_debt ? '1.5rem' : '0'; ?>;">
            <div style="text-align: center; padding: 1rem; background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.03);">
                <div style="font-size: 0.65rem; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 0.25rem;">Tổng HĐ</div>
                <div style="font-size: 1.1rem; font-weight: 900; color: #0f172a;"><?php echo format_money($total_amount); ?></div>
            </div>
            <div style="text-align: center; padding: 1rem; background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.03);">
                <div style="font-size: 0.65rem; font-weight: 800; color: #16a34a; text-transform: uppercase; margin-bottom: 0.25rem;">Đã thu</div>
                <div style="font-size: 1.1rem; font-weight: 900; color: #15803d;"><?php echo format_money($total_paid); ?></div>
            </div>
            <div style="text-align: center; padding: 1rem; background: <?php echo $has_debt ? '#fef2f2' : 'white'; ?>; border-radius: 12px; border: <?php echo $has_debt ? '2px solid #fecaca' : '1px solid #e2e8f0'; ?>;">
                <div style="font-size: 0.65rem; font-weight: 800; color: #dc2626; text-transform: uppercase; margin-bottom: 0.25rem;">Còn nợ</div>
                <div style="font-size: 1.1rem; font-weight: 900; color: <?php echo $has_debt ? '#dc2626' : '#94a3b8'; ?>;"><?php echo $has_debt ? format_money($debt_amount) : '0₫'; ?></div>
            </div>
            <div style="text-align: center; padding: 1rem; background: <?php echo ($debt_days > 7 && $has_debt) ? '#fef2f2' : 'white'; ?>; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.03);">
                <div style="font-size: 0.65rem; font-weight: 800; color: <?php echo ($debt_days > 7 && $has_debt) ? '#dc2626' : '#64748b'; ?>; text-transform: uppercase; margin-bottom: 0.25rem;">Ngày nợ</div>
                <div style="font-size: 1.1rem; font-weight: 900; color: <?php echo $has_debt ? ($debt_days > 7 ? '#dc2626' : '#f59e0b') : '#94a3b8'; ?>;">
                    <?php echo $has_debt ? $debt_days . ' ngày' : '—'; ?>
                </div>
            </div>
        </div>

        <?php if ($has_debt): ?>
        <button type="button" onclick="document.getElementById('collectDebtModal').style.display='flex'" class="btn" style="width: 100%; height: 48px; font-size: 1rem; font-weight: 800; border-radius: 12px; background: linear-gradient(135deg, #dc2626, #ef4444); color: white; border: none; box-shadow: 0 8px 20px rgba(220,38,38,0.3); display: flex; align-items: center; justify-content: center; gap: 0.5rem; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
            <i class="fas fa-hand-holding-usd"></i> Thu nợ — <?php echo format_money($debt_amount); ?>
        </button>
        <?php endif; ?>

        <?php if (!empty($payment_history)): ?>
        <div style="margin-top: 1.5rem;">
            <div style="font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 0.75rem;"><i class="fas fa-receipt"></i> Lịch sử thanh toán (<?php echo count($payment_history); ?> lần)</div>
            <?php 
            $method_icons = ['cash' => 'fa-money-bill-wave', 'transfer_personal' => 'fa-university', 'transfer_company' => 'fa-building', 'card' => 'fa-credit-card'];
            $method_labels = ['cash' => 'Tiền mặt', 'transfer_personal' => 'CK Cá nhân', 'transfer_company' => 'TK Công ty', 'card' => 'Quẹt thẻ'];
            $running_total = 0;
            foreach ($payment_history as $pi => $pay): 
                $running_total += (float)$pay['amount'];
                $remaining_after = $total_amount - $running_total;
                $is_first = ($pi === 0);
                $m_icon = $method_icons[$pay['payment_method']] ?? 'fa-coins';
                $m_label = $method_labels[$pay['payment_method']] ?? 'Khác';
            ?>
            <div style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.75rem 0; border-bottom: 1px solid <?php echo $has_debt ? '#fde8e8' : '#e2e8f0'; ?>; <?php echo $pi === count($payment_history)-1 ? 'border: none;' : ''; ?>">
                <div style="width: 32px; height: 32px; background: <?php echo $is_first ? '#eef2ff' : '#f0fdf4'; ?>; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <i class="fas <?php echo $m_icon; ?>" style="font-size: 0.7rem; color: <?php echo $is_first ? '#6366f1' : '#16a34a'; ?>;"></i>
                </div>
                <div style="flex: 1; min-width: 0;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-weight: 800; font-size: 0.9rem; color: #15803d;">+<?php echo format_money($pay['amount']); ?></span>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span style="font-size: 0.7rem; color: #94a3b8;"><?php echo date('d/m/Y H:i', strtotime($pay['paid_at'])); ?></span>
                            <form method="POST" style="display: inline; margin: 0;" id="rmPay<?php echo $pay['id']; ?>">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete_payment">
                                <input type="hidden" name="payment_id" value="<?php echo $pay['id']; ?>">
                                <button type="button" title="Xóa thanh toán này" onclick="confirmAndSubmit(document.getElementById('rmPay<?php echo $pay['id']; ?>'), 'Xóa khoản thanh toán này và trừ lại số tiền đã thu?')" style="color: #ef4444; background: none; border: none; cursor: pointer; padding: 0; outline: none; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'"><i class="fas fa-trash-alt"></i></button>
                            </form>
                        </div>
                    </div>
                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.15rem;">
                        <?php echo e($m_label); ?>
                        <?php if ($pay['collector_name']): ?> · <?php echo e($pay['collector_name']); ?><?php endif; ?>
                        <?php if ($pay['note']): ?> — <em><?php echo e($pay['note']); ?></em><?php endif; ?>
                    </div>
                    <?php if ($remaining_after > 0): ?>
                    <div style="font-size: 0.65rem; color: #ef4444; margin-top: 0.1rem;">Còn nợ sau lần này: <?php echo format_money($remaining_after); ?></div>
                    <?php else: ?>
                    <div style="font-size: 0.65rem; color: #16a34a; margin-top: 0.1rem;">✅ Đã thanh toán đủ</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Shared Users Management -->
    <div class="section-card">
        <div class="section-title"><i class="fas fa-users"></i> Người được phép sử dụng gói</div>
        
        <!-- Add user form -->
        <form method="POST" style="display: flex; gap: 0.75rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add_user">
            <select name="patient_id" class="form-input" required style="flex: 1; min-width: 250px;">
                <option value="">-- Chọn bệnh nhân --</option>
                <?php foreach ($patients as $p): ?>
                    <?php if ($p['id'] != $package['patient_id']): // Exclude owner ?>
                        <option value="<?php echo $p['id']; ?>"><?php echo e($p['full_name']); ?> (<?php echo e($p['phone']); ?>)</option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary" style="border-radius: 12px;">
                <i class="fas fa-plus"></i> Thêm người dùng
            </button>
        </form>

        <!-- List of shared users -->
        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
            <?php if (empty($shared_users)): ?>
                <div style="color: #94a3b8; font-style: italic;">Chưa chia sẻ cho ai. Thêm người bên trên để cho phép họ sử dụng gói này.</div>
            <?php endif; ?>
            <?php foreach ($shared_users as $su): ?>
                <div class="shared-badge">
                    <i class="fas fa-user-check" style="color: #10b981;"></i>
                    <?php echo e($su['full_name']); ?>
                    <span style="font-size: 0.75rem; color: #94a3b8;">(<?php echo e($su['phone']); ?>)</span>
                    <form method="POST" style="display: inline; margin: 0;" id="rmShare<?php echo $su['id']; ?>">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="remove_user">
                        <input type="hidden" name="share_id" value="<?php echo $su['id']; ?>">
                        <button type="button" class="remove-btn" title="Xóa" onclick="confirmAndSubmit(document.getElementById('rmShare<?php echo $su['id']; ?>'), 'Xóa quyền sử dụng gói?')"><i class="fas fa-times"></i></button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Usage History -->
    <div class="section-card">
        <div class="section-title" style="display: flex; justify-content: space-between; align-items: center;">
            <div><i class="fas fa-history"></i> Lịch sử sử dụng gói (<?php echo count($usage_logs); ?> lượt)</div>
        </div>
        
        <?php if ($package['sessions_remaining'] > 0): ?>
        <form method="POST" onsubmit="this.querySelector('button[type=submit]').disabled = true; this.querySelector('button[type=submit]').innerHTML = '<i class=\'fas fa-spinner fa-spin\'></i> Đang xử lý...';" style="background: #f8fafc; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; gap: 0.5rem; align-items: flex-end; flex-wrap: wrap;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="manual_deduct">
            <div style="flex: 1; min-width: 200px;">
                <label style="font-size: 0.8rem; font-weight: 700; color: #475569; display: block; margin-bottom: 0.25rem;">Thời gian lùi</label>
                <input type="datetime-local" name="used_at" class="form-input" style="padding: 0.5rem;" value="<?php echo date('Y-m-d\TH:i'); ?>" max="<?php echo date('Y-m-d\TH:i'); ?>" required>
            </div>
            <div style="flex: 1; min-width: 200px;">
                <label style="font-size: 0.8rem; font-weight: 700; color: #475569; display: block; margin-bottom: 0.25rem;">Người sử dụng</label>
                <select name="used_by" class="form-input" style="padding: 0.5rem;" required>
                    <option value="<?php echo $package['patient_id']; ?>"><?php echo e($package['owner_name']); ?> (Chủ sở hữu)</option>
                    <?php foreach ($shared_users as $su): ?>
                        <option value="<?php echo $su['patient_id']; ?>"><?php echo e($su['full_name']); ?> (Dùng chung)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex: 1; min-width: 200px;">
                <label style="font-size: 0.8rem; font-weight: 700; color: #475569; display: block; margin-bottom: 0.25rem;">Liên kết Lịch hẹn</label>
                <select name="appointment_id" id="msApptSelect" class="form-input" style="padding: 0.5rem;" onchange="autoFillTech(this)">
                    <option value="">-- Không liên kết --</option>
                    <?php foreach ($recent_appts as $ra): ?>
                        <option value="<?php echo $ra['id']; ?>" data-doc="<?php echo $ra['doctor_id']; ?>">Hẹn <?php echo date('d/m, H:i', strtotime($ra['appointment_date'])); ?> (<?php echo e(mb_substr($ra['patient_name'], 0, 10)); ?>...)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex: 1; min-width: 200px;">
                <label style="font-size: 0.8rem; font-weight: 700; color: #475569; display: block; margin-bottom: 0.25rem;">KTV / Bác sĩ</label>
                <select name="technician_id" id="msTechSelect" class="form-input" style="padding: 0.5rem;">
                    <option value="">-- Chọn KTV/BS --</option>
                    <?php foreach ($technicians as $tech): ?>
                        <option value="<?php echo $tech['id']; ?>"><?php echo e($tech['full_name']); ?> (<?php echo e($tech['role_name']); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem; border-radius: 8px;">
                <i class="fas fa-minus"></i> Trừ buổi
            </button>
            <script>
            function autoFillTech(sel) {
                var docId = sel.options[sel.selectedIndex].getAttribute('data-doc');
                if (docId) {
                    var techSel = document.getElementById('msTechSelect');
                    if(techSel) techSel.value = docId;
                }
            }
            </script>
        </form>
        <?php endif; ?>
        
        <?php if (empty($usage_logs)): ?>
            <div style="text-align: center; padding: 2rem; color: #94a3b8;">
                <i class="fas fa-clipboard-list" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                <div>Gói chưa được sử dụng lần nào.</div>
            </div>
        <?php else: ?>
            <table class="table" style="width: 100%;">
                <thead>
                    <tr style="text-align: left; border-bottom: 2px solid #e2e8f0;">
                        <th style="padding: 0.75rem;">Thời gian</th>
                        <th style="padding: 0.75rem;">Người sử dụng</th>
                        <th style="padding: 0.75rem;">Căn cứ</th>
                        <th style="padding: 0.75rem;">KTV phụ trách</th>
                        <th style="padding: 0.75rem;">Giá trị</th>
                        <th style="padding: 0.75rem; text-align: right;">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usage_logs as $log): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 0.75rem;">
                                <div style="font-weight: 700; color: #1e293b;"><?php echo date('H:i', strtotime($log['used_at'])); ?></div>
                                <div style="font-size: 0.8rem; color: #64748b;"><?php echo date('d/m/Y', strtotime($log['used_at'])); ?></div>
                            </td>
                            <td style="padding: 0.75rem;">
                                <strong><?php echo e($log['used_by_name']); ?></strong>
                                <?php if ($log['patient_id'] != $package['patient_id']): ?>
                                    <span style="font-size: 0.7rem; background: #fef3c7; color: #92400e; padding: 0.1rem 0.4rem; border-radius: 4px; margin-left: 0.3rem;">Dùng chung</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 0.75rem;">
                                <?php if ($log['appt_id']): ?>
                                    <a href="/modules/appointments/view.php?id=<?php echo $log['appt_id']; ?>" target="_blank" style="font-size: 0.75rem; background: #e0f2fe; color: #0284c7; padding: 0.2rem 0.4rem; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.25rem;"><i class="fas fa-calendar-check"></i> Hẹn #<?php echo $log['appt_id']; ?></a>
                                <?php elseif ($log['treatment_id']): ?>
                                    <a href="/modules/medical/treatments_view.php?id=<?php echo $log['treatment_id']; ?>" target="_blank" style="font-size: 0.75rem; background: #ecfdf5; color: #059669; padding: 0.2rem 0.4rem; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.25rem;"><i class="fas fa-notes-medical"></i> Phiếu #<?php echo $log['treatment_id']; ?></a>
                                <?php else: ?>
                                    <span style="font-size: 0.75rem; color: #94a3b8; font-style: italic;">Thủ công</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 0.75rem;"><?php echo e($log['technician_name'] ?: '-'); ?></td>
                            <td style="padding: 0.75rem; font-weight: 700; color: var(--primary);"><?php echo format_money($per_session); ?></td>
                            <td style="padding: 0.75rem; text-align: right;">
                                <?php if ($log['treatment_id'] === null): ?>
                                    <button type="button" onclick="editManualLog(<?php echo $log['id']; ?>, '<?php echo date('Y-m-d\TH:i', strtotime($log['used_at'])); ?>', <?php echo $log['patient_id']; ?>, <?php echo $log['technician_id'] !== null ? $log['technician_id'] : 'null'; ?>, <?php echo $log['appt_id'] !== null ? $log['appt_id'] : 'null'; ?>)" title="Chỉnh sửa lịch sử" style="color: #3b82f6; background: none; border: none; cursor: pointer; padding: 0.25rem;"><i class="fas fa-edit"></i></button>
                                    <form method="POST" style="display: inline; margin: 0;" id="rmLog<?php echo $log['id']; ?>">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete_manual_deduct">
                                        <input type="hidden" name="log_id" value="<?php echo $log['id']; ?>">
                                        <button type="button" title="Xoá lịch sử" onclick="confirmAndSubmit(document.getElementById('rmLog<?php echo $log['id']; ?>'), 'Xoá lịch sử này và hoàn lại 1 buổi cho gói?')" style="color: #ef4444; background: none; border: none; cursor: pointer; padding: 0.25rem;"><i class="fas fa-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Edit Log -->
<div id="editLogModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: white; border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); width: 100%; max-width: 450px; overflow: hidden; transform: scale(0.95); animation: modalIn 0.2s forwards;">
        <form method="POST">
            <?php echo csrf_field(); ?>
            <div style="padding: 1.5rem 2rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-weight: 800; font-size: 1.25rem; color: #1e293b;"><i class="fas fa-edit" style="color: #3b82f6;"></i> Chỉnh sửa ngày</h3>
                <button type="button" onclick="document.getElementById('editLogModal').style.display='none'" style="background: none; border: none; color: #94a3b8; font-size: 1.2rem; cursor: pointer;"><i class="fas fa-times"></i></button>
            </div>
            <div style="padding: 2rem;">
                <input type="hidden" name="action" value="edit_manual_deduct">
                <input type="hidden" name="log_id" id="editLogId" value="">
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="margin-bottom: 0.5rem; display: block; font-weight: 600;">Chọn thời gian mới</label>
                    <input type="datetime-local" name="used_at" id="editLogUsedAt" class="form-input" max="<?php echo date('Y-m-d\TH:i'); ?>" style="padding: 0.75rem;" required>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="margin-bottom: 0.5rem; display: block; font-weight: 600;">Sửa người sử dụng</label>
                    <select name="used_by" id="editLogUsedBy" class="form-input" style="padding: 0.75rem;" required>
                        <option value="<?php echo $package['patient_id']; ?>"><?php echo e($package['owner_name']); ?> (Chủ sở hữu)</option>
                        <?php foreach ($shared_users as $su): ?>
                            <option value="<?php echo $su['patient_id']; ?>"><?php echo e($su['full_name']); ?> (Dùng chung)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 0; margin-top: 1rem;">
                    <label class="form-label" style="margin-bottom: 0.5rem; display: block; font-weight: 600;">Sửa Căn cứ (Lịch hẹn)</label>
                    <select name="appointment_id" id="editLogApptId" class="form-input" style="padding: 0.75rem;">
                        <option value="">-- Không liên kết --</option>
                        <?php foreach ($recent_appts as $ra): ?>
                            <option value="<?php echo $ra['id']; ?>">Hẹn <?php echo date('d/m, H:i', strtotime($ra['appointment_date'])); ?> (<?php echo e(mb_substr($ra['patient_name'], 0, 10)); ?>...)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 0; margin-top: 1rem;">
                    <label class="form-label" style="margin-bottom: 0.5rem; display: block; font-weight: 600;">Sửa KTV / Bác sĩ</label>
                    <select name="technician_id" id="editLogTechnicianId" class="form-input" style="padding: 0.75rem;">
                        <option value="">-- Không có --</option>
                        <?php foreach ($technicians as $tech): ?>
                            <option value="<?php echo $tech['id']; ?>"><?php echo e($tech['full_name']); ?> (<?php echo e($tech['role_name']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div style="padding: 1.5rem 2rem; background: #f8fafc; display: flex; justify-content: flex-end; gap: 1rem; border-top: 1px solid #e2e8f0;">
                <button type="button" onclick="document.getElementById('editLogModal').style.display='none'" class="btn" style="background: white; border: 1px solid #cbd5e1; color: #475569;">Hủy bỏ</button>
                <button type="submit" class="btn btn-primary" style="border-radius: 10px;"><i class="fas fa-save"></i> Cập nhật</button>
            </div>
        </form>
    </div>
</div>

<script>
function editManualLog(log_id, used_at, used_by, technician_id, appt_id) {
    document.getElementById('editLogId').value = log_id;
    document.getElementById('editLogUsedAt').value = used_at;
    document.getElementById('editLogUsedBy').value = used_by;
    document.getElementById('editLogTechnicianId').value = technician_id || '';
    document.getElementById('editLogApptId').value = appt_id || '';
    document.getElementById('editLogModal').style.display = 'flex';
}
</script>

<!-- Modal Thu Nợ -->
<?php if ($has_debt): ?>
<div id="collectDebtModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: white; border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); width: 100%; max-width: 480px; overflow: hidden; transform: scale(0.95); animation: modalIn 0.2s forwards;">
        <form method="POST" onsubmit="this.querySelector('button[type=submit]').disabled = true;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="collect_debt">
            <div style="padding: 1.5rem 2rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, #dc2626, #ef4444);">
                <h3 style="margin: 0; font-weight: 800; font-size: 1.25rem; color: white;"><i class="fas fa-hand-holding-usd"></i> Thu công nợ</h3>
                <button type="button" onclick="document.getElementById('collectDebtModal').style.display='none'" style="background: rgba(255,255,255,0.2); border: none; color: white; font-size: 1rem; cursor: pointer; width: 30px; height: 30px; border-radius: 8px;"><i class="fas fa-times"></i></button>
            </div>
            <div style="padding: 2rem;">
                <div style="background: #fef2f2; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-weight: 700; color: #991b1b; font-size: 0.9rem;">Công nợ hiện tại:</span>
                    <span style="font-weight: 900; color: #dc2626; font-size: 1.25rem;"><?php echo format_money($debt_amount); ?></span>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="display: block; font-weight: 700; margin-bottom: 0.5rem;">Số tiền thu (VNĐ)</label>
                    <input type="text" id="collectAmountDisplay" class="form-input" style="font-size: 1.2rem; font-weight: 800; padding: 0.75rem; color: #15803d; border-color: #16a34a; background: #f0fdf4;" required value="<?php echo number_format($debt_amount, 0, ',', '.'); ?>">
                    <input type="hidden" name="collect_amount" id="collectAmountVal" value="<?php echo $debt_amount; ?>">
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="display: block; font-weight: 700; margin-bottom: 0.5rem;">Ngày thu</label>
                    <input type="datetime-local" name="collect_date" class="form-input" value="<?php echo date('Y-m-d\TH:i'); ?>" style="padding: 0.5rem;">
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="display: block; font-weight: 700; margin-bottom: 0.5rem;">Hình thức thanh toán</label>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.5rem;">
                        <label class="method-card selected" style="border: 1px solid #e2e8f0; border-radius: 10px; padding: 0.75rem; cursor: pointer; text-align: center; transition: all 0.2s; position: relative;">
                            <input type="radio" name="collect_method" value="cash" checked style="position: absolute; opacity: 0;" onchange="updateDebtMethodUI(this)">
                            <i class="fas fa-money-bill-wave" style="color: #10b981; font-size: 1.2rem; display: block; margin-bottom: 0.25rem;"></i>
                            <span style="font-size: 0.75rem; font-weight: 700;">Tiền mặt</span>
                        </label>
                        <label class="method-card" style="border: 1px solid #e2e8f0; border-radius: 10px; padding: 0.75rem; cursor: pointer; text-align: center; transition: all 0.2s; position: relative;">
                            <input type="radio" name="collect_method" value="transfer_personal" style="position: absolute; opacity: 0;" onchange="updateDebtMethodUI(this)">
                            <i class="fas fa-university" style="color: #3b82f6; font-size: 1.2rem; display: block; margin-bottom: 0.25rem;"></i>
                            <span style="font-size: 0.75rem; font-weight: 700;">CK Cá nhân</span>
                        </label>
                        <label class="method-card" style="border: 1px solid #e2e8f0; border-radius: 10px; padding: 0.75rem; cursor: pointer; text-align: center; transition: all 0.2s; position: relative;">
                            <input type="radio" name="collect_method" value="transfer_company" style="position: absolute; opacity: 0;" onchange="updateDebtMethodUI(this)">
                            <i class="fas fa-building" style="color: #8b5cf6; font-size: 1.2rem; display: block; margin-bottom: 0.25rem;"></i>
                            <span style="font-size: 0.75rem; font-weight: 700;">TK Công ty</span>
                        </label>
                        <label class="method-card" style="border: 1px solid #e2e8f0; border-radius: 10px; padding: 0.75rem; cursor: pointer; text-align: center; transition: all 0.2s; position: relative;">
                            <input type="radio" name="collect_method" value="card" style="position: absolute; opacity: 0;" onchange="updateDebtMethodUI(this)">
                            <i class="fas fa-credit-card" style="color: #f59e0b; font-size: 1.2rem; display: block; margin-bottom: 0.25rem;"></i>
                            <span style="font-size: 0.75rem; font-weight: 700;">Quẹt thẻ</span>
                        </label>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="display: block; font-weight: 700; margin-bottom: 0.5rem;">Ghi chú</label>
                    <input type="text" name="collect_note" class="form-input" placeholder="VD: Khách trả nốt, Thu nợ đợt 2..." style="padding: 0.5rem;">
                </div>
            </div>
            <div style="padding: 1.5rem 2rem; background: #f8fafc; display: flex; justify-content: flex-end; gap: 1rem; border-top: 1px solid #e2e8f0;">
                <button type="button" onclick="document.getElementById('collectDebtModal').style.display='none'" class="btn" style="background: white; border: 1px solid #cbd5e1; color: #475569;">Hủy bỏ</button>
                <button type="submit" class="btn" style="background: linear-gradient(135deg, #dc2626, #ef4444); color: white; font-weight: 800; border: none; border-radius: 10px; box-shadow: 0 4px 12px rgba(220,38,38,0.3);"><i class="fas fa-check-circle"></i> Xác nhận Thu</button>
            </div>
        </form>
    </div>
</div>
<script>
// Format collect amount input
document.getElementById('collectAmountDisplay').addEventListener('input', function() {
    var raw = this.value.replace(/[^\d]/g, '');
    if (!raw) raw = '0';
    var num = parseInt(raw, 10);
    var maxDebt = <?php echo (int)$debt_amount; ?>;
    if (num > maxDebt) num = maxDebt;
    this.value = new Intl.NumberFormat('vi-VN').format(num);
    document.getElementById('collectAmountVal').value = num;
});
function updateDebtMethodUI(radio) {
    var modal = document.getElementById('collectDebtModal');
    modal.querySelectorAll('.method-card').forEach(function(c) { c.classList.remove('selected'); c.style.borderColor = '#e2e8f0'; c.style.background = '#fff'; });
    radio.closest('.method-card').classList.add('selected');
    radio.closest('.method-card').style.borderColor = '#6366f1';
    radio.closest('.method-card').style.background = '#eef2ff';
}
</script>
<?php endif; ?>

<!-- Modal Xóa Gói -->
<div id="deletePkgModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: white; border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); width: 100%; max-width: 450px; overflow: hidden; transform: scale(0.95); animation: modalIn 0.2s forwards;">
        <div style="padding: 1.5rem 2rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-weight: 800; font-size: 1.25rem; color: #ef4444;"><i class="fas fa-exclamation-triangle"></i> Xác nhận xóa gói</h3>
            <button onclick="document.getElementById('deletePkgModal').style.display='none'" style="background: none; border: none; color: #94a3b8; font-size: 1.2rem; cursor: pointer;"><i class="fas fa-times"></i></button>
        </div>
        <div style="padding: 2rem;">
            <p style="color: #475569; font-size: 0.95rem; margin-bottom: 1.5rem; line-height: 1.5;">
                Việc xóa gói là hành động <strong>không thể hoàn tác</strong>. Hành động này sẽ xóa vĩnh viễn gói, <strong><?php echo count($usage_logs); ?> lượt dùng</strong> trong lịch sử, và hủy bỏ quyền dùng chung của người khác.
            </p>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete_package">
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label" style="margin-bottom: 0.5rem; display: block;">Gõ chữ <strong style="color: #ef4444;">XOA</strong> để xác nhận</label>
                    <input type="text" name="confirm_text" class="form-input" required autocomplete="off" pattern="XOA" title="Vui lòng gõ chữ XOA (viết hoa)" placeholder="XOA">
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" onclick="document.getElementById('deletePkgModal').style.display='none'" class="btn" style="background: #f1f5f9; color: #64748b;">Huỷ</button>
                    <button type="submit" class="btn" style="background: #ef4444; color: white; font-weight: 700; border-radius: 10px;"><i class="fas fa-trash-alt"></i> Khẳng định Xóa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
@keyframes modalIn {
    to { transform: scale(1); }
}
</style>

<?php require_once '../../templates/footer.php'; ?>
