<?php
// modules/patients/index.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('view_patients');

$page_title = __('patient.list.title');
$current_page = 'patients';
require_once '../../templates/header.php';

$db = getDB();
$search = $_GET['search'] ?? '';
$label = $_GET['label'] ?? '';
$gender_filter = $_GET['gender'] ?? '';
$gender = $gender_filter; // M4 FIX: Remove duplicate, use alias
$period = $_GET['period'] ?? '';
$start_date_filter = $_GET['start_date'] ?? '';
$end_date_filter = $_GET['end_date'] ?? '';

$sql = "SELECT p.*, COUNT(ms.id) as record_count, MAX(ms.session_date) as last_visit 
        FROM patients p 
        LEFT JOIN medical_sessions ms ON p.id = ms.patient_id";
$where = [];
$params = [];

if ($search) {
    // H3 FIX: Escape LIKE wildcards
    $safe_search = addcslashes($search, '%_');
    $where[] = "(p.full_name LIKE ? OR p.phone LIKE ? OR p.customer_id LIKE ?)";
    $params[] = "%$safe_search%";
    $params[] = "%$safe_search%";
    $params[] = "%$safe_search%";
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
                            <option value="<?php echo e($l); ?>" <?php echo $label === $l ? 'selected' : ''; ?>><?php echo e(get_patient_label_translation($l)); ?></option>
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
        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
            <a href="add.php" class="btn btn-primary shadow-sm" style="padding: 0.6rem 1.2rem; font-weight: 700; font-size: 0.9rem; border-radius: 12px; white-space: nowrap;">
                <i class="fas fa-plus"></i> <?php echo __('common.add_new'); ?>
            </a>
            <div style="display: flex; gap: 0.4rem;">
                <a href="export.php?<?php echo http_build_query($_GET); ?>" class="btn btn-outline-primary" style="padding: 0.4rem; flex: 1; font-size: 0.75rem; border-radius: 8px; font-weight: 700;" title="<?php echo __('patient.index.export_title'); ?>">
                    <i class="fas fa-file-export"></i> <?php echo __('common.export'); ?>
                </a>
                <a href="import.php" class="btn btn-outline-success" style="padding: 0.4rem; flex: 1; font-size: 0.75rem; border-radius: 8px; font-weight: 700;" title="<?php echo __('patient.index.import_title'); ?>">
                    <i class="fas fa-file-import"></i> <?php echo __('common.import'); ?>
                </a>
                <a href="../medical/backup.php" class="btn btn-outline-danger" style="padding: 0.4rem; flex: 1; font-size: 0.75rem; border-radius: 8px; font-weight: 700;" title="<?php echo __('patient.index.backup_title'); ?>">
                    <i class="fas fa-database"></i> <?php echo __('common.backup'); ?>
                </a>
            </div>
        </div>
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

<div class="card" style="padding: 0; overflow: hidden; border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
    <table class="table" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: transparent; text-align: left;">
                <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: #94a3b8; font-weight: 700; border-bottom: 2px solid #f1f5f9; width: 12%;"><?php echo __('appointment.table.created_at'); ?></th>
                <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: #94a3b8; font-weight: 700; border-bottom: 2px solid #f1f5f9; width: 28%;"><?php echo __('patient.table.patient_info'); ?></th>
                <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: #94a3b8; font-weight: 700; border-bottom: 2px solid #f1f5f9; width: 20%;"><?php echo __('patient.table.contact'); ?></th>
                <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: #94a3b8; font-weight: 700; border-bottom: 2px solid #f1f5f9; width: 25%;"><?php echo __('patient.table.clinical_history'); ?></th>
                <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: #94a3b8; font-weight: 700; border-bottom: 2px solid #f1f5f9; text-align: right; width: 15%;"><?php echo __('common.actions'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($patients as $p): ?>
                <tr class="patient-row" style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s;" onmouseover="this.style.background='#f8fafc';" onmouseout="this.style.background='transparent';">
                    <td style="padding: 1.25rem 1.5rem; color: #64748b; font-size: 0.85rem;">
                        <?php echo date('d/m/Y', strtotime($p['created_at'])); ?>
                    </td>
                    <td style="padding: 1.25rem 1.5rem;">
                        <div style="display: flex; flex-direction: column;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                                <span style="font-size: 0.7rem; font-weight: 800; background: #f1f5f9; padding: 0.1rem 0.4rem; border-radius: 4px; color: var(--text-muted);">
                                    <?php echo e($p['customer_id'] ?: 'BN-' . $p['id']); ?>
                                </span>
                                <?php if ($p['label']): ?>
                                    <?php
                                        $lbl = mb_strtolower(trim($p['label']), 'UTF-8');
                                        $bg = '#dcfce7'; $c = '#16a34a'; // default green
                                        if (mb_strpos($lbl, 'đang điều trị') !== false) { $bg = '#dbeafe'; $c = '#2563eb'; } 
                                        elseif (mb_strpos($lbl, 'cần chăm sóc') !== false || mb_strpos($lbl, 'khẩn') !== false) { $bg = '#ffedd5'; $c = '#ea580c'; } 
                                        elseif (mb_strpos($lbl, 'vip') !== false) { $bg = '#fef3c7'; $c = '#d97706'; } 
                                        elseif (mb_strpos($lbl, 'khách cũ') !== false) { $bg = '#f1f5f9'; $c = '#475569'; } 
                                        elseif (mb_strpos($lbl, 'duy anh') !== false) { $bg = '#fce7f3'; $c = '#db2777'; } 
                                    ?>
                                    <span style="font-size: 0.65rem; font-weight: 800; text-transform: uppercase; background: <?php echo $bg; ?>; color: <?php echo $c; ?>; padding: 0.15rem 0.6rem; border-radius: 8px; display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fas fa-tag"></i> <?php echo e(get_patient_label_translation($p['label'])); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <a href="view.php?id=<?php echo $p['id']; ?>" style="font-weight: 700; color: var(--primary); font-size: 0.95rem; text-decoration: none; display: inline-block; transition: color 0.2s;" onmouseover="this.style.color='#1d4ed8';" onmouseout="this.style.color='var(--primary)';">
                                <?php echo e($p['full_name']); ?>
                            </a>
                        </div>
                    </td>
                    <td style="padding: 1.25rem 1.5rem;">
                        <div style="font-weight: 600; color: #475569; font-size: 0.85rem;"><i class="fas fa-phone-alt" style="color: #94a3b8; font-size: 0.75rem; width: 14px;"></i> <?php echo e($p['phone']); ?></div>
                        <div style="font-size: 0.8rem; color: #94a3b8; margin-top: 0.2rem;"><i class="fas fa-envelope" style="font-size: 0.75rem; width: 14px;"></i> <?php echo e($p['email'] ?: '—'); ?></div>
                    </td>
                    <td style="padding: 1.25rem 1.5rem;">
                        <div style="font-weight: 600; font-size: 0.85rem; color: #334155; margin-bottom: 0.2rem;">
                            <i class="fas fa-notes-medical" style="color: #94a3b8; width: 16px;"></i> <?php echo $p['record_count']; ?> <?php echo __('patient.table.treatment_sessions'); ?>
                        </div>
                        <div style="font-size: 0.8rem; color: #64748b;">
                            <i class="fas fa-clock" style="color: #cbd5e1; width: 16px;"></i> <?php echo __('patient.table.last_visit'); ?> <?php echo $p['last_visit'] ? date('d/m/Y', strtotime($p['last_visit'])) : __('common.none'); ?>
                        </div>
                    </td>
                    <td style="padding: 1.25rem 1.5rem; text-align: right;">
                        <div style="display: flex; gap: 0.4rem; justify-content: flex-end;">
                            <a href="../appointments/add.php?patient_id=<?php echo $p['id']; ?>" style="background: #6366f1; color: white; padding: 0.4rem 0.8rem; font-weight: 600; font-size: 0.8rem; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.25rem; white-space: nowrap; transition: background 0.2s;" onmouseover="this.style.background='#4f46e5';" onmouseout="this.style.background='#6366f1';">
                                <i class="fas fa-calendar-plus"></i> <?php echo __('leads.table.btn_book'); ?>
                            </a>
                            <a href="view.php?id=<?php echo $p['id']; ?>" title="<?php echo __('common.view_details'); ?>" style="background: #f1f5f9; color: #64748b; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; text-decoration: none; transition: background 0.2s;" onmouseover="this.style.background='#e2e8f0';" onmouseout="this.style.background='#f1f5f9';">
                                <i class="fas fa-user-edit"></i>
                            </a>
                            <form action="delete.php" method="POST" style="display:inline" id="delPat<?php echo $p['id']; ?>">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                <button type="button" onclick="confirmAndSubmit(document.getElementById('delPat<?php echo $p['id']; ?>'), '<?php echo __('patient.confirm_delete'); ?>')" title="<?php echo __('common.delete'); ?>" style="background: #fef2f2; color: #ef4444; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; border: none; cursor:pointer; transition: background 0.2s;" onmouseover="this.style.background='#fee2e2';" onmouseout="this.style.background='#fef2f2';">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
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
