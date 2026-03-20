<?php
// modules/leads/index.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

$db = getDB();

// Handle Quick Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_add'])) {
    $stmt = $db->prepare("
        INSERT INTO leads (full_name, phone, source, medical_group, consultant_id, status)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $_POST['full_name'],
        $_POST['phone'],
        $_POST['source'],
        $_POST['medical_group'],
        $_POST['consultant_id'] ?: null,
        $_POST['status'] ?? 'new'
    ]);
    set_flash('Đã thêm Lead nhanh thành công!');
    redirect('index.php');
}

$page_title = 'Quản lý Lead Marketing';
$current_page = 'leads';
require_once '../../templates/header.php';

$db = getDB();

// Medical Groups from central function
$medical_groups = get_medical_groups();
$lead_sources = get_lead_sources();

$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$group_filter = $_GET['medical_group'] ?? '';
$consultant_filter = $_GET['consultant_id'] ?? '';
$period = $_GET['period'] ?? '';
$start_date_filter = $_GET['start_date'] ?? '';
$end_date_filter = $_GET['end_date'] ?? '';

$sql = "SELECT * FROM leads";
$params = [];
$conditions = [];

if ($search) {
    $conditions[] = "(full_name LIKE ? OR phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $conditions[] = "status = ?";
    $params[] = $status_filter;
}

if ($group_filter) {
    $conditions[] = "medical_group = ?";
    $params[] = $group_filter;
}

if ($consultant_filter) {
    $conditions[] = "consultant_id = ?";
    $params[] = $consultant_filter;
}

if ($period) {
    $range = get_date_range($period, $start_date_filter, $end_date_filter);
    $conditions[] = "created_at BETWEEN ? AND ?";
    $params[] = $range['start'];
    $params[] = $range['end'];
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

// Pagination Logic
$limit = 20;
$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;

$count_sql = "SELECT COUNT(*) FROM leads";
if (!empty($conditions)) {
    $count_sql .= " WHERE " . implode(" AND ", $conditions);
}
$c_stmt = $db->prepare($count_sql);
$c_stmt->execute($params);
$total_count = $c_stmt->fetchColumn();

$sql .= " ORDER BY created_at DESC";
$sql .= get_sql_limit($limit, $page);

$stmt = $db->prepare($sql);
$stmt->execute($params);
$leads = $stmt->fetchAll();

// Statistics
$stats_stmt = $db->query("SELECT status, COUNT(*) as count FROM leads GROUP BY status");
$stats = $stats_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Fetch consultants (users with role 'cskh' or 'admin')
$consultants_stmt = $db->query("
    SELECT u.id, u.full_name 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    WHERE r.name IN ('cskh', 'admin') AND u.status = 'active'
    ORDER BY u.full_name
");
$consultants = $consultants_stmt->fetchAll();

$is_filtered = $search || $status_filter || $group_filter || $consultant_filter || $period;
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

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <div class="card" style="padding: 1rem; display: flex; align-items: center; gap: 0.75rem; background: linear-gradient(135deg, #4f46e5, #818cf8); color: white; border-radius: 16px;">
        <div style="width: 38px; height: 38px; background: rgba(255,255,255,0.2); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
            <i class="fas fa-user-plus"></i>
        </div>
        <div>
            <div style="font-size: 0.65rem; opacity: 0.9; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Hồ sơ mới</div>
            <div style="font-size: 1.25rem; font-weight: 800;"><?php echo $stats['new'] ?? 0; ?></div>
        </div>
    </div>
    <div class="card" style="padding: 1rem; display: flex; align-items: center; gap: 0.75rem; background: linear-gradient(135deg, #f59e0b, #fbbf24); color: white; border-radius: 16px;">
        <div style="width: 38px; height: 38px; background: rgba(255,255,255,0.2); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
            <i class="fas fa-comment-dots"></i>
        </div>
        <div>
            <div style="font-size: 0.65rem; opacity: 0.9; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Đã liên hệ</div>
            <div style="font-size: 1.25rem; font-weight: 800;"><?php echo $stats['contacted'] ?? 0; ?></div>
        </div>
    </div>
    <div class="card" style="padding: 1rem; display: flex; align-items: center; gap: 0.75rem; background: linear-gradient(135deg, #10b981, #34d399); color: white; border-radius: 16px;">
        <div style="width: 38px; height: 38px; background: rgba(255,255,255,0.2); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div>
            <div style="font-size: 0.65rem; opacity: 0.9; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Đã đặt lịch</div>
            <div style="font-size: 1.25rem; font-weight: 800;"><?php echo $stats['scheduled'] ?? 0; ?></div>
        </div>
    </div>
</div>

<div class="card filter-card">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
        <form method="GET" id="filterForm" style="flex: 1;">
            <div class="filter-grid">
                <!-- Search -->
                <div class="filter-group">
                    <label class="filter-label">Tìm kiếm</label>
                    <div style="position: relative;">
                        <i class="fas fa-search" style="position: absolute; left: 0.8rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.75rem;"></i>
                        <input type="text" name="search" class="form-input filter-input" placeholder="Tên, SĐT..." value="<?php echo e($search); ?>" style="padding-left: 2.2rem !important;">
                    </div>
                </div>

                <!-- Status Filter -->
                <div class="filter-group">
                    <label class="filter-label">Trạng thái</label>
                    <select name="status" class="form-input filter-input" onchange="this.form.submit()">
                        <option value="">-- Trạng thái --</option>
                        <option value="new" <?php echo $status_filter === 'new' ? 'selected' : ''; ?>>Mới</option>
                        <option value="contacted" <?php echo $status_filter === 'contacted' ? 'selected' : ''; ?>>Đã liên hệ</option>
                        <option value="scheduled" <?php echo $status_filter === 'scheduled' ? 'selected' : ''; ?>>Đã đặt lịch</option>
                        <option value="converted" <?php echo $status_filter === 'converted' ? 'selected' : ''; ?>>Đã chuyển đổi</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Đã hủy</option>
                    </select>
                </div>

                <!-- Group Filter -->
                <div class="filter-group">
                    <label class="filter-label">Nhóm bệnh</label>
                    <select name="medical_group" class="form-input filter-input" onchange="this.form.submit()">
                        <option value="">-- Nhóm bệnh --</option>
                        <?php foreach ($medical_groups as $mg): ?>
                            <option value="<?php echo $mg; ?>" <?php echo $group_filter === $mg ? 'selected' : ''; ?>><?php echo $mg; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Consultant Filter -->
                <div class="filter-group">
                    <label class="filter-label">Tư vấn viên</label>
                    <select name="consultant_id" class="form-input filter-input" onchange="this.form.submit()">
                        <option value="">-- Tư vấn viên --</option>
                        <?php foreach ($consultants as $con): ?>
                            <option value="<?php echo $con['id']; ?>" <?php echo (int)$consultant_filter === (int)$con['id'] ? 'selected' : ''; ?>><?php echo e($con['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                <div class="filter-btn-group">
                    <span class="filter-label" style="margin-bottom: 0; margin-right: 0.25rem;">Thời gian:</span>
                    <input type="hidden" name="period" id="periodInput" value="<?php echo e($period); ?>">
                    <a href="#" class="filter-btn <?php echo $period == '' ? 'active' : ''; ?>" onclick="setPeriod('')">Tất cả</a>
                    <a href="#" class="filter-btn <?php echo $period == 'today' ? 'active' : ''; ?>" onclick="setPeriod('today')">Hôm nay</a>
                    <a href="#" class="filter-btn <?php echo $period == 'week' ? 'active' : ''; ?>" onclick="setPeriod('week')">Tuần</a>
                    <a href="#" class="filter-btn <?php echo $period == 'month' ? 'active' : ''; ?>" onclick="setPeriod('month')">Tháng</a>
                    <a href="#" class="filter-btn <?php echo $period == 'quarter' ? 'active' : ''; ?>" onclick="setPeriod('quarter')">Quý</a>
                    <a href="#" class="filter-btn <?php echo $period == 'year' ? 'active' : ''; ?>" onclick="setPeriod('year')">Năm</a>
                    <a href="#" class="filter-btn <?php echo $period == 'custom' ? 'active' : ''; ?>" onclick="setPeriod('custom')">Tùy chọn</a>
                </div>

                <div id="customDates" style="display: <?php echo $period == 'custom' ? 'flex' : 'none'; ?>; gap: 0.5rem; align-items: center;">
                    <div class="custom-range-box">
                        <input type="date" name="start_date" class="custom-range-input" value="<?php echo e($start_date_filter); ?>">
                        <span style="color: #94a3b8; font-size: 0.8rem;">→</span>
                        <input type="date" name="end_date" class="custom-range-input" value="<?php echo e($end_date_filter); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" style="height: 32px; padding: 0 0.75rem; border-radius: 8px;">Áp dụng</button>
                </div>

                <?php if ($is_filtered): ?>
                    <div style="margin-left: auto;">
                        <a href="index.php" style="color: #ef4444; font-size: 0.8rem; font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 0.25rem;">
                            <i class="fas fa-times-circle"></i> XÓA LỌC
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </form>
        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
            <a href="add.php" class="btn btn-primary shadow-sm" style="padding: 0.5rem 1rem; font-weight: 700; font-size: 0.85rem; border-radius: 10px; white-space: nowrap;">
                <i class="fas fa-plus"></i> THÊM LEAD
            </a>
            <p style="color: var(--text-muted); font-size: 0.75rem; font-weight: 700; background: #f1f5f9; padding: 0.25rem 0.5rem; border-radius: 6px; text-align: center;">
                <?php echo count($leads); ?> Lead
            </p>
        </div>
    </div>
</div>

<script>
function setPeriod(p) {
    document.getElementById('periodInput').value = p;
    // Clear custom dates when selecting a period
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

    <div style="overflow: auto; max-height: calc(100vh - 220px); border-radius: 12px; border: 1px solid var(--border-color); background: white;">
        <table class="table" style="width: 100%; border-collapse: separate; border-spacing: 0;">
            <thead>
                <!-- Column Titles (Sticky Top 0) -->
                <tr style="text-align: left; background: #f8fafc;">
                    <th style="position: sticky; top: 0; z-index: 10; padding: 1rem; width: 120px; background: #f8fafc; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05rem;">Ngày tạo</th>
                    <th style="position: sticky; top: 0; z-index: 10; padding: 1rem; width: 220px; background: #f8fafc; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05rem;">Thông tin Lead</th>
                    <th style="position: sticky; top: 0; z-index: 10; padding: 1rem; width: 180px; background: #f8fafc; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05rem;">Nguồn & Nhóm</th>
                    <th style="position: sticky; top: 0; z-index: 10; padding: 1rem; width: 160px; background: #f8fafc; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05rem;">Tư vấn viên</th>
                    <th style="position: sticky; top: 0; z-index: 10; padding: 1rem; width: 180px; background: #f8fafc; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05rem;">Trạng thái tư vấn</th>
                    <th style="position: sticky; top: 0; z-index: 10; padding: 1rem; width: 200px; background: #f8fafc; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05rem;">Thời gian liên hệ</th>
                    <th style="position: sticky; top: 0; z-index: 10; padding: 1rem; width: 150px; background: #f8fafc; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05rem;">Thao tác</th>
                </tr>
                <!-- Quick Add Row (Sticky Top 48px) -->
                <tr style="background: #f1f5f9; border-bottom: 1px solid var(--border-color);">
                    <td style="position: sticky; top: 48px; z-index: 9; padding: 0.75rem 1rem; background: #f1f5f9;">
                        <span class="badge" style="background: #6366f1; color: white; font-weight: 800; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.7rem;"><i class="fas fa-bolt"></i> Nhanh</span>
                    </td>
                    <td style="position: sticky; top: 48px; z-index: 9; padding: 0.75rem 0.5rem; background: #f1f5f9;">
                        <input type="text" name="full_name" class="form-input" placeholder="Tên..." style="padding: 0.35rem 0.6rem; font-size: 0.8rem; border-radius: 8px;" required form="quick-add-form">
                    </td>
                    <td style="position: sticky; top: 48px; z-index: 9; padding: 0.75rem 0.5rem; background: #f1f5f9;">
                        <div style="display: flex; gap: 0.3rem;">
                            <input type="text" name="phone" class="form-input" placeholder="SĐT..." style="padding: 0.35rem 0.6rem; font-size: 0.8rem; border-radius: 8px;" required form="quick-add-form">
                            <select name="source" class="form-input" style="padding: 0.35rem; font-size: 0.8rem; border-radius: 8px;" form="quick-add-form">
                                <?php foreach ($lead_sources as $key => $label): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </td>
                    <td style="position: sticky; top: 48px; z-index: 9; padding: 0.75rem 0.5rem; background: #f1f5f9;">
                        <select name="medical_group" class="form-input" style="padding: 0.35rem; font-size: 0.8rem; border-radius: 8px;" form="quick-add-form">
                            <option value="">-- Nhóm bệnh --</option>
                            <?php foreach ($medical_groups as $mg): ?>
                                <option value="<?php echo $mg; ?>"><?php echo $mg; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td style="position: sticky; top: 48px; z-index: 9; padding: 0.75rem 0.5rem; background: #f1f5f9;">
                        <select name="consultant_id" class="form-input" style="padding: 0.35rem; font-size: 0.8rem; border-radius: 8px;" form="quick-add-form">
                            <option value="">-- Chọn TVV --</option>
                            <?php foreach ($consultants as $con): ?>
                                <option value="<?php echo $con['id']; ?>"><?php echo e($con['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td style="position: sticky; top: 48px; z-index: 9; padding: 0.75rem 0.5rem; background: #f1f5f9;">
                         <select name="status" class="form-input" style="padding: 0.35rem; font-size: 0.8rem; border-radius: 8px; font-weight: 600;" form="quick-add-form">
                            <option value="new" selected>Mới (New)</option>
                            <option value="contacted">Đã liên hệ</option>
                            <option value="scheduled">Đã đặt lịch</option>
                        </select>
                    </td>
                    <td style="position: sticky; top: 48px; z-index: 9; padding: 0.75rem 1rem; background: #f1f5f9;">
                        <form id="quick-add-form" method="POST">
                            <input type="hidden" name="quick_add" value="1">
                            <button type="submit" class="btn btn-primary" style="padding: 0.35rem 0.8rem; font-size: 0.8rem; border-radius: 8px; width: 100%; white-space: nowrap;">
                                <i class="fas fa-plus"></i> LƯU
                            </button>
                        </form>
                    </td>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leads as $l): ?>
                    <tr style="border-bottom: 1px solid var(--border-color); hover: background: #f1f5f9;">
                        <td style="padding: 1rem; font-size: 0.85rem; color: var(--text-muted);">
                            <?php echo date('d/m/Y', strtotime($l['created_at'])); ?>
                        </td>
                        <td style="padding: 1rem;">
                            <div style="font-weight: 700; color: var(--text-main);"><?php echo e($l['full_name']); ?></div>
                            <div style="font-size: 0.85rem; color: var(--primary); font-weight: 600;"><?php echo e($l['phone']); ?></div>
                        </td>
                        <td style="padding: 0.75rem 1rem;">
                            <div style="display: flex; flex-direction: column; gap: 0.3rem;">
                                <!-- Inline Source Selection -->
                                <form action="update_quick.php" method="POST" class="quick-status-form">
                                    <input type="hidden" name="id" value="<?php echo $l['id']; ?>">
                                    <div style="display: flex; align-items: center; gap: 0.3rem; font-size: 0.8rem; color: #64748b;">
                                        <i class="fas fa-share-alt"></i>
                                        <select name="source" class="form-input" style="padding: 0; font-size: 0.8rem; border: none; background: transparent; color: inherit; width: auto; font-weight: 600;" onchange="updateLead(this)">
                                            <?php foreach ($lead_sources as $key => $label): ?>
                                                <option value="<?php echo $key; ?>" <?php echo $l['source'] === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </form>

                                <!-- Inline Group Selection -->
                                <form action="update_quick.php" method="POST" class="quick-status-form">
                                    <input type="hidden" name="id" value="<?php echo $l['id']; ?>">
                                    <div style="display: flex; align-items: center; gap: 0.3rem; font-size: 0.8rem; color: #4f46e5; font-weight: 600;">
                                        <i class="fas fa-stethoscope"></i>
                                        <select name="medical_group" class="form-input" style="padding: 0; font-size: 0.8rem; border: none; background: transparent; color: inherit; width: auto; font-weight: 700;" onchange="updateLead(this)">
                                            <option value="">-- Nhóm bệnh --</option>
                                            <?php foreach ($medical_groups as $mg): ?>
                                                <option value="<?php echo $mg; ?>" <?php echo $l['medical_group'] === $mg ? 'selected' : ''; ?>><?php echo $mg; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </form>
                            </div>
                        </td>
                        <td style="padding: 1rem;">
                            <form action="update_quick.php" method="POST" class="quick-status-form">
                                <input type="hidden" name="id" value="<?php echo $l['id']; ?>">
                                <select name="consultant_id" class="form-input" style="padding: 0.3rem; font-size: 0.8rem; border: none; background: transparent;" onchange="updateLead(this)">
                                    <option value="">-- Chưa giao --</option>
                                    <?php foreach ($consultants as $con): ?>
                                        <option value="<?php echo $con['id']; ?>" <?php echo (int)$l['consultant_id'] === (int)$con['id'] ? 'selected' : ''; ?>><?php echo e($con['full_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                        <td style="padding: 1rem;">
                            <form action="update_quick.php" method="POST" class="quick-status-form">
                                <input type="hidden" name="id" value="<?php echo $l['id']; ?>">
                                <select name="consultation_status" class="form-input" style="padding: 0.4rem; font-size: 0.8rem; border-radius: 8px; font-weight: 600; border: 1px solid #e2e8f0;" onchange="updateLead(this)">
                                    <option value="">-- Chọn trạng thái --</option>
                                    <?php 
                                    $statuses = ['Mới', 'Đã liên hệ', 'Hẹn gọi lại', 'Đã đặt lịch', 'Đã đến khám', 'Hủy/Không nhu cầu'];
                                    foreach ($statuses as $s): ?>
                                        <option value="<?php echo $s; ?>" <?php echo $l['consultation_status'] === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                        <td style="padding: 1rem; font-size: 0.75rem;">
                            <div style="display: flex; flex-direction: column; gap: 0.2rem;">
                                <div><i class="fas fa-phone-alt" style="width: 14px; color: #94a3b8;"></i> LH: <?php echo $l['contact_time'] ? date('H:i d/m', strtotime($l['contact_time'])) : '--'; ?></div>
                                <div><i class="fas fa-history" style="width: 14px; color: #94a3b8;"></i> Lại: <?php echo $l['recontact_time'] ? date('H:i d/m', strtotime($l['recontact_time'])) : '--'; ?></div>
                                <div><i class="fas fa-calendar-check" style="width: 14px; color: #3b82f6;"></i> BOOK: <?php echo $l['appointment_booking_time'] ? date('H:i d/m', strtotime($l['appointment_booking_time'])) : '--'; ?></div>
                            </div>
                        </td>
                        <td style="padding: 1.2rem 1rem; white-space: nowrap;">
                            <div style="display: flex; gap: 0.4rem; align-items: center;">
                                <a href="../appointments/add.php?lead_id=<?php echo $l['id']; ?>" class="btn btn-sm" style="background: #4f46e5; color: white; padding: 0.4rem 0.6rem; font-size: 0.75rem; border-radius: 8px;" title="Đặt lịch khám">
                                    <i class="fas fa-calendar-plus"></i> Đặt lịch
                                </a>
                                <a href="edit.php?id=<?php echo $l['id']; ?>" class="btn btn-sm" style="background: #f1f5f9; color: var(--text-main); width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; padding: 0; border-radius: 8px;">
                                    <i class="fas fa-edit" style="font-size: 0.8rem;"></i>
                                </a>
                                <a href="delete.php?id=<?php echo $l['id']; ?>" class="btn btn-sm" style="background: #fff1f2; color: #e11d48; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; padding: 0; border-radius: 8px;" onclick="return confirm('Xóa Lead này?')">
                                    <i class="fas fa-trash" style="font-size: 0.8rem;"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($leads)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            <i class="fas fa-inbox" style="font-size: 2rem; display: block; margin-bottom: 1rem; opacity: 0.3;"></i>
                            Chưa có dữ liệu Lead nào.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php echo render_pagination($total_count, $limit, $page); ?>
</div>

<style>
.btn-icon {
    width: 32px;
    height: 32px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.shadow-sm {
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}
</style>

<div id="quick-toast" style="position: fixed; bottom: 2rem; right: 2rem; background: #0f172a; color: white; padding: 0.8rem 1.5rem; border-radius: 12px; box-shadow: var(--shadow-lg); display: none; z-index: 9999; font-weight: 600; font-size: 0.9rem;">
    <i class="fas fa-check-circle" style="color: #10b981; margin-right: 0.5rem;"></i> <span id="toast-msg">Đã lưu!</span>
</div>

<script>
function showToast(msg) {
    const toast = document.getElementById('quick-toast');
    document.getElementById('toast-msg').innerText = msg;
    toast.style.display = 'block';
    setTimeout(() => { toast.style.display = 'none'; }, 2000);
}

function updateLead(select) {
    const form = select.closest('form');
    const formData = new FormData(form);
    const selectName = select.name;
    const selectValue = select.value;

    // Optional: add loading style
    select.style.opacity = '0.5';

    fetch('update_quick.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        select.style.opacity = '1';
        if (data.success) {
            let label = 'thông tin';
            if (selectName === 'consultation_status') label = 'trạng thái';
            else if (selectName === 'consultant_id') label = 'TVV';
            else if (selectName === 'source') label = 'nguồn';
            else if (selectName === 'medical_group') label = 'nhóm bệnh';
            showToast('Đã lưu ' + label + '!');
        } else {
            alert('Lỗi khi lưu!');
            location.reload();
        }
    })
    .catch(error => {
        select.style.opacity = '1';
        console.error('Error:', error);
        location.reload();
    });
}
</script>

<?php require_once '../../templates/footer.php'; ?>
