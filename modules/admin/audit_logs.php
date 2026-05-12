<?php
// modules/admin/audit_logs.php

require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('view_audit_logs');

$db = getDB();
$page_title = __('audit.title') . ' — ' . __('audit_logs');
$current_page = 'admin_audit';

// Filters
$action_filter = $_GET['action'] ?? '';
$user_filter = $_GET['user_id'] ?? '';
$target_filter = $_GET['target'] ?? '';

$target_map = [
    'patients' => 'Bệnh nhân',
    'leads' => 'Khách hàng',
    'appointments' => 'Lịch hẹn',
    'medical_sessions' => 'Phiên khám',
    'medical_history' => 'Bệnh án',
    'invoices' => 'Hóa đơn',
    'invoice_items' => 'Mục hóa đơn',
    'packages' => 'Gói dịch vụ',
    'patient_packages' => 'Gói của bệnh nhân',
    'users' => 'Nhân sự',
    'roles' => 'Phân quyền',
    'products' => 'Sản phẩm/Vật tư'
];

$where = ["1=1"];
$params = [];

if ($action_filter) {
    $where[] = "a.action = ?";
    $params[] = $action_filter;
}
if ($user_filter) {
    $where[] = "a.user_id = ?";
    $params[] = $user_filter;
}
if ($target_filter) {
    $where[] = "a.target_table = ?";
    $params[] = $target_filter;
}

$where_sql = implode(" AND ", $where);

// Pagination Logic
$limit = 20;
$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;

$count_query = "
    SELECT COUNT(*) 
    FROM audit_logs a
    JOIN users u ON a.user_id = u.id
    WHERE $where_sql
";
$c_stmt = $db->prepare($count_query);
$c_stmt->execute($params);
$total_count = $c_stmt->fetchColumn();

// Base Query
$query = "
    SELECT a.*, u.full_name as user_name 
    FROM audit_logs a
    JOIN users u ON a.user_id = u.id
    WHERE $where_sql
    ORDER BY a.created_at DESC
" . get_sql_limit($limit, $page);

$stmt = $db->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get unique actions and users for filter
$actions = $db->query("SELECT DISTINCT action FROM audit_logs")->fetchAll(PDO::FETCH_COLUMN);
$users = $db->query("SELECT id, full_name FROM users")->fetchAll();
$all_targets = $db->query("SELECT DISTINCT target_table FROM audit_logs")->fetchAll(PDO::FETCH_COLUMN);

require_once '../../templates/header.php';
?>

<div class="card" style="background: var(--glass-bg); backdrop-filter: blur(20px);">
    <div style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 style="margin: 0; font-weight: 800; color: var(--primary);"><i class="fas fa-history"></i> <?php echo __('audit.title'); ?></h2>
            <p style="color: var(--text-muted); margin-top: 0.25rem;"><?php echo __('audit.subtitle'); ?></p>
        </div>
    </div>

    <!-- Quick Module Tabs -->
    <div style="display: flex; gap: 0.5rem; margin-bottom: 2rem; border-bottom: 2px solid #eef2f6; padding-bottom: 1rem; overflow-x: auto;">
        <?php
        $tabs = [
            '' => ['label' => 'Tất cả', 'icon' => 'fa-globe'],
            'patient_packages' => ['label' => 'Gói Dịch Vụ', 'icon' => 'fa-box-open'],
            'medical_history' => ['label' => 'Bệnh án', 'icon' => 'fa-notes-medical'],
            'appointments' => ['label' => 'Lịch hẹn', 'icon' => 'fa-calendar-check'],
            'patients' => ['label' => 'Khách hàng', 'icon' => 'fa-users'],
            'invoices' => ['label' => 'Thu Chi', 'icon' => 'fa-file-invoice-dollar'],
            'users' => ['label' => 'Nhân sự', 'icon' => 'fa-user-tie']
        ];
        foreach ($tabs as $key => $tab) {
            $is_active = $target_filter === $key;
            $bg = $is_active ? 'var(--primary)' : 'transparent';
            $color = $is_active ? 'white' : '#64748b';
            $border = $is_active ? 'border-color: var(--primary);' : 'border-color: transparent;';
            echo '<a href="?target='.$key.'&action='.$action_filter.'&user_id='.$user_filter.'" style="padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.85rem; font-weight: 700; color: '.$color.'; background: '.$bg.'; text-decoration: none; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s; white-space: nowrap;"><i class="fas '.$tab['icon'].'"></i> '.$tab['label'].'</a>';
        }
        ?>
    </div>

    <!-- Filter Bar -->
    <form method="GET" style="display: flex; gap: 1rem; margin-bottom: 2rem; background: #f8fafc; padding: 1.5rem; border-radius: 16px; border: 1px solid #eef2f6; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 150px;">
            <label style="font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 0.4rem;">Đối tượng</label>
            <select name="target" class="form-input" style="padding: 0.6rem 1rem; border-radius: 10px; font-weight: 600; font-size: 0.85rem;">
                <option value="">Tất cả đối tượng</option>
                <?php foreach ($all_targets as $t): ?>
                    <option value="<?php echo $t; ?>" <?php echo $target_filter === $t ? 'selected' : ''; ?>><?php echo isset($target_map[$t]) ? $target_map[$t] : $t; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex: 1; min-width: 150px;">
            <label style="font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 0.4rem;"><?php echo __('audit.action'); ?></label>
            <select name="action" class="form-input" style="padding: 0.6rem 1rem; border-radius: 10px; font-weight: 600; font-size: 0.85rem;">
                <option value=""><?php echo __('audit.all_actions'); ?></option>
                <?php foreach ($actions as $act): ?>
                    <option value="<?php echo $act; ?>" <?php echo $action_filter == $act ? 'selected' : ''; ?>><?php echo $act; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex: 1; min-width: 150px;">
            <label style="font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 0.4rem;"><?php echo __('audit.actor'); ?></label>
            <select name="user_id" class="form-input" style="padding: 0.6rem 1rem; border-radius: 10px; font-weight: 600; font-size: 0.85rem;">
                <option value=""><?php echo __('audit.all_staff'); ?></option>
                <?php foreach ($users as $u): ?>
                    <option value="<?php echo $u['id']; ?>" <?php echo $user_filter == $u['id'] ? 'selected' : ''; ?>><?php echo e($u['full_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display: flex; align-items: flex-end;">
            <button type="submit" class="btn btn-primary" style="padding: 0.6rem 1.5rem; border-radius: 10px; font-weight: 600; font-size: 0.85rem; height: auto;"><?php echo __('audit.filter'); ?></button>
            <a href="audit_logs.php" class="btn" style="margin-left: 0.5rem; background: #e2e8f0; color: #475569; display: flex; align-items: center; text-decoration: none; border-radius: 10px; padding: 0.6rem 1.5rem; font-weight: 600; font-size: 0.85rem; height: auto;"><?php echo __('audit.clear'); ?></a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th><?php echo __('audit.time'); ?></th>
                    <th><?php echo __('audit.staff'); ?></th>
                    <th><?php echo __('audit.action'); ?></th>
                    <th><?php echo __('audit.target'); ?></th>
                    <th><?php echo __('audit.id'); ?></th>
                    <th><?php echo __('audit.diff'); ?></th>
                    <th><?php echo __('audit.ip'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="7" style="text-align: center; padding: 3rem; color: #94a3b8;"><?php echo __('audit.no_logs'); ?></td></tr>
                <?php endif; ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td style="white-space: nowrap; font-size: 0.8rem; font-weight: 600; color: #64748b;">
                            <?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <div style="width: 28px; height: 28px; background: #eef2ff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; color: #4f46e5; font-weight: 800; border: 1px solid #c7d2fe;">
                                    <?php echo substr($log['user_name'], 0, 1); ?>
                                </div>
                                <span style="font-weight: 700; color: var(--text-main); font-size: 0.85rem;"><?php echo e($log['user_name']); ?></span>
                            </div>
                        </td>
                        <td>
                            <?php 
                            $badge_color = '#64748b';
                            $action_vi = $log['action'];
                            if ($log['action'] == 'create') { $badge_color = '#10b981'; $action_vi = 'TẠO MỚI'; }
                            if ($log['action'] == 'update') { $badge_color = '#3b82f6'; $action_vi = 'CẬP NHẬT'; }
                            if ($log['action'] == 'delete') { $badge_color = '#ef4444'; $action_vi = 'XÓA'; }
                            if ($log['action'] == 'reopen_session') { $badge_color = '#f59e0b'; $action_vi = 'MỞ LẠI PHIÊN'; }
                            ?>
                            <span style="background: <?php echo $badge_color; ?>15; color: <?php echo $badge_color; ?>; padding: 0.25rem 0.6rem; border-radius: 50px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase;">
                                <?php echo $action_vi; ?>
                            </span>
                        </td>
                        <td style="font-size: 0.8rem; font-weight: 600; color: #475569;">
                            <span style="background: #eef2ff; color: #4f46e5; padding: 0.2rem 0.5rem; border-radius: 6px; border: 1px solid #c7d2fe; white-space: nowrap;">
                                <i class="fas fa-layer-group" style="margin-right: 0.3rem;"></i><?php echo isset($target_map[$log['target_table']]) ? $target_map[$log['target_table']] : $log['target_table']; ?>
                            </span>
                        </td>
                        <td style="font-size: 0.8rem; font-weight: 800; color: var(--primary);">
                            #<?php echo $log['target_id']; ?>
                            <?php 
                            // Extract name from JSON
                            $data = json_decode($log['new_data'] ?: $log['old_data'], true);
                            $target_name = '';
                            if (is_array($data)) {
                                $target_name = $data['full_name'] ?? $data['patient_name'] ?? $data['name'] ?? $data['title'] ?? $data['code'] ?? '';
                            }
                            if ($target_name) {
                                echo '<div style="font-size: 0.75rem; color: #64748b; font-weight: 600; margin-top: 0.2rem;">' . e($target_name) . '</div>';
                            }
                            ?>
                        </td>
                        <td>
                            <button class="btn-detail" onclick="showDiff(<?php echo $log['id']; ?>)"><i class="fas fa-eye"></i> <?php echo __('audit.view_detail'); ?></button>
                            <div id="diff-<?php echo $log['id']; ?>" style="display: none;">
                                <div class="diff-container">
                                    <div class="diff-header" style="display: flex; justify-content: space-between; align-items: center;">
                                        <span>Chi tiết thay đổi - <?php echo isset($target_map[$log['target_table']]) ? $target_map[$log['target_table']] : $log['target_table']; ?> #<?php echo $log['target_id']; ?></span>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-bordered" style="width: 100%; border-collapse: collapse;">
                                            <thead>
                                                <tr style="background: #f8fafc;">
                                                    <th style="padding: 1rem; border: 1px solid #e2e8f0; width: 30%;">Trường Dữ Liệu</th>
                                                    <th style="padding: 1rem; border: 1px solid #e2e8f0; width: 35%;">Dữ liệu Cũ</th>
                                                    <th style="padding: 1rem; border: 1px solid #e2e8f0; width: 35%; color: #10b981;">Dữ liệu Mới</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $old_arr = is_array(json_decode($log['old_data'], true)) ? json_decode($log['old_data'], true) : [];
                                                $new_arr = is_array(json_decode($log['new_data'], true)) ? json_decode($log['new_data'], true) : [];
                                                $all_keys = array_unique(array_merge(array_keys($old_arr), array_keys($new_arr)));
                                                
                                                foreach ($all_keys as $k):
                                                    $o_val = isset($old_arr[$k]) ? (is_array($old_arr[$k]) ? json_encode($old_arr[$k], JSON_UNESCAPED_UNICODE) : $old_arr[$k]) : '<i>(Trống)</i>';
                                                    $n_val = isset($new_arr[$k]) ? (is_array($new_arr[$k]) ? json_encode($new_arr[$k], JSON_UNESCAPED_UNICODE) : $new_arr[$k]) : '<i>(Trống)</i>';
                                                    
                                                    if ($o_val !== $n_val):
                                                ?>
                                                <tr>
                                                    <td style="padding: 0.75rem; border: 1px solid #e2e8f0; font-weight: 700; color: #475569;"><?php echo e($k); ?></td>
                                                    <td style="padding: 0.75rem; border: 1px solid #e2e8f0; color: #ef4444; background: #fef2f2; word-break: break-word;"><?php echo $o_val; ?></td>
                                                    <td style="padding: 0.75rem; border: 1px solid #e2e8f0; color: #10b981; background: #ecfdf5; word-break: break-word; font-weight: 600;"><?php echo $n_val; ?></td>
                                                </tr>
                                                <?php endif; endforeach; ?>
                                                
                                                <?php if (empty($all_keys)): ?>
                                                    <tr><td colspan="3" style="text-align: center; padding: 2rem;">Không có dữ liệu chi tiết</td></tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td style="font-size: 0.75rem; color: #94a3b8; font-family: monospace;"><?php echo $log['ip_address']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div style="margin-top: 1rem;">
        <?php echo render_pagination($total_count, $limit, $page); ?>
    </div>
</div>

<style>
.btn-detail {
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    color: #475569;
    padding: 0.25rem 0.75rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-detail:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}
.diff-container {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    width: 80%;
    max-width: 1000px;
    height: 80%;
    z-index: 1000;
    padding: 2rem;
    border-radius: 20px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    overflow-y: auto;
}
.diff-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    z-index: 999;
}
.diff-header {
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--primary);
    margin-bottom: 1.5rem;
    border-bottom: 2px solid #f1f5f9;
    padding-bottom: 1rem;
}
.diff-sub {
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    margin-bottom: 0.5rem;
    color: #64748b;
}
.diff-pre {
    background: #f8fafc;
    padding: 1rem;
    border-radius: 12px;
    font-size: 0.75rem;
    border: 1px solid #e2e8f0;
    white-space: pre-wrap;
    word-break: break-all;
    max-height: 500px;
    overflow-y: auto;
}
</style>

<script>
function showDiff(id) {
    const detail = document.getElementById('diff-' + id).innerHTML;
    const overlay = document.createElement('div');
    overlay.className = 'diff-overlay';
    overlay.onclick = () => document.body.removeChild(overlay);
    
    const wrapper = document.createElement('div');
    wrapper.innerHTML = detail;
    overlay.appendChild(wrapper);
    
    document.body.appendChild(overlay);
}
</script>

<?php require_once '../../templates/footer.php'; ?>
