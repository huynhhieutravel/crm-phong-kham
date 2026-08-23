<?php
// modules/sales/topup.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_once '../../includes/coin_functions.php';
require_permission('manage_sales');

$page_title = 'Nạp Coin & Quản Lý Ví';
$current_page = 'sales';

$db = getDB();
$search = trim($_GET['search'] ?? '');

// Handle Top-Up POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'topup_coins') {
    verify_csrf();
    $patient_id = (int)$_POST['patient_id'];
    $topup_amount = (int)($_POST['topup_amount'] ?? 0);
    $topup_coins = (int)($_POST['topup_coins'] ?? 0);
    $note = trim($_POST['note'] ?? '');
    $payment_method = $_POST['payment_method'] ?? 'cash';

    if ($patient_id > 0 && $topup_coins > 0) {
        topup_patient_coins($db, $patient_id, $topup_coins, $topup_amount, $_SESSION['user_id'], $note ?: 'Nạp Coin tại trang Quản lý Ví', $payment_method);
        set_flash("Đã nạp thành công $topup_coins Coins cho bệnh nhân!", 'success');
    } else {
        set_flash('Vui lòng nhập số Coin hợp lệ.', 'error');
    }
    header("Location: topup.php?search=" . urlencode($search));
    exit;
}

// Handle Deduct POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'deduct_coins') {
    verify_csrf();
    $patient_id = (int)$_POST['patient_id'];
    $deduct_coins = (int)($_POST['deduct_coins'] ?? 0);
    $note = trim($_POST['note'] ?? '');

    if ($patient_id > 0 && $deduct_coins > 0) {
        try {
            if (deduct_patient_coins($db, $patient_id, $deduct_coins, 0, $_SESSION['user_id'], $note ?: 'Trừ Coin thủ công')) {
                set_flash("Đã trừ thành công $deduct_coins Coins của bệnh nhân!", 'success');
            } else {
                set_flash('Số dư Coin không đủ để trừ.', 'error');
            }
        } catch (Exception $e) {
            set_flash('Lỗi khi trừ Coin: ' . $e->getMessage(), 'error');
        }
    } else {
        set_flash('Vui lòng nhập số Coin hợp lệ.', 'error');
    }
    header("Location: topup.php?search=" . urlencode($search));
    exit;
}

// Fetch patients matching search or recent patients
$patients = [];
if ($search) {
    $safe_search = addcslashes($search, '%_');
    $stmt = $db->prepare("SELECT id, full_name, phone, customer_id FROM patients WHERE full_name LIKE ? OR phone LIKE ? OR customer_id LIKE ? ORDER BY id DESC LIMIT 20");
    $stmt->execute(["%$safe_search%", "%$safe_search%", "%$safe_search%"]);
    $patients = $stmt->fetchAll();
} else {
    $stmt = $db->query("SELECT id, full_name, phone, customer_id FROM patients ORDER BY id DESC LIMIT 15");
    $patients = $stmt->fetchAll();
}

// Attach coin balances
foreach ($patients as &$p) {
    $p['coin_balance'] = get_patient_coin_balance($db, $p['id']);
}
unset($p);

require_once '../../templates/header.php';
?>

<div style="max-width: 1000px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-weight: 800; color: #1e293b; margin: 0;"><i class="fas fa-coins" style="color: #f59e0b;"></i> Quản Lý Ví & Nạp Coin Bệnh Nhân</h1>
            <p style="color: #64748b; margin-top: 0.25rem;">Nạp tiền quy đổi ra Coin hoặc kiểm tra số dư ví tích điểm của khách hàng</p>
        </div>
    </div>

    <!-- Search Box -->
    <div class="card" style="margin-bottom: 1.5rem; border-radius: 16px;">
        <form method="GET" style="display: flex; gap: 0.75rem;">
            <div style="position: relative; flex: 1;">
                <i class="fas fa-search" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                <input type="text" name="search" class="form-input" placeholder="Tìm theo tên bệnh nhân, số điện thoại, mã BN..." value="<?php echo e($search); ?>" style="padding-left: 2.5rem !important;">
            </div>
            <button type="submit" class="btn btn-primary" style="padding: 0 1.5rem; font-weight: 700; border-radius: 10px;">
                <i class="fas fa-search"></i> Tìm kiếm
            </button>
        </form>
    </div>

    <!-- Patients List & TopUp Table -->
    <div class="card" style="padding: 0; overflow: hidden; border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
        <table class="table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: transparent; text-align: left;">
                    <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 700; border-bottom: 2px solid #f1f5f9; width: 15%;">Mã BN</th>
                    <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 700; border-bottom: 2px solid #f1f5f9; width: 30%;">Họ và Tên</th>
                    <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 700; border-bottom: 2px solid #f1f5f9; width: 20%;">Số Điện Thoại</th>
                    <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 700; border-bottom: 2px solid #f1f5f9; width: 15%;">Số Dư Ví Coin</th>
                    <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 700; border-bottom: 2px solid #f1f5f9; text-align: right; width: 20%;">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($patients)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 3rem; color: #94a3b8;">Không tìm thấy bệnh nhân nào.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($patients as $p): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 1.25rem 1.5rem;">
                                <span style="font-size: 0.75rem; font-weight: 800; background: #f1f5f9; padding: 0.2rem 0.5rem; border-radius: 6px; color: var(--text-muted);">
                                    <?php echo e($p['customer_id'] ?: 'BN-'.$p['id']); ?>
                                </span>
                            </td>
                            <td style="padding: 1.25rem 1.5rem; font-weight: 700; color: #1e293b;">
                                <a href="../patients/view.php?id=<?php echo $p['id']; ?>" style="color: #1e293b; text-decoration: none;">
                                    <?php echo e($p['full_name']); ?>
                                </a>
                            </td>
                            <td style="padding: 1.25rem 1.5rem; color: #64748b; font-size: 0.9rem;">
                                <?php echo e($p['phone']); ?>
                            </td>
                            <td style="padding: 1.25rem 1.5rem;">
                                <span style="font-size: 1rem; font-weight: 900; color: #d97706; background: #fffbebf5; padding: 0.25rem 0.6rem; border-radius: 8px; border: 1px solid #fde68a;">
                                    <i class="fas fa-coins"></i> <?php echo $p['coin_balance']; ?> Coins
                                </span>
                            </td>
                            <td style="padding: 1.25rem 1.5rem; text-align: right;">
                                <div style="display: flex; gap: 0.25rem; justify-content: flex-end; flex-wrap: wrap;">
                                    <button type="button" onclick="openTopUpModal(<?php echo $p['id']; ?>, '<?php echo e(addslashes($p['full_name'])); ?>')" class="btn btn-sm" style="background: #f59e0b; color: white; border-radius: 8px; font-weight: 700; padding: 0.4rem 0.6rem; flex: 1; min-width: max-content;">
                                        <i class="fas fa-plus-circle"></i> Nạp
                                    </button>
                                    <button type="button" onclick="openDeductModal(<?php echo $p['id']; ?>, '<?php echo e(addslashes($p['full_name'])); ?>', <?php echo $p['coin_balance']; ?>)" class="btn btn-sm" style="background: #ef4444; color: white; border-radius: 8px; font-weight: 700; padding: 0.4rem 0.6rem; flex: 1; min-width: max-content;">
                                        <i class="fas fa-minus-circle"></i> Trừ
                                    </button>
                                </div>
                                <button type="button" onclick="openHistoryModal(<?php echo $p['id']; ?>, '<?php echo e(addslashes($p['full_name'])); ?>')" class="btn btn-sm" style="background: #e2e8f0; color: #475569; border-radius: 8px; font-weight: 600; padding: 0.4rem 0.8rem; margin-top: 0.25rem; width: 100%;">
                                    <i class="fas fa-history"></i> Lịch sử
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Nạp Coin -->
<div id="topupPageModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 20px; padding: 2rem; max-width: 450px; width: 90%; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">
        <h3 style="margin-top: 0; font-weight: 800; color: #b45309;"><i class="fas fa-coins"></i> Nạp Coin Cho <span id="targetPatientName"></span></h3>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="topup_coins">
            <input type="hidden" name="patient_id" id="targetPatientId" value="">
            
            <div style="margin-bottom: 1rem;">
                <label style="font-size: 0.85rem; font-weight: 700; color: #1e293b;">Số tiền thực nạp (VNĐ)</label>
                <input type="number" name="topup_amount" class="form-input" placeholder="900000" style="margin-top: 0.25rem; font-size: 1rem; font-weight: 700;" oninput="document.getElementById('modalCoinsInput').value = Math.floor(this.value / 300000);">
            </div>
            
            <div style="margin-bottom: 1rem;">
                <label style="font-size: 0.85rem; font-weight: 700; color: #1e293b;">Số Coins quy đổi</label>
                <input type="number" step="1" name="topup_coins" id="modalCoinsInput" class="form-input" placeholder="3" style="margin-top: 0.25rem; font-size: 1.3rem; font-weight: 800; color: #d97706;" required>
                <small style="color: #64748b; display: block; margin-top: 0.35rem;">Gợi ý: 900.000đ = 3 Coins, 600.000đ = 2 Coins</small>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label style="font-size: 0.85rem; font-weight: 700; color: #1e293b;">Ghi chú (Tuỳ chọn)</label>
                <input type="text" name="note" class="form-input" placeholder="Khách nạp gói ưu đãi..." style="margin-top: 0.25rem;">
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label style="font-size: 0.85rem; font-weight: 700; color: #1e293b;">Hình thức thanh toán</label>
                <select name="payment_method" class="form-input" style="margin-top: 0.25rem;">
                    <option value="cash">Tiền mặt</option>
                    <option value="transfer_personal">CK Cá nhân</option>
                    <option value="transfer_company">TK Công ty</option>
                    <option value="card">Quẹt thẻ</option>
                </select>
            </div>

            <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                <button type="button" onclick="document.getElementById('topupPageModal').style.display='none';" class="btn" style="background: #f1f5f9; color: #475569; font-weight: 600;">Hủy</button>
                <button type="submit" class="btn" style="background: #f59e0b; color: white; font-weight: 800;">Xác Nhận Nạp</button>
            </div>
        </form>
    </div>
</div>

<script>
function openTopUpModal(id, name) {
    document.getElementById('targetPatientId').value = id;
    document.getElementById('targetPatientName').innerText = name;
    document.getElementById('topupPageModal').style.display = 'flex';
}
</script>

<!-- Modal Trừ Coin -->
<div id="deductPageModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 20px; padding: 2rem; max-width: 450px; width: 90%; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">
        <h3 style="margin-top: 0; font-weight: 800; color: #ef4444;"><i class="fas fa-minus-circle"></i> Trừ Coin Của <span id="targetDeductPatientName"></span></h3>
        <p style="color: #64748b; font-size: 0.85rem; margin-top: -0.5rem; margin-bottom: 1.5rem;">Số dư hiện tại: <strong id="targetDeductBalance" style="color: #d97706;">0</strong> Coins</p>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="deduct_coins">
            <input type="hidden" name="patient_id" id="targetDeductPatientId" value="">
            
            <div style="margin-bottom: 1rem;">
                <label style="font-size: 0.85rem; font-weight: 700; color: #1e293b;">Số Coins cần trừ</label>
                <input type="number" step="1" name="deduct_coins" class="form-input" placeholder="1" style="margin-top: 0.25rem; font-size: 1.3rem; font-weight: 800; color: #ef4444;" required>
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <label style="font-size: 0.85rem; font-weight: 700; color: #1e293b;">Lý do trừ (Bắt buộc)</label>
                <input type="text" name="note" class="form-input" placeholder="Trừ cho người đi kèm..." style="margin-top: 0.25rem;" required>
            </div>

            <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                <button type="button" onclick="document.getElementById('deductPageModal').style.display='none';" class="btn" style="background: #f1f5f9; color: #475569; font-weight: 600;">Hủy</button>
                <button type="submit" class="btn" style="background: #ef4444; color: white; font-weight: 800;">Xác Nhận Trừ</button>
            </div>
        </form>
    </div>
</div>

<script>
function openDeductModal(id, name, balance) {
    document.getElementById('targetDeductPatientId').value = id;
    document.getElementById('targetDeductPatientName').innerText = name;
    document.getElementById('targetDeductBalance').innerText = balance;
    document.getElementById('deductPageModal').style.display = 'flex';
}
</script>

<!-- Modal Lịch sử Giao dịch Coin -->
<div id="historyPageModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 20px; padding: 2rem; max-width: 600px; width: 95%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="margin: 0; font-weight: 800; color: #1e293b;"><i class="fas fa-history"></i> Lịch sử Ví Coin - <span id="historyPatientName"></span></h3>
            <button type="button" onclick="document.getElementById('historyPageModal').style.display='none';" style="background: transparent; border: none; font-size: 1.25rem; color: #94a3b8; cursor: pointer;"><i class="fas fa-times"></i></button>
        </div>
        
        <table class="table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="text-align: left; background: #f8fafc;">
                    <th style="padding: 0.75rem; color: #64748b; font-size: 0.8rem;">Thời gian</th>
                    <th style="padding: 0.75rem; color: #64748b; font-size: 0.8rem;">Giao dịch</th>
                    <th style="padding: 0.75rem; color: #64748b; font-size: 0.8rem; text-align: right;">Số Coin</th>
                    <th style="padding: 0.75rem; color: #64748b; font-size: 0.8rem; text-align: right;">Hành động</th>
                </tr>
            </thead>
            <tbody id="historyTableBody">
                <tr><td colspan="4" style="text-align: center; padding: 2rem;">Đang tải...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
function openHistoryModal(patient_id, name) {
    document.getElementById('historyPatientName').innerText = name;
    document.getElementById('historyPageModal').style.display = 'flex';
    document.getElementById('historyTableBody').innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 2rem;">Đang tải...</td></tr>';
    
    fetch('coin_history_api.php?patient_id=' + patient_id)
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                let html = '';
                if (res.data.length === 0) {
                    html = '<tr><td colspan="4" style="text-align: center; padding: 2rem; color: #94a3b8;">Chưa có giao dịch nào</td></tr>';
                } else {
                    res.data.forEach(tx => {
                        let typeBadge = '';
                        let amountColor = tx.amount > 0 ? '#10b981' : '#ef4444';
                        let amountSign = tx.amount > 0 ? '+' : '';
                        let canCancel = false;
                        
                        if (tx.transaction_type === 'topup') {
                            typeBadge = '<span style="background: #dbeafe; color: #2563eb; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 700;">Nạp Coin</span>';
                            canCancel = true;
                        } else if (tx.transaction_type === 'usage') {
                            typeBadge = '<span style="background: #fee2e2; color: #ef4444; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 700;">Sử dụng</span>';
                        } else if (tx.transaction_type === 'exchange_from_package') {
                            typeBadge = '<span style="background: #fef3c7; color: #d97706; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 700;">Quy đổi Gói</span>';
                        } else if (tx.transaction_type === 'refund') {
                            typeBadge = '<span style="background: #f3f4f6; color: #6b7280; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 700;">Huỷ Nạp (Hoàn)</span>';
                        }
                        
                        html += `
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 0.75rem; font-size: 0.85rem; color: #64748b;">
                                    ${tx.created_at}<br>
                                    <small style="color: #b45309; font-weight: 600;"><i class="fas fa-user-tie"></i> ${tx.created_by_name || 'Hệ thống'}</small>
                                </td>
                                <td style="padding: 0.75rem; font-size: 0.85rem;">
                                    ${typeBadge}<br>
                                    <small style="color: #1e293b; font-weight: 500;">${tx.note || ''}</small>
                                </td>
                                <td style="padding: 0.75rem; font-size: 1rem; font-weight: 800; text-align: right; color: ${amountColor};">
                                    ${amountSign}${tx.amount}
                                </td>
                                <td style="padding: 0.75rem; text-align: right;">
                                    ${canCancel ? `<button onclick="cancelCoinTx(${tx.id})" class="btn btn-sm" style="background: #fee2e2; color: #ef4444; font-size: 0.7rem; padding: 0.2rem 0.5rem;">Huỷ</button>` : ''}
                                </td>
                            </tr>
                        `;
                    });
                }
                document.getElementById('historyTableBody').innerHTML = html;
            } else {
                document.getElementById('historyTableBody').innerHTML = '<tr><td colspan="4" style="text-align: center; color: #ef4444;">Lỗi: ' + res.error + '</td></tr>';
            }
        })
        .catch(err => {
            document.getElementById('historyTableBody').innerHTML = '<tr><td colspan="4" style="text-align: center; color: #ef4444;">Lỗi kết nối</td></tr>';
        });
}

function cancelCoinTx(transaction_id) {
    if (confirm('Bạn có chắc chắn muốn huỷ giao dịch nạp Coin này? (Hệ thống sẽ thu hồi Coin và Huỷ Hóa đơn tương ứng)')) {
        let reason = prompt('Lý do huỷ giao dịch: (VD: Nhập nhầm số lượng)');
        if (reason === null) return;
        
        fetch('cancel_coin_topup_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ transaction_id: transaction_id, reason: reason })
        })
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                alert('Huỷ giao dịch thành công!');
                window.location.reload();
            } else {
                alert('Lỗi: ' + res.error);
            }
        })
        .catch(err => {
            alert('Lỗi kết nối server.');
        });
    }
}
</script>

<?php require_once '../../templates/footer.php'; ?>
