<?php
// modules/sales/migrate_packages_to_coins.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_once '../../includes/coin_functions.php';

// Chỉ cho phép admin cao nhất thực hiện
require_permission('manage_services'); 

$page_title = 'Chuyển Đổi Gói Cũ Sang Ví Coin';
$current_page = 'config_coins';
require_once '../../templates/header.php';

$db = getDB();
$success = '';
$error = '';

// Lấy danh sách toàn bộ bệnh nhân đang còn gói chưa sử dụng hết
$stmt = $db->query("
    SELECT pp.*, pkg.name as package_name, pkg.coin_cost,
           p.full_name as patient_name, p.phone as patient_phone,
           (pp.sessions_remaining * COALESCE(pkg.coin_cost, 2.0)) as expected_coins
    FROM patient_packages pp
    JOIN packages pkg ON pp.package_id = pkg.id
    JOIN patients p ON pp.patient_id = p.id
    WHERE pp.sessions_remaining > 0 AND pp.status = 'active'
    ORDER BY p.full_name ASC
");
$pending_packages = $stmt->fetchAll();

// Tổng hợp theo bệnh nhân để báo cáo (một bệnh nhân có thể có nhiều gói)
$patients_summary = [];
$total_coins_to_mint = 0;
foreach ($pending_packages as $pkg) {
    $pid = $pkg['patient_id'];
    if (!isset($patients_summary[$pid])) {
        $patients_summary[$pid] = [
            'name' => $pkg['patient_name'],
            'phone' => $pkg['patient_phone'],
            'packages' => [],
            'total_coins' => 0
        ];
    }
    $patients_summary[$pid]['packages'][] = $pkg;
    $patients_summary[$pid]['total_coins'] += (float)$pkg['expected_coins'];
    $total_coins_to_mint += (float)$pkg['expected_coins'];
}

// Xử lý chạy Migration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'run_migration') {
    try {
        $db->beginTransaction();
        
        $converted_count = 0;
        foreach ($pending_packages as $pkg) {
            // Sử dụng hàm an toàn đã viết sẵn trong coin_functions.php
            $result = convert_package_to_coins($db, $pkg['id'], $_SESSION['user_id']);
            if ($result !== false) {
                $converted_count++;
            }
        }
        
        $db->commit();
        $success = "Thành công! Đã chuyển đổi $converted_count gói còn hiệu lực sang Ví Coin cho khách hàng.";
        $pending_packages = []; // Reset list
        $patients_summary = [];
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Lỗi khi chạy Migration: " . $e->getMessage();
    }
}

?>

<style>
.warning-box {
    background: #fffbeb; border: 1px solid #fde68a; border-radius: 12px; padding: 1.5rem;
    margin-bottom: 2rem; color: #92400e; display: flex; gap: 1rem; align-items: flex-start;
}
.warning-box i { font-size: 2rem; color: #f59e0b; }
.warning-box h4 { margin: 0 0 0.5rem 0; font-weight: 800; font-size: 1.1rem; }
.warning-box ul { margin: 0; padding-left: 1.2rem; }
.warning-box li { margin-bottom: 0.25rem; font-size: 0.9rem; }
</style>

<div class="content-body">
    <div style="max-width: 1000px; width: 100%; margin: 0 auto;">
        <div class="breadcrumb mb-2" style="font-size: 0.75rem; font-weight: 700; letter-spacing: 1px; color: #94a3b8;">
            CRM / HỆ THỐNG / CHUYỂN ĐỔI DỮ LIỆU
        </div>
        <h1 style="font-size: 2rem; font-weight: 800; color: #0f172a; margin-bottom: 1.5rem;">
            🔄 Công Cụ Quy Đổi Gói Cũ Sang Ví Coin
        </h1>

        <?php if ($success): ?>
            <div class="alert alert-success" style="padding: 1.5rem; font-size: 1.1rem; text-align: center; border-radius: 12px; margin-bottom: 2rem;">
                <i class="fas fa-check-circle" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i> 
                <strong><?php echo e($success); ?></strong>
                <div style="margin-top: 1rem;">
                    <a href="config_packages.php" class="btn btn-primary">Quay lại Cấu hình Gói</a>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo e($error); ?></div>
        <?php endif; ?>

        <?php if (!$success && !empty($pending_packages)): ?>
            <div class="warning-box">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <h4>CẢNH BÁO QUAN TRỌNG TRƯỚC KHI THỰC HIỆN</h4>
                    <ul>
                        <li>Hệ thống sẽ chuyển toàn bộ số buổi còn lại của các gói đang kích hoạt thành số dư Coin.</li>
                        <li><b>Mất tính năng dùng chung gói:</b> Số Coin sẽ được nạp trực tiếp vào ví của <b>người mua gói</b>. Những người thân trước đây được chia sẻ gói này sẽ KHÔNG tự sử dụng Coin được nữa (Lễ tân phải tự trừ bằng tay).</li>
                        <li>Các gói sau khi chuyển đổi sẽ chuyển trạng thái thành "Đã dùng hết (Exhausted)". Hành động này không thể hoàn tác tự động.</li>
                    </ul>
                </div>
            </div>

            <div class="card" style="border-radius: 16px; padding: 1.5rem; margin-bottom: 2rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h3 style="margin: 0; font-weight: 800; color: #1e293b;">
                        Danh Sách Bệnh Nhân Sẽ Được Quy Đổi
                    </h3>
                    <div style="background: #f8fafc; padding: 0.5rem 1rem; border-radius: 8px; border: 1px solid #e2e8f0; font-weight: 700; color: #d97706;">
                        Tổng phát hành dự kiến: <span style="font-size: 1.2rem;"><?php echo $total_coins_to_mint; ?> Coins</span>
                    </div>
                </div>

                <div style="max-height: 500px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px;">
                    <table class="table" style="width: 100%;">
                        <thead style="position: sticky; top: 0; background: #f8fafc; z-index: 10;">
                            <tr style="text-align: left;">
                                <th style="padding: 0.75rem;">Bệnh Nhân</th>
                                <th style="padding: 0.75rem;">Số Điện Thoại</th>
                                <th style="padding: 0.75rem;">Chi Tiết Gói Đang Còn</th>
                                <th style="padding: 0.75rem; text-align: right;">Sẽ nhận được</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($patients_summary as $pid => $data): ?>
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 0.75rem; font-weight: 700; color: var(--primary);">
                                        <a href="../patients/view.php?id=<?php echo $pid; ?>" target="_blank" style="text-decoration: none; color: inherit;"><?php echo e($data['name']); ?></a>
                                    </td>
                                    <td style="padding: 0.75rem; font-size: 0.85rem; color: #64748b;">
                                        <?php echo e($data['phone']); ?>
                                    </td>
                                    <td style="padding: 0.75rem; font-size: 0.8rem; color: #334155;">
                                        <?php foreach ($data['packages'] as $p): ?>
                                            <div style="background: #f1f5f9; padding: 0.3rem 0.5rem; border-radius: 4px; margin-bottom: 0.25rem;">
                                                <b><?php echo e($p['package_name']); ?></b>: Còn <?php echo $p['sessions_remaining']; ?> buổi (x <?php echo (float)$p['coin_cost']; ?> Coins/buổi)
                                            </div>
                                        <?php endforeach; ?>
                                    </td>
                                    <td style="padding: 0.75rem; text-align: right; font-weight: 800; color: #d97706; font-size: 1.1rem;">
                                        +<?php echo $data['total_coins']; ?> Coins
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <form method="POST" onsubmit="return confirm('BẠN CÓ CHẮC CHẮN MUỐN CHẠY QUY ĐỔI TOÀN BỘ? Hành động này sẽ thay đổi số dư ví của toàn bộ khách hàng ở trên và đóng các gói hiện tại của họ!');" style="margin-top: 2rem; text-align: right;">
                    <input type="hidden" name="action" value="run_migration">
                    <button type="submit" class="btn btn-primary" style="padding: 1rem 2rem; font-size: 1.1rem; border-radius: 12px; font-weight: 800;">
                        <i class="fas fa-bolt"></i> CHẠY QUY ĐỔI NGAY
                    </button>
                </form>
            </div>
        <?php elseif (!$success): ?>
            <div style="text-align: center; padding: 4rem 2rem; background: white; border-radius: 16px; border: 2px dashed #e2e8f0;">
                <i class="fas fa-check-circle" style="font-size: 4rem; color: #10b981; margin-bottom: 1rem;"></i>
                <h3 style="margin: 0; color: #1e293b; font-weight: 800;">Tất cả sạch sẽ!</h3>
                <p style="color: #64748b; margin-top: 0.5rem;">Không tìm thấy gói cũ nào cần quy đổi trong hệ thống. Khách hàng hiện tại chỉ đang dùng Ví Coin.</p>
                <div style="margin-top: 1.5rem;">
                    <a href="config_packages.php" class="btn">Quay lại Cấu hình Gói</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../templates/footer.php'; ?>
