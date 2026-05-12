<?php
// modules/cskh/index.php — CSKH Action Inbox (Call Center Mode)
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('view_patients');

$page_title = 'Chăm sóc Khách hàng';
$current_page = 'cskh';
// Giao diện Action Inbox không cần file main.js/footer chuẩn ảnh hưởng chiều cao, 
// ta dùng header để lấy nav, nhưng sẽ custom layout để fix vừa màn hình.
require_once '../../templates/header.php';

$db = getDB();

// Filters
$filter_status = $_GET['status'] ?? 'active'; // active = pending+in_progress+overdue
$filter_color = $_GET['color'] ?? '';
$search = $_GET['search'] ?? '';
$view = $_GET['view'] ?? 'list';
$sel_month = (int)($_GET['sel_month'] ?? date('n'));
$sel_year = (int)($_GET['sel_year'] ?? date('Y'));

// Count by color
$count_sql = "SELECT priority_color, COUNT(*) as cnt FROM cskh_tasks WHERE status IN ('pending','in_progress','overdue') GROUP BY priority_color";
$color_counts = [];
foreach ($db->query($count_sql)->fetchAll() as $row) {
    $color_counts[$row['priority_color']] = $row['cnt'];
}
$total_active = array_sum($color_counts);

// Query Base
$conditions = [];
$params = [];

if ($filter_status === 'active') {
    $conditions[] = "t.status IN ('pending','in_progress','overdue')";
} elseif ($filter_status === 'completed') {
    $conditions[] = "t.status = 'completed'";
} elseif ($filter_status === 'gray') {
    $conditions[] = "t.priority_color = 'gray'";
} else {
    $conditions[] = "1=1";
}

if ($filter_color && $filter_color !== 'all') {
    $conditions[] = "t.priority_color = ?";
    $params[] = $filter_color;
}

if ($search) {
    $conditions[] = "(p.full_name LIKE ? OR p.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where = implode(' AND ', $conditions);

// Pagination vs All
$limit = ($view === 'calendar') ? 9999 : 50; // Inbox mode loads top 50 tasks to chew through
$page = max(1, (int)($_GET['page'] ?? 1));

// If Calendar View overrides
$where_calendar = $where;
$params_calendar = $params;
if ($view === 'calendar') {
    $where_calendar .= " AND MONTH(t.due_date) = ? AND YEAR(t.due_date) = ?";
    $params_calendar[] = $sel_month;
    $params_calendar[] = $sel_year;
    
    $count_stmt = $db->prepare("SELECT COUNT(*) FROM cskh_tasks t JOIN patients p ON t.patient_id = p.id WHERE $where_calendar");
    $count_stmt->execute($params_calendar);
    $total_count = $count_stmt->fetchColumn();
    
    $sql = "SELECT t.*, p.full_name, p.phone, r.rule_name, r.trigger_event, r.retry_max, r.retry_interval_days
            FROM cskh_tasks t JOIN patients p ON t.patient_id = p.id JOIN cskh_rules r ON t.rule_id = r.id
            WHERE $where_calendar ORDER BY FIELD(t.priority_color, 'red','yellow','green','gray'), FIELD(t.status, 'overdue','pending','in_progress','completed','canceled'), t.due_date ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params_calendar);
    $tasks = $stmt->fetchAll();
} else {
    $count_stmt = $db->prepare("SELECT COUNT(*) FROM cskh_tasks t JOIN patients p ON t.patient_id = p.id WHERE $where");
    $count_stmt->execute($params);
    $total_count = $count_stmt->fetchColumn();
    
    $sql = "SELECT t.*, p.full_name, p.phone, r.rule_name, r.trigger_event, r.retry_max, r.retry_interval_days
            FROM cskh_tasks t JOIN patients p ON t.patient_id = p.id JOIN cskh_rules r ON t.rule_id = r.id
            WHERE $where ORDER BY FIELD(t.priority_color, 'red','yellow','green','gray'), FIELD(t.status, 'overdue','pending','in_progress','completed','canceled'), t.due_date ASC" . get_sql_limit($limit, $page);
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();
}

// Eager Loading (PHP Batching) to solve N+1 issue
if (!empty($tasks)) {
    $patient_ids = array_unique(array_column($tasks, 'patient_id'));
    if (!empty($patient_ids)) {
        $in_clause = implode(',', array_map('intval', $patient_ids));
        
        // 1. Last session
        $stmt1 = $db->query("SELECT patient_id, MAX(session_date) FROM medical_sessions WHERE patient_id IN ($in_clause) GROUP BY patient_id");
        $last_sessions = $stmt1->fetchAll(PDO::FETCH_KEY_PAIR);

        // 2. Next Appt
        $stmt2 = $db->query("SELECT patient_id, MIN(appointment_date) FROM appointments WHERE patient_id IN ($in_clause) AND appointment_date > NOW() AND status IN ('scheduled','confirmed') GROUP BY patient_id");
        $next_appts = $stmt2->fetchAll(PDO::FETCH_KEY_PAIR);

        // 3. Remaining sessions
        $stmt3 = $db->query("SELECT patient_id, SUM(sessions_remaining) FROM patient_packages WHERE patient_id IN ($in_clause) AND sessions_remaining > 0 GROUP BY patient_id");
        $remaining_sessions = $stmt3->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach ($tasks as &$t) {
            $pid = $t['patient_id'];
            $t['last_session'] = $last_sessions[$pid] ?? null;
            $t['next_appointment'] = $next_appts[$pid] ?? null;
            $t['remaining_sessions'] = $remaining_sessions[$pid] ?? null;
        }
        unset($t);
    }
}

$color_labels = [
    'red' => ['🔴 Cần xử lý ngay', '#dc2626', '#fef2f2', '#fecaca'],
    'yellow' => ['🟡 Đang chờ chốt', '#d97706', '#fffbeb', '#fde68a'],
    'green' => ['🟢 Đã ổn định', '#16a34a', '#f0fdf4', '#bbf7d0'],
    'gray' => ['⚪ Lưu ý / Tạm ẩn', '#6b7280', '#f9fafb', '#e5e7eb'],
];

$status_labels = [
    'pending' => ['Chờ xử lý', '#f59e0b', '#fffbeb'],
    'in_progress' => ['Đang gọi', '#3b82f6', '#eff6ff'],
    'overdue' => ['Quá hạn', '#ef4444', '#fef2f2'],
    'completed' => ['Hoàn thành', '#22c55e', '#f0fdf4'],
    'canceled' => ['Bỏ qua', '#9ca3af', '#f9fafb'],
];

// Helper for JS Object
$js_tasks = [];
foreach ($tasks as $t) {
    $t['cl_text'] = $color_labels[$t['priority_color']][1] ?? '#64748b';
    $t['sl_text'] = $status_labels[$t['status']][0] ?? 'Chờ xử lý';
    $t['sl_bg'] = $status_labels[$t['status']][2] ?? '#f1f5f9';
    $t['sl_color'] = $status_labels[$t['status']][1] ?? '#475569';
    $js_tasks[$t['id']] = $t;
}
?>

<style>
/* CSS for the Inbox Layout */
.task-btn {
    padding: 0.5rem 1rem; border-radius: 10px; font-weight: 700; font-size: 0.8rem;
    border: none; cursor: pointer; transition: all 0.2s; text-decoration: none;
    display: inline-flex; align-items: center; gap: 0.4rem; justify-content: center;
}
.task-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.task-btn-primary { background: linear-gradient(135deg, #4f46e5, #6366f1); color: white; }
.btn-white { background: white; color: var(--primary); }

.cskh-tabs { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.cskh-tab {
    padding: 0.5rem 1rem; border-radius: 12px; font-weight: 700; font-size: 0.8rem;
    text-decoration: none; transition: all 0.2s; border: 2px solid transparent;
    display: flex; align-items: center; gap: 0.5rem; white-space: nowrap;
}
.cskh-tab:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
.cskh-tab.active { border-color: currentColor; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }

/* Split Pane specific */
.split-container { display: flex; gap: 1.5rem; height: calc(100vh - 160px); min-height: 500px; }
.left-pane {
    flex: 0 0 380px; background: white; border-radius: 16px; border: 1px solid #e2e8f0;
    display: flex; flex-direction: column; box-shadow: 0 4px 15px rgba(0,0,0,0.02); overflow: hidden;
}
.right-pane {
    flex: 1; background: white; border-radius: 16px; border: 1px solid #e2e8f0;
    display: flex; flex-direction: column; position: relative; box-shadow: 0 4px 15px rgba(0,0,0,0.02); overflow: hidden;
}

.task-list-item {
    padding: 1rem 1.25rem; border-bottom: 1px solid #f1f5f9; cursor: pointer;
    transition: all 0.2s; display: flex; flex-direction: column; gap: 0.4rem;
    border-left: 4px solid transparent; position: relative;
}
.task-list-item:hover { background: #f8fafc; }
.task-list-item.selected { background: #eff6ff; border-left-color: #3b82f6; }
.task-list-item.anim-leave { animation: slideOutLeft 0.3s ease forwards; opacity: 0; }

@keyframes slideOutLeft {
    to { transform: translateX(-100%); margin-bottom: -100px; padding: 0; border: 0; }
}

.t-name { font-weight: 800; font-size: 0.95rem; color: #0f172a; }
.t-phone { font-size: 0.75rem; color: #64748b; font-weight: 600; display: flex; align-items: center; gap: 0.3rem; }
.t-badge { font-size: 0.65rem; font-weight: 700; padding: 2px 6px; border-radius: 4px; background: #e2e8f0; color: #475569; }

/* Right Pane Content */
.rp-header { padding: 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: flex-start; background: #f8fafc; }
.summary-box { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem; flex: 1; text-align: center; }
.summary-val { font-size: 1.1rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem; }

/* Radio Checkboxes */
.rp-radio-label {
    display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1rem; border: 2px solid #e2e8f0; 
    border-radius: 12px; cursor: pointer; font-weight: 700; font-size: 0.9rem; transition: all 0.2s;
}
.rp-radio-label:hover { background: #f8fafc; }
input[type="radio"]:checked + .rp-radio-bg-green { border-color: #22c55e; background: #f0fdf4; color: #166534; }
input[type="radio"]:checked + .rp-radio-bg-yellow { border-color: #f59e0b; background: #fffbeb; color: #92400e; }
input[type="radio"]:checked + .rp-radio-bg-gray { border-color: #64748b; background: #f8fafc; color: #475569; }
input[type="radio"]:checked + .rp-radio-bg-blue { border-color: #3b82f6; background: #eff6ff; color: #1e40af; }

/* Custom Checkbox */
.cb-label {
    display: flex; align-items: center; gap: 0.6rem; font-size: 0.85rem; font-weight: 600; color: #475569; cursor: pointer; padding: 0.4rem 0;
}
.cb-label input { width: 16px; height: 16px; cursor: pointer; }
</style>

<!-- Top Toolbar -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
    <div class="cskh-tabs">
        <a href="?view=<?php echo $view; ?>&status=active" class="cskh-tab <?php echo $filter_status === 'active' && !$filter_color ? 'active' : ''; ?>" style="background: #e2e8f0; color: #475569;">
            Tất cả <span style="background: #475569; color: white; padding: 2px 6px; border-radius: 6px; font-size: 0.65rem; margin-left: 0.2rem;"><?php echo $total_active; ?></span>
        </a>
        <?php foreach ($color_labels as $color => $info): ?>
        <a href="?view=<?php echo $view; ?>&status=active&color=<?php echo $color; ?>" class="cskh-tab <?php echo $filter_color === $color ? 'active' : ''; ?>" style="background: <?php echo $info[2]; ?>; color: <?php echo $info[1]; ?>;">
            <?php echo $info[0]; ?> <span style="background: <?php echo $info[1]; ?>; color: white; padding: 2px 6px; border-radius: 6px; font-size: 0.65rem;"><?php echo $color_counts[$color] ?? 0; ?></span>
        </a>
        <?php endforeach; ?>
        <a href="?view=<?php echo $view; ?>&status=completed" class="cskh-tab <?php echo $filter_status === 'completed' ? 'active' : ''; ?>" style="background: #f0fdf4; color: #16a34a;">
            ✅ Đã xong
        </a>
    </div>
    
    <form method="GET" style="display: flex; gap: 0.5rem; align-items: center;">
        <input type="hidden" name="status" value="<?php echo e($filter_status); ?>">
        <?php if ($filter_color): ?><input type="hidden" name="color" value="<?php echo e($filter_color); ?>"><?php endif; ?>
        <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="🔍 Tìm tên/SĐT..." class="form-input" style="width: 200px; height: 38px; border-radius: 10px; font-size: 0.85rem;">
        <button type="submit" class="task-btn task-btn-primary" style="height: 38px;">Tìm</button>
        
        <div style="width: 1px; height: 30px; background: #e2e8f0; margin: 0 0.2rem;"></div>
        
        <div style="display: flex; background: #e2e8f0; padding: 4px; border-radius: 10px;">
            <a href="?view=list&status=<?php echo urlencode($filter_status); ?>&color=<?php echo urlencode($filter_color); ?>" class="task-btn <?php echo $view === 'list' ? 'btn-white' : ''; ?>" style="height: 30px; padding: 0 0.75rem; color: <?php echo $view === 'list' ? 'var(--primary)' : '#64748b'; ?>;">
                <i class="fas fa-inbox"></i> Inbox
            </a>
            <a href="?view=calendar&status=<?php echo urlencode($filter_status); ?>&color=<?php echo urlencode($filter_color); ?>&sel_month=<?php echo $sel_month; ?>&sel_year=<?php echo $sel_year; ?>" class="task-btn <?php echo $view === 'calendar' ? 'btn-white' : ''; ?>" style="height: 30px; padding: 0 0.75rem; color: <?php echo $view === 'calendar' ? 'var(--primary)' : '#64748b'; ?>;">
                <i class="fas fa-calendar-alt"></i> Lịch
            </a>
        </div>
    </form>
</div>

<?php if ($view === 'calendar'): ?>
    <!-- CALENDAR VIEW (Retained heavily from previous build) -->
    <div class="card" style="padding: 1.5rem; border-radius: 16px; flex: 1; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: #0f172a;">Lịch gọi chăm sóc</h3>
            <div style="display: flex; gap: 0.5rem;">
                <?php 
                $prev_month = $sel_month - 1; $prev_year = $sel_year;
                if ($prev_month < 1) { $prev_month = 12; $prev_year--; }
                $next_month = $sel_month + 1; $next_year = $sel_year;
                if ($next_month > 12) { $next_month = 1; $next_year++; }
                ?>
                <a href="?view=calendar&status=<?php echo urlencode($filter_status); ?>&color=<?php echo urlencode($filter_color); ?>&sel_month=<?php echo $prev_month; ?>&sel_year=<?php echo $prev_year; ?>" class="task-btn" style="background:#f1f5f9; color:#475569;"><i class="fas fa-chevron-left"></i></a>
                <span style="font-weight: 800; display: flex; align-items: center; padding: 0 1rem;">Tháng <?php echo $sel_month; ?>/<?php echo $sel_year; ?></span>
                <a href="?view=calendar&status=<?php echo urlencode($filter_status); ?>&color=<?php echo urlencode($filter_color); ?>&sel_month=<?php echo $next_month; ?>&sel_year=<?php echo $next_year; ?>" class="task-btn" style="background:#f1f5f9; color:#475569;"><i class="fas fa-chevron-right"></i></a>
            </div>
        </div>

        <?php
        $first_day_str = sprintf("%04d-%02d-01", $sel_year, $sel_month);
        $first_day_ts = strtotime($first_day_str);
        $first_day_of_week = date('N', $first_day_ts) - 1; 
        $days_in_month = date('t', $first_day_ts);
        
        $grouped_tasks = [];
        foreach ($tasks as $t) {
            $day = (int)date('d', strtotime($t['due_date']));
            $grouped_tasks[$day][] = $t;
        }
        ?>
        <div style="display: grid; grid-template-columns: repeat(7, minmax(100px, 1fr)); gap: 10px;">
            <?php foreach (['Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7', 'CN'] as $lbl): ?>
                <div style="text-align: center; font-size: 0.75rem; font-weight: 800; color: #94a3b8; padding: 0.5rem; text-transform: uppercase;"><?php echo $lbl; ?></div>
            <?php endforeach; ?>
            <?php for ($i = 0; $i < $first_day_of_week; $i++): ?>
                <div style="min-height: 100px; background: #f8fafc; border-radius: 12px; border: 1px dashed #e2e8f0;"></div>
            <?php endfor; ?>
            <?php for ($d = 1; $d <= $days_in_month; $d++): 
                $current_date_str = sprintf("%04d-%02d-%02d", $sel_year, $sel_month, $d);
                $is_today = ($current_date_str == date('Y-m-d'));
                $day_tasks = isset($grouped_tasks[$d]) ? $grouped_tasks[$d] : [];
            ?>
                <div style="min-height: 120px; background: white; border-radius: 12px; border: 2px solid <?php echo $is_today ? 'var(--primary)' : '#eef2f6'; ?>; padding: 0.6rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <span style="font-weight: 800; font-size: 1.1rem; color: <?php echo $is_today ? 'var(--primary)' : '#0f172a'; ?>;"><?php echo $d; ?></span>
                        <?php if (count($day_tasks) > 0): ?>
                            <span style="background: <?php echo $is_today ? 'var(--primary)' : '#f1f5f9'; ?>; color: <?php echo $is_today ? 'white' : '#475569'; ?>; font-size: 0.7rem; font-weight: 800; padding: 0.15rem 0.4rem; border-radius: 6px;"><?php echo count($day_tasks); ?></span>
                        <?php endif; ?>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 4px;">
                        <?php foreach ($day_tasks as $t): 
                                $cl = $color_labels[$t['priority_color']] ?? $color_labels['gray'];
                                $is_done = ($t['status'] === 'completed' || $t['status'] === 'canceled');
                        ?>
                            <div title="<?php echo e($t['full_name']); ?>" 
                                 onclick="location.href='?view=list&search=<?php echo urlencode($t['phone']); ?>'"
                                 style="font-size: 0.7rem; padding: 4px 6px; border-radius: 6px; cursor: pointer; border-left: 3px solid <?php echo $cl[1]; ?>; background: <?php echo $is_done ? '#f8fafc' : $cl[2]; ?>; color: <?php echo $is_done ? '#94a3b8' : '#0f172a'; ?>; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 600; <?php echo $is_done ? 'text-decoration: line-through;' : ''; ?>">
                                <?php echo e($t['full_name']); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>
<?php else: ?>
    <!-- INBOX MODE: Split Pane -->
    <div class="split-container">
        <!-- LEFT: List -->
        <div class="left-pane">
            <div style="padding: 1rem; border-bottom: 1px solid #e2e8f0; background: #f8fafc; font-weight: 800; color: #0f172a; display: flex; justify-content: space-between;">
                <span>Danh sách chờ gọi</span>
                <span style="color: #64748b; font-size: 0.8rem; font-weight: 700;"><?php echo count($tasks); ?> / <?php echo $total_count; ?></span>
            </div>
            <div style="flex: 1; overflow-y: auto;" id="left-task-list">
                <?php if (empty($tasks)): ?>
                    <div style="padding: 3rem 1rem; text-align: center; color: #94a3b8;">
                        <i class="fas fa-check-circle" style="font-size: 2.5rem; margin-bottom: 1rem; color: #cbd5e1;"></i>
                        <div style="font-size: 0.95rem; font-weight: 700;">Hòm thư trống</div>
                        <div style="font-size: 0.8rem;">Bạn đã xử lý xong công việc.</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($tasks as $t): 
                        $cl = $color_labels[$t['priority_color']] ?? $color_labels['gray'];
                    ?>
                    <div class="task-list-item" id="nav-item-<?php echo $t['id']; ?>" onclick="loadTask(<?php echo $t['id']; ?>)">
                        <div style="position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: <?php echo $cl[1]; ?>;"></div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div class="t-name"><?php echo e($t['full_name']); ?></div>
                            <div class="t-badge" style="background: <?php echo $t['sl_bg']; ?>; color: <?php echo $t['sl_color']; ?>;"><?php echo date('d/m', strtotime($t['due_date'])); ?></div>
                        </div>
                        <div class="t-phone"><i class="fas fa-phone-alt"></i> <?php echo e($t['phone']); ?></div>
                        <div style="font-size: 0.75rem; color: #1e293b; font-weight: 600; display: flex; align-items: center; gap: 0.3rem; margin-top: 0.2rem;">
                            <i class="fas fa-tag" style="color: #cbd5e1;"></i> <?php echo e($t['rule_name']); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <?php if ($total_count > $limit): ?>
            <div style="padding: 0.75rem; border-top: 1px solid #e2e8f0; text-align: center; background: #f8fafc;">
                <?php echo render_pagination($total_count, $limit, $page); ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- RIGHT: Action Screen -->
        <div class="right-pane">
            <!-- Empty State -->
            <div id="rp-empty" style="position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #f8fafc; z-index: 10;">
                <i class="fas fa-headset" style="font-size: 4rem; color: #cbd5e1; margin-bottom: 1.5rem;"></i>
                <h3 style="margin: 0; color: #475569; font-weight: 800;">Chọn Khách Hàng</h3>
                <p style="color: #94a3b8; font-size: 0.9rem; margin-top: 0.5rem;">Click vào danh sách bên trái để bắt đầu chăm sóc</p>
            </div>

            <!-- Active Form -->
            <div id="rp-content" style="display: none; height: 100%; flex-direction: column;">
                <div class="rp-header">
                    <div>
                        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                            <h2 id="r-name" style="margin: 0; font-size: 1.6rem; font-weight: 900; color: #0f172a;">Name</h2>
                            <span id="r-status" class="t-badge" style="font-size: 0.75rem; padding: 4px 8px;">Status</span>
                        </div>
                        <div style="display: flex; gap: 1.5rem; color: #64748b; font-weight: 600; font-size: 0.9rem;">
                            <span id="r-phone"><i class="fas fa-phone-alt"></i> Phone</span>
                            <span id="r-rule" style="color: #4f46e5;"><i class="fas fa-tag"></i> Rule</span>
                            <span id="r-retry" style="color: #dc2626; display: none;"><i class="fas fa-redo"></i> Lần gọi: <span></span></span>
                        </div>
                    </div>
                    <div>
                        <button class="task-btn task-btn-secondary" onclick="skipTask()">
                            <i class="fas fa-forward"></i> Bỏ qua
                        </button>
                    </div>
                </div>

                <div style="flex: 1; overflow-y: auto; padding: 1.5rem; background: #f8fafc;">
                    
                    <!-- Medical Summary Cards -->
                    <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem;">
                        <div class="summary-box">
                            <div style="font-size: 0.8rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Khám lần cuối</div>
                            <div class="summary-val" id="r-last">—</div>
                        </div>
                        <div class="summary-box">
                            <div style="font-size: 0.8rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Lịch hẹn tới</div>
                            <div class="summary-val" id="r-next">—</div>
                        </div>
                        <div class="summary-box">
                            <div style="font-size: 0.8rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Gói còn lại</div>
                            <div class="summary-val" id="r-remain">—</div>
                        </div>
                    </div>

                    <!-- Action Form -->
                    <form id="actionForm" style="background: white; padding: 1.5rem; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 15px rgba(0,0,0,0.02);">
                        <input type="hidden" name="task_id" id="r-task-id">
                        <input type="hidden" name="ajax" value="1">

                        <!-- Call Interaction Result -->
                        <div style="font-weight: 800; color: #0f172a; margin-bottom: 0.8rem; font-size: 1.05rem;">Trạng thái cuộc gọi</div>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; margin-bottom: 1.5rem;">
                            <label class="rp-radio-label rp-radio-bg-green" style="border-color: #22c55e; background: #f0fdf4; color: #166534;">
                                <input type="radio" name="interaction_result" value="answered" checked style="display: none;">
                                <i class="fas fa-headset text-lg"></i> KH Nghe máy
                            </label>
                            <label class="rp-radio-label rp-radio-bg-yellow">
                                <input type="radio" name="interaction_result" value="busy" style="display: none;">
                                <i class="fas fa-phone-slash text-lg"></i> Bận / Cúp máy
                            </label>
                            <label class="rp-radio-label rp-radio-bg-gray">
                                <input type="radio" name="interaction_result" value="no_answer" style="display: none;">
                                <i class="fas fa-phone-volume text-lg"></i> Không mầm / Sai số
                            </label>
                        </div>

                        <!-- Call Outcome -->
                        <div id="outcome-box">
                            <div style="font-weight: 800; color: #0f172a; margin-bottom: 0.8rem; font-size: 1.05rem;">Kết quả & Thái độ</div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1.5rem;">
                                <label class="rp-radio-label rp-radio-bg-green" style="border-color: #22c55e; background: #f0fdf4; color: #166534;">
                                    <input type="radio" name="call_outcome" value="booked" checked style="display: none;">
                                    <div>
                                        <div style="font-size: 0.95rem; display: flex; align-items: center; gap: 0.5rem;"><i class="fas fa-calendar-check"></i> Đã chốt hẹn</div>
                                        <div style="font-size: 0.75rem; font-weight: 600; opacity: 0.8; margin-top: 0.2rem;">Hoàn thành, dán thẻ Xanh</div>
                                    </div>
                                </label>
                                <label class="rp-radio-label rp-radio-bg-yellow">
                                    <input type="radio" name="call_outcome" value="thinking" style="display: none;">
                                    <div>
                                        <div style="font-size: 0.95rem; display: flex; align-items: center; gap: 0.5rem;"><i class="fas fa-comment-dots"></i> Cần suy nghĩ thêm</div>
                                        <div style="font-size: 0.75rem; font-weight: 600; opacity: 0.8; margin-top: 0.2rem;">Tự dời ngày gọi sang T+2</div>
                                    </div>
                                </label>
                                <label class="rp-radio-label rp-radio-bg-gray">
                                    <input type="radio" name="call_outcome" value="refused" style="display: none;">
                                    <div>
                                        <div style="font-size: 0.95rem; display: flex; align-items: center; gap: 0.5rem;"><i class="fas fa-ban"></i> Từ chối điều trị tiếp</div>
                                        <div style="font-size: 0.75rem; font-weight: 600; opacity: 0.8; margin-top: 0.2rem;">Lưu kho, dán thẻ Xám</div>
                                    </div>
                                </label>
                                <label class="rp-radio-label rp-radio-bg-blue">
                                    <input type="radio" name="call_outcome" value="info_only" style="display: none;">
                                    <div>
                                        <div style="font-size: 0.95rem; display: flex; align-items: center; gap: 0.5rem;"><i class="fas fa-heart"></i> Chỉ hỏi thăm sức khỏe</div>
                                        <div style="font-size: 0.75rem; font-weight: 600; opacity: 0.8; margin-top: 0.2rem;">Hoàn thành kịch bản</div>
                                    </div>
                                </label>
                            </div>

                            <!-- Care Checklist -->
                            <div style="font-weight: 800; color: #0f172a; margin-bottom: 0.8rem; font-size: 1.05rem;">Checklist chăm sóc</div>
                            <div style="margin-bottom: 1.5rem; display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                                <label class="cb-label"><input type="checkbox" name="remind_rest" value="1"> 💤 Nhắc nghỉ ngơi, tránh bê vác</label>
                                <label class="cb-label"><input type="checkbox" name="remind_exercise" value="1"> 🏃 Nhắc vận động nhẹ, giãn cơ</label>
                                <label class="cb-label"><input type="checkbox" name="remind_review" value="1"> ⭐ Xin review bản đồ Google</label>
                                <label class="cb-label"><input type="checkbox" name="remind_package" value="1"> 📦 Cảnh báo sắp hết buổi trị liệu</label>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div style="font-weight: 800; color: #0f172a; margin-bottom: 0.8rem; font-size: 1.05rem;">Ghi chú tình trạng</div>
                        <textarea name="notes" rows="3" class="form-input" style="border-radius: 12px; margin-bottom: 1.5rem; resize: vertical; border: 2px solid #e2e8f0; background: #f8fafc;" placeholder="Nhập phàn nàn của khách, yêu cầu đặc biệt..."></textarea>

                        <button type="submit" id="btn-submit" class="task-btn task-btn-primary" style="width: 100%; height: 50px; font-size: 1.1rem; border-radius: 14px;">
                            <i class="fas fa-paper-plane"></i> LƯU & GỌI NGƯỜI TIẾP THEO
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
const tasksDataset = <?php echo json_encode($js_tasks, JSON_UNESCAPED_UNICODE); ?>;
let currentTaskId = null;

function loadTask(id) {
    if (!tasksDataset[id]) return;
    const t = tasksDataset[id];
    currentTaskId = id;
    
    // Highlight list item
    document.querySelectorAll('.task-list-item').forEach(el => el.classList.remove('selected'));
    const itemEl = document.getElementById('nav-item-' + id);
    if(itemEl) itemEl.classList.add('selected');

    // Hide empty, show content
    document.getElementById('rp-empty').style.display = 'none';
    document.getElementById('rp-content').style.display = 'flex';

    // Populate data
    document.getElementById('r-task-id').value = t.id;
    document.getElementById('r-name').textContent = t.full_name;
    document.getElementById('r-phone').innerHTML = `<i class="fas fa-phone-alt"></i> ${t.phone}`;
    document.getElementById('r-rule').innerHTML = `<i class="fas fa-tag"></i> ${t.rule_name}`;
    
    document.getElementById('r-status').textContent = t.sl_text;
    document.getElementById('r-status').style.background = t.sl_bg;
    document.getElementById('r-status').style.color = t.sl_color;

    if (t.retry_count > 0) {
        document.getElementById('r-retry').style.display = 'inline-block';
        document.getElementById('r-retry').querySelector('span').textContent = `${t.retry_count}/${t.retry_max}`;
    } else {
        document.getElementById('r-retry').style.display = 'none';
    }

    document.getElementById('r-last').textContent = t.last_session ? new Date(t.last_session).toLocaleDateString('vi-VN') : 'Chưa khám';
    document.getElementById('r-next').textContent = t.next_appointment ? new Date(t.next_appointment).toLocaleDateString('vi-VN') : 'Không có';
    document.getElementById('r-remain').textContent = t.remaining_sessions !== null ? `${t.remaining_sessions} buổi` : '—';
    
    // Reset Form
    document.getElementById('actionForm').reset();
    
    // Radio UI Reset
    document.querySelectorAll('.rp-radio-label').forEach(lbl => {
        lbl.style.borderColor = '#e2e8f0';
        lbl.style.background = 'transparent';
        // restore label custom colors visually (CSS :checked handles the active state)
    });
    document.getElementById('outcome-box').style.opacity = '1';
    document.getElementById('outcome-box').style.pointerEvents = 'auto';
}

// Visual radio logic logic
document.querySelectorAll('input[name="interaction_result"]').forEach(radio => {
    radio.addEventListener('change', function() {
        if(this.value === 'answered') {
            document.getElementById('outcome-box').style.opacity = '1';
            document.getElementById('outcome-box').style.pointerEvents = 'auto';
        } else {
            document.getElementById('outcome-box').style.opacity = '0.5';
            document.getElementById('outcome-box').style.pointerEvents = 'none';
        }
    });
});

document.addEventListener("DOMContentLoaded", function() {
    // Auto-select first item in inbox mode
    <?php if ($view === 'list' && !empty($tasks)): ?>
        loadTask(<?php echo $tasks[0]['id']; ?>);
    <?php endif; ?>
    
    // AJAX Form Submit
    const form = document.getElementById('actionForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-submit');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ĐANG XỬ LÝ...';
            btn.disabled = true;

            fetch('process.php', {
                method: 'POST',
                body: new FormData(form)
            })
            .then(res => res.json())
            .then(data => {
                if(data.ok) {
                    toast(data.message || 'Xong!', 'success');
                    removeCurrentAndLoadNext();
                } else {
                    alert(data.message || 'Lỗi xử lý');
                    btn.innerHTML = '<i class="fas fa-paper-plane"></i> LƯU & GỌI NGƯỜI TIẾP THEO';
                    btn.disabled = false;
                }
            })
            .catch(err => {
                alert('Lỗi kết nối mạng');
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> LƯU & GỌI NGƯỜI TIẾP THEO';
                btn.disabled = false;
            });
        });
    }
});

function skipTask() {
    if (!currentTaskId) return;
    confirmAndRun(function() {
        var body = new FormData();
        body.append('action', 'skip');
        body.append('task_id', currentTaskId);
        body.append('ajax', '1');

        fetch('process.php', { method: 'POST', body: body })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            toast('Đã bỏ qua task', 'warning');
            removeCurrentAndLoadNext();
        });
    }, 'Bỏ qua và đóng task này?');
}

function removeCurrentAndLoadNext() {
    const item = document.getElementById('nav-item-' + currentTaskId);
    if(item) {
        item.classList.add('anim-leave');
        
        setTimeout(() => {
            let nextItem = item.nextElementSibling; // div or something
            item.remove();
            
            // Clean tasksDataset
            delete tasksDataset[currentTaskId];
            currentTaskId = null;
            
            // Find next available task-list-item
            const remaining = document.querySelectorAll('.task-list-item');
            if (remaining.length > 0) {
                // Get ID from the element id string
                const nextId = remaining[0].id.replace('nav-item-', '');
                loadTask(nextId);
            } else {
                document.getElementById('rp-empty').style.display = 'flex';
                document.getElementById('rp-content').style.display = 'none';
            }
        }, 300);
    }
}

function toast(msg, type='success') {
    // Simple custom toast to avoid reload
    const div = document.createElement('div');
    div.style.position = 'fixed';
    div.style.bottom = '20px';
    div.style.right = '20px';
    div.style.padding = '15px 25px';
    div.style.background = type === 'success' ? '#22c55e' : (type==='error'?'#ef4444':'#f59e0b');
    div.style.color = 'white';
    div.style.borderRadius = '8px';
    div.style.zIndex = '10000';
    div.style.fontWeight = '700';
    div.style.boxShadow = '0 10px 25px rgba(0,0,0,0.2)';
    div.innerHTML = msg;
    document.body.appendChild(div);
    setTimeout(() => { div.style.opacity = '0'; div.style.transition = 'opacity 0.5s'; }, 2500);
    setTimeout(() => div.remove(), 3000);
}
</script>

<?php require_once '../../templates/footer.php'; ?>
