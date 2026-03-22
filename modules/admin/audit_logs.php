<?php
// modules/admin/audit_logs.php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Only Admin can access
if (!has_role('admin')) {
    set_flash('Bạn không có quyền truy cập trang này.', 'error');
    redirect('../../index.php');
}

$db = getDB();
$page_title = __('audit.title') . ' — ' . __('audit_logs');
$current_page = 'admin_audit';

// Filters
$action_filter = $_GET['action'] ?? '';
$user_filter = $_GET['user_id'] ?? '';

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

$where_sql = implode(" AND ", $where);

// Base Query
$query = "
    SELECT a.*, u.full_name as user_name 
    FROM audit_logs a
    JOIN users u ON a.user_id = u.id
    WHERE $where_sql
    ORDER BY a.created_at DESC
    LIMIT 100
";

$stmt = $db->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get unique actions for filter
$actions = $db->query("SELECT DISTINCT action FROM audit_logs")->fetchAll(PDO::FETCH_COLUMN);
$users = $db->query("SELECT id, full_name FROM users")->fetchAll();

require_once '../../templates/header.php';
?>

<div class="card" style="background: var(--glass-bg); backdrop-filter: blur(20px);">
    <div style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 style="margin: 0; font-weight: 800; color: var(--primary);"><i class="fas fa-history"></i> <?php echo __('audit.title'); ?></h2>
            <p style="color: var(--text-muted); margin-top: 0.25rem;"><?php echo __('audit.subtitle'); ?></p>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" style="display: flex; gap: 1rem; margin-bottom: 2rem; background: #f8fafc; padding: 1.5rem; border-radius: 16px; border: 1px solid #eef2f6;">
        <div style="flex: 1;">
            <label style="font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase;"><?php echo __('audit.action'); ?></label>
            <select name="action" class="form-input" style="height: 42px;">
                <option value=""><?php echo __('audit.all_actions'); ?></option>
                <?php foreach ($actions as $act): ?>
                    <option value="<?php echo $act; ?>" <?php echo $action_filter == $act ? 'selected' : ''; ?>><?php echo $act; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex: 1;">
            <label style="font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase;"><?php echo __('audit.actor'); ?></label>
            <select name="user_id" class="form-input" style="height: 42px;">
                <option value=""><?php echo __('audit.all_staff'); ?></option>
                <?php foreach ($users as $u): ?>
                    <option value="<?php echo $u['id']; ?>" <?php echo $user_filter == $u['id'] ? 'selected' : ''; ?>><?php echo e($u['full_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display: flex; align-items: flex-end;">
            <button type="submit" class="btn btn-primary" style="height: 42px; padding: 0 1.5rem;"><?php echo __('audit.filter'); ?></button>
            <a href="audit_logs.php" class="btn" style="height: 42px; margin-left: 0.5rem; background: #e2e8f0; color: #475569;"><?php echo __('audit.clear'); ?></a>
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
                            if ($log['action'] == 'create') $badge_color = '#10b981';
                            if ($log['action'] == 'update') $badge_color = '#3b82f6';
                            if ($log['action'] == 'reopen_session') $badge_color = '#f59e0b';
                            ?>
                            <span style="background: <?php echo $badge_color; ?>15; color: <?php echo $badge_color; ?>; padding: 0.25rem 0.6rem; border-radius: 50px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase;">
                                <?php echo $log['action']; ?>
                            </span>
                        </td>
                        <td style="font-size: 0.8rem; font-weight: 600; color: #475569;"><?php echo $log['target_table']; ?></td>
                        <td style="font-size: 0.8rem; font-weight: 800; color: var(--primary);">#<?php echo $log['target_id']; ?></td>
                        <td>
                            <button class="btn-detail" onclick="showDiff(<?php echo $log['id']; ?>)"><?php echo __('audit.view_detail'); ?></button>
                            <div id="diff-<?php echo $log['id']; ?>" style="display: none;">
                                <div class="diff-container">
                                    <div class="diff-header"><?php echo __('audit.diff_header'); ?></div>
                                    <div style="display: flex; gap: 1rem;">
                                        <div style="flex: 1;">
                                            <div class="diff-sub"><?php echo __('audit.old'); ?></div>
                                            <pre class="diff-pre"><?php echo e(json_encode(json_decode($log['old_data']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                                        </div>
                                        <div style="flex: 1;">
                                            <div class="diff-sub" style="color: #10b981;"><?php echo __('audit.new'); ?></div>
                                            <pre class="diff-pre" style="border-color: #10b981;"><?php echo e(json_encode(json_decode($log['new_data']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                                        </div>
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
