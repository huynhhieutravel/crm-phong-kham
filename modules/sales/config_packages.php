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
            set_flash('Đã ẩn gói thành công!');
        } catch (PDOException $e) {
            set_flash(__('common.db_error') . $e->getMessage(), 'error');
        }
    } elseif (isset($_POST['hard_delete_id'])) {
        try {
            $stmt = $db->prepare("DELETE FROM packages WHERE id = ?");
            $stmt->execute([$_POST['hard_delete_id']]);
            set_flash('Đã xoá vĩnh viễn gói thành công!');
        } catch (PDOException $e) {
            if ($e->getCode() == 23000 || $e->getCode() == 1451) {
                set_flash('Không thể xoá vĩnh viễn vì gói này đã được bệnh nhân mua. Vui lòng sử dụng tính năng Ẩn.', 'error');
            } else {
                set_flash(__('common.db_error') . $e->getMessage(), 'error');
            }
        }
    } elseif (!empty($_POST['package_id'])) {
        $stmt_old = $db->prepare("SELECT name FROM packages WHERE id = ?");
        $stmt_old->execute([$_POST['package_id']]);
        $old_pkg = $stmt_old->fetch();

        $stmt = $db->prepare("UPDATE packages SET name = ?, total_sessions = ?, total_price = ?, coin_cost = ? WHERE id = ?");
        $stmt->execute([
            $_POST['name'],
            $_POST['total_sessions'],
            $_POST['total_price'],
            $_POST['coin_cost'],
            $_POST['package_id']
        ]);
        
        set_flash(__('sales.config.update_success'));
    } else {
        $stmt = $db->prepare("INSERT INTO packages (name, total_sessions, total_price, coin_cost) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_POST['name'],
            $_POST['total_sessions'],
            $_POST['total_price'],
            $_POST['coin_cost']
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

$packages = $db->query("
    SELECT pkg.*, 
           COUNT(pp.id) as active_patients, 
           SUM(pp.sessions_remaining) as total_sessions_left
    FROM packages pkg
    LEFT JOIN patient_packages pp ON pkg.id = pp.package_id AND pp.sessions_remaining > 0 AND pp.status = 'active'
    WHERE pkg.status = 'active' OR pkg.status IS NULL
    GROUP BY pkg.id
    ORDER BY pkg.id DESC
")->fetchAll();

// Calculate Summary Statistics
$total_packages = count($packages);
$total_active_patients = array_sum(array_column($packages, 'active_patients'));
$total_sessions_left = array_sum(array_column($packages, 'total_sessions_left'));
$avg_coin = $total_packages > 0 ? round(array_sum(array_column($packages, 'coin_cost')) / $total_packages, 1) : 2.0;
?>

<style>
/* Modern Package Config Styling */
.pkg-container {
    max-width: 1560px;
    margin: 0 auto;
}

/* Stat Cards */
.pkg-stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.25rem;
    margin-bottom: 2rem;
}

.pkg-stat-card {
    background: #ffffff;
    border-radius: 16px;
    padding: 1.25rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 1.25rem;
    border: 1px solid #f1f5f9;
    box-shadow: 0 4px 16px -2px rgba(15, 23, 42, 0.04);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.pkg-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px -4px rgba(15, 23, 42, 0.08);
    border-color: #e2e8f0;
}

.pkg-stat-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
}

.pkg-stat-info .pkg-stat-value {
    font-size: 1.6rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.2;
    margin-bottom: 0.15rem;
}

.pkg-stat-info .pkg-stat-label {
    font-size: 0.825rem;
    font-weight: 600;
    color: #64748b;
}

/* Main Two Columns */
.pkg-main-grid {
    display: grid;
    grid-template-columns: 380px 1fr;
    gap: 1.75rem;
    align-items: start;
}

@media (max-width: 1180px) {
    .pkg-main-grid {
        grid-template-columns: 1fr;
    }
}

/* Form Card */
.pkg-card {
    background: #ffffff;
    border-radius: 20px;
    padding: 1.75rem;
    border: 1px solid #f1f5f9;
    box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05);
}

.pkg-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 1.25rem;
    margin-bottom: 1.5rem;
    border-bottom: 1px solid #f1f5f9;
}

.pkg-card-title {
    font-size: 1.15rem;
    font-weight: 800;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin: 0;
}

.pkg-badge-edit {
    font-size: 0.75rem;
    background: #eef2ff;
    color: #4f46e5;
    padding: 0.25rem 0.65rem;
    border-radius: 20px;
    font-weight: 700;
}

/* Form Inputs */
.pkg-form-group {
    margin-bottom: 1.25rem;
}

.pkg-label {
    display: block;
    font-size: 0.85rem;
    font-weight: 700;
    color: #334155;
    margin-bottom: 0.45rem;
}

.pkg-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.pkg-input-icon {
    position: absolute;
    left: 1rem;
    color: #94a3b8;
    font-size: 0.95rem;
    pointer-events: none;
    transition: color 0.2s;
}

.pkg-input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.6rem;
    border-radius: 12px;
    border: 1.5px solid #e2e8f0;
    background: #f8fafc;
    font-size: 0.95rem;
    font-weight: 500;
    color: #0f172a;
    transition: all 0.25s ease;
}

.pkg-input:focus {
    background: #ffffff;
    border-color: #6366f1;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12);
    outline: none;
}

.pkg-input:focus + .pkg-input-icon,
.pkg-input-wrapper:focus-within .pkg-input-icon {
    color: #6366f1;
}

.pkg-unit-tag {
    position: absolute;
    right: 0.85rem;
    font-size: 0.8rem;
    font-weight: 700;
    color: #94a3b8;
    pointer-events: none;
}

/* Preset Chips */
.pkg-preset-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-top: 0.5rem;
}

.pkg-chip {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
    padding: 0.2rem 0.6rem;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
}

.pkg-chip:hover {
    background: #e0e7ff;
    color: #4338ca;
    border-color: #c7d2fe;
    transform: translateY(-1px);
}

/* Live Preview Card */
.pkg-preview-box {
    margin-top: 1.5rem;
    padding: 1.25rem;
    border-radius: 16px;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    color: #ffffff;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.2);
    position: relative;
    overflow: hidden;
}

.pkg-preview-box::before {
    content: '';
    position: absolute;
    top: -50px;
    right: -50px;
    width: 120px;
    height: 120px;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.3) 0%, rgba(99, 102, 241, 0) 70%);
    border-radius: 50%;
}

.pkg-preview-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.pkg-preview-tag {
    font-size: 0.7rem;
    text-transform: uppercase;
    font-weight: 800;
    letter-spacing: 0.05em;
    color: #818cf8;
}

.pkg-preview-sessions {
    background: rgba(255, 255, 255, 0.12);
    backdrop-filter: blur(4px);
    padding: 0.2rem 0.6rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    color: #e2e8f0;
}

.pkg-preview-name {
    font-size: 1.1rem;
    font-weight: 800;
    color: #ffffff;
    margin-bottom: 0.85rem;
    min-height: 1.5rem;
    word-break: break-word;
}

.pkg-preview-footer {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding-top: 0.85rem;
}

.pkg-preview-price {
    font-size: 1.25rem;
    font-weight: 800;
    color: #38bdf8;
    line-height: 1.2;
}

.pkg-preview-unit {
    font-size: 0.75rem;
    color: #94a3b8;
    font-weight: 500;
    margin-top: 0.15rem;
}

.pkg-preview-coin {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: #ffffff;
    padding: 0.35rem 0.75rem;
    border-radius: 10px;
    font-size: 0.8rem;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    box-shadow: 0 4px 10px rgba(217, 119, 6, 0.3);
}

/* Submit & Cancel Buttons */
.pkg-btn-submit {
    width: 100%;
    margin-top: 1.25rem;
    padding: 0.85rem 1.5rem;
    border-radius: 12px;
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    color: #ffffff;
    font-weight: 700;
    font-size: 0.95rem;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.35);
    transition: all 0.25s ease;
}

.pkg-btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.45);
    background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
}

.pkg-btn-cancel {
    width: 100%;
    margin-top: 0.6rem;
    padding: 0.75rem;
    border-radius: 12px;
    background: #f8fafc;
    color: #64748b;
    border: 1px solid #e2e8f0;
    font-weight: 600;
    font-size: 0.9rem;
    text-align: center;
    display: block;
    text-decoration: none;
    transition: all 0.2s ease;
}

.pkg-btn-cancel:hover {
    background: #f1f5f9;
    color: #334155;
    border-color: #cbd5e1;
}

/* Table Area */
.pkg-table-card {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #f1f5f9;
    box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05);
    overflow: hidden;
}

.pkg-table-header {
    padding: 1.5rem 1.75rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    border-bottom: 1px solid #f1f5f9;
}

.pkg-search-box {
    position: relative;
    width: 280px;
}

.pkg-search-input {
    width: 100%;
    padding: 0.65rem 1rem 0.65rem 2.4rem;
    border-radius: 10px;
    border: 1.5px solid #e2e8f0;
    background: #f8fafc;
    font-size: 0.875rem;
    transition: all 0.2s ease;
}

.pkg-search-input:focus {
    background: #ffffff;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    outline: none;
}

.pkg-search-icon {
    position: absolute;
    left: 0.85rem;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 0.85rem;
}

.pkg-table-wrapper {
    width: 100%;
    overflow-x: auto;
}

.pkg-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    text-align: left;
}

.pkg-table th {
    background: #f8fafc;
    padding: 1rem 1.25rem;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
    border-bottom: 1px solid #e2e8f0;
}

.pkg-table td {
    padding: 1.1rem 1.25rem;
    font-size: 0.9rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
    transition: background 0.2s ease;
}

.pkg-table tbody tr:hover td {
    background: #f8fafc;
}

.pkg-table tbody tr.is-editing td {
    background: #f5f7ff;
    border-top: 1px solid #c7d2fe;
    border-bottom: 1px solid #c7d2fe;
}

/* Badges & Cell Details */
.pkg-name-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.pkg-name-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: #eff6ff;
    color: #3b82f6;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    flex-shrink: 0;
}

.pkg-name-text {
    font-weight: 700;
    color: #1e293b;
    font-size: 0.95rem;
}

.pkg-id-tag {
    font-size: 0.7rem;
    color: #94a3b8;
    font-weight: 500;
}

.pkg-session-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.75rem;
    background: #f1f5f9;
    color: #334155;
    border-radius: 20px;
    font-weight: 700;
    font-size: 0.825rem;
}

.pkg-price-text {
    font-weight: 800;
    color: #0f172a;
    font-size: 0.95rem;
}

.pkg-unit-price {
    font-size: 0.75rem;
    color: #64748b;
    font-weight: 500;
    margin-top: 0.15rem;
}

.pkg-coin-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.75rem;
    background: #fffbeb;
    color: #b45309;
    border: 1px solid #fef3c7;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.85rem;
}

.pkg-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 700;
}

.pkg-status-active {
    background: #ecfdf5;
    color: #059669;
    border: 1px solid #a7f3d0;
}

.pkg-status-empty {
    background: #f1f5f9;
    color: #94a3b8;
}

/* Action Button Group */
.pkg-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.4rem;
}

.pkg-btn-action {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: none;
    cursor: pointer;
    font-size: 0.85rem;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    text-decoration: none;
}

.pkg-btn-action:hover {
    transform: translateY(-2px);
}

.pkg-btn-edit {
    background: #e0e7ff;
    color: #4338ca;
}
.pkg-btn-edit:hover {
    background: #4338ca;
    color: #ffffff;
    box-shadow: 0 4px 10px rgba(67, 56, 202, 0.3);
}

.pkg-btn-hide {
    background: #fef3c7;
    color: #b45309;
}
.pkg-btn-hide:hover {
    background: #b45309;
    color: #ffffff;
    box-shadow: 0 4px 10px rgba(180, 83, 9, 0.3);
}

.pkg-btn-delete {
    background: #fee2e2;
    color: #b91c1c;
}
.pkg-btn-delete:hover {
    background: #b91c1c;
    color: #ffffff;
    box-shadow: 0 4px 10px rgba(185, 28, 28, 0.3);
}

.pkg-empty-state {
    padding: 3.5rem 1.5rem;
    text-align: center;
    color: #94a3b8;
}
.pkg-empty-state i {
    font-size: 2.5rem;
    margin-bottom: 0.75rem;
    color: #cbd5e1;
}
</style>

<div class="pkg-container">
    <!-- Top Summary Stat Cards -->
    <div class="pkg-stat-grid">
        <div class="pkg-stat-card">
            <div class="pkg-stat-icon" style="background: #eef2ff; color: #4f46e5;">
                <i class="fas fa-boxes-stacked"></i>
            </div>
            <div class="pkg-stat-info">
                <div class="pkg-stat-value"><?php echo $total_packages; ?></div>
                <div class="pkg-stat-label">Tổng gói cấu hình</div>
            </div>
        </div>

        <div class="pkg-stat-card">
            <div class="pkg-stat-icon" style="background: #ecfdf5; color: #059669;">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="pkg-stat-info">
                <div class="pkg-stat-value"><?php echo $total_active_patients; ?></div>
                <div class="pkg-stat-label">Bệnh nhân đang sử dụng</div>
            </div>
        </div>

        <div class="pkg-stat-card">
            <div class="pkg-stat-icon" style="background: #e0f2fe; color: #0284c7;">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="pkg-stat-info">
                <div class="pkg-stat-value"><?php echo $total_sessions_left; ?></div>
                <div class="pkg-stat-label">Tổng số buổi còn lại</div>
            </div>
        </div>

        <div class="pkg-stat-card">
            <div class="pkg-stat-icon" style="background: #fffbeb; color: #d97706;">
                <i class="fas fa-coins"></i>
            </div>
            <div class="pkg-stat-info">
                <div class="pkg-stat-value"><?php echo $avg_coin; ?> <span style="font-size: 0.95rem; font-weight: 600;">Coins</span></div>
                <div class="pkg-stat-label">Quy đổi Coin trung bình</div>
            </div>
        </div>
    </div>

    <!-- Main Workspace Grid -->
    <div class="pkg-main-grid">
        <!-- Left Column: Form & Real-time Live Preview -->
        <div>
            <div class="pkg-card">
                <div class="pkg-card-header">
                    <h3 class="pkg-card-title">
                        <i class="fas <?php echo $edit_package ? 'fa-pen-to-square' : 'fa-circle-plus'; ?>" style="color: #6366f1;"></i>
                        <?php echo $edit_package ? __('sales.config.edit_package') : __('sales.config.add_package'); ?>
                    </h3>
                    <?php if ($edit_package): ?>
                        <span class="pkg-badge-edit"><i class="fas fa-hashtag"></i> ID #<?php echo $edit_package['id']; ?></span>
                    <?php endif; ?>
                </div>

                <form method="POST" action="config_packages.php" id="packageForm">
                    <?php echo csrf_field(); ?>
                    <?php if ($edit_package): ?>
                        <input type="hidden" name="package_id" value="<?php echo $edit_package['id']; ?>">
                    <?php endif; ?>

                    <!-- Tên gói -->
                    <div class="pkg-form-group">
                        <label class="pkg-label"><?php echo __('sales.config.package_name'); ?> <span style="color: #ef4444;">*</span></label>
                        <div class="pkg-input-wrapper">
                            <input type="text" id="pkg_name" name="name" class="pkg-input" required 
                                   placeholder="VD: Gói Chiropractic 10 buổi" 
                                   value="<?php echo $edit_package ? e($edit_package['name']) : ''; ?>">
                            <i class="fas fa-tag pkg-input-icon"></i>
                        </div>
                    </div>

                    <!-- Số buổi -->
                    <div class="pkg-form-group">
                        <label class="pkg-label"><?php echo __('sales.config.total_sessions'); ?> <span style="color: #ef4444;">*</span></label>
                        <div class="pkg-input-wrapper">
                            <input type="number" id="pkg_sessions" min="1" name="total_sessions" class="pkg-input" required 
                                   placeholder="10" 
                                   value="<?php echo $edit_package ? $edit_package['total_sessions'] : '10'; ?>">
                            <i class="fas fa-calendar-days pkg-input-icon"></i>
                            <span class="pkg-unit-tag">buổi</span>
                        </div>
                        <div class="pkg-preset-row">
                            <button type="button" class="pkg-chip" onclick="setSessions(1)">1 buổi</button>
                            <button type="button" class="pkg-chip" onclick="setSessions(5)">5 buổi</button>
                            <button type="button" class="pkg-chip" onclick="setSessions(10)">10 buổi</button>
                            <button type="button" class="pkg-chip" onclick="setSessions(12)">12 buổi</button>
                            <button type="button" class="pkg-chip" onclick="setSessions(20)">20 buổi</button>
                        </div>
                    </div>

                    <!-- Giá trọn gói -->
                    <div class="pkg-form-group">
                        <label class="pkg-label"><?php echo __('sales.config.total_price'); ?> <span style="color: #ef4444;">*</span></label>
                        <div class="pkg-input-wrapper">
                            <input type="text" id="total_price_display" class="pkg-input" required 
                                   placeholder="5.000.000" 
                                   value="<?php echo $edit_package ? number_format(round($edit_package['total_price']), 0, ',', '.') : '5.000.000'; ?>">
                            <input type="hidden" name="total_price" id="total_price_real" 
                                   value="<?php echo $edit_package ? round($edit_package['total_price']) : '5000000'; ?>">
                            <i class="fas fa-money-bill-wave pkg-input-icon"></i>
                            <span class="pkg-unit-tag">VNĐ</span>
                        </div>
                        <div class="pkg-preset-row">
                            <button type="button" class="pkg-chip" onclick="setPrice(3000000)">3.000.000đ</button>
                            <button type="button" class="pkg-chip" onclick="setPrice(5000000)">5.000.000đ</button>
                            <button type="button" class="pkg-chip" onclick="setPrice(6000000)">6.000.000đ</button>
                            <button type="button" class="pkg-chip" onclick="setPrice(10000000)">10.000.000đ</button>
                        </div>
                    </div>

                    <!-- Quy đổi Coin -->
                    <div class="pkg-form-group">
                        <label class="pkg-label">Quy đổi Coin / buổi <span style="color: #ef4444;">*</span></label>
                        <div class="pkg-input-wrapper">
                            <input type="number" step="0.5" id="pkg_coin" name="coin_cost" class="pkg-input" required 
                                   placeholder="2" 
                                   value="<?php echo $edit_package ? (isset($edit_package['coin_cost']) ? $edit_package['coin_cost'] : '2') : '2'; ?>">
                            <i class="fas fa-coins pkg-input-icon" style="color: #d97706;"></i>
                            <span class="pkg-unit-tag">Coins</span>
                        </div>
                        <div class="pkg-preset-row">
                            <button type="button" class="pkg-chip" onclick="setCoin(1)">1.0 Coin</button>
                            <button type="button" class="pkg-chip" onclick="setCoin(1.5)">1.5 Coins</button>
                            <button type="button" class="pkg-chip" onclick="setCoin(2)">2.0 Coins</button>
                            <button type="button" class="pkg-chip" onclick="setCoin(3)">3.0 Coins</button>
                        </div>
                    </div>

                    <!-- Submit / Cancel -->
                    <button type="submit" class="pkg-btn-submit">
                        <i class="fas <?php echo $edit_package ? 'fa-check-circle' : 'fa-plus-circle'; ?>"></i> 
                        <?php echo $edit_package ? __('sales.config.update_btn') : __('sales.config.save_btn'); ?>
                    </button>

                    <?php if ($edit_package): ?>
                        <a href="config_packages.php" class="pkg-btn-cancel">
                            <i class="fas fa-times-circle"></i> <?php echo __('sales.config.cancel_edit'); ?>
                        </a>
                    <?php endif; ?>
                </form>

                <!-- Live Dynamic Preview -->
                <div class="pkg-preview-box">
                    <div class="pkg-preview-header">
                        <span class="pkg-preview-tag"><i class="fas fa-eye"></i> Xem trước thẻ gói</span>
                        <span class="pkg-preview-sessions" id="prev_sessions">10 buổi</span>
                    </div>
                    <div class="pkg-preview-name" id="prev_name">Gói Chiropractic 10 buổi</div>
                    <div class="pkg-preview-footer">
                        <div>
                            <div class="pkg-preview-price" id="prev_price">5.000.000đ</div>
                            <div class="pkg-preview-unit" id="prev_unit_price">500.000đ / buổi</div>
                        </div>
                        <div class="pkg-preview-coin">
                            <i class="fas fa-coins"></i>
                            <span id="prev_coin">2.0</span> Coins/buổi
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Package List Table -->
        <div>
            <div class="pkg-table-card">
                <div class="pkg-table-header">
                    <div>
                        <h3 class="pkg-card-title">
                            <i class="fas fa-list-check" style="color: #6366f1;"></i>
                            <?php echo __('sales.config.list_title'); ?>
                            <span style="font-size: 0.8rem; background: #f1f5f9; color: #475569; padding: 0.2rem 0.6rem; border-radius: 20px; font-weight: 700; margin-left: 0.4rem;">
                                <?php echo count($packages); ?> gói
                            </span>
                        </h3>
                    </div>

                    <div class="pkg-search-box">
                        <input type="text" id="pkgSearchInput" class="pkg-search-input" placeholder="Tìm kiếm gói theo tên, số buổi...">
                        <i class="fas fa-search pkg-search-icon"></i>
                    </div>
                </div>

                <div class="pkg-table-wrapper">
                    <table class="pkg-table" id="packagesTable">
                        <thead>
                            <tr>
                                <th><?php echo __('sales.config.package_name'); ?></th>
                                <th style="text-align: center;"><?php echo __('sales.config.total_sessions'); ?></th>
                                <th><?php echo __('sales.config.total_price'); ?></th>
                                <th>Quy đổi Coin/buổi</th>
                                <th>Đang sử dụng</th>
                                <th style="text-align: right;"><?php echo __('common.actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($packages)): ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="pkg-empty-state">
                                            <i class="fas fa-box-open"></i>
                                            <p style="margin: 0; font-weight: 600;">Chưa có gói dịch vụ nào được tạo.</p>
                                            <span style="font-size: 0.8rem;">Hãy dùng biểu mẫu bên trái để thêm gói đầu tiên!</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($packages as $pkg): 
                                    $is_editing = ($edit_package && $edit_package['id'] == $pkg['id']);
                                    $unit_price = ($pkg['total_sessions'] > 0) ? round($pkg['total_price'] / $pkg['total_sessions']) : 0;
                                ?>
                                    <tr class="pkg-row <?php echo $is_editing ? 'is-editing' : ''; ?>" data-name="<?php echo strtolower(e($pkg['name'])); ?>" data-sessions="<?php echo $pkg['total_sessions']; ?>">
                                        <td>
                                            <div class="pkg-name-cell">
                                                <div class="pkg-name-icon">
                                                    <i class="fas fa-layer-group"></i>
                                                </div>
                                                <div>
                                                    <div class="pkg-name-text"><?php echo e($pkg['name']); ?></div>
                                                    <div class="pkg-id-tag">Mã gói #<?php echo $pkg['id']; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="text-align: center;">
                                            <span class="pkg-session-badge">
                                                <i class="fas fa-calendar-check" style="color: #6366f1; font-size: 0.75rem;"></i>
                                                <?php echo $pkg['total_sessions']; ?> buổi
                                            </span>
                                        </td>
                                        <td>
                                            <div class="pkg-price-text"><?php echo format_money($pkg['total_price']); ?></div>
                                            <div class="pkg-unit-price"><?php echo format_money($unit_price); ?> / buổi</div>
                                        </td>
                                        <td>
                                            <div class="pkg-coin-badge">
                                                <i class="fas fa-coins" style="color: #d97706;"></i>
                                                <span><?php echo isset($pkg['coin_cost']) ? number_format($pkg['coin_cost'], 1) : '2.0'; ?> Coins</span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($pkg['active_patients'] > 0): ?>
                                                <span class="pkg-status-badge pkg-status-active">
                                                    <i class="fas fa-users"></i>
                                                    <?php echo $pkg['active_patients']; ?> BN (<?php echo $pkg['total_sessions_left']; ?> buổi còn)
                                                </span>
                                            <?php else: ?>
                                                <span class="pkg-status-badge pkg-status-empty">
                                                    <i class="fas fa-user-minus" style="font-size: 0.7rem;"></i> Chưa có BN
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="pkg-actions">
                                                <a href="config_packages.php?edit_id=<?php echo $pkg['id']; ?>" 
                                                   class="pkg-btn-action pkg-btn-edit" 
                                                   title="Chỉnh sửa thông tin gói">
                                                    <i class="fas fa-pen"></i>
                                                </a>
                                                <button type="button" 
                                                        onclick="confirmAndPost('config_packages.php', {delete_id: <?php echo $pkg['id']; ?>}, 'Bạn có chắc chắn muốn ẨN gói này khỏi danh sách bán?')" 
                                                        class="pkg-btn-action pkg-btn-hide" 
                                                        title="Ẩn gói này">
                                                    <i class="fas fa-eye-slash"></i>
                                                </button>
                                                <button type="button" 
                                                        onclick="confirmAndPost('config_packages.php', {hard_delete_id: <?php echo $pkg['id']; ?>}, 'Bạn có chắc chắn muốn XOÁ VĨNH VIỄN gói này không? Thao tác không thể hoàn tác!')" 
                                                        class="pkg-btn-action pkg-btn-delete" 
                                                        title="Xóa vĩnh viễn">
                                                    <i class="fas fa-trash-can"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Format Currency Helper
function formatVND(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".") + 'đ';
}

// Interactive Preset Helpers
function setSessions(val) {
    document.getElementById('pkg_sessions').value = val;
    updateLivePreview();
}

function setPrice(val) {
    document.getElementById('total_price_real').value = val;
    document.getElementById('total_price_display').value = val.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    updateLivePreview();
}

function setCoin(val) {
    document.getElementById('pkg_coin').value = val;
    updateLivePreview();
}

// Live Preview Updater
function updateLivePreview() {
    const nameInput = document.getElementById('pkg_name');
    const sessionsInput = document.getElementById('pkg_sessions');
    const realPriceInput = document.getElementById('total_price_real');
    const coinInput = document.getElementById('pkg_coin');

    const prevName = document.getElementById('prev_name');
    const prevSessions = document.getElementById('prev_sessions');
    const prevPrice = document.getElementById('prev_price');
    const prevUnitPrice = document.getElementById('prev_unit_price');
    const prevCoin = document.getElementById('prev_coin');

    const nameVal = nameInput ? nameInput.value.trim() : '';
    const sessionsVal = sessionsInput ? parseInt(sessionsInput.value, 10) || 0 : 0;
    const priceVal = realPriceInput ? parseInt(realPriceInput.value, 10) || 0 : 0;
    const coinVal = coinInput ? parseFloat(coinInput.value) || 0 : 0;

    if (prevName) prevName.textContent = nameVal ? nameVal : 'Tên gói dịch vụ';
    if (prevSessions) prevSessions.textContent = sessionsVal + ' buổi';
    if (prevPrice) prevPrice.textContent = formatVND(priceVal);
    
    if (prevUnitPrice) {
        if (sessionsVal > 0 && priceVal > 0) {
            const unitPrice = Math.round(priceVal / sessionsVal);
            prevUnitPrice.textContent = formatVND(unitPrice) + ' / buổi';
        } else {
            prevUnitPrice.textContent = '0đ / buổi';
        }
    }

    if (prevCoin) prevCoin.textContent = coinVal.toFixed(1);
}

document.addEventListener('DOMContentLoaded', function() {
    const displayInput = document.getElementById('total_price_display');
    const realInput = document.getElementById('total_price_real');
    const nameInput = document.getElementById('pkg_name');
    const sessionsInput = document.getElementById('pkg_sessions');
    const coinInput = document.getElementById('pkg_coin');

    if (displayInput && realInput) {
        displayInput.addEventListener('input', function(e) {
            let val = this.value.replace(/\D/g, '');
            realInput.value = val;
            if (val !== '') {
                this.value = parseInt(val, 10).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            } else {
                this.value = '';
            }
            updateLivePreview();
        });
    }

    if (nameInput) nameInput.addEventListener('input', updateLivePreview);
    if (sessionsInput) sessionsInput.addEventListener('input', updateLivePreview);
    if (coinInput) coinInput.addEventListener('input', updateLivePreview);

    // Initial trigger
    updateLivePreview();

    // Instant Client-side Filter for Packages Table
    const searchInput = document.getElementById('pkgSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const term = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('.pkg-row');
            
            rows.forEach(row => {
                const name = row.getAttribute('data-name') || '';
                const sessions = row.getAttribute('data-sessions') || '';
                if (name.includes(term) || sessions.includes(term)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});
</script>

<?php require_once '../../templates/footer.php'; ?>
