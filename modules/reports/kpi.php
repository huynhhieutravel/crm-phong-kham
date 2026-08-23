<?php
// modules/reports/kpi.php — Báo cáo thống kê KPI Kỹ thuật viên
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

require_permission('view_reports');

$page_title = __('reports') ?? 'Báo cáo' . ' — ' . 'KPI Kỹ Thuật Viên';
$current_page = 'reports';
require_once '../../templates/header.php';

$db = getDB();

$filter_month = $_GET['month'] ?? date('m');
$filter_year = $_GET['year'] ?? date('Y');

// Fetch user data mapping
$users = $db->query("SELECT id, full_name FROM users WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
$user_map = [];
foreach($users as $u) {
    $user_map[(string)$u['id']] = $u['full_name'];
}

// Fetch all histories for this month
$stmt = $db->prepare("
    SELECT h.id, h.history_data, h.created_at, p.full_name as patient_name
    FROM medical_history h
    JOIN patients p ON h.patient_id = p.id
    WHERE h.type = 'dong_y' 
      AND MONTH(h.created_at) = ? 
      AND YEAR(h.created_at) = ?
    ORDER BY h.created_at DESC
");
$stmt->execute([$filter_month, $filter_year]);
$histories = $stmt->fetchAll();

$kpi_stats = []; 
$treatment_logs = [];

foreach($histories as $row) {
    if (!$row['history_data']) continue;
    $data = json_decode($row['history_data'], true);
    if (!is_array($data)) continue;
    
    $t1 = $data['technician_1'] ?? '';
    $t2 = $data['technician_2'] ?? '';
    $notes = $data['treatment_notes'] ?? '';

    // Stat counting
    if ($t1) {
        if (!isset($kpi_stats[$t1])) $kpi_stats[$t1] = ['main' => 0, 'assist' => 0];
        $kpi_stats[$t1]['main']++;
    }
    if ($t2) {
        if (!isset($kpi_stats[$t2])) $kpi_stats[$t2] = ['main' => 0, 'assist' => 0];
        $kpi_stats[$t2]['assist']++;
    }

    if ($t1 || $t2) {
        $treatment_logs[] = [
            'id' => $row['id'],
            'date' => $row['created_at'],
            'patient' => $row['patient_name'],
            't1_name' => $t1 ? ($user_map[(string)$t1] ?? 'KTV đã xóa') : '—',
            't2_name' => $t2 ? ($user_map[(string)$t2] ?? 'KTV đã xóa') : '—',
            'notes' => $notes
        ];
    }
}

// Convert kpi_stats to flat array and sort
$kpi_array = [];
foreach($kpi_stats as $uid => $counts) {
    $kpi_array[] = [
        'user_id' => $uid,
        'name' => $user_map[(string)$uid] ?? 'Tài khoản đã xóa',
        'main' => $counts['main'],
        'assist' => $counts['assist'],
        'total' => $counts['main'] + $counts['assist']
    ];
}
usort($kpi_array, function($a, $b) { return $b['total'] <=> $a['total']; });
?>

<style>
    .kpi-card { background: white; border-radius: 16px; border: 1px solid #e2e8f0; padding: 1.5rem; transition: all 0.2s ease; }
    .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); }
    .filter-bar { display: flex; gap: 1rem; margin-bottom: 2rem; align-items: center; }
    .filter-bar select { padding: 0.6rem 1.25rem; border-radius: 50px; border: 1px solid #cbd5e1; font-weight: 700; background: white; cursor: pointer; }
    .badge-main { background: #dbeafe; color: #1d4ed8; font-weight: 800; padding: 0.2rem 0.6rem; border-radius: 50px; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 0.25rem; }
    .badge-assist { background: #fef3c7; color: #b45309; font-weight: 800; padding: 0.2rem 0.6rem; border-radius: 50px; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 0.25rem; }
</style>

<div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem;">
    <div>
        <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--primary); margin: 0 0 0.5rem 0;"><i class="fas fa-users-cog"></i> Báo Cáo KPI Kỹ Thuật Viên (Đông Y)</h2>
        <p style="color: var(--text-muted); margin: 0;">Quản lý và thống kê hiệu suất thực hiện liệu trình của đội ngũ KTV</p>
    </div>
    <form class="filter-bar" method="GET" style="margin: 0;">
        <select name="month">
            <?php for ($m = 1; $m <= 12; $m++): ?>
            <option value="<?php echo sprintf('%02d', $m); ?>" <?php echo $m == $filter_month ? 'selected' : ''; ?>>Tháng <?php echo $m; ?></option>
            <?php endfor; ?>
        </select>
        <select name="year">
            <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
            <option value="<?php echo $y; ?>" <?php echo $y == $filter_year ? 'selected' : ''; ?>>Năm <?php echo $y; ?></option>
            <?php endfor; ?>
        </select>
        <button type="submit" class="btn btn-primary" style="border-radius: 50px; padding: 0.6rem 1.25rem; font-weight: 800;"><i class="fas fa-filter"></i> Lọc dữ liệu</button>
    </form>
</div>

<!-- TOP KPI RANKS -->
<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <?php if(empty($kpi_array)): ?>
        <div style="grid-column: 1 / -1; background: white; padding: 3rem; text-align: center; border-radius: 20px; border: 1px solid #e2e8f0;">
            <i class="fas fa-box-open" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 1rem;"></i>
            <h4 style="color: #64748b; font-weight: 700;">Chưa có dữ liệu phân công KTV trong tháng này!</h4>
        </div>
    <?php endif; ?>
    <?php foreach(array_slice($kpi_array, 0, 4) as $index => $kpi): ?>
        <div class="kpi-card" style="position: relative;">
            <?php if($index == 0): ?>
                <div style="position: absolute; top: -12px; right: 20px; background: #fbbf24; color: white; padding: 0.25rem 0.75rem; border-radius: 50px; font-size: 0.75rem; font-weight: 800; box-shadow: 0 4px 6px -1px rgba(251, 191, 36, 0.5);"><i class="fas fa-crown"></i> TOP 1</div>
            <?php endif; ?>
            <div style="font-weight: 800; color: #1e293b; font-size: 1.1rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;"><i class="fas fa-user-md text-primary"></i> <?php echo e($kpi['name']); ?></div>
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <span style="font-size: 0.7rem; color: #64748b; text-transform: uppercase; font-weight: 800; display: block; margin-bottom: 0.25rem;">Là KTV Chính</span>
                    <span class="badge-main"><i class="fas fa-star"></i> <?php echo $kpi['main']; ?> lượt</span>
                </div>
                <div style="width: 1px; height: 30px; background: #e2e8f0;"></div>
                <div>
                    <span style="font-size: 0.7rem; color: #64748b; text-transform: uppercase; font-weight: 800; display: block; margin-bottom: 0.25rem;">Trợ lý phụ</span>
                    <span class="badge-assist"><i class="fas fa-hands-helping"></i> <?php echo $kpi['assist']; ?> lượt</span>
                </div>
            </div>
            <div style="margin-top: 1.25rem; background: #f8fafc; padding: 0.75rem; border-radius: 10px; text-align: center;">
                <span style="font-size: 0.7rem; font-weight: 700; color: #94a3b8; display: block; margin-bottom: 0.25rem; text-transform: uppercase;">Tổng KPÍ (Lượt)</span>
                <span style="font-size: 1.5rem; font-weight: 900; color: var(--primary);"><?php echo $kpi['total']; ?></span>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem;">
    <!-- ALL STAFF TABLE -->
    <div class="card" style="padding: 1.5rem; height: fit-content;">
        <h3 style="font-size: 1rem; color: #0f172a; margin: 0 0 1.5rem 0; font-weight: 800; display: flex; justify-content: space-between; align-items: center;">
            <span style="display: flex; align-items: center; gap: 0.5rem;"><i class="fas fa-chart-pie text-primary"></i> Thống kê toàn bộ KTV</span>
        </h3>
        <table class="table" style="width: 100%;">
            <thead>
                <tr style="border-bottom: 2px solid #e2e8f0;">
                    <th style="padding: 0.75rem 0.5rem; font-size: 0.8rem; text-transform: uppercase; color: #64748b;">Họ tên KTV</th>
                    <th style="padding: 0.75rem 0.5rem; font-size: 0.8rem; text-transform: uppercase; text-align: center; color: #64748b;">Chính</th>
                    <th style="padding: 0.75rem 0.5rem; font-size: 0.8rem; text-transform: uppercase; text-align: center; color: #64748b;">Phụ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($kpi_array as $row): ?>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 0.75rem 0.5rem; font-weight: 700; font-size: 0.9rem;"><?php echo e($row['name']); ?></td>
                    <td style="padding: 0.75rem 0.5rem; text-align: center;"><span class="badge-main" style="background: transparent; border: 1px solid #bfdbfe;"><?php echo $row['main']; ?></span></td>
                    <td style="padding: 0.75rem 0.5rem; text-align: center;"><span class="badge-assist" style="background: transparent; border: 1px solid #fcd34d;"><?php echo $row['assist']; ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- LOG DETAIL TABLE -->
    <div class="card" style="padding: 1.5rem;">
        <h3 style="font-size: 1rem; color: #0f172a; margin: 0 0 1.5rem 0; font-weight: 800; display: flex; justify-content: space-between; align-items: center;">
            <span style="display: flex; align-items: center; gap: 0.5rem;"><i class="fas fa-list-ol text-primary"></i> Bảng kê chi tiết công việc</span>
            <span style="font-size: 0.75rem; background: #e2e8f0; padding: 0.25rem 0.75rem; border-radius: 50px; color: #475569;"><?php echo count($treatment_logs); ?> ca</span>
        </h3>
        <div style="overflow-x: auto;">
            <table class="table" style="width: 100%; min-width: 600px;">
                <thead>
                    <tr style="border-bottom: 2px solid #e2e8f0;">
                        <th style="padding: 0.75rem 0.5rem; font-size: 0.8rem; text-transform: uppercase; color: #64748b;">Ngày / Bệnh nhân</th>
                        <th style="padding: 0.75rem 0.5rem; font-size: 0.8rem; text-transform: uppercase; color: #64748b;">Phân công</th>
                        <th style="padding: 0.75rem 0.5rem; font-size: 0.8rem; text-transform: uppercase; color: #64748b;">Ghi chú thực hiện</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($treatment_logs as $log): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 0.75rem 0.5rem;">
                            <div style="font-weight: 800; color: #1e293b; font-size: 0.9rem; margin-bottom: 0.2rem;"><?php echo e($log['patient']); ?></div>
                            <div style="font-size: 0.75rem; color: #64748b; font-weight: 600;"><i class="far fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($log['date'])); ?></div>
                        </td>
                        <td style="padding: 0.75rem 0.5rem;">
                            <div style="margin-bottom: 0.25rem;"><span class="badge-main" style="width: 50px; justify-content: center;">Chính</span> <?php echo e($log['t1_name']); ?></div>
                            <?php if($log['t2_name'] !== '—'): ?>
                                <div><span class="badge-assist" style="width: 50px; justify-content: center;">Phụ</span> <?php echo e($log['t2_name']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 0.75rem 0.5rem;">
                            <div style="background: #f8fafc; padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 0.85rem; color: #475569; font-style: italic;">
                                <?php echo $log['notes'] ? nl2br(e($log['notes'])) : '<span style="color:#94a3b8;">(Không có ghi chú...)</span>'; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../templates/footer.php'; ?>
