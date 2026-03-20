<?php
// modules/medical/index.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

$page_title = 'Hồ sơ bệnh án';
$current_page = 'medical';
require_once '../../templates/header.php';

$db = getDB();
$search = $_GET['search'] ?? '';
$period = $_GET['period'] ?? '';
$start_date_param = $_GET['start_date'] ?? '';
$end_date_param = $_GET['end_date'] ?? '';

// 1. Current Patients at Clinic (Checked-in today)
$now = date('Y-m-d');
$stmt_active = $db->prepare("
    SELECT p.*, a.id as appointment_id, a.appointment_date, a.status as app_status,
           (SELECT id FROM medical_sessions WHERE patient_id = p.id AND DATE(session_date) = ? AND status = 'active' LIMIT 1) as active_session_id
    FROM patients p
    JOIN appointments a ON p.id = a.patient_id
    WHERE DATE(a.appointment_date) = ? AND a.status = 'arrived'
    ORDER BY a.appointment_date ASC
");
$stmt_active->execute([$now, $now]);
$active_patients = $stmt_active->fetchAll();

// 2. Recent Medical Activity (Last 10 records)
$stmt_recent = $db->query("
    SELECT mh.*, p.full_name, p.phone
    FROM medical_history mh
    JOIN patients p ON mh.patient_id = p.id
    ORDER BY mh.created_at DESC
    LIMIT 10
");
$recent_activity = $stmt_recent->fetchAll();

// 3. Main Patient Search/List
$search_params = [];
$conditions = [];
if ($search) {
    $conditions[] = "(p.full_name LIKE ? OR p.phone LIKE ?)";
    $search_params = ["%$search%", "%$search%"];
}

if ($period) {
    $range = get_date_range($period, $start_date_param, $end_date_param);
    $sql = "SELECT p.*, h.history_count, h.last_record,
            (SELECT id FROM medical_sessions WHERE patient_id = p.id AND DATE(session_date) = CURDATE() AND status = 'active' LIMIT 1) as active_session_id
            FROM patients p
            INNER JOIN (
                SELECT patient_id, COUNT(*) as history_count, MAX(created_at) as last_record
                FROM medical_history
                WHERE created_at BETWEEN ? AND ?
                GROUP BY patient_id
            ) h ON p.id = h.patient_id";
    $params = [$range['start'], $range['end']];
    $count_sql = "SELECT COUNT(DISTINCT p.id) FROM patients p INNER JOIN medical_history mh ON p.id = mh.patient_id WHERE mh.created_at BETWEEN ? AND ?";
} else {
    $sql = "SELECT p.*, 
            (SELECT COUNT(*) FROM medical_history WHERE patient_id = p.id) as history_count,
            (SELECT id FROM medical_sessions WHERE patient_id = p.id AND DATE(session_date) = CURDATE() AND status = 'active' LIMIT 1) as active_session_id
            FROM patients p";
    $params = [];
    $count_sql = "SELECT COUNT(*) FROM patients p";
}

if ($conditions) {
    $clause = ($period ? " AND " : " WHERE ") . implode(" AND ", $conditions);
    $sql .= $clause;
    $count_sql .= $clause;
    $params = array_merge($params, $search_params);
}

// Pagination
$limit = 15;
$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;

$c_stmt = $db->prepare($count_sql);
$c_stmt->execute($params);
$total_count = $c_stmt->fetchColumn();

$sql .= " ORDER BY " . ($period ? "h.last_record DESC" : "p.full_name ASC");
$sql .= get_sql_limit($limit, $page);

$stmt = $db->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll();

$is_filtered = $search || $period;
?>

<style>
.filter-card {
    padding: 1rem !important;
    margin-bottom: 1.5rem !important;
    border-radius: 16px !important;
}
.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}
.filter-group { margin-bottom: 0; }
.filter-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.025em;
    margin-bottom: 0.25rem;
    display: block;
    color: var(--text-muted);
    font-weight: 700;
}
.filter-input {
    height: 38px !important;
    font-size: 0.85rem !important;
    padding: 0.5rem 0.75rem !important;
    border-radius: 10px !important;
}
.filter-btn-group {
    display: flex;
    gap: 0.4rem;
    flex-wrap: wrap;
    align-items: center;
}
.filter-btn {
    padding: 0.4rem 0.8rem;
    border-radius: 8px;
    background: #f1f5f9;
    color: var(--text-muted);
    font-size: 0.8rem;
    font-weight: 600;
    text-decoration: none;
    transition: background 0.15s, color 0.15s;
    border: 1px solid transparent;
}
.filter-btn:hover { background: #e2e8f0; color: var(--text-main); }
.filter-btn.active {
    background: var(--primary);
    color: white;
}
.custom-range-box {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    background: #f8fafc;
    padding: 0.4rem 0.75rem;
    border-radius: 10px;
    border: 1px solid var(--border-color);
}
.custom-range-input {
    border: none;
    background: transparent;
    font-size: 0.8rem;
    color: var(--text-main);
    width: 110px;
    outline: none;
}
</style>

<div class="card filter-card">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap;">
        <form method="GET" id="filterForm" style="flex: 1;">
            <div class="filter-grid">
                <div class="filter-group">
                    <label class="filter-label">Tìm kiếm hồ sơ</label>
                    <div style="position: relative;">
                        <i class="fas fa-search" style="position: absolute; left: 0.8rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.75rem;"></i>
                        <input type="text" name="search" class="form-input filter-input" placeholder="Tên hoặc SĐT..." value="<?php echo e($search); ?>" style="padding-left: 2.2rem !important;">
                    </div>
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                <div class="filter-btn-group">
                    <span class="filter-label" style="margin-bottom: 0; margin-right: 0.25rem;">Lọc theo:</span>
                    <input type="hidden" name="period" id="periodInput" value="<?php echo e($period); ?>">
                    <a href="#" class="filter-btn <?php echo $period == '' ? 'active' : ''; ?>" onclick="setPeriod('')">Tất cả</a>
                    <a href="#" class="filter-btn <?php echo $period == 'today' ? 'active' : ''; ?>" onclick="setPeriod('today')">Hôm nay</a>
                    <a href="#" class="filter-btn <?php echo $period == 'week' ? 'active' : ''; ?>" onclick="setPeriod('week')">Tuần</a>
                    <a href="#" class="filter-btn <?php echo $period == 'month' ? 'active' : ''; ?>" onclick="setPeriod('month')">Tháng</a>
                    <a href="#" class="filter-btn <?php echo $period == 'custom' ? 'active' : ''; ?>" onclick="setPeriod('custom')">Tùy chọn</a>
                </div>

                <div id="customDates" style="display: <?php echo $period == 'custom' ? 'flex' : 'none'; ?>; gap: 0.5rem; align-items: center;">
                    <div class="custom-range-box">
                        <input type="date" name="start_date" class="custom-range-input" value="<?php echo e($start_date_param); ?>">
                        <input type="date" name="end_date" class="custom-range-input" value="<?php echo e($end_date_param); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" style="height: 32px;">Lọc</button>
                </div>

                <?php if ($is_filtered): ?>
                    <a href="index.php" style="color: #ef4444; font-size: 0.8rem; font-weight: 700; text-decoration: none;">
                        <i class="fas fa-times-circle"></i> XÓA LỌC
                    </a>
                <?php endif; ?>
            </div>
        </form>
        <div style="color: var(--text-muted); font-weight: 700; background: #f1f5f9; padding: 0.4rem 0.8rem; border-radius: 10px; font-size: 0.85rem;">
            <i class="fas fa-user-circle"></i> <?php echo $total_count; ?> bệnh nhân
        </div>
    </div>
</div>

<!-- 🏥 CLINICAL WORKSPACE SECTIONS -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Section 1: Active Patients (Checked-in) -->
    <div class="card" style="padding: 1.5rem; border-radius: 20px; border-left: 5px solid #10b981;">
        <h4 style="font-size: 1rem; font-weight: 800; margin-bottom: 1.25rem; color: #0f172a; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-door-open" style="color: #10b981;"></i> ĐANG TẠI PHÒNG KHÁM
            <span class="badge" style="background: #10b981; color: white;"><?php echo count($active_patients); ?></span>
        </h4>
        
        <?php if (empty($active_patients)): ?>
            <div style="text-align: center; padding: 1.5rem; background: #f8fafc; border-radius: 12px; color: #94a3b8; font-size: 0.85rem; font-weight: 600;">
                Hiện không có bệnh nhân nào đang chờ.
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <?php foreach ($active_patients as $ap): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; background: #f0fdf4; padding: 0.8rem 1rem; border-radius: 12px; border: 1px solid #dcfce7;">
                        <div>
                            <div style="font-weight: 800; color: #166534;"><?php echo e($ap['full_name']); ?></div>
                            <div style="font-size: 0.75rem; color: #15803d; font-weight: 600;">Check-in: <?php echo date('H:i', strtotime($ap['appointment_date'])); ?></div>
                        </div>
                        <div style="display: flex; gap: 0.4rem;">
                            <a href="session_start.php?patient_id=<?php echo $ap['id']; ?>" class="btn btn-sm" style="background: #10b981; color: white; font-weight: 700;">
                                <?php echo $ap['active_session_id'] ? 'TIẾP TỤC KHÁM' : 'BẮT ĐẦU KHÁM'; ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Section 2: Recent Activity -->
    <div class="card" style="padding: 1.5rem; border-radius: 20px; border-left: 5px solid #6366f1;">
        <h4 style="font-size: 1rem; font-weight: 800; margin-bottom: 1.25rem; color: #0f172a; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-clock-rotate-left" style="color: #6366f1;"></i> HOẠT ĐỘNG GẦN ĐÂY
        </h4>
        
        <div style="max-height: 200px; overflow-y: auto; padding-right: 5px;">
            <?php foreach ($recent_activity as $ra): ?>
                <div style="display: flex; align-items: center; gap: 1rem; padding: 0.6rem 0; border-bottom: 1px solid #f1f5f9;">
                    <div style="width: 32px; height: 32px; background: <?php echo $ra['type'] == 'chiropractic' ? '#f5f3ff' : '#fdf2f2'; ?>; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: <?php echo $ra['type'] == 'chiropractic' ? '#7c3aed' : '#dc2626'; ?>; font-size: 0.8rem;">
                        <i class="fas <?php echo $ra['type'] == 'chiropractic' ? 'fa-bone' : 'fa-leaf'; ?>"></i>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-size: 0.85rem; font-weight: 700; color: #1e293b;"><?php echo e($ra['full_name']); ?></div>
                        <div style="font-size: 0.7rem; color: #64748b; font-weight: 600;">
                            <?php 
                                $type_map = [
                                    'chiro_history' => 'Tiền sử Chiro',
                                    'chiro_exam' => 'Khám Chiro',
                                    'chiropractic' => 'SOAP',
                                    'dong_y' => 'Đông Y'
                                ];
                                echo $type_map[$ra['type']] ?? 'Hồ sơ y tế';
                            ?> 
                            • <?php echo time_elapsed_string($ra['created_at']); ?>
                        </div>
                    </div>
                    <a href="session_view.php?id=<?php echo $ra['session_id']; ?>" style="font-size: 0.75rem; color: var(--primary); font-weight: 700; text-decoration: none;">XEM SỔ</a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card" style="padding: 0; border-radius: 20px; overflow: hidden;">
    <div style="padding: 1.25rem 1.5rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
        <h4 style="font-size: 1rem; font-weight: 800; color: #0f172a; margin: 0;">DANH SÁCH BỆNH NHÂN</h4>
    </div>
    <div class="table-responsive">
    <table class="table" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="text-align: left; background: white;">
                <th style="padding: 1rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; color: #64748b;">Bệnh nhân</th>
                <th style="padding: 1rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; color: #64748b;">Liên hệ</th>
                <th style="padding: 1rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; color: #64748b;">Trạng thái hồ sơ</th>
                <th style="padding: 1rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; color: #64748b; text-align: right;">Thao tác</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($patients as $p): ?>
                <tr style="border-top: 1px solid #f1f5f9;">
                    <td style="padding: 1rem 1.5rem;">
                        <div style="font-weight: 800; color: #1e293b;"><?php echo e($p['full_name']); ?></div>
                        <div style="font-size: 0.7rem; color: #94a3b8; font-weight: 600;">ID: #<?php echo str_pad($p['id'], 5, '0', STR_PAD_LEFT); ?></div>
                    </td>
                    <td style="padding: 1rem 1.5rem;">
                        <div style="font-size: 0.9rem; font-weight: 700; color: #475569;"><?php echo e($p['phone']); ?></div>
                    </td>
                    <td style="padding: 1rem 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <?php if ($p['history_count'] > 0): ?>
                                <span class="badge" style="background: #e0e7ff; color: #4338ca; border-radius: 6px; font-weight: 700;">
                                    <?php echo $p['history_count']; ?> đầu mục
                                </span>
                            <?php else: ?>
                                <span class="badge" style="background: #f1f5f9; color: #94a3b8; border-radius: 6px; font-weight: 700;">CHƯA CÓ HỒ SƠ</span>
                            <?php endif; ?>

                            <?php if ($p['active_session_id']): ?>
                                <span class="badge" style="background: #fef9c3; color: #854d0e; border-radius: 6px; font-weight: 700;">
                                    <i class="fas fa-spinner fa-spin"></i> ĐANG KHÁM
                                </span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td style="padding: 1rem 1.5rem; text-align: right;">
                        <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                            <a href="session_start.php?patient_id=<?php echo $p['id']; ?>" class="btn btn-primary" style="font-weight: 800; padding: 0.5rem 1.25rem;">
                                <?php echo $p['active_session_id'] ? 'TIẾP TỤC KHÁM' : 'CHỌN KHÁM'; ?>
                            </a>
                            <a href="../patients/view.php?id=<?php echo $p['id']; ?>" class="btn" style="background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0;" title="Xem hồ sơ chi tiết">
                                <i class="fas fa-eye"></i>
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
    </div>
</div>
</div>

<script>
function setPeriod(p) {
    document.getElementById('periodInput').value = p;
    if (p !== 'custom') {
        const s = document.querySelector('input[name="start_date"]');
        const e = document.querySelector('input[name="end_date"]');
        if (s) s.value = '';
        if (e) e.value = '';
        document.getElementById('filterForm').submit();
    } else {
        document.getElementById('customDates').style.display = 'flex';
        document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
        if (event && event.target) {
            event.target.classList.add('active');
        }
    }
}
</script>

<?php echo render_pagination($total_count, $limit, $page); ?>

<?php require_once '../../templates/footer.php'; ?>
