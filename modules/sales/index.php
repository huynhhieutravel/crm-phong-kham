<?php
// modules/sales/index.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('view_sales');
$page_title = __('sales.index.page_title');
$current_page = 'sales';
require_once '../../templates/header.php';

$db = getDB();

// Fetch all packages grouped by patient
$stmt = $db->query("
    SELECT pp.*, 
           pkg.name as package_name, pkg.total_sessions as pkg_total_sessions,
           pt.full_name as patient_name, pt.phone as patient_phone, pt.id as pid
    FROM patient_packages pp
    JOIN packages pkg ON pp.package_id = pkg.id
    JOIN patients pt ON pp.patient_id = pt.id
    ORDER BY pt.full_name ASC, FIELD(pp.status, 'active','exhausted','expired'), pp.purchase_date DESC
");
$all_rows = $stmt->fetchAll();

// Group by patient
$grouped = [];
foreach ($all_rows as $row) {
    $pid = $row['pid'];
    if (!isset($grouped[$pid])) {
        $grouped[$pid] = [
            'name' => $row['patient_name'],
            'phone' => $row['patient_phone'],
            'pid' => $pid,
            'packages' => [],
            'total_remaining' => 0,
            'total_packages' => 0,
            'active_count' => 0,
            'total_debt' => 0,
        ];
    }
    $grouped[$pid]['packages'][] = $row;
    $grouped[$pid]['total_packages']++;
    $grouped[$pid]['total_remaining'] += $row['sessions_remaining'];
    if ($row['status'] === 'active') $grouped[$pid]['active_count']++;
    // Tính công nợ
    $pkg_debt = max(0, (float)$row['total_amount'] - (float)$row['paid_amount']);
    $grouped[$pid]['total_debt'] += $pkg_debt;
}

// Fetch usage logs for all packages
$all_pkg_ids = array_column($all_rows, 'id');
$usage_by_pkg = [];
if (!empty($all_pkg_ids)) {
    $in = implode(',', array_map('intval', $all_pkg_ids));
    $uStmt = $db->query("
        SELECT pul.*, p.full_name as used_by_name,
               u.full_name as technician_name,
               t.session_id,
               a.id as appointment_id
        FROM package_usage_logs pul
        JOIN patients p ON pul.patient_id = p.id
        LEFT JOIN treatments t ON pul.treatment_id = t.id
        LEFT JOIN users u ON COALESCE(t.technician_id, pul.technician_id) = u.id
        LEFT JOIN appointments a ON pul.appointment_id = a.id
        WHERE pul.patient_package_id IN ($in)
        ORDER BY pul.used_at DESC
    ");
    foreach ($uStmt->fetchAll() as $log) {
        $usage_by_pkg[$log['patient_package_id']][] = $log;
    }
}

// Fetch shared users for all packages
$shared_by_pkg = [];
if (!empty($all_pkg_ids)) {
    $in = implode(',', array_map('intval', $all_pkg_ids));
    $sStmt = $db->query("
        SELECT psu.patient_package_id, p.full_name 
        FROM package_shared_users psu 
        JOIN patients p ON psu.patient_id = p.id 
        WHERE psu.patient_package_id IN ($in)
    ");
    foreach ($sStmt->fetchAll() as $s) {
        $shared_by_pkg[$s['patient_package_id']][] = $s['full_name'];
    }
}

$total_patients = count($grouped);
$total_active = array_sum(array_column($grouped, 'active_count'));
$total_sessions_left = array_sum(array_column($grouped, 'total_remaining'));
?>

<div class="card" style="border: none; box-shadow: var(--shadow-premium);">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h3 style="margin: 0; font-weight: 800; font-size: 1.25rem;"><?php echo __('sales.index.tracking_title'); ?></h3>
            <div style="font-size: 0.8rem; color: #64748b; margin-top: 0.25rem;">
                <span style="font-weight: 700; color: #4f46e5;"><?php echo $total_patients; ?></span> <?php echo strtolower(__('common.customer')); ?> · 
                <span style="font-weight: 700; color: #10b981;"><?php echo $total_active; ?></span> <?php echo __('sales.index.active_packages'); ?> · 
                <span style="font-weight: 700; color: #f59e0b;"><?php echo $total_sessions_left; ?></span> <?php echo __('sales.index.sessions_remaining'); ?>
            </div>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <a href="config_packages.php" class="btn" style="background: #f1f5f9; color: var(--text-main);">
                <i class="fas fa-cog"></i> <?php echo __('sales.index.config_packages'); ?>
            </a>
            <a href="add_package.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> <?php echo __('sales.index.sell_package'); ?>
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <div style="display: flex; gap: 0.75rem; margin-bottom: 1.5rem; flex-wrap: wrap; align-items: center;">
        <div style="flex: 1; min-width: 200px; position: relative;">
            <i class="fas fa-search" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.8rem;"></i>
            <input type="text" id="salesSearch" placeholder="<?php echo __('sales.index.search_placeholder'); ?>" oninput="filterSales()" style="width: 100%; padding: 0.6rem 0.75rem 0.6rem 2.25rem; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 0.85rem; outline: none; transition: border-color 0.2s;" onfocus="this.style.borderColor='#6366f1'" onblur="this.style.borderColor='#e2e8f0'">
        </div>
        <div style="display: flex; gap: 0.4rem;">
            <button class="spill active" onclick="filterStatus('all', this)"><?php echo __('common.all'); ?></button>
            <button class="spill" onclick="filterStatus('has_active', this)"><?php echo __('sales.index.has_sessions'); ?></button>
            <button class="spill" onclick="filterStatus('exhausted', this)"><?php echo __('sales.index.exhausted_sessions'); ?></button>
            <button class="spill" onclick="filterStatus('has_debt', this)" style="color: #dc2626;"><i class="fas fa-exclamation-circle"></i> Còn nợ</button>
        </div>
    </div>

    <!-- Customer List -->
    <div id="customerList">
        <?php if (empty($grouped)): ?>
            <div style="text-align: center; padding: 3rem; color: #94a3b8;">
                <i class="fas fa-box-open" style="font-size: 2.5rem; margin-bottom: 1rem; display: block;"></i>
                <div style="font-weight: 700;"><?php echo __('sales.index.no_packages'); ?></div>
            </div>
        <?php else: ?>
            <?php $ci = 0; foreach ($grouped as $pid => $cust): 
                $has_active = $cust['active_count'] > 0;
            ?>
            <div class="customer-row" 
                 data-name="<?php echo strtolower($cust['name']); ?>" 
                 data-phone="<?php echo $cust['phone']; ?>"
                 data-status="<?php echo $has_active ? 'has_active' : 'exhausted'; ?>"
                 data-debt="<?php echo $cust['total_debt'] > 0 ? 'has_debt' : ''; ?>"
                 style="border: 1px solid <?php echo $cust['total_debt'] > 0 ? '#fecaca' : '#e2e8f0'; ?>; border-radius: 14px; margin-bottom: 0.75rem; overflow: hidden; transition: all 0.2s;">
                
                <!-- Customer Header Row -->
                <div onclick="toggleCustomer(<?php echo $ci; ?>)" style="padding: 1rem 1.25rem; cursor: pointer; display: flex; align-items: center; gap: 1rem; transition: background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
                    
                    <!-- Avatar -->
                    <div style="width: 42px; height: 42px; background: <?php echo $has_active ? 'linear-gradient(135deg,#6366f1,#8b5cf6)' : '#e2e8f0'; ?>; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 800; font-size: 0.9rem; flex-shrink: 0;">
                        <?php echo mb_substr($cust['name'], 0, 1); ?>
                    </div>
                    
                    <!-- Name & Phone -->
                    <div style="flex: 1; min-width: 0;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <a href="../patients/view.php?id=<?php echo $pid; ?>" style="font-weight: 800; font-size: 0.95rem; color: #1e293b; text-decoration: none;" onclick="event.stopPropagation();" onmouseover="this.style.color='#6366f1'" onmouseout="this.style.color='#1e293b'">
                                <?php echo e($cust['name']); ?>
                            </a>
                            <?php if ($has_active): ?>
                            <span style="width: 7px; height: 7px; background: #10b981; border-radius: 50%; box-shadow: 0 0 6px #10b981;"></span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size: 0.75rem; color: #94a3b8; font-weight: 600;"><?php echo e($cust['phone']); ?></div>
                    </div>

                    <!-- Stats -->
                    <div style="display: flex; gap: 1.5rem; align-items: center;">
                        <div style="text-align: center;">
                            <div style="font-size: 0.6rem; font-weight: 700; color: #94a3b8; text-transform: uppercase;"><?php echo __('sales.index.package_unit'); ?></div>
                            <div style="font-weight: 800; font-size: 1rem; color: #4f46e5;"><?php echo $cust['total_packages']; ?></div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 0.6rem; font-weight: 700; color: #94a3b8; text-transform: uppercase;"><?php echo __('sales.index.sessions_left'); ?></div>
                            <div style="font-weight: 800; font-size: 1rem; color: <?php echo $cust['total_remaining'] <= 3 ? '#ef4444' : '#10b981'; ?>;"><?php echo $cust['total_remaining']; ?></div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 0.6rem; font-weight: 700; color: #94a3b8; text-transform: uppercase;"><?php echo __('sales.index.active_status'); ?></div>
                            <div style="font-weight: 800; font-size: 1rem; color: <?php echo $cust['active_count'] > 0 ? '#10b981' : '#94a3b8'; ?>;"><?php echo $cust['active_count']; ?></div>
                        </div>
                        <?php if ($cust['total_debt'] > 0): ?>
                        <div style="text-align: center;">
                            <div style="font-size: 0.6rem; font-weight: 700; color: #dc2626; text-transform: uppercase;">Nợ</div>
                            <div style="font-weight: 800; font-size: 0.8rem; color: #dc2626;"><?php echo number_format($cust['total_debt'], 0, ',', '.'); ?>đ</div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <i class="fas fa-chevron-down" id="custIcon<?php echo $ci; ?>" style="color: #94a3b8; font-size: 0.75rem; transition: transform 0.3s; flex-shrink: 0;"></i>
                </div>

                <!-- Package Details (hidden) -->
                <div id="custDetail<?php echo $ci; ?>" style="display: none; border-top: 1px solid #f1f5f9; background: #fafbfc;">
                    <?php foreach ($cust['packages'] as $pi => $pkg):
                        $total_sess = $pkg['pkg_total_sessions'] ?: 1;
                        $logs = $usage_by_pkg[$pkg['id']] ?? [];
                        $used = count($logs);
                        $pct = round(($used / $total_sess) * 100);
                        $shared = $shared_by_pkg[$pkg['id']] ?? [];
                        $is_active = $pkg['status'] === 'active';
                        $bar_color = $pct >= 80 ? '#ef4444' : ($pct >= 50 ? '#f59e0b' : '#10b981');
                        $status_map = [
                            'active' => [__('sales.status.active'),'#ecfdf5','#10b981'],
                            'exhausted' => [__('sales.status.exhausted'),'#fffbeb','#f59e0b'],
                            'expired' => [__('sales.status.expired'),'#fef2f2','#ef4444']
                        ];
                        $st = $status_map[$pkg['status']] ?? $status_map['active'];
                        $uid = $ci . '_' . $pi;
                        
                        // Tự động đồng bộ số buổi còn lại nếu bị lệch (fix lỗi sai số buổi)
                        $expected_rem = max(0, $total_sess - $used);
                        if ($pkg['sessions_remaining'] != $expected_rem) {
                            $db->prepare("UPDATE patient_packages SET sessions_remaining = ? WHERE id = ?")->execute([$expected_rem, $pkg['id']]);
                            $pkg['sessions_remaining'] = $expected_rem;
                            if ($expected_rem > 0 && $pkg['status'] === 'exhausted') {
                                $db->prepare("UPDATE patient_packages SET status = 'active' WHERE id = ?")->execute([$pkg['id']]);
                            } elseif ($expected_rem <= 0 && $pkg['status'] === 'active') {
                                $db->prepare("UPDATE patient_packages SET status = 'exhausted' WHERE id = ?")->execute([$pkg['id']]);
                            }
                        }
                    ?>
                    <div style="padding: 0.75rem 1.25rem; border-bottom: 1px solid #f1f5f9; <?php echo !$is_active ? 'opacity: 0.6;' : ''; ?>">
                        <!-- Package row -->
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 28px; height: 28px; background: <?php echo $st[1]; ?>; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i class="fas fa-box" style="font-size: 0.65rem; color: <?php echo $st[2]; ?>;"></i>
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <div style="display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.2rem;">
                                    <span style="font-weight: 700; font-size: 0.85rem; color: #1e293b;"><?php echo e($pkg['package_name']); ?></span>
                                    <span style="font-size: 0.55rem; font-weight: 800; background: <?php echo $st[1]; ?>; color: <?php echo $st[2]; ?>; padding: 0.1rem 0.35rem; border-radius: 4px;"><?php echo $st[0]; ?></span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <div style="flex: 1; max-width: 120px; background: #f1f5f9; height: 4px; border-radius: 2px; overflow: hidden;">
                                        <div style="background: <?php echo $bar_color; ?>; height: 100%; width: <?php echo $pct; ?>%;"></div>
                                    </div>
                                    <span style="font-size: 0.7rem; font-weight: 800; color: <?php echo $bar_color; ?>;"><?php echo $used; ?>/<?php echo $total_sess; ?></span>
                                </div>
                            </div>
                            <div style="font-size: 0.7rem; color: #64748b; text-align: right; flex-shrink: 0;">
                                <div><?php echo __('sales.index.purchased_on'); ?> <?php echo date('d/m/Y', strtotime($pkg['purchase_date'])); ?></div>
                                <div style="font-weight: 700; color: #6366f1;"><?php echo number_format($pkg['total_amount'], 0, ',', '.'); ?><?php echo __('common.currency'); ?></div>
                            </div>
                            <?php if (!empty($shared)): ?>
                            <div style="flex-shrink: 0;" title="<?php echo __('sales.index.shared_with'); ?><?php echo implode(', ', $shared); ?>">
                                <i class="fas fa-users" style="color: #6366f1; font-size: 0.75rem;"></i>
                            </div>
                            <?php endif; ?>
                            <button onclick="event.stopPropagation(); togglePkgUsage('<?php echo $uid; ?>')" style="border: none; background: #f1f5f9; width: 24px; height: 24px; border-radius: 6px; cursor: pointer; color: #64748b; font-size: 0.6rem; flex-shrink: 0;" title="<?php echo __('sales.index.view_history'); ?>">
                                <i class="fas fa-history"></i>
                            </button>
                            <a href="manage_shared.php?id=<?php echo $pkg['id']; ?>" style="border: none; background: #eef2ff; width: 24px; height: 24px; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #6366f1; font-size: 0.6rem; text-decoration: none; flex-shrink: 0;" title="<?php echo __('sales.index.manage_package'); ?>">
                                <i class="fas fa-cog"></i>
                            </a>
                        </div>

                        <div id="usage_<?php echo $uid; ?>" style="display: none; margin-top: 0.5rem; padding: 0.5rem 0.75rem; background: white; border-radius: 8px; border: 1px solid #f1f5f9;">
                            <div style="font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.4rem;">
                                <i class="fas fa-history"></i> <?php echo sprintf(__('sales.index.usage_history'), count($logs)); ?>
                            </div>
                            <?php if (empty($logs)): ?>
                                <div style="text-align: center; padding: 0.5rem; color: #cbd5e1; font-size: 0.75rem; font-style: italic;"><?php echo __('sales.index.not_used'); ?></div>
                            <?php else: ?>
                                <?php foreach (array_slice($logs, 0, 5) as $log): ?>
                                <div style="display: flex; align-items: center; gap: 0.4rem; padding: 0.25rem 0; border-bottom: 1px solid #fafbfc; font-size: 0.7rem;">
                                    <div style="width: 5px; height: 5px; background: #10b981; border-radius: 50%;"></div>
                                    <span style="font-weight: 700; color: #1e293b;"><?php echo date('d/m H:i', strtotime($log['used_at'])); ?></span>
                                    <?php if ($log['technician_name']): ?>
                                        <span style="color: #64748b;">&middot; <?php echo e($log['technician_name']); ?></span>
                                    <?php endif; ?>
                                    <span style="flex: 1;"></span>
                                    <?php if ($log['appointment_id']): ?>
                                    <a href="../appointments/view.php?id=<?php echo $log['appointment_id']; ?>" style="width: 18px; height: 18px; background: #eef2ff; color: #6366f1; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; font-size: 0.55rem;"><i class="fas fa-calendar-check"></i></a>
                                    <?php endif; ?>
                                    <?php if ($log['session_id']): ?>
                                    <a href="../medical/session_view.php?id=<?php echo $log['session_id']; ?>" style="width: 18px; height: 18px; background: #f0fdf4; color: #10b981; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; font-size: 0.55rem;"><i class="fas fa-file-medical"></i></a>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                                <?php if (count($logs) > 5): ?>
                                <a href="manage_shared.php?id=<?php echo $pkg['id']; ?>" style="display: block; text-align: center; padding: 0.3rem; font-size: 0.65rem; color: #6366f1; font-weight: 700; text-decoration: none;"><?php echo sprintf(__('sales.index.view_all_usage'), count($logs)); ?></a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php $ci++; endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.spill {
    padding: 0.4rem 0.85rem;
    border-radius: 50px;
    background: #f1f5f9;
    color: #64748b;
    font-size: 0.75rem;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}
.spill:hover { background: #e2e8f0; }
.spill.active {
    background: #4f46e5;
    color: white;
    box-shadow: 0 4px 10px rgba(79, 70, 229, 0.25);
}
</style>

<script>
function toggleCustomer(idx) {
    var detail = document.getElementById('custDetail' + idx);
    var icon = document.getElementById('custIcon' + idx);
    if (detail.style.display === 'none') {
        detail.style.display = 'block';
        icon.style.transform = 'rotate(180deg)';
    } else {
        detail.style.display = 'none';
        icon.style.transform = 'rotate(0deg)';
    }
}

function togglePkgUsage(uid) {
    var el = document.getElementById('usage_' + uid);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

var currentStatusFilter = 'all';

function filterSales() {
    var q = document.getElementById('salesSearch').value.toLowerCase();
    var rows = document.querySelectorAll('.customer-row');
    rows.forEach(function(row) {
        var name = row.getAttribute('data-name') || '';
        var phone = row.getAttribute('data-phone') || '';
        var status = row.getAttribute('data-status') || '';
        var debt = row.getAttribute('data-debt') || '';
        var matchText = name.indexOf(q) !== -1 || phone.indexOf(q) !== -1;
        var matchStatus = currentStatusFilter === 'all' || status === currentStatusFilter || (currentStatusFilter === 'has_debt' && debt === 'has_debt');
        row.style.display = (matchText && matchStatus) ? 'block' : 'none';
    });
}

function filterStatus(status, btn) {
    currentStatusFilter = status;
    document.querySelectorAll('.spill').forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');
    filterSales();
}
</script>

<?php require_once '../../templates/footer.php'; ?>
