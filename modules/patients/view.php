<?php
// modules/patients/view.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('view_patients');
$page_title = __('patient.detail.title');
$current_page = 'patients';
require_once '../../templates/header.php';

$id = isset($_GET['id']) ? $_GET['id'] : 0;
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

// Fetch package progress
$stmt = $db->prepare("
    SELECT pp.*, p.name as package_name 
    FROM patient_packages pp 
    JOIN packages p ON pp.package_id = p.id 
    WHERE pp.patient_id = ? AND pp.status = 'active'
");
$stmt->execute([$id]);
$packages = $stmt->fetchAll();

// Fetch re-examination rules
$stmt = $db->prepare("SELECT * FROM reexam_rules WHERE patient_id = ? ORDER BY next_due_at ASC");
$stmt->execute([$id]);
$rules = $stmt->fetchAll();
?>

<div style="display: flex; gap: 1.5rem;">
    <!-- Left Column: Patient Info -->
    <div style="flex: 1;">
<div style="display: flex; flex-direction: column; gap: 1.5rem; width: 100%;">
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
        if ($h['type'] === 'chiro_history') {
            $chiro_history = $h;
            break;
        }
    }
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
                            <i class="fas fa-print"></i> In PDF
                        </a>
                        <a href="../medical/chiro_history.php?patient_id=<?php echo $patient['id']; ?>&id=<?php echo $chiro_history['id']; ?>" class="btn" style="background: white; border: 1px solid #d8b4fe; color: #9333ea; font-weight: 800; border-radius: 50px; padding: 0.5rem 1rem; transition: all 0.2s;" onmouseover="this.style.background='#9333ea'; this.style.color='white';" onmouseout="this.style.background='white'; this.style.color='#9333ea';">
                            <i class="fas fa-edit"></i> <?php echo __('patient.history.view_update'); ?>
                        </a>
                    </div>
                <?php else: ?>
                    <a href="../medical/chiro_history.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-primary" style="background: #9333ea; border: none; font-weight: 800; border-radius: 50px; padding: 0.5rem 1rem; box-shadow: 0 4px 12px rgba(147, 51, 234, 0.3);">
                        <i class="fas fa-plus-circle"></i> <?php echo __('patient.history.create_new'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
        <!-- Left: Medical Timeline -->
        <div class="card" style="border: none; box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2.5rem; padding-bottom: 1rem; border-bottom: 1px solid #f1f5f9;">
                <h3 style="margin: 0; font-weight: 800;"><i class="fas fa-stream" style="color: var(--primary);"></i> <?php echo __('patient.history.title'); ?></h3>
                <a href="../medical/session_add.php?patient_id=<?php echo $id; ?>" class="btn btn-primary shadow-sm" style="border-radius: 50px; font-size: 0.85rem;">
                    <i class="fas fa-plus"></i> <?php echo __('patient.history.new_session'); ?>
                </a>
            </div>

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
                        if ($h['type'] === 'chiro_history') continue;
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
                    if ($h['type'] === 'chiro_history') continue;
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
                                    'chiropractic' => __('medical.type.chiropractic'),
                                    'soap_note' => __('medical.type.chiropractic'),
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
                                        <i class="fas fa-print"></i> In PDF
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>

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

                <!-- Package Progress -->
                <?php if (empty($packages)): ?>
                    <div style="padding: 1.5rem; text-align: center; background: #f8fafc; border-radius: 16px; border: 1px dashed #e2e8f0; margin-bottom: 1.5rem;">
                        <i class="fas fa-shopping-basket" style="font-size: 1.5rem; color: #cbd5e1; margin-bottom: 0.75rem;"></i>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;"><?php echo __('patient.finance.no_packages'); ?></p>
                    </div>
                <?php else: ?>
                    <?php foreach ($packages as $pkg): ?>
                        <div style="padding: 1rem; background: #fffcf0; border: 1px solid #fde68a; border-radius: 12px; margin-bottom: 0.75rem;">
                            <div style="font-weight: 800; font-size: 0.9rem; color: #92400e;"><?php echo e($pkg['package_name']); ?></div>
                            <div style="display: flex; justify-content: space-between; font-size: 0.75rem; margin-top: 0.25rem;">
                                <span><?php echo __('patient.finance.remaining'); ?> <strong><?php echo $pkg['remaining_sessions']; ?>/<?php echo $pkg['total_sessions']; ?></strong></span>
                                <span style="color: var(--text-muted);"><?php echo __('patient.finance.expired'); ?> <?php echo date('d/m/y', strtotime($pkg['expiry_date'])); ?></span>
                            </div>
                            <div style="margin-top: 0.5rem; background: #fef3c7; height: 4px; border-radius: 2px; overflow: hidden;">
                                <div style="background: #f59e0b; height: 100%; width: <?php echo ($pkg['remaining_sessions'] / $pkg['total_sessions']) * 100; ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

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
                        <div style="background: white; padding: 1rem; border-radius: 12px; margin-bottom: 0.75rem; box-shadow: 0 2px 4px rgba(0,0,0,0.02); display: flex; justify-content: space-between; align-items: center;">
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
                                <form action="manage_reexam.php" method="POST" style="margin: 0;" onsubmit="return confirm('<?php echo __('common.confirm_delete'); ?>')">
                                    <input type="hidden" name="patient_id" value="<?php echo $id; ?>">
                                    <input type="hidden" name="id" value="<?php echo $rule['id']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" style="background: none; border: none; color: #cbd5e1; cursor: pointer; padding: 0.25rem;"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
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

/* Filter Pill Styles */
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
</style>

<script>
function toggleReexamForm() {
    const form = document.getElementById('reexamForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
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
</script>

<?php require_once '../../templates/footer.php'; ?>
