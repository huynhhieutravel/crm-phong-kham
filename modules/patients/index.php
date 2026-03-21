<?php
// modules/patients/index.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

$page_title = __('patient.list.title');
$current_page = 'patients';
require_once '../../templates/header.php';

$db = getDB();
$search = $_GET['search'] ?? '';
$label = $_GET['label'] ?? '';
$gender = $_GET['gender'] ?? '';
$period = $_GET['period'] ?? '';
$start_date_filter = $_GET['start_date'] ?? '';
$end_date_filter = $_GET['end_date'] ?? '';

$sql = "SELECT p.*, COUNT(ms.id) as record_count 
        FROM patients p 
        LEFT JOIN medical_sessions ms ON p.id = ms.patient_id";
$where = [];
$params = [];

if ($search) {
    $where[] = "(p.full_name LIKE ? OR p.phone LIKE ? OR p.customer_id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($label) {
    $where[] = "p.label = ?";
    $params[] = $label;
}

if ($gender) {
    $where[] = "p.gender = ?";
    $params[] = $gender;
}

if ($period) {
    $range = get_date_range($period, $start_date_filter, $end_date_filter);
    $where[] = "p.created_at BETWEEN ? AND ?";
    $params[] = $range['start'];
    $params[] = $range['end'];
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

// Pagination Logic
$limit = 20;
$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;

// Count total for pagination
$count_sql = "SELECT COUNT(*) FROM patients p";
if (!empty($where)) {
    $count_sql .= " WHERE " . implode(" AND ", $where);
}
$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($params);
$total_count = $count_stmt->fetchColumn();

// Fetch labels for filter
$labels = $db->query("SELECT DISTINCT label FROM patients WHERE label IS NOT NULL AND label != '' ORDER BY label ASC")->fetchAll(PDO::FETCH_COLUMN);

$sql .= " GROUP BY p.id ORDER BY p.created_at DESC";
$sql .= get_sql_limit($limit, $page);

$stmt = $db->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll();

$is_filtered = $search || $label || $gender || $period;
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
                <!-- Search -->
                <div class="filter-group">
                    <label class="filter-label"><?php echo __('common.search'); ?></label>
                    <div style="position: relative;">
                        <i class="fas fa-search" style="position: absolute; left: 0.8rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.75rem;"></i>
                        <input type="text" name="search" class="form-input filter-input" placeholder="<?php echo __('patient.search_placeholder'); ?>" value="<?php echo e($search); ?>" style="padding-left: 2.2rem !important;">
                    </div>
                </div>

                <!-- Label Filter -->
                <div class="filter-group">
                    <label class="filter-label"><?php echo __('patient.label'); ?></label>
                    <select name="label" class="form-input filter-input" onchange="this.form.submit()">
                        <option value=""><?php echo __('patient.filter.all_labels'); ?></option>
                        <?php foreach ($labels as $l): ?>
                            <option value="<?php echo e($l); ?>" <?php echo $label === $l ? 'selected' : ''; ?>><?php echo e($l); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Gender Filter -->
                <div class="filter-group">
                    <label class="filter-label"><?php echo __('patient.gender'); ?></label>
                    <select name="gender" class="form-input filter-input" onchange="this.form.submit()">
                        <option value=""><?php echo __('patient.filter.all_genders'); ?></option>
                        <option value="male" <?php echo $gender === 'male' ? 'selected' : ''; ?>><?php echo __('patient.gender.male'); ?></option>
                        <option value="female" <?php echo $gender === 'female' ? 'selected' : ''; ?>><?php echo __('patient.gender.female'); ?></option>
                    </select>
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                <div class="filter-btn-group">
                    <span class="filter-label" style="margin-bottom: 0; margin-right: 0.25rem;"><?php echo __('common.time_colon'); ?></span>
                    <input type="hidden" name="period" id="periodInput" value="<?php echo e($period); ?>">
                    <a href="#" class="filter-btn <?php echo $period == '' ? 'active' : ''; ?>" onclick="setPeriod('')"><?php echo __('filter.all'); ?></a>
                    <a href="#" class="filter-btn <?php echo $period == 'today' ? 'active' : ''; ?>" onclick="setPeriod('today')"><?php echo __('filter.today'); ?></a>
                    <a href="#" class="filter-btn <?php echo $period == 'week' ? 'active' : ''; ?>" onclick="setPeriod('week')"><?php echo __('filter.week'); ?></a>
                    <a href="#" class="filter-btn <?php echo $period == 'month' ? 'active' : ''; ?>" onclick="setPeriod('month')"><?php echo __('filter.month'); ?></a>
                    <a href="#" class="filter-btn <?php echo $period == 'quarter' ? 'active' : ''; ?>" onclick="setPeriod('quarter')"><?php echo __('filter.quarter'); ?></a>
                    <a href="#" class="filter-btn <?php echo $period == 'year' ? 'active' : ''; ?>" onclick="setPeriod('year')"><?php echo __('filter.year'); ?></a>
                    <a href="#" class="filter-btn <?php echo $period == 'custom' ? 'active' : ''; ?>" onclick="setPeriod('custom')"><?php echo __('filter.custom'); ?></a>
                </div>

                <div id="customDates" style="display: <?php echo $period == 'custom' ? 'flex' : 'none'; ?>; gap: 0.5rem; align-items: center;">
                    <div class="custom-range-box">
                        <input type="date" name="start_date" class="custom-range-input" value="<?php echo e($start_date_filter); ?>">
                        <span style="color: #94a3b8; font-size: 0.8rem;">→</span>
                        <input type="date" name="end_date" class="custom-range-input" value="<?php echo e($end_date_filter); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" style="height: 32px; padding: 0 0.75rem; border-radius: 8px;"><?php echo __('common.apply'); ?></button>
                </div>

                <?php if ($is_filtered): ?>
                    <div style="margin-left: auto;">
                        <a href="index.php" style="color: #ef4444; font-size: 0.8rem; font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 0.25rem;">
                            <i class="fas fa-times-circle"></i> <?php echo __('common.clear_filter'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </form>
        <a href="add.php" class="btn btn-primary shadow-sm" style="padding: 0.6rem 1.2rem; font-weight: 700; font-size: 0.9rem; border-radius: 12px; white-space: nowrap;">
            <i class="fas fa-plus"></i> <?php echo __('common.add_new'); ?>
        </a>
    </div>
</div>

<script>
function setPeriod(p) {
    document.getElementById('periodInput').value = p;
    if (p !== 'custom') {
        document.getElementById('filterForm').submit();
    } else {
        document.getElementById('customDates').style.display = 'flex';
        document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
        event.target.classList.add('active');
    }
}
</script>

<div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--border-color);">
    <table class="table" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f8fafc; text-align: left;">
                <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: var(--text-muted); font-weight: 700; border-bottom: 2px solid var(--border-color);"><?php echo __('patient.table.id_name'); ?></th>
                <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: var(--text-muted); font-weight: 700; border-bottom: 2px solid var(--border-color);"><?php echo __('patient.table.contact'); ?></th>
                <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: var(--text-muted); font-weight: 700; border-bottom: 2px solid var(--border-color);"><?php echo __('patient.table.dob_gender'); ?></th>
                <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: var(--text-muted); font-weight: 700; border-bottom: 2px solid var(--border-color); text-align: center;"><?php echo __('patient.table.record_count'); ?></th>
                <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: var(--text-muted); font-weight: 700; border-bottom: 2px solid var(--border-color); text-align: center;"><?php echo __('common.action'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($patients as $p): ?>
                <tr class="patient-row" style="border-bottom: 1px solid var(--border-color); transition: background 0.2s;">
                    <td style="padding: 1.25rem 1.5rem;">
                        <div style="display: flex; flex-direction: column;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                                <span style="font-size: 0.7rem; font-weight: 800; background: #f1f5f9; padding: 0.1rem 0.4rem; border-radius: 4px; color: var(--text-muted);">
                                    <?php echo e($p['customer_id'] ?: 'BN-' . $p['id']); ?>
                                </span>
                                <?php if ($p['label']): ?>
                                    <span style="font-size: 0.65rem; font-weight: 700; text-transform: uppercase; background: <?php 
                                        echo strtolower($p['label']) === 'vip' ? '#fef3c7' : '#dcfce7'; 
                                    ?>; color: <?php 
                                        echo strtolower($p['label']) === 'vip' ? '#d97706' : '#16a34a'; 
                                    ?>; padding: 0.1rem 0.5rem; border-radius: 99px;">
                                        <?php echo e($p['label']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div style="font-weight: 700; color: var(--text-main); font-size: 1.05rem;"><?php echo e($p['full_name']); ?></div>
                        </div>
                    </td>
                    <td style="padding: 1.25rem 1.5rem;">
                        <div style="font-weight: 600; color: var(--primary);"><i class="fas fa-phone-alt" style="font-size: 0.8rem;"></i> <?php echo e($p['phone']); ?></div>
                        <div style="font-size: 0.85rem; color: var(--text-muted);"><?php echo e($p['email'] ?: '—'); ?></div>
                    </td>
                    <td style="padding: 1.25rem 1.5rem;">
                        <div style="font-weight: 500;"><?php echo $p['birthday'] ? date('d/m/Y', strtotime($p['birthday'])) : '—'; ?></div>
                        <div style="font-size: 0.85rem; color: var(--text-muted);">
                            <?php echo $p['gender'] === 'male' ? '<i class="fas fa-mars" style="color: #2563eb;"></i> ' . __('patient.gender.male') : ($p['gender'] === 'female' ? '<i class="fas fa-venus" style="color: #e4405f;"></i> ' . __('patient.gender.female') : __('patient.gender.other')); ?>
                        </div>
                    </td>
                    <td style="padding: 1.25rem 1.5rem; text-align: center;">
                        <span style="display: inline-flex; align-items: center; justify-content: center; min-width: 28px; height: 28px; background: <?php echo $p['record_count'] > 0 ? '#eff6ff' : '#f8fafc'; ?>; color: <?php echo $p['record_count'] > 0 ? '#2563eb' : '#94a3b8'; ?>; border-radius: 8px; font-weight: 700; font-size: 0.85rem; border: 1px solid <?php echo $p['record_count'] > 0 ? '#dbeafe' : '#e2e8f0'; ?>;">
                            <?php echo $p['record_count']; ?>
                        </span>
                    </td>
                    <td style="padding: 1.25rem 1.5rem;">
                        <div style="display: flex; gap: 0.5rem; justify-content: center;">
                            <a href="../appointments/add.php?patient_id=<?php echo $p['id']; ?>" class="btn btn-sm" title="<?php echo __('appointment.book_title'); ?>" style="background: #fdf2f8; color: #db2777; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; padding: 0; border-radius: 10px;">
                                <i class="fas fa-calendar-plus"></i>
                            </a>
                            <a href="view.php?id=<?php echo $p['id']; ?>" class="btn btn-sm" title="<?php echo __('common.view_details'); ?>" style="background: #eff6ff; color: #2563eb; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; padding: 0; border-radius: 10px;">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="edit.php?id=<?php echo $p['id']; ?>" class="btn btn-sm" title="<?php echo __('common.edit'); ?>" style="background: #f1f5f9; color: var(--text-muted); width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; padding: 0; border-radius: 10px;">
                                <i class="fas fa-edit"></i>
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($patients)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 5rem 2rem;">
                         <div style="opacity: 0.1; margin-bottom: 1rem;"><i class="fas fa-users-slash fa-4x"></i></div>
                         <div style="color: var(--text-muted); font-size: 1.1rem; font-weight: 600;"><?php echo __('patient.no_data'); ?></div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php echo render_pagination($total_count, $limit, $page); ?>

<style>
.patient-row:hover { background: #f8fafc; }
</style>

<?php require_once '../../templates/footer.php'; ?>
