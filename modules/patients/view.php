<?php
// modules/patients/view.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('view_patients');
$page_title = __('patient.detail.title');
$current_page = 'patients';
require_once '../../templates/header.php';

$id = (int)($_GET['id'] ?? 0);
$db = getDB();

$stmt = $db->prepare("
    SELECT p.*, u.full_name as consultant_name 
    FROM patients p 
    LEFT JOIN users u ON p.consultant_id = u.id 
    WHERE p.id = ?
");
$stmt->execute([$id]);
$patient = $stmt->fetch();

if (!$patient) {
    echo "<div class='card'>" . __('patient.not_found') . "</div>";
    require_once '../../templates/footer.php';
    exit;
}

// Handle Package -> Coin Conversion POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'convert_package_to_coins') {
    verify_csrf();
    $convert_pkg_id = (int)$_POST['patient_package_id'];
    require_once '../../includes/coin_functions.php';
    $coins_gained = convert_package_to_coins($db, $convert_pkg_id, $_SESSION['user_id']);
    if ($coins_gained) {
        set_flash("Đã quy đổi gói thành công! Bệnh nhân được cộng $coins_gained Coins vào Ví.", 'success');
    } else {
        set_flash("Quy đổi thất bại hoặc gói không còn hợp lệ.", 'error');
    }
    header("Location: view.php?id=$id");
    exit;
}

require_once '../../includes/coin_functions.php';
$patient_coin_balance = get_patient_coin_balance($db, $id);

// Fetch medical sessions
$stmt = $db->prepare("SELECT s.*, u.full_name as doctor_name FROM medical_sessions s JOIN users u ON s.doctor_id = u.id WHERE s.patient_id = ? ORDER BY s.session_date DESC");
$stmt->execute([$id]);
$sessions = $stmt->fetchAll();

// Fetch medical history summary
$stmt = $db->prepare("SELECT h.*, u.full_name as doctor_name FROM medical_history h LEFT JOIN users u ON h.created_by = u.id WHERE h.patient_id = ? ORDER BY h.created_at DESC");
$stmt->execute([$id]);
$histories = $stmt->fetchAll();

// Fetch treatments summary
$stmt = $db->prepare("SELECT t.*, u.full_name as technician_name FROM treatments t JOIN users u ON t.technician_id = u.id WHERE t.patient_id = ? ORDER BY t.treatment_date DESC");
$stmt->execute([$id]);
$treatments = $stmt->fetchAll();

// Fetch ALL packages (active + exhausted + expired) with full info
$stmt = $db->prepare("
    SELECT pp.*, pkg.name as package_name, pkg.total_sessions as pkg_total_sessions, pkg.total_price as pkg_price
    FROM patient_packages pp 
    JOIN packages pkg ON pp.package_id = pkg.id 
    WHERE pp.patient_id = ?
    ORDER BY FIELD(pp.status, 'active','exhausted','expired'), pp.purchase_date DESC
");
$stmt->execute([$id]);
$all_packages = $stmt->fetchAll();

// Fetch usage logs for all packages of this patient, with appointment + session links
$pkg_ids = array_column($all_packages, 'id');
$usage_logs_by_pkg = [];
if (!empty($pkg_ids)) {
    $in_clause = implode(',', array_map('intval', $pkg_ids));
    $usage_stmt = $db->query("
        SELECT pul.*, 
               p.full_name as used_by_name,
               u.full_name as technician_name,
               t.treatment_date,
               t.session_id,
               a.id as appointment_id, a.appointment_date
        FROM package_usage_logs pul
        JOIN patients p ON pul.patient_id = p.id
        LEFT JOIN treatments t ON pul.treatment_id = t.id
        LEFT JOIN users u ON COALESCE(t.technician_id, pul.technician_id) = u.id
        LEFT JOIN appointments a ON pul.appointment_id = a.id
        WHERE pul.patient_package_id IN ($in_clause)
        ORDER BY pul.used_at DESC
    ");
    foreach ($usage_stmt->fetchAll() as $log) {
        $usage_logs_by_pkg[$log['patient_package_id']][] = $log;
    }
}

// Auto-sync sessions_remaining if out of sync BEFORE calculating summaries
foreach ($all_packages as &$pkg) {
    $total_sess = $pkg['pkg_total_sessions'] ?: 1;
    $logs = $usage_logs_by_pkg[$pkg['id']] ?? [];
    $used = count($logs);
    
    $expected_rem = max(0, $total_sess - $used);
    
    // Auto-sync sessions_remaining
    if ($pkg['sessions_remaining'] != $expected_rem) {
        $db->prepare("UPDATE patient_packages SET sessions_remaining = ? WHERE id = ?")->execute([$expected_rem, $pkg['id']]);
        $pkg['sessions_remaining'] = $expected_rem;
    }
    
    // Auto-sync status independently
    if ($expected_rem > 0 && $pkg['status'] === 'exhausted') {
        $db->prepare("UPDATE patient_packages SET status = 'active' WHERE id = ?")->execute([$pkg['id']]);
        $pkg['status'] = 'active';
    } elseif ($expected_rem <= 0 && $pkg['status'] === 'active') {
        $db->prepare("UPDATE patient_packages SET status = 'exhausted' WHERE id = ?")->execute([$pkg['id']]);
        $pkg['status'] = 'exhausted';
    }
}
unset($pkg);

// Summary stats
$active_packages = array_filter($all_packages, function($p) { return $p['status'] === 'active'; });
$total_remaining = array_sum(array_column($active_packages, 'sessions_remaining'));

// Keep $packages for backward compat (active only)
$packages = $active_packages;

// Fetch re-examination rules
$stmt = $db->prepare("SELECT * FROM reexam_rules WHERE patient_id = ? ORDER BY next_due_at ASC");
$stmt->execute([$id]);
$rules = $stmt->fetchAll();
?>

<div style="display: flex; gap: 1.5rem;">
    <!-- Left Column: Patient Info -->
    <div style="flex: 1;">
<div style="display: flex; flex-direction: column; gap: 1.5rem; width: 100%;">
    <!-- Back Button -->
    <div style="display: flex; align-items: center; gap: 1rem;">
        <a href="index.php" style="display: inline-flex; align-items: center; gap: 0.5rem; color: var(--primary); font-weight: 700; font-size: 0.9rem; text-decoration: none; padding: 0.5rem 1rem; background: #eef2ff; border-radius: 10px; transition: all 0.2s;" onmouseover="this.style.background='#e0e7ff'; this.style.transform='translateX(-3px)'" onmouseout="this.style.background='#eef2ff'; this.style.transform='translateX(0)'">
            <i class="fas fa-arrow-left"></i> <?php echo __('common.back_to_list'); ?>
        </a>
    </div>

    <!-- Top Banner Card -->
    <div class="card" style="padding: 0; overflow: hidden; border: none; box-shadow: var(--shadow-premium); background: white;">
        <div style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); padding: 2.5rem; color: white; display: flex; align-items: center; gap: 2.5rem; position: relative;">
            <div style="width: 100px; height: 100px; background: rgba(255,255,255,0.1); border-radius: 24px; display: flex; align-items: center; justify-content: center; font-size: 3rem; border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(10px);">
                <i class="fas fa-user-astronaut" style="color: var(--primary-light);"></i>
            </div>
            <div style="flex: 1;">
                <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 0.5rem;">
                    <h1 style="margin: 0; font-size: 2.25rem; font-weight: 800; letter-spacing: -0.02em;"><?php echo e($patient['full_name']); ?></h1>
                    
                    <div style="display: flex; gap: 0.5rem;">
                        <span style="font-size: 0.75rem; font-weight: 800; background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: white; padding: 0.25rem 0.75rem; border-radius: 50px; text-transform: uppercase; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);">
                            <i class="fas fa-crown"></i> Premium
                        </span>
                        
                        <?php if (!empty($patient['label'])): ?>
                            <span style="font-size: 0.75rem; font-weight: 800; background: #fee2e2; color: #b91c1c; padding: 0.25rem 0.75rem; border-radius: 50px; text-transform: uppercase; border: 1px solid #fecaca;">
                                <i class="fas fa-tag"></i> <?php echo e($patient['label']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display: flex; gap: 1.5rem; font-size: 1rem; font-weight: 500; opacity: 0.8;">
                    <span><i class="fas fa-fingerprint" style="color: var(--primary-light); margin-right: 0.4rem;"></i> <?php echo e($patient['customer_id'] ?: 'BN-' . $patient['id']); ?></span>
                    <span><i class="fas fa-phone-alt" style="color: var(--primary-light); margin-right: 0.4rem;"></i> <?php echo e($patient['phone']); ?></span>
                    <span style="display: flex; align-items: center; gap: 0.5rem;">
                         <span style="width: 8px; height: 8px; background: #10b981; border-radius: 50%; box-shadow: 0 0 8px #10b981;"></span> 
                         <?php echo __('patient.status.active'); ?>
                    </span>
                </div>
            </div>
            <div style="display: flex; gap: 1rem;">
                <a href="../appointments/add.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-primary" style="background: #db2777; border: none; box-shadow: 0 4px 12px rgba(219, 39, 119, 0.4);">
                    <i class="fas fa-calendar-plus"></i> <?php echo __('appointment.book_title'); ?>
                </a>
                <a href="edit.php?id=<?php echo $patient['id']; ?>" class="btn" style="background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(5px);">
                    <i class="fas fa-user-edit"></i> <?php echo __('common.edit'); ?>
                </a>
            </div>
        </div>

        <!-- Active Packages Summary -->
        <?php if (!empty($active_packages)): ?>
        <div style="padding: 1.5rem 2.5rem; background: #f0fdf4; border-bottom: 1px solid #bbf7d0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; background: #10b981; color: white; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);">
                    <i class="fas fa-box-open"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #065f46;">Gói Dịch Vụ Đang Kích Hoạt</h3>
                    <div style="font-size: 0.95rem; color: #047857; font-weight: 600; margin-top: 0.25rem;">
                        Còn <strong style="font-size: 1.1rem;"><?php echo $total_remaining; ?></strong> buổi trong <?php echo count($active_packages); ?> gói
                    </div>
                </div>
            </div>
                <!-- Coin Wallet Display Card -->
                <div style="background: linear-gradient(135deg, #fffbebf5, #fef3c7); border: 1px solid #fde68a; padding: 0.75rem 1rem; border-radius: 10px; min-width: 180px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <div style="font-size: 0.75rem; font-weight: 800; color: #b45309; text-transform: uppercase;">Ví Coin Tích Điểm</div>
                        <div style="font-size: 1.2rem; font-weight: 900; color: #d97706;"><i class="fas fa-coins"></i> <?php echo $patient_coin_balance; ?> Coins</div>
                    </div>
                </div>

                <?php foreach ($active_packages as $pkg): 
                    $used = max(0, $pkg['pkg_total_sessions'] - $pkg['sessions_remaining']);
                    $pct = $pkg['pkg_total_sessions'] > 0 ? min(100, round(($used / $pkg['pkg_total_sessions']) * 100)) : 0;
                    $bar_color = $pct >= 80 ? '#ef4444' : ($pct >= 50 ? '#f59e0b' : '#10b981');
                ?>
                <div style="background: white; border: 1px solid #bbf7d0; padding: 0.75rem 1rem; border-radius: 10px; min-width: 210px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem; align-items: center;">
                        <span style="font-size: 0.85rem; font-weight: 800; color: #1e293b;"><?php echo e($pkg['package_name']); ?></span>
                        <span style="font-size: 0.8rem; font-weight: 800; color: <?php echo $bar_color; ?>;"><?php echo $pkg['sessions_remaining']; ?>/<?php echo $pkg['pkg_total_sessions']; ?></span>
                    </div>
                    <div style="background: #e2e8f0; height: 6px; border-radius: 3px; overflow: hidden; margin-bottom: 0.5rem;">
                        <div style="background: <?php echo $bar_color; ?>; height: 100%; width: <?php echo $pct; ?>%;"></div>
                    </div>
                    <?php if ($pkg['sessions_remaining'] > 0): ?>
                        <form method="POST" onsubmit="return confirm('Bạn có chắc chắn muốn quy đổi các buổi còn lại của gói [<?php echo e($pkg['package_name']); ?>] sang Coins trong Ví không?');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="convert_package_to_coins">
                            <input type="hidden" name="patient_package_id" value="<?php echo $pkg['id']; ?>">
                            <button type="submit" class="btn btn-sm" style="width: 100%; font-size: 0.7rem; font-weight: 700; padding: 0.2rem 0.5rem; background: #fffbebf5; color: #d97706; border: 1px solid #fde68a; border-radius: 6px;">
                                <i class="fas fa-sync-alt"></i> Quy đổi ra Coins
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Info Grid -->
        <div style="padding: 2.5rem; display: grid; grid-template-columns: repeat(3, 1fr); gap: 2.5rem; border-bottom: 1px solid var(--border-color);">
            <div class="info-group">
                <h4 class="info-section-title"><i class="fas fa-info-circle"></i> <?php echo __('patient.info.administrative'); ?></h4>
                <div class="info-item"><span><?php echo __('patient.info.dob'); ?></span> <strong><?php echo $patient['birthday'] ? date('d/m/Y', strtotime($patient['birthday'])) : '—'; ?></strong></div>
                <div class="info-item"><span><?php echo __('patient.gender'); ?></span> <strong><?php echo $patient['gender'] === 'male' ? __('patient.gender.male') : ($patient['gender'] === 'female' ? __('patient.gender.female') : __('patient.gender.other')); ?></strong></div>
                <div class="info-item"><span><?php echo __('patient.info.occupation'); ?></span> <strong><?php echo e($patient['occupation'] ?: '—'); ?></strong></div>
            </div>
            <div class="info-group">
                <h4 class="info-section-title"><i class="fas fa-map-marker-alt"></i> <?php echo __('patient.info.contact'); ?></h4>
                <div class="info-item"><span><?php echo __('patient.info.address'); ?></span> <strong><?php echo e($patient['address'] ?: '—'); ?></strong></div>
                <div class="info-item"><span><?php echo __('patient.info.branch'); ?></span> <strong><?php echo e($patient['branch'] ?: __('common.main_branch')); ?></strong></div>
                <div class="info-item"><span><?php echo __('patient.info.source'); ?></span> <strong style="color: var(--primary); font-weight: 800;"><?php echo e($patient['source'] ?: '—'); ?></strong></div>
            </div>
            <div class="info-group">
                <h4 class="info-section-title"><i class="fas fa-user-tie"></i> <?php echo __('patient.info.assigned_to'); ?></h4>
                <div class="info-item"><span><?php echo __('patient.info.consultant'); ?></span> <strong style="color: #6366f1;"><?php echo e($patient['consultant_name'] ?: '—'); ?></strong></div>
                <div class="info-item"><span><?php echo __('patient.info.file_id'); ?></span> <strong><?php echo e($patient['customer_id'] ?: '—'); ?></strong></div>
                <div style="margin-top: 1rem; display: flex; gap: 1rem;">
                    <?php if ($patient['zalo_number']): ?>
                        <a href="https://zalo.me/<?php echo $patient['zalo_number']; ?>" target="_blank" class="social-icon" style="background: #0068ff;"><i class="fas fa-comment"></i></a>
                    <?php endif; ?>
                    <?php if ($patient['facebook_link']): ?>
                        <a href="<?php echo $patient['facebook_link']; ?>" target="_blank" class="social-icon" style="background: #1877f2;"><i class="fab fa-facebook-f"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div style="padding: 1.5rem 2.5rem; background: #f8fafc; display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; border-top: 1px solid #f1f5f9;">
            <!-- Medical Notes (For Doctor) -->
            <div style="display: flex; align-items: flex-start; gap: 1rem; border-right: 1px solid #e2e8f0; padding-right: 2rem;">
                <div style="width: 40px; height: 40px; background: #eef2ff; color: #6366f1; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><i class="fas fa-user-md"></i></div>
                <div style="flex: 1;">
                    <div style="font-size: 0.75rem; font-weight: 800; color: #6366f1; text-transform: uppercase; margin-bottom: 0.25rem;"><?php echo __('medical.record.medical'); ?></div>
                    <div style="font-size: 0.9rem; color: var(--text-main); line-height: 1.5;">
                        <?php echo nl2br(e($patient['notes'] ?: __('patient.info.no_notes'))); ?>
                    </div>
                </div>
            </div>

            <!-- Personal Preferences (For Consultant/Receptionist) -->
            <div style="display: flex; align-items: flex-start; gap: 1rem;">
                <div style="width: 40px; height: 40px; background: #fdf2f8; color: #db2777; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><i class="fas fa-heart"></i></div>
                <div style="flex: 1;">
                    <div style="font-size: 0.75rem; font-weight: 800; color: #db2777; text-transform: uppercase; margin-bottom: 0.25rem;"><?php echo __('patient.info.personal_notes'); ?></div>
                    <div style="font-size: 0.95rem; color: #9d174d; font-weight: 600; line-height: 1.5;">
                        <?php echo nl2br(e($patient['personal_notes'] ?: __('common.no_data'))); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

        <style>
        .info-row { border-bottom: 1px solid #f8fafc; padding: 0.6rem 0; display: flex; justify-content: space-between; }
        .info-label { color: var(--text-muted); font-size: 0.85rem; }
        .info-value { font-weight: 600; font-size: 0.9rem; color: var(--text-main); }
        .btn-edit-top:hover { background: rgba(255,255,255,0.2) !important; transform: scale(1.1); }
        </style>

    <?php
    $chiro_history = null;
    foreach ($histories as $h) {
        if ($h['type'] === 'chiro_history_v2') {
            $chiro_history = $h;
            break;
        }
    }
    
    $history_url = 'chiro_history_v2.php';
    ?>
    <div class="card" style="margin-bottom: 1.5rem; border: none; box-shadow: var(--shadow-sm); padding: 1.5rem; background: #faf5ff; border: 1px solid #e9d5ff;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
            <div style="display: flex; gap: 1rem; align-items: flex-start;">
                <div style="width: 48px; height: 48px; background: white; color: #9333ea; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; box-shadow: 0 4px 6px -1px rgba(147, 51, 234, 0.1);">
                    <i class="fas fa-file-medical-alt"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-weight: 800; color: #1e293b; font-size: 1.15rem; margin-bottom: 0.25rem;"><?php echo __('medical.type.chiro_history'); ?></h3>
                    <div style="font-size: 0.85rem; color: #64748b; font-weight: 500;">
                        <?php if ($chiro_history): ?>
                            <span style="display: inline-flex; align-items: center; gap: 0.25rem; color: #16a34a; font-weight: 700; background: #dcfce7; padding: 0.15rem 0.5rem; border-radius: 50px; font-size: 0.75rem;"><i class="fas fa-check-circle"></i> <?php echo __('patient.history.has_data'); ?></span>
                            <?php echo __('patient.history.updated_at'); ?> <?php echo date('d/m/Y H:i', strtotime($chiro_history['updated_at'] ?: $chiro_history['created_at'])); ?> 
                            <?php echo __('patient.history.by'); ?> <strong style="color: #475569;"><?php echo e($chiro_history['doctor_name'] ?? 'Hệ thống'); ?></strong>
                        <?php else: ?>
                            <span style="color: #ea580c; font-weight: 600;"><i class="fas fa-exclamation-triangle" style="margin-right: 2px;"></i> <?php echo __('patient.history.no_history_yet'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div>
                <?php if ($chiro_history): ?>
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="../medical/print_record.php?type=history&id=<?php echo $chiro_history['id']; ?>" target="_blank" class="btn" style="background: white; border: 1px solid #d8b4fe; color: #9333ea; font-weight: 800; border-radius: 50px; padding: 0.5rem 1rem; transition: all 0.2s;" onmouseover="this.style.background='#9333ea'; this.style.color='white';" onmouseout="this.style.background='white'; this.style.color='#9333ea';">
                            <i class="fas fa-print"></i> <?php echo __('common.print_pdf', 'In PDF'); ?>
                        </a>
                        <a href="../medical/<?php echo $history_url; ?>?patient_id=<?php echo $patient['id']; ?>&id=<?php echo $chiro_history['id']; ?>" class="btn" style="background: white; border: 1px solid #d8b4fe; color: #9333ea; font-weight: 800; border-radius: 50px; padding: 0.5rem 1rem; transition: all 0.2s;" onmouseover="this.style.background='#9333ea'; this.style.color='white';" onmouseout="this.style.background='white'; this.style.color='#9333ea';">
                            <i class="fas fa-edit"></i> <?php echo __('patient.history.view_update'); ?>
                        </a>
                    </div>
                <?php else: ?>
                    <a href="../medical/<?php echo $history_url; ?>?patient_id=<?php echo $patient['id']; ?>" class="btn btn-primary" style="background: #9333ea; border: none; font-weight: 800; border-radius: 50px; padding: 0.5rem 1rem; box-shadow: 0 4px 12px rgba(147, 51, 234, 0.3);">
                        <i class="fas fa-plus-circle"></i> <?php echo __('patient.history.create_new'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <style>
        .patient-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
        }
        @media (max-width: 900px) {
            .patient-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <div class="patient-grid">
        <!-- Left: Medical Timeline -->
        <div class="card" style="border: none; box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid #f1f5f9;">
                <h3 style="margin: 0; font-weight: 800;"><i class="fas fa-laptop-medical" style="color: var(--primary);"></i> <?php echo __('patient.history.title'); ?></h3>
                <a href="../medical/session_add.php?patient_id=<?php echo $id; ?>" class="btn btn-primary shadow-sm" style="border-radius: 50px; font-size: 0.85rem;">
                    <i class="fas fa-plus"></i> <?php echo __('patient.history.new_session'); ?>
                </a>
            </div>

            <div style="display: flex; gap: 0.5rem; margin-bottom: 2rem; border-bottom: 2px solid #e2e8f0;">
                <button class="med-tab-btn active" onclick="switchMedicalTab('timeline', this)"><i class="fas fa-stream"></i> <?php echo __('patient.history.tab_timeline', 'Lịch sử khám bệnh'); ?></button>
                <button class="med-tab-btn" onclick="switchMedicalTab('gallery', this)"><i class="fas fa-images"></i> <?php echo __('patient.history.tab_gallery', 'Kho Hình Ảnh X-Quang'); ?></button>
            </div>

            <div id="tab-timeline">
            <div class="timeline-visual" style="position: relative; padding-left: 2rem;">
                <div style="position: absolute; left: 0.25rem; top: 0; bottom: 0; width: 2px; background: #f1f5f9;"></div>
                
                <?php 
                // Grouping Logic: Combine Sessions and Orphan items
                $timeline_items = [];
                
                // 1. Add Sessions (Only those belonging to THIS patient)
                foreach ($sessions as $session) {
                    $session_parts = [];
                    // Find all history records for THIS session
                    foreach ($histories as $h) {
                        if ($h['type'] === 'chiro_history_v2') continue;
                        if ($h['session_id'] == $session['id']) {
                            $session_parts[] = $h;
                        }
                    }
                    // Find all treatments for THIS session
                    foreach ($treatments as $t) {
                        if ($t['session_id'] == $session['id']) {
                            $session_parts[] = $t;
                        }
                    }
                    
                    $timeline_items[] = [
                        'type' => 'session',
                        'data' => $session,
                        'parts' => $session_parts,
                        'date' => $session['session_date']
                    ];
                }
                
                // 2. Add Orphan Histories (or those belonging to non-existent/wrong sessions)
                $session_ids = array_column($sessions, 'id');
                foreach ($histories as $h) {
                    if ($h['type'] === 'chiro_history_v2') continue;
                    if (!$h['session_id'] || !in_array($h['session_id'], $session_ids)) {
                        $timeline_items[] = [
                            'type' => 'history',
                            'data' => $h,
                            'date' => $h['created_at']
                        ];
                    }
                }
                
                // 3. Add Orphan Treatments
                foreach ($treatments as $t) {
                    if (!$t['session_id'] || !in_array($t['session_id'], $session_ids)) {
                        $timeline_items[] = [
                            'type' => 'treatment',
                            'data' => $t,
                            'date' => $t['treatment_date']
                        ];
                    }
                }
                
                // 4. Sort all by date
                usort($timeline_items, function($a, $b) {
                    return strtotime($b['date']) <=> strtotime($a['date']);
                });
                ?>

                <?php if (empty($timeline_items)): ?>
                    <p style="text-align: center; color: var(--text-muted); padding: 2rem;"><?php echo __('patient.history.no_data'); ?></p>
                <?php else: ?>
                    <style>
                        .session-card {
                            background: white;
                            border: 1px solid #e2e8f0;
                            border-radius: 20px;
                            padding: 1.5rem;
                            margin-bottom: 2rem;
                            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
                            transition: all 0.3s ease;
                            border-left: 6px solid var(--primary);
                        }
                        .session-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
                        .part-badge {
                            font-size: 0.7rem;
                            font-weight: 800;
                            padding: 0.25rem 0.6rem;
                            border-radius: 50px;
                            background: #f1f5f9;
                            color: #64748b;
                            display: inline-flex;
                            align-items: center;
                            gap: 0.25rem;
                            margin-right: 0.5rem;
                            margin-top: 0.5rem;
                        }
                    </style>
                    <div id="timeline-container">
                    <?php foreach ($timeline_items as $item): ?>
                        
                        <?php if ($item['type'] === 'session'): 
                            $s = $item['data'];
                        ?>
                            <div class="session-card timeline-node" data-type="session">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                                    <div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">
                                            <?php echo date('d/m/Y - H:i', strtotime($s['session_date'])); ?>
                                        </div>
                                        <h4 style="margin: 0.25rem 0; font-size: 1.25rem; font-weight: 800; color: #1e293b;">
                                            <?php echo __('medical.general_session'); ?>
                                        </h4>
                                    </div>
                                    <a href="../medical/session_view.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline" style="border-radius: 50px;">
                                        <?php echo __('common.details'); ?> <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>

                                <?php if ($s['assessment'] || $s['treatment_plan']): ?>
                                    <div style="background: #f8fafc; border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem; border: 1px solid #f1f5f9;">
                                        <?php if ($s['assessment']): ?>
                                            <div style="margin-bottom: 0.75rem;">
                                                <small style="color: var(--primary); font-weight: 800; text-transform: uppercase; font-size: 0.65rem;"><?php echo __('medical.assessment'); ?></small>
                                                <div style="margin-top: 0.25rem; font-size: 0.9rem; line-height: 1.4; color: #475569;"><?php echo $s['assessment']; ?></div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($s['treatment_plan']): ?>
                                            <div>
                                                <small style="color: #10b981; font-weight: 800; text-transform: uppercase; font-size: 0.65rem;"><?php echo __('medical.plan'); ?></small>
                                                <div style="margin-top: 0.25rem; font-size: 0.9rem; line-height: 1.4; color: #475569;"><?php echo $s['treatment_plan']; ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <div style="display: flex; flex-wrap: wrap;">
                                    <?php foreach ($item['parts'] as $part): 
                                        $p_is_tr = isset($part['session_data']);
                                        $p_type = $p_is_tr ? 'treatment' : $part['type'];
                                            $labels = [
                                                'chiro_history' => __('medical.part.history'),
                                                'chiro_exam' => __('medical.part.exam'),
                                                'chiropractic' => __('medical.part.soap'),
                                                'dong_y' => __('medical.part.dong_y'),
                                                'treatment' => __('medical.part.treatment')
                                            ];
                                            $p_label = isset($labels[$p_type]) ? $labels[$p_type] : __('medical.part.other');
                                        $icons = [
                                            'chiro_history' => 'fa-history',
                                            'chiro_exam' => 'fa-stethoscope',
                                            'chiropractic' => 'fa-notes-medical',
                                            'dong_y' => 'fa-leaf',
                                            'treatment' => 'fa-hand-holding-medical'
                                        ];
                                        $p_icon = isset($icons[$p_type]) ? $icons[$p_type] : 'fa-file';
                                    ?>
                                        <span class="part-badge">
                                            <i class="fas <?php echo $p_icon; ?>"></i> <?php echo $p_label; ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                        <?php else: 
                            // Render orphan history/treatment as a simple node
                            $i = $item['data'];
                            $is_tr = ($item['type'] === 'treatment');
                                $types = [
                                    'chiro_exam' => __('medical.type.chiro_exam'),
                                    'chiro_history' => __('medical.type.chiro_history'),
                                    'chiro_history_v2' => 'Tiền sử Chiropractic (V2)',
                                    'chiropractic' => __('medical.type.chiropractic'),
                                    'soap_note' => __('medical.type.chiropractic'),
                                    'soap_note_v2' => 'Tái khám Follow-up (V2)',
                                    'pathologie_v2' => 'Bệnh lý Chiropractic (V2)',
                                    'dong_y' => __('medical.type.dong_y')
                                ];
                                $type_label = isset($types[$i['type']]) ? $types[$i['type']] : __('medical.type.general');
                            $color = $is_tr ? '#10b981' : '#6366f1';
                        ?>
                            <div class="timeline-node" data-type="<?php echo $item['type']; ?>" style="position: relative; margin-bottom: 2rem; padding-left: 1rem;">
                                <div style="position: absolute; left: -2.15rem; top: 0; width: 28px; height: 28px; background: white; border: 2px solid <?php echo $color; ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; z-index: 2;">
                                    <i class="fas fa-file-medical" style="color: <?php echo $color; ?>; font-size: 0.7rem;"></i>
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 800;"><?php echo date('d/m/Y', strtotime($item['date'])); ?></div>
                                <div style="font-weight: 700; color: #1e293b;"><?php echo $type_label; ?></div>
                                <div style="font-size: 0.85rem; color: var(--text-muted);"><?php echo $is_tr ? __('medical.record.treatment') : __('medical.record.medical'); ?></div>
                                <div style="display: flex; gap: 0.75rem; margin-top: 0.25rem;">
                                    <?php if (!$is_tr): ?>
                                    <a href="../medical/view_form.php?id=<?php echo $i['id']; ?>" style="font-size: 0.75rem; color: var(--primary); text-decoration: none; font-weight: 700;">
                                        <i class="fas fa-eye"></i> <?php echo __('medical.record.review'); ?>
                                    </a>
                                    <?php endif; ?>
                                    <a href="../medical/print_record.php?type=<?php echo $is_tr ? 'treatment' : 'history'; ?>&id=<?php echo $i['id']; ?>" target="_blank" style="font-size: 0.75rem; color: #10b981; text-decoration: none; font-weight: 700;">
                                        <i class="fas fa-print"></i> <?php echo __('common.print_pdf', 'In PDF'); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>

                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            </div> <!-- End tab-timeline -->
            
            <div id="tab-gallery" style="display: none;">
                <?php
                // Build gallery array from $histories
                $gallery_items = [];
                foreach ($histories as $h) {
                    if (!empty($h['attachments']) && $h['attachments'] !== '[]') {
                        $atts = json_decode($h['attachments'], true);
                        if ($atts) {
                            foreach ($atts as $att) {
                                // Only add images (skip pdfs for the visual gallery if preferred, or include them with generic icon)
                                if (strpos((isset($att['type']) ? $att['type'] : ''), 'image') !== false) {
                                    $att['date'] = $h['created_at'];
                                    $att['doctor'] = $h['doctor_name'];
                                    $att['session_id'] = $h['session_id'];
                                    $gallery_items[] = $att;
                                }
                            }
                        }
                    }
                }
                
                // Sort images by date DESC
                usort($gallery_items, function($a, $b) {
                    return strtotime($b['date']) <=> strtotime($a['date']);
                });
                
                if (empty($gallery_items)): ?>
                    <div style="text-align: center; padding: 4rem 2rem; border: 2px dashed #e2e8f0; border-radius: 16px; background: #f8fafc;">
                        <i class="fas fa-images" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 1rem;"></i>
                        <h4 style="margin: 0; color: #64748b;"><?php echo __('patient.history.no_images_title', 'Chưa có hình ảnh nào'); ?></h4>
                        <p style="font-size: 0.85rem; color: #94a3b8; margin-top: 0.5rem;"><?php echo __('patient.history.no_images_desc', 'Hình ảnh X-Quang / Hồ sơ sẽ tự động xuất hiện ở đây khi bạn tải lên trong Buổi khám.'); ?></p>
                    </div>
                <?php else: ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 1rem;">
                        <?php foreach($gallery_items as $att): ?>
                        <div style="position: relative; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; aspect-ratio: 1; box-shadow: 0 2px 4px rgba(0,0,0,0.05); transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.03)'" onmouseout="this.style.transform='scale(1)'">
                            <div style="cursor: pointer; width: 100%; height: 100%;" onclick="openLightbox('<?php echo addslashes($att['path']); ?>')">
                                <img src="<?php echo $att['path']; ?>" style="width: 100%; height: 100%; object-fit: cover; display: block;" alt="<?php echo e($att['name']); ?>">
                            </div>
                            <div style="position: absolute; pointer-events: none; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,0.85)); padding: 2rem 0.5rem 0.5rem; color: white; display: flex; flex-direction: column; gap: 0.2rem;">
                                <div style="font-size: 0.75rem; font-weight: 700; text-shadow: 0 1px 2px rgba(0,0,0,0.8); line-height: 1.2;">
                                    <?php echo e(mb_strlen($att['name']) > 25 ? mb_substr($att['name'], 0, 22) . '...' : $att['name']); ?>
                                </div>
                                <div style="font-size: 0.6rem; color: #94a3b8; font-weight: 600;">
                                    <?php echo date('d/m/Y', strtotime($att['date'])); ?> 
                                </div>
                            </div>
                            <?php if ($att['session_id']): ?>
                            <a href="../medical/session_view.php?id=<?php echo $att['session_id']; ?>" title="<?php echo __('patient.history.view_session_btn', 'Xem buổi khám'); ?>" style="position: absolute; top: 8px; right: 8px; background: rgba(255,255,255,0.9); width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #6366f1; text-decoration: none; font-size: 0.75rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); pointer-events: auto;">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

            </div>
        </div>

        <!-- Right: Actions & Financial Info -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            <!-- Financial Card -->
            <div class="card" style="padding: 2rem; border: none; background: white; box-shadow: var(--shadow-sm);">
                <h4 style="margin: 0 0 1.5rem 0; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; letter-spacing: 1px;"><?php echo __('patient.finance.title'); ?></h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                    <div style="background: #f0fdf4; padding: 1rem; border-radius: 12px; border: 1px solid #dcfce7; text-align: center;">
                        <div style="font-size: 0.65rem; font-weight: 800; color: #166534; text-transform: uppercase;"><?php echo __('patient.finance.paid'); ?></div>
                        <div style="font-size: 1.1rem; font-weight: 800; color: #15803d; margin-top: 0.25rem;">0</div>
                    </div>
                    <div style="background: #fef2f2; padding: 1rem; border-radius: 12px; border: 1px solid #fee2e2; text-align: center;">
                        <div style="font-size: 0.65rem; font-weight: 800; color: #991b1b; text-transform: uppercase;"><?php echo __('patient.finance.debt'); ?></div>
                        <div style="font-size: 1.1rem; font-weight: 800; color: #b91c1c; margin-top: 0.25rem;">0</div>
                    </div>
                </div>

                <!-- Package Management Section -->
                <div style="margin-bottom: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h4 style="margin: 0; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; letter-spacing: 1px;"><i class="fas fa-box-open" style="color: #6366f1;"></i> <?php echo __('patient.pkg.title', 'Gói Dịch Vụ'); ?></h4>
                        <?php if (!empty($all_packages)): ?>
                        <span style="font-size: 0.7rem; font-weight: 800; background: #eef2ff; color: #4f46e5; padding: 0.2rem 0.6rem; border-radius: 50px;">
                            <?php echo count($active_packages); ?> <?php echo __('patient.pkg.pkg_unit', 'gói'); ?> · <?php echo $total_remaining; ?> <?php echo __('patient.pkg.session_unit', 'buổi'); ?>
                        </span>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($all_packages)): ?>
                        <div style="padding: 2rem; text-align: center; background: #f8fafc; border-radius: 16px; border: 1px dashed #e2e8f0;">
                            <i class="fas fa-box-open" style="font-size: 2rem; color: #cbd5e1; margin-bottom: 0.75rem; display: block;"></i>
                            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;"><?php echo __('patient.pkg.no_package', 'Chưa mua gói nào'); ?></p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($all_packages as $pi => $pkg):
                            $total_sess = $pkg['pkg_total_sessions'] ?: 1;
                            $logs = $usage_logs_by_pkg[$pkg['id']] ?? [];
                            $used = count($logs);
                            
                            $is_active = $pkg['status'] === 'active';
                            $pct = round(($used / $total_sess) * 100);
                            $status_colors = [
                                'active' => ['#10b981','#ecfdf5','#d1fae5'],
                                'exhausted' => ['#f59e0b','#fffbeb','#fef3c7'],
                                'expired' => ['#94a3b8','#f8fafc','#e2e8f0']
                            ];
                            $sc = $status_colors[$pkg['status']] ?? $status_colors['expired'];
                            $bar_color = $pct >= 80 ? '#ef4444' : ($pct >= 50 ? '#f59e0b' : '#10b981');
                        ?>
                        <div style="margin-bottom: 0.75rem; border: 1px solid <?php echo $sc[2]; ?>; border-radius: 14px; overflow: hidden; background: white; <?php echo !$is_active ? 'opacity: 0.7;' : ''; ?>">
                            <!-- Package Header (clickable) -->
                            <div onclick="togglePkgDetail(<?php echo $pi; ?>)" style="padding: 1rem; cursor: pointer; display: flex; align-items: center; gap: 0.75rem; transition: background 0.2s;" onmouseover="this.style.background='<?php echo $sc[1]; ?>'" onmouseout="this.style.background='white'">
                                <div style="flex: 1; min-width: 0;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem;">
                                        <span style="font-weight: 800; font-size: 0.85rem; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo e($pkg['package_name']); ?></span>
                                        <span style="font-size: 0.6rem; font-weight: 800; background: <?php echo $sc[1]; ?>; color: <?php echo $sc[0]; ?>; padding: 0.1rem 0.4rem; border-radius: 4px; text-transform: uppercase; flex-shrink: 0;"><?php echo strtoupper($pkg['status']); ?></span>
                                    </div>
                                    <!-- Progress bar -->
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <div style="flex: 1; background: #f1f5f9; height: 6px; border-radius: 3px; overflow: hidden;">
                                            <div style="background: <?php echo $bar_color; ?>; height: 100%; width: <?php echo $pct; ?>%; border-radius: 3px; transition: width 0.3s;"></div>
                                        </div>
                                        <span style="font-size: 0.7rem; font-weight: 800; color: <?php echo $bar_color; ?>; white-space: nowrap;"><?php echo $used; ?>/<?php echo $total_sess; ?></span>
                                    </div>
                                </div>
                                <i class="fas fa-chevron-down" id="pkgIcon<?php echo $pi; ?>" style="color: #94a3b8; font-size: 0.7rem; transition: transform 0.3s;"></i>
                            </div>

                            <!-- Package Detail (hidden by default, except first active) -->
                            <div id="pkgDetail<?php echo $pi; ?>" style="display: <?php echo ($pi === 0 && $is_active) ? 'block' : 'none'; ?>; border-top: 1px solid #f1f5f9;">
                                <div style="padding: 0.75rem 1rem; background: #f8fafc; display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.75rem;">
                                    <div><span style="color: #64748b;"><?php echo __('patient.pkg.sessions_remaining', 'Buổi còn lại:'); ?></span> <strong style="color: #0f172a;"><?php echo $pkg['sessions_remaining']; ?></strong></div>
                                    <div><span style="color: #64748b;"><?php echo __('patient.pkg.purchase_date', 'Ngày mua:'); ?></span> <strong><?php echo date('d/m/Y', strtotime($pkg['purchase_date'])); ?></strong></div>
                                    <div><span style="color: #64748b;"><?php echo __('patient.pkg.value', 'Giá trị:'); ?></span> <strong style="color: #6366f1;"><?php echo number_format($pkg['total_amount'], 0, ',', '.'); ?>đ</strong></div>
                                    <div><span style="color: #64748b;"><?php echo __('patient.pkg.expiry', 'Hết hạn:'); ?></span> <strong><?php echo $pkg['expire_date'] ? date('d/m/Y', strtotime($pkg['expire_date'])) : '—'; ?></strong></div>
                                </div>

                                <!-- Usage History -->
                                <div style="padding: 0.75rem 1rem;">
                                    <div style="font-size: 0.7rem; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 0.5rem;">
                                        <i class="fas fa-history"></i> <?php echo __('patient.pkg.usage_history', 'Lịch sử dùng'); ?> (<?php echo count($logs); ?> <?php echo __('patient.pkg.turns', 'lượt'); ?>)
                                    </div>
                                    <?php if (empty($logs)): ?>
                                        <div style="text-align: center; padding: 0.75rem; color: #94a3b8; font-size: 0.8rem; font-style: italic;"><?php echo __('patient.pkg.not_used', 'Chưa sử dụng'); ?></div>
                                    <?php else: ?>
                                        <?php foreach (array_slice($logs, 0, 5) as $log): ?>
                                        <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0; border-bottom: 1px solid #f8fafc; font-size: 0.75rem;">
                                            <div style="width: 6px; height: 6px; background: #10b981; border-radius: 50%; flex-shrink: 0;"></div>
                                            <div style="flex: 1; min-width: 0;">
                                                <div style="font-weight: 700; color: #1e293b;">
                                                    <?php echo date('d/m H:i', strtotime($log['used_at'])); ?>
                                                    <?php if ($log['technician_name']): ?>
                                                        <span style="color: #64748b; font-weight: 600;">· <?php echo e($log['technician_name']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div style="display: flex; gap: 0.25rem; flex-shrink: 0;">
                                                <?php if ($log['appointment_id']): ?>
                                                <a href="../appointments/view.php?id=<?php echo $log['appointment_id']; ?>" title="<?php echo __('appointment.view_title'); ?>" style="width: 22px; height: 22px; background: #eef2ff; color: #6366f1; border-radius: 6px; display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 0.6rem;"><i class="fas fa-calendar-check"></i></a>
                                                <?php endif; ?>
                                                <?php if ($log['session_id']): ?>
                                                <a href="../medical/session_view.php?id=<?php echo $log['session_id']; ?>" title="<?php echo __('patient.history.view_session_btn', 'Xem buổi khám'); ?>" style="width: 22px; height: 22px; background: #f0fdf4; color: #10b981; border-radius: 6px; display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 0.6rem;"><i class="fas fa-file-medical"></i></a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                        <?php if (count($logs) > 5): ?>
                                        <a href="../sales/manage_shared.php?id=<?php echo $pkg['id']; ?>" style="display: block; text-align: center; padding: 0.4rem; font-size: 0.7rem; color: #6366f1; font-weight: 700; text-decoration: none;"><?php echo sprintf(__('patient.pkg.view_all_turns', 'Xem tất cả %s lượt →'), count($logs)); ?></a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <a href="../sales/add_package.php?patient_id=<?php echo $id; ?>" class="btn shadow-sm" style="width: 100%; justify-content: center; background: #0f172a; color: white;">
                    <i class="fas fa-cart-plus"></i> <?php echo __('patient.finance.new_package'); ?>
                </a>
            </div>

            <!-- Re-exam & Follow-up Section -->
            <div class="card" style="padding: 2rem; border: none; background: #fffbeb; box-shadow: var(--shadow-sm); border-left: 4px solid #f59e0b;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h4 style="margin: 0; font-size: 0.75rem; font-weight: 800; color: #92400e; text-transform: uppercase;"><?php echo __('patient.reexam.title'); ?></h4>
                    <button onclick="toggleReexamForm()" class="btn btn-sm" style="background: #fef3c7; color: #92400e; border-radius: 50% !important; width: 28px; height: 28px; padding: 0; justify-content: center;"><i class="fas fa-plus"></i></button>
                </div>
                
                <div id="reexamForm" style="display: none; margin-bottom: 1.5rem; padding: 1.25rem; background: white; border-radius: 16px; border: 1px solid #fde68a;">
                    <form action="manage_reexam.php" method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="patient_id" value="<?php echo $id; ?>">
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted);"><?php echo __('patient.reexam.service_note'); ?></label>
                            <input type="text" name="service_name" class="form-input" placeholder="<?php echo __('patient.reexam.service_placeholder'); ?>" required style="padding: 0.5rem; font-size: 0.85rem;">
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                            <div class="form-group">
                                <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted);"><?php echo __('patient.reexam.frequency'); ?></label>
                                <div style="display: flex; gap: 0.5rem;">
                                    <input type="number" name="frequency" class="form-input" min="1" value="3" style="padding: 0.5rem; font-size: 0.85rem; flex: 1;" required>
                                    <select name="frequency_type" class="form-input" style="padding: 0.5rem; font-size: 0.85rem; flex: 1;">
                                        <option value="days"><?php echo __('common.day'); ?></option>
                                        <option value="weeks"><?php echo __('common.week'); ?></option>
                                        <option value="months" selected><?php echo __('common.month'); ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted);"><?php echo __('patient.reexam.start_date'); ?></label>
                                <input type="date" name="first_date" class="form-input" value="<?php echo date('Y-m-d', strtotime('+3 months')); ?>" style="padding: 0.4rem; font-size: 0.85rem;">
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                             <button type="submit" class="btn btn-sm btn-primary" style="background: #d97706; border: none; font-weight: 700;"><?php echo __('common.setup'); ?></button>
                             <button type="button" onclick="toggleReexamForm()" class="btn btn-sm" style="background: #f1f5f9; font-weight: 700;"><?php echo __('common.cancel'); ?></button>
                        </div>
                    </form>
                </div>

                <?php if (empty($rules)): ?>
                    <p style="font-size: 0.85rem; color: #92400e; opacity: 0.7;"><?php echo __('patient.reexam.no_data'); ?></p>
                <?php else: ?>
                    <?php foreach ($rules as $rule): ?>
                        <div id="reexamView<?php echo $rule['id']; ?>" style="background: white; padding: 1rem; border-radius: 12px; margin-bottom: 0.75rem; box-shadow: 0 2px 4px rgba(0,0,0,0.02); display: flex; justify-content: space-between; align-items: center;">
                            <div style="flex: 1;">
                                <?php
                                $f_type = ($rule['frequency_type'] === 'days') ? __('common.day') : (($rule['frequency_type'] === 'weeks') ? __('common.week') : __('common.month'));
                                $freq_str = $rule['frequency'] . ' ' . $f_type;
                                ?>
                                <div style="font-weight: 800; color: var(--text-main); font-size: 0.9rem;"><?php echo e($rule['service_name']); ?> <span style="font-size: 0.7rem; color: var(--text-muted); font-weight: 600; background: #e2e8f0; padding: 0.1rem 0.4rem; border-radius: 4px; margin-left: 0.4rem; white-space: nowrap;"><?php echo __('common.every'); ?> <?php echo $freq_str; ?></span></div>
                                <div style="font-size: 0.85rem; font-weight: 700; color: <?php echo strtotime($rule['next_due_at']) <= time() ? '#dc2626' : '#ea580c'; ?>; margin-top: 0.25rem;">
                                    <?php echo __('patient.reexam.appointment'); ?> <?php echo date('d/m/Y', strtotime($rule['next_due_at'])); ?>
                                </div>
                            </div>
                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                <a href="../appointments/add.php?contact_id=patient:<?php echo $id; ?>&type=re_exam&reexam_rule_id=<?php echo $rule['id']; ?>" class="btn btn-sm" style="background: #fef3c7; color: #92400e; font-size: 0.7rem; padding: 0.25rem 0.5rem;"><?php echo __('appointment.book_title'); ?></a>
                                <button type="button" onclick="toggleEditReexam(<?php echo $rule['id']; ?>)" style="background: none; border: none; color: #64748b; cursor: pointer; padding: 0.25rem;"><i class="fas fa-edit"></i></button>
                                <form action="manage_reexam.php" method="POST" style="margin: 0;" id="delReexam<?php echo $rule['id']; ?>">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="patient_id" value="<?php echo $id; ?>">
                                    <input type="hidden" name="id" value="<?php echo $rule['id']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="button" onclick="confirmAndSubmit(document.getElementById('delReexam<?php echo $rule['id']; ?>'), '<?php echo __('common.confirm_delete'); ?>')" style="background: none; border: none; color: #cbd5e1; cursor: pointer; padding: 0.25rem;"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </div>

                        <!-- Edit Form -->
                        <div id="reexamEdit<?php echo $rule['id']; ?>" style="display: none; background: #fffbeb; padding: 1rem; border-radius: 12px; margin-bottom: 0.75rem; border: 1px solid #fde68a;">
                            <form action="manage_reexam.php" method="POST" style="margin:0;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="patient_id" value="<?php echo $id; ?>">
                                <input type="hidden" name="id" value="<?php echo $rule['id']; ?>">
                                <div class="form-group" style="margin-bottom: 1rem;">
                                    <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted);"><?php echo __('patient.reexam.service_note'); ?></label>
                                    <input type="text" name="service_name" class="form-input" value="<?php echo e($rule['service_name']); ?>" required style="padding: 0.5rem; font-size: 0.85rem;">
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                                    <div class="form-group">
                                        <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted);"><?php echo __('patient.reexam.frequency'); ?></label>
                                        <div style="display: flex; gap: 0.5rem;">
                                            <input type="number" name="frequency" class="form-input" min="1" value="<?php echo $rule['frequency']; ?>" style="padding: 0.5rem; font-size: 0.85rem; flex: 1;" required>
                                            <select name="frequency_type" class="form-input" style="padding: 0.5rem; font-size: 0.85rem; flex: 1;">
                                                <option value="days" <?php echo $rule['frequency_type'] == 'days' ? 'selected' : ''; ?>><?php echo __('common.day'); ?></option>
                                                <option value="weeks" <?php echo $rule['frequency_type'] == 'weeks' ? 'selected' : ''; ?>><?php echo __('common.week'); ?></option>
                                                <option value="months" <?php echo $rule['frequency_type'] == 'months' ? 'selected' : ''; ?>><?php echo __('common.month'); ?></option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted);"><?php echo __('patient.reexam.start_date'); ?></label>
                                        <input type="date" name="first_date" class="form-input" value="<?php echo date('Y-m-d', strtotime($rule['next_due_at'])); ?>" style="padding: 0.4rem; font-size: 0.85rem;">
                                    </div>
                                </div>
                                <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                    <button type="button" onclick="toggleEditReexam(<?php echo $rule['id']; ?>)" class="btn btn-sm" style="background: white; font-weight: 700; border: 1px solid #cbd5e1;"><?php echo __('common.cancel'); ?></button>
                                    <button type="submit" class="btn btn-sm btn-primary" style="background: #d97706; border: none; font-weight: 700;">Lưu thay đổi</button>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.info-section-title { font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; letter-spacing: 1px; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem; }
.info-section-title i { color: var(--primary-light); font-size: 0.9rem; }
.info-item { display: flex; justify-content: space-between; margin-bottom: 0.75rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f8fafc; font-size: 0.9rem; }
.info-item span { color: var(--text-muted); }
.social-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; text-decoration: none; transition: all 0.2s; }
.social-icon:hover { transform: translateY(-3px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.timeline-node:last-child { margin-bottom: 0 !important; }

/* Filter Pill Styles (Legacy Timeline Filters) */
.filter-pill {
    padding: 0.4rem 1rem;
    border-radius: 50px;
    background: #f1f5f9;
    color: #64748b;
    font-size: 0.8rem;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}
.filter-pill:hover { background: #e2e8f0; }
.filter-pill.active {
    background: var(--primary);
    color: white;
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
}

/* Medical Tab Styles */
.med-tab-btn {
    background: none;
    border: none;
    color: #64748b;
    font-weight: 800;
    font-size: 0.95rem;
    padding: 0.75rem 1.5rem;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    transition: all 0.2s;
    margin-bottom: -2px;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.med-tab-btn:hover { color: var(--primary); }
.med-tab-btn.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
}
</style>

<script>
function toggleReexamForm() {
    const form = document.getElementById('reexamForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

function toggleEditReexam(id) {
    const editForm = document.getElementById('reexamEdit' + id);
    const viewDiv = document.getElementById('reexamView' + id);
    if (editForm.style.display === 'none') {
        editForm.style.display = 'block';
        viewDiv.style.display = 'none';
    } else {
        editForm.style.display = 'none';
        viewDiv.style.display = 'flex';
    }
}

function filterTimeline(type, event) {
    // Update active pill
    document.querySelectorAll('.filter-pill').forEach(pill => {
        pill.classList.remove('active');
    });
    
    if (event && event.currentTarget) {
        event.currentTarget.classList.add('active');
    }

    const nodes = document.querySelectorAll('.timeline-node');
    nodes.forEach(node => {
        if (type === 'all' || node.getAttribute('data-type') === type) {
            node.style.display = 'block';
        } else {
            node.style.display = 'none';
        }
    });
}

function togglePkgDetail(idx) {
    var detail = document.getElementById('pkgDetail' + idx);
    var icon = document.getElementById('pkgIcon' + idx);
    if (detail.style.display === 'none') {
        detail.style.display = 'block';
        icon.style.transform = 'rotate(180deg)';
    } else {
        detail.style.display = 'none';
        icon.style.transform = 'rotate(0deg)';
    }
}

function switchMedicalTab(tabId, btn) {
    document.querySelectorAll('.med-tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    document.getElementById('tab-timeline').style.display = 'none';
    document.getElementById('tab-gallery').style.display = 'none';
    
    document.getElementById('tab-' + tabId).style.display = 'block';
}

function openLightbox(src) {
    var overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.85);z-index:9999;display:flex;align-items:center;justify-content:center;cursor:pointer;backdrop-filter:blur(5px)';
    overlay.onclick = function() { document.body.removeChild(overlay); };
    
    var img = document.createElement('img');
    img.src = src;
    img.style.cssText = 'max-width:90%;max-height:90%;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,0.5)';
    overlay.appendChild(img);
    
    var closeBtn = document.createElement('div');
    closeBtn.innerHTML = '<i class="fas fa-times"></i>';
    closeBtn.style.cssText = 'position:absolute;top:1.5rem;right:1.5rem;width:40px;height:40px;background:rgba(255,255,255,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:1.2rem;cursor:pointer';
    overlay.appendChild(closeBtn);
    
    document.body.appendChild(overlay);
}
</script>

<?php require_once '../../templates/footer.php'; ?>
