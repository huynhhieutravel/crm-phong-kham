<?php
// modules/appointments/index.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

$page_title = 'Quản lý Lịch hẹn';
$current_page = 'appointments';
require_once '../../templates/header.php';

$db = getDB();

$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';
$doctor_filter = $_GET['doctor_id'] ?? '';
$type_filter = $_GET['type'] ?? '';
$view = $_GET['view'] ?? 'list';

$query = "
    SELECT 
        a.*, 
        COALESCE(p.full_name, l.full_name) as contact_name,
        COALESCE(p.phone, l.phone) as contact_phone,
        p.label as patient_label,
        u.full_name as doctor_name,
        CASE WHEN a.patient_id IS NOT NULL THEN 'Patient' ELSE 'Lead' END as contact_type
    FROM appointments a
    LEFT JOIN patients p ON a.patient_id = p.id
    LEFT JOIN leads l ON a.lead_id = l.id
    LEFT JOIN users u ON a.doctor_id = u.id
";

$conditions = [];
$params = [];

if ($search) {
    $conditions[] = "(p.full_name LIKE ? OR l.full_name LIKE ? OR p.phone LIKE ? OR l.phone LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
}

if ($status_filter) {
    $conditions[] = "a.status = ?";
    $params[] = $status_filter;
}

if ($doctor_filter) {
    $conditions[] = "a.doctor_id = ?";
    $params[] = $doctor_filter;
}

if ($type_filter) {
    $conditions[] = "a.type = ?";
    $params[] = $type_filter;
}

if ($date_filter) {
    if ($view === 'timeline_week') {
        $ts = strtotime($date_filter);
        $start = date('Y-m-d', strtotime('monday this week', $ts));
        $end = date('Y-m-d', strtotime('sunday this week', $ts));
        $conditions[] = "DATE(a.appointment_date) BETWEEN ? AND ?";
        $params[] = $start;
        $params[] = $end;
    } elseif ($view === 'timeline_month') {
        $ts = strtotime($date_filter);
        $start = date('Y-m-01', $ts);
        $end = date('Y-m-t', $ts);
        $conditions[] = "DATE(a.appointment_date) BETWEEN ? AND ?";
        $params[] = $start;
        $params[] = $end;
    } else {
        $conditions[] = "DATE(a.appointment_date) = ?";
        $params[] = $date_filter;
    }
} elseif ($view === 'timeline') {
    $conditions[] = "DATE(a.appointment_date) = CURDATE()";
} elseif ($view === 'timeline_week') {
    $conditions[] = "DATE(a.appointment_date) BETWEEN DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) AND DATE_ADD(CURDATE(), INTERVAL 6-WEEKDAY(CURDATE()) DAY)";
} elseif ($view === 'timeline_month') {
    $conditions[] = "DATE(a.appointment_date) BETWEEN DATE_FORMAT(CURDATE(), '%Y-%m-01') AND LAST_DAY(CURDATE())";
}

if ($conditions) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$query .= " ORDER BY a.appointment_date ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

// Fetch staff for assignment (Doctors, CSKH, Admins)
$doctors_stmt = $db->query("
    SELECT u.id, u.full_name, r.display_name as role_name 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    WHERE r.name IN ('doctor', 'cskh', 'admin') AND u.status = 'active'
    ORDER BY r.name = 'doctor' DESC, u.full_name ASC
");
$doctors = $doctors_stmt->fetchAll();

$status_map = [
    'scheduled' => ['label' => 'Đã đặt lịch', 'color' => '#6366f1', 'icon' => 'fa-calendar-alt'],
    'confirmed' => ['label' => 'Đã xác nhận', 'color' => '#8b5cf6', 'icon' => 'fa-check-double'],
    'arrived'   => ['label' => 'Đã đến', 'color' => '#10b981', 'icon' => 'fa-walking'],
    'completed' => ['label' => 'Hoàn thành', 'color' => '#059669', 'icon' => 'fa-check-circle'],
    'no_show'   => ['label' => 'Vắng mặt', 'color' => '#f59e0b', 'icon' => 'fa-user-slash'],
    'cancelled' => ['label' => 'Đã hủy', 'color' => '#ef4444', 'icon' => 'fa-times-circle']
];

$type_map = [
    'consultation' => ['label' => 'Tư vấn', 'color' => '#3b82f6', 'icon' => 'fa-comments'],
    'treatment'    => ['label' => 'Điều trị', 'color' => '#10b981', 'icon' => 'fa-hand-holding-medical'],
    're_exam'      => ['label' => 'Tái khám', 'color' => '#8b5cf6', 'icon' => 'fa-redo'],
    'adjustment'   => ['label' => 'Hỗ trợ', 'color' => '#64748b', 'icon' => 'fa-tools']
];
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h2 style="margin: 0; font-weight: 800; color: var(--text-main);">Danh sách Lịch hẹn</h2>
        <p style="color: var(--text-muted); margin-top: 0.25rem;">Quản lý và theo dõi lịch trình khám của phòng khám</p>
    </div>
    <a href="add.php" class="btn btn-primary shadow-sm" style="padding: 0.75rem 1.5rem; font-weight: 700;">
        <i class="fas fa-calendar-plus"></i> ĐẶT LỊCH MỚI
    </a>
</div>

<div class="card" style="margin-bottom: 2rem; padding: 1.25rem; border: none; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
    <form method="GET" id="filter-form">
        <input type="hidden" name="view" value="<?php echo e($view); ?>">
        
        <div style="display: grid; grid-template-columns: 1.5fr 1fr 1fr 1fr 1fr auto; gap: 1rem; align-items: flex-end;">
            <!-- Search -->
            <div class="filter-group">
                <label class="filter-label"><i class="fas fa-search"></i> Tìm kiếm</label>
                <div style="position: relative;">
                    <input type="text" name="search" class="form-input filter-input" placeholder="Tên, Số điện thoại..." value="<?php echo e($search); ?>" style="padding-left: 2.5rem;">
                    <i class="fas fa-search" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.8rem;"></i>
                </div>
            </div>

            <!-- Doctor -->
            <div class="filter-group">
                <label class="filter-label"><i class="fas fa-user-md"></i> Bác sĩ</label>
                <select name="doctor_id" class="form-input filter-input" onchange="this.form.submit()">
                    <option value="">Tất cả bác sĩ</option>
                    <option value="0" <?php echo $doctor_filter === '0' ? 'selected' : ''; ?>>-- Chưa chỉ định --</option>
                    <?php foreach ($doctors as $doc): ?>
                        <option value="<?php echo $doc['id']; ?>" <?php echo (int)$doctor_filter === (int)$doc['id'] ? 'selected' : ''; ?>><?php echo e($doc['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status -->
            <div class="filter-group">
                <label class="filter-label"><i class="fas fa-info-circle"></i> Trạng thái</label>
                <select name="status" class="form-input filter-input" onchange="this.form.submit()">
                    <option value="">Tất cả trạng thái</option>
                    <?php foreach ($status_map as $key => $info): ?>
                        <option value="<?php echo $key; ?>" <?php echo $status_filter === $key ? 'selected' : ''; ?>><?php echo $info['label']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Type -->
            <div class="filter-group">
                <label class="filter-label"><i class="fas fa-notes-medical"></i> Loại</label>
                <select name="type" class="form-input filter-input" onchange="this.form.submit()">
                    <option value="">Tất cả loại</option>
                    <?php foreach ($type_map as $key => $info): ?>
                        <option value="<?php echo $key; ?>" <?php echo $type_filter === $key ? 'selected' : ''; ?>><?php echo $info['label']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Date -->
            <div class="filter-group">
                <label class="filter-label"><i class="fas fa-calendar-day"></i> Ngày khám</label>
                <input type="date" name="date" class="form-input filter-input" value="<?php echo e($date_filter); ?>" onchange="this.form.submit()">
            </div>

            <!-- Actions -->
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <button type="submit" class="btn btn-primary" style="height: 42px; padding: 0 1.25rem; border-radius: 12px; font-weight: 700;">Lọc</button>
                <?php if ($search || $status_filter || $date_filter || $doctor_filter || $type_filter): ?>
                    <a href="?view=<?php echo $view; ?>" class="btn" style="height: 42px; width: 42px; background: #fee2e2; color: #ef4444; border-radius: 12px; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" title="Xóa lọc">
                        <i class="fas fa-times"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
        <div style="display: flex; gap: 0.5rem;">
            <?php 
            $active_filters = [];
            if ($search) $active_filters[] = "Tìm: $search";
            if ($status_filter) $active_filters[] = "Trạng thái: " . ($status_map[$status_filter]['label'] ?? $status_filter);
            if ($type_filter) $active_filters[] = "Loại: " . ($type_map[$type_filter]['label'] ?? $type_filter);
            if ($date_filter) {
                if ($view === 'timeline_month') {
                    $active_filters[] = "Tháng: " . date('m/Y', strtotime($date_filter));
                } else {
                    $active_filters[] = "Ngày: " . date('d/m/Y', strtotime($date_filter));
                }
            }
            ?>
            <?php foreach($active_filters as $f): ?>
                <span style="background: #eff6ff; color: #3b82f6; padding: 0.35rem 0.75rem; border-radius: 50px; font-size: 0.75rem; font-weight: 700; border: 1px solid #dbeafe;">
                    <?php echo e($f); ?>
                </span>
            <?php endforeach; ?>
        </div>

        <div style="display: flex; background: #f1f5f9; padding: 0.3rem; border-radius: 14px;">
            <?php 
            $view_btn_params = "search=$search&status=$status_filter&date=$date_filter&doctor_id=$doctor_filter&type=$type_filter";
            ?>
            <a href="?view=list&<?php echo $view_btn_params; ?>" class="btn btn-sm <?php echo $view === 'list' ? 'btn-white shadow-sm' : ''; ?>" style="border-radius: 10px; padding: 0.5rem 1rem; border: none; font-weight: 700; color: <?php echo $view === 'list' ? 'var(--primary)' : 'var(--text-muted)'; ?>; background: <?php echo $view === 'list' ? 'white' : 'transparent'; ?>;">
                <i class="fas fa-list"></i> Danh sách
            </a>
            <a href="?view=timeline&<?php echo $view_btn_params; ?>" class="btn btn-sm <?php echo $view === 'timeline' ? 'btn-white shadow-sm' : ''; ?>" style="border-radius: 10px; padding: 0.5rem 1rem; border: none; font-weight: 700; color: <?php echo $view === 'timeline' ? 'var(--primary)' : 'var(--text-muted)'; ?>; background: <?php echo $view === 'timeline' ? 'white' : 'transparent'; ?>;">
                <i class="fas fa-clock"></i> Ngày
            </a>
            <a href="?view=timeline_week&<?php echo $view_btn_params; ?>" class="btn btn-sm <?php echo $view === 'timeline_week' ? 'btn-white shadow-sm' : ''; ?>" style="border-radius: 10px; padding: 0.5rem 1rem; border: none; font-weight: 700; color: <?php echo $view === 'timeline_week' ? 'var(--primary)' : 'var(--text-muted)'; ?>; background: <?php echo $view === 'timeline_week' ? 'white' : 'transparent'; ?>;">
                <i class="fas fa-calendar-week"></i> Tuần
            </a>
            <a href="?view=timeline_month&<?php echo $view_btn_params; ?>" class="btn btn-sm <?php echo $view === 'timeline_month' ? 'btn-white shadow-sm' : ''; ?>" style="border-radius: 10px; padding: 0.5rem 1rem; border: none; font-weight: 700; color: <?php echo $view === 'timeline_month' ? 'var(--primary)' : 'var(--text-muted)'; ?>; background: <?php echo $view === 'timeline_month' ? 'white' : 'transparent'; ?>;">
                <i class="fas fa-calendar-alt"></i> Tháng
            </a>
        </div>
    </div>
</div>

<?php if ($view === 'list'): ?>
    <div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--border-color);">
        <div style="overflow-x: auto;">
            <table class="table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8fafc; text-align: left;">
                        <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: var(--text-muted); font-weight: 700; border-bottom: 2px solid var(--border-color);">Thời gian</th>
                        <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: var(--text-muted); font-weight: 700; border-bottom: 2px solid var(--border-color);">Bệnh nhân</th>
                        <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: var(--text-muted); font-weight: 700; border-bottom: 2px solid var(--border-color);">Bác sĩ khám</th>
                        <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: var(--text-muted); font-weight: 700; border-bottom: 2px solid var(--border-color);">Trạng thái</th>
                        <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: var(--text-muted); font-weight: 700; border-bottom: 2px solid var(--border-color); text-align: center;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $a): ?>
                        <tr class="appointment-row" style="border-bottom: 1px solid var(--border-color); transition: background 0.2s;">
                            <td style="padding: 1.25rem 1.5rem;">
                                <div style="font-weight: 700; color: var(--text-main); font-size: 1rem;"><?php echo date('H:i', strtotime($a['appointment_date'])); ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo date('d/m/Y', strtotime($a['appointment_date'])); ?></div>
                            </td>
                            <td style="padding: 1.25rem 1.5rem;">
                                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.35rem; flex-wrap: wrap;">
                                    <span style="font-weight: 700; color: var(--text-main);"><?php echo e($a['contact_name']); ?></span>
                                    
                                    <div style="display: flex; gap: 0.25rem; align-items: center;">
                                        <span style="font-size: 0.6rem; font-weight: 800; padding: 0.15rem 0.4rem; border-radius: 4px; text-transform: uppercase; <?php echo $a['contact_type'] === 'Patient' ? 'background: #e0f2fe; color: #0369a1;' : 'background: #fef3c7; color: #92400e;'; ?>">
                                            <?php echo $a['contact_type'] === 'Patient' ? 'PATIENT' : 'LEAD'; ?>
                                        </span>
                                        
                                        <?php if (!empty($a['patient_label'])): ?>
                                            <span style="font-size: 0.6rem; font-weight: 800; padding: 0.15rem 0.4rem; border-radius: 4px; background: #fee2e2; color: #b91c1c; text-transform: uppercase;">
                                                <i class="fas fa-tag"></i> <?php echo e($a['patient_label']); ?>
                                            </span>
                                        <?php endif; ?>

                                        <?php if (!empty($a['notes'])): ?>
                                            <div class="note-tooltip-container">
                                                <i class="fas fa-sticky-note note-icon"></i>
                                                <div class="note-tooltip"><?php echo nl2br(e($a['notes'])); ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    <div style="font-size: 0.8rem; color: var(--primary); font-weight: 600;"><i class="fas fa-phone-alt" style="font-size: 0.7rem;"></i> <?php echo e($a['contact_phone']); ?></div>
                                    
                                    <?php 
                                    $t = $type_map[$a['type']] ?? ['label' => $a['type'], 'color' => '#64748b', 'icon' => 'fa-calendar'];
                                    ?>
                                    <span style="font-size: 0.65rem; font-weight: 700; color: <?php echo $t['color']; ?>; background: <?php echo $t['color']; ?>1a; padding: 0.1rem 0.6rem; border-radius: 50px; border: 1px solid <?php echo $t['color']; ?>33;">
                                        <i class="fas <?php echo $t['icon']; ?>" style="font-size: 0.6rem;"></i> <?php echo $t['label']; ?>
                                    </span>
                                </div>
                            </td>
                            <td style="padding: 1.25rem 1.5rem; width: 220px;">
                                <form action="update_appointment.php" method="POST" class="quick-status-form">
                                    <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                    <select name="doctor_id" class="form-input" style="padding: 0.4rem; font-size: 0.9rem; border: 1px solid transparent; background: transparent; font-weight: 600; cursor: pointer;" onchange="updateAppointment(this)">
                                        <option value="">-- Chưa chỉ định --</option>
                                        <?php foreach ($doctors as $doc): ?>
                                            <option value="<?php echo $doc['id']; ?>" <?php echo (int)$a['doctor_id'] === (int)$doc['id'] ? 'selected' : ''; ?>><?php echo e($doc['full_name']); ?> (<?php echo e($doc['role_name']); ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </td>
                            <td style="padding: 1.25rem 1.5rem;">
                                <?php 
                                $status = $status_map[$a['status']] ?? ['label' => $a['status'], 'color' => '#64748b', 'icon' => 'fa-question-circle'];
                                ?>
                                <form action="update_appointment.php" method="POST">
                                    <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                    <select name="status" class="form-input" style="width: auto; padding: 0.35rem 0.75rem; border-radius: 50px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; border: 1px solid <?php echo $status['color']; ?>; color: <?php echo $status['color']; ?>; background: <?php echo $status['color']; ?>0d;" onchange="updateAppointment(this)">
                                        <?php foreach ($status_map as $key => $info): ?>
                                            <option value="<?php echo $key; ?>" <?php echo $a['status'] === $key ? 'selected' : ''; ?>><?php echo $info['label']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </td>
                            <td style="padding: 1.25rem 1.5rem;">
                                <div style="display: flex; gap: 0.5rem; justify-content: center; align-items: center;">
                                    <?php if ($a['contact_type'] === 'Lead' && $a['status'] !== 'cancelled' && $a['status'] !== 'arrived'): ?>
                                        <a href="checkin.php?id=<?php echo $a['id']; ?>" class="btn btn-sm" style="background: #10b981; color: white; padding: 0.5rem 1rem; font-weight: 700; border-radius: 10px; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2);">
                                            <i class="fas fa-sign-in-alt"></i> CHECK-IN
                                        </a>
                                    <?php endif; ?>
                                    
                                    <div class="dropdown-action" style="position: relative;">
                                        <button onclick="toggleAction(<?php echo $a['id']; ?>, event)" class="btn btn-sm" style="background: #f1f5f9; color: var(--text-muted); width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; padding: 0; border-radius: 10px;">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <div id="action-menu-<?php echo $a['id']; ?>" class="action-menu" style="display: none; position: absolute; right: 0; top: 100%; width: 180px; background: white; border-radius: 12px; box-shadow: var(--shadow-lg); z-index: 1000; padding: 0.5rem; border: 1px solid var(--border-color); margin-top: 0.5rem;">
                                            <a href="view.php?id=<?php echo $a['id']; ?>" class="action-item"><i class="fas fa-eye"></i> Xem chi tiết</a>
                                            <a href="edit.php?id=<?php echo $a['id']; ?>" class="action-item"><i class="fas fa-edit"></i> Chỉnh sửa</a>
                                            <?php if ($a['patient_id']): ?>
                                                <a href="../patients/view.php?id=<?php echo $a['patient_id']; ?>" class="action-item"><i class="fas fa-user"></i> Hồ sơ bệnh nhân</a>
                                            <?php endif; ?>
                                            <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 0.5rem 0;">
                                            <a href="delete.php?id=<?php echo $a['id']; ?>" class="action-item" style="color: #ef4444;" onclick="return confirm('Bạn có chắc chắn muốn xóa lịch hẹn này?')"><i class="fas fa-trash-alt"></i> Xóa lịch hẹn</a>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php elseif ($view === 'timeline'): ?>
    <!-- Day Timeline View -->
    <div class="card" style="padding: 1.5rem; overflow-x: auto;">
        <?php
        $target_date = $date_filter ?: date('Y-m-d');
        $doctors_with_unassigned = array_merge([['id' => 0, 'full_name' => 'Chưa chỉ định', 'role_name' => 'N/A']], $doctors);
        
        // Group appointments by staff for this specific day
        $day_grouped = [];
        foreach ($appointments as $a) {
            $did = $a['doctor_id'] ?: 0;
            $day_grouped[$did][] = $a;
        }
        ?>
        <div style="text-align: center; margin-bottom: 2rem;">
            <h2 style="margin: 0; font-weight: 800; color: var(--text-main);">Lịch trình ngày <?php echo date('d/m/Y', strtotime($target_date)); ?></h2>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;">
            <?php foreach ($doctors_with_unassigned as $doc): ?>
                <?php 
                $staff_appts = $day_grouped[$doc['id']] ?? [];
                if (empty($staff_appts) && $doc['id'] !== 0) continue; // Skip empty staff unless unassigned
                ?>
                <div class="staff-day-column" style="background: #f8fafc; border-radius: 16px; padding: 1.25rem; border: 1px solid var(--border-color);">
                    <div style="margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 2px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-weight: 800; color: var(--text-main); font-size: 0.95rem;">
                            <i class="fas fa-user-md" style="color: var(--primary); margin-right: 0.5rem;"></i>
                            <?php echo e($doc['full_name']); ?>
                        </span>
                        <span style="font-size: 0.7rem; font-weight: 700; background: #e2e8f0; color: var(--text-muted); padding: 0.2rem 0.5rem; border-radius: 6px;">
                            <?php echo count($staff_appts); ?> Ca
                        </span>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <?php if (empty($staff_appts)): ?>
                            <div style="text-align: center; padding: 2rem; color: var(--text-muted); font-size: 0.85rem; font-style: italic;">
                                Không có lịch hẹn
                            </div>
                        <?php else: ?>
                            <?php foreach ($staff_appts as $a): 
                                $status = $status_map[$a['status']] ?? ['color' => '#64748b', 'label' => 'Unknown'];
                            ?>
                                <div class="day-event-card" style="background: white; border-radius: 12px; padding: 1rem; box-shadow: var(--shadow-sm); border-left: 4px solid <?php echo $status['color']; ?>; cursor: pointer; transition: transform 0.2s;" onclick="location.href='view.php?id=<?php echo $a['id']; ?>'">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                        <span style="font-weight: 800; font-size: 1rem; color: var(--text-main);"><?php echo date('H:i', strtotime($a['appointment_date'])); ?></span>
                                        <span style="font-size: 0.65rem; font-weight: 800; text-transform: uppercase; color: <?php echo $status['color']; ?>;"><?php echo $status['label']; ?></span>
                                    </div>
                                    <div style="font-weight: 700; color: var(--text-main); margin-bottom: 0.25rem;"><?php echo e($a['contact_name']); ?></div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-phone-alt" style="font-size: 0.6rem;"></i> <?php echo e($a['contact_phone']); ?>
                                    </div>
                                    <?php if (!empty($a['notes'])): ?>
                                        <div style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px dashed #e2e8f0; font-size: 0.75rem; color: var(--text-muted); line-height: 1.4;">
                                            <i class="fas fa-sticky-note" style="font-size: 0.65rem; color: #f59e0b;"></i> <?php echo e(mb_strimwidth($a['notes'], 0, 80, "...")); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php elseif ($view === 'timeline_week'): ?>
    <!-- Week Timeline View -->
    <div class="card" style="padding: 1.5rem; overflow-x: auto;">
        <?php
        $start_date = $date_filter ?: date('Y-m-d');
        $ts = strtotime($start_date);
        $start_of_week = date('Y-m-d', strtotime('monday this week', $ts));
        $week_days = [];
        for ($i = 0; $i < 7; $i++) {
            $week_days[] = date('Y-m-d', strtotime("+$i days", strtotime($start_of_week)));
        }
        
        $doctors_with_unassigned = array_merge([['id' => 0, 'full_name' => 'Chưa chỉ định']], $doctors);
        
        // Group appointments by doctor and day
        $grouped_week = [];
        foreach ($appointments as $a) {
            $did = $a['doctor_id'] ?: 0;
            $day = date('Y-m-d', strtotime($a['appointment_date']));
            $grouped_week[$did][$day][] = $a;
        }
        ?>
        <div class="timeline-grid" style="display: grid; grid-template-columns: 150px repeat(7, 1fr); min-width: 1000px;">
            <!-- Header Row -->
            <div style="padding: 1rem; border-bottom: 2px solid #e2e8f0; font-weight: 800; color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase;">Bác sĩ</div>
            <?php foreach ($week_days as $day): ?>
                <div style="padding: 1rem; border-bottom: 2px solid #e2e8f0; text-align: center; border-left: 1px solid #f1f5f9; font-weight: 700; background: <?php echo $day === date('Y-m-d') ? '#fffbeb' : 'transparent'; ?>;">
                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;"><?php echo date('D', strtotime($day)); ?></div>
                    <div style="font-size: 1rem; color: var(--text-main);"><?php echo date('d/m', strtotime($day)); ?></div>
                </div>
            <?php endforeach; ?>

            <!-- Doctor Rows -->
            <?php foreach ($doctors_with_unassigned as $doc): ?>
                <div style="padding: 1rem; border-bottom: 1px solid #e2e8f0; font-weight: 700; background: #f8fafc; border-right: 1px solid #e2e8f0; display: flex; align-items: center; font-size: 0.9rem;">
                    <?php echo e($doc['full_name']); ?>
                </div>
                <?php foreach ($week_days as $day): ?>
                    <div style="border-bottom: 1px solid #e2e8f0; border-left: 1px solid #f1f5f9; padding: 0.5rem; min-height: 100px; background: white;">
                        <?php 
                        $day_appts = $grouped_week[$doc['id']][$day] ?? [];
                        foreach ($day_appts as $a): 
                            $status = $status_map[$a['status']] ?? ['color' => '#64748b'];
                        ?>
                            <div class="week-event" style="background: <?php echo $status['color']; ?>; color: white; padding: 0.35rem 0.6rem; border-radius: 6px; font-size: 0.7rem; margin-bottom: 0.25rem; cursor: pointer; position: relative;" onclick="toggleAction(<?php echo $a['id']; ?>, event)">
                                <strong style="display: block;"><?php echo date('H:i', strtotime($a['appointment_date'])); ?></strong>
                                <span style="display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo e($a['contact_name']); ?></span>
                                
                                <div id="action-menu-<?php echo $a['id']; ?>" class="action-menu" style="display: none; position: absolute; left: 0; top: 100%; width: 180px; background: white; border-radius: 12px; box-shadow: var(--shadow-lg); z-index: 1000; padding: 0.5rem; border: 1px solid var(--border-color); color: var(--text-main);">
                                    <a href="view.php?id=<?php echo $a['id']; ?>" class="action-item"><i class="fas fa-eye"></i> Xem chi tiết</a>
                                    <a href="edit.php?id=<?php echo $a['id']; ?>" class="action-item"><i class="fas fa-edit"></i> Chỉnh sửa</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </div>
<?php elseif ($_GET['view'] === 'timeline_month'): ?>
    <!-- Month Timeline View (Calendar) -->
    <div class="card" style="padding: 1.5rem; border-radius: 20px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
        <?php
        $target_date = $date_filter ?: date('Y-m-d');
        $ts = strtotime($target_date);
        $month = date('m', $ts);
        $year = date('Y', $ts);
        
        $first_day_ts = strtotime("$year-$month-01");
        $first_day_of_week = date('N', $first_day_ts) - 1; // 0 (Mon) to 6 (Sun)
        $days_in_month = date('t', $first_day_ts);
        
        // Month Navigation
        $prev_month_date = date('Y-m-d', strtotime("-1 month", $first_day_ts));
        $next_month_date = date('Y-m-d', strtotime("+1 month", $first_day_ts));
        
        $nav_params = "view=timeline_month&search=$search&status=$status_filter&doctor_id=$doctor_filter&type=$type_filter";
        
        // Group appointments by day
        $grouped_month = [];
        foreach ($appointments as $a) {
            $day = (int)date('d', strtotime($a['appointment_date']));
            $grouped_month[$day][] = $a;
        }
        ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2.5rem; padding: 0 1rem;">
            <a href="?<?php echo $nav_params; ?>&date=<?php echo $prev_month_date; ?>" class="btn shadow-sm" style="background: white; border: 1px solid #e2e8f0; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; border-radius: 12px; color: var(--text-main); transition: all 0.2s;">
                <i class="fas fa-chevron-left"></i>
            </a>
            <h2 style="margin: 0; font-weight: 800; color: var(--text-main); font-size: 1.5rem; letter-spacing: -0.02em;">Tháng <?php echo $month; ?> / <?php echo $year; ?></h2>
            <a href="?<?php echo $nav_params; ?>&date=<?php echo $next_month_date; ?>" class="btn shadow-sm" style="background: white; border: 1px solid #e2e8f0; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; border-radius: 12px; color: var(--text-main); transition: all 0.2s;">
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>

        <div class="calendar-grid" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 12px;">
            <!-- Day labels -->
            <?php foreach (['Thứ 2','Thứ 3','Thứ 4','Thứ 5','Thứ 6','Thứ 7','Chủ Nhật'] as $lbl): ?>
                <div style="text-align: center; font-size: 0.7rem; font-weight: 800; color: #94a3b8; padding: 0.5rem; text-transform: uppercase; letter-spacing: 0.05em;"><?php echo $lbl; ?></div>
            <?php endforeach; ?>

            <!-- Empty cells before first day -->
            <?php for ($i = 0; $i < $first_day_of_week; $i++): ?>
                <div style="min-height: 130px; background: #f8fafc; border-radius: 16px; border: 1px dashed #e2e8f0;"></div>
            <?php endfor; ?>

            <!-- Actual days -->
            <?php for ($d = 1; $d <= $days_in_month; $d++): 
                $current_date_str = "$year-$month-" . str_pad($d, 2, '0', STR_PAD_LEFT);
                $is_today = ($current_date_str == date('Y-m-d'));
                $day_appts = $grouped_month[$d] ?? [];
            ?>
                <div style="min-height: 130px; background: white; border-radius: 16px; border: 1px solid <?php echo $is_today ? 'var(--primary)' : '#eef2f6'; ?>; padding: 0.85rem; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative; <?php echo $is_today ? 'box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);' : ''; ?>" 
                     class="calendar-day-cell <?php echo $is_today ? 'today' : ''; ?>"
                     onclick="location.href='?view=timeline&date=<?php echo $current_date_str; ?>&<?php echo $view_btn_params; ?>'">
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem;">
                        <span style="font-weight: 800; font-size: 1.15rem; color: <?php echo $is_today ? 'var(--primary)' : 'var(--text-main)'; ?>;">
                            <?php echo $d; ?>
                        </span>
                        <?php if (count($day_appts) > 0): ?>
                            <span style="background: <?php echo $is_today ? 'var(--primary)' : '#f1f5f9'; ?>; color: <?php echo $is_today ? 'white' : '#475569'; ?>; font-size: 0.65rem; font-weight: 800; padding: 0.2rem 0.5rem; border-radius: 6px;">
                                <?php echo count($day_appts); ?> Ca
                            </span>
                        <?php endif; ?>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 5px;">
                        <?php foreach (array_slice($day_appts, 0, 3) as $a): 
                            $status = $status_map[$a['status']] ?? ['color' => '#64748b'];
                        ?>
                            <div style="font-size: 0.65rem; padding: 4px 8px; border-radius: 6px; background: <?php echo $status['color']; ?>; color: white; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 700; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                <?php echo date('H:i', strtotime($a['appointment_date'])); ?> <?php echo e($a['contact_name']); ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($day_appts) > 3): ?>
                            <div style="font-size: 0.6rem; color: var(--text-muted); text-align: center; font-weight: 700; margin-top: 4px; background: #f8fafc; padding: 2px; border-radius: 4px;">+ <?php echo count($day_appts) - 3; ?> lịch hẹn</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>
<?php endif; ?>

<style>
.appointment-row:hover { background: #f1f5f9; }
.appointment-row select:hover { background: white !important; border-color: #e2e8f0 !important; }
.text-xs { font-size: 0.7rem; }
.filter-label {
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
    color: #94a3b8;
    margin-bottom: 0.5rem;
    display: block;
    letter-spacing: 0.025em;
}
.filter-input {
    height: 42px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.2s;
}
.filter-input:focus {
    background: white;
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
}
.btn-white {
    background: white;
    color: var(--primary);
}
.calendar-day-cell {
    cursor: pointer;
}
.calendar-day-cell:hover {
    border-color: var(--primary) !important;
    transform: translateY(-4px);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05) !important;
    z-index: 10;
}
.calendar-day-cell.today {
    background: #fdf2f205;
}
.action-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.6rem 0.75rem;
    color: var(--text-main);
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 600;
    border-radius: 8px;
    transition: all 0.2s;
}
.action-item:hover {
    background: #f8fafc;
    color: var(--primary);
}
.action-item i {
    width: 16px;
    text-align: center;
    font-size: 0.9rem;
    color: #94a3b8;
}
.action-item:hover i {
    color: var(--primary);
}
.timeline-event:hover {
    transform: scale(1.05);
    box-shadow: 0 8px 15px rgba(0,0,0,0.2);
}
.week-event:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
}
.day-event-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-md);
}

/* Tooltip styles */
.note-tooltip-container {
    position: relative;
    display: inline-block;
    margin-left: 0.5rem;
    cursor: help;
}
.note-icon {
    color: #f59e0b;
    font-size: 0.85rem;
    transition: transform 0.2s;
}
.note-tooltip-container:hover .note-icon {
    transform: scale(1.2);
}
.note-tooltip {
    visibility: hidden;
    width: 250px;
    background-color: #1e293b;
    color: #fff;
    text-align: left;
    border-radius: 12px;
    padding: 1rem;
    position: absolute;
    z-index: 1001;
    bottom: 125%;
    left: 50%;
    margin-left: -125px;
    opacity: 0;
    transition: opacity 0.3s;
    font-size: 0.75rem;
    line-height: 1.5;
    font-weight: 500;
    box-shadow: var(--shadow-lg);
    border: 1px solid rgba(255,255,255,0.1);
}
.note-tooltip::after {
    content: "";
    position: absolute;
    top: 100%;
    left: 50%;
    margin-left: -5px;
    border-width: 5px;
    border-style: solid;
    border-color: #1e293b transparent transparent transparent;
}
.note-tooltip-container:hover .note-tooltip {
    visibility: visible;
    opacity: 1;
}
</style>

<div id="quick-toast" style="position: fixed; bottom: 2rem; right: 2rem; background: #0f172a; color: white; padding: 0.8rem 1.5rem; border-radius: 12px; box-shadow: var(--shadow-lg); display: none; z-index: 9999; font-weight: 600; font-size: 0.9rem;">
    <i class="fas fa-check-circle" style="color: #10b981; margin-right: 0.5rem;"></i> <span id="toast-msg">Đã cập nhật!</span>
</div>

<script>
function toggleAction(id, event) {
    event.stopPropagation();
    const menu = document.getElementById('action-menu-' + id);
    const allMenus = document.querySelectorAll('.action-menu');
    
    allMenus.forEach(m => {
        if (m.id !== 'action-menu-' + id) m.style.display = 'none';
    });
    
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}

document.addEventListener('click', function() {
    document.querySelectorAll('.action-menu').forEach(m => m.style.display = 'none');
});

function showToast(msg) {
    const toast = document.getElementById('quick-toast');
    document.getElementById('toast-msg').innerText = msg;
    toast.style.display = 'block';
    setTimeout(() => { toast.style.display = 'none'; }, 2000);
}

function updateAppointment(select) {
    const form = select.closest('form');
    const formData = new FormData(form);
    const selectName = select.name;

    select.style.opacity = '0.5';

    fetch('update_appointment.php', {
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
            showToast('Đã lưu ' + (selectName === 'doctor_id' ? 'bác sĩ' : 'trạng thái') + '!');
            if (selectName === 'status') {
                // Refresh to update colors if not doing it via CSS/JS dynamically
                setTimeout(() => location.reload(), 500);
            }
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
