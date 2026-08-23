<?php
// modules/medical/session_view.php

require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('view_medical');

$db = getDB();
$session_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$session_id) {
    set_flash(__('medical.session.err_missing_id'), 'error');
    redirect('../patients/index.php');
}

// 1. Fetch Session Info
$stmt = $db->prepare("
    SELECT s.*, p.full_name as patient_name, p.birthday, p.gender, p.id as patient_id, 
           u.full_name as doctor_name
    FROM medical_sessions s
    JOIN patients p ON s.patient_id = p.id
    JOIN users u ON s.doctor_id = u.id
    WHERE s.id = ?
");
$stmt->execute([$session_id]);
$session = $stmt->fetch();

if (!$session) {
    set_flash(__('medical.session.err_not_found'), 'error');
    redirect('../patients/index.php');
}

// 2. Handle POST (Assessment & Plan & Status Changes)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    // Check Lock: If completed and not admin, block editing
    if ($session['status'] === 'completed' && !has_role('admin')) {
        set_flash(__('medical.session.err_locked'), 'error');
        header("Location: session_view.php?id=$session_id");
        exit;
    }

    // Admin Re-open Logic
    if (isset($_POST['reopen']) && has_role('admin')) {
        // Enforce 7-day rule: Cannot re-open if more than 7 days have passed since session date
        $session_date = new DateTime($session['session_date']);
        $now = new DateTime();
        $interval = $now->diff($session_date);
        
        if ($interval->days > 7) {
            set_flash(__('medical.session.err_reopen_expired'), 'error');
        } else {
            $stmt = $db->prepare("UPDATE medical_sessions SET status = 'active', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$session_id]);
            log_audit($_SESSION['user_id'], 'reopen_session', 'medical_sessions', $session_id, ['status' => 'completed'], ['status' => 'active']);
            set_flash(__('medical.session.msg_reopened'));
        }
        redirect("session_view.php?id=$session_id");
    }

    $can_edit_summary = (has_role('doctor') || has_role('admin'));
    $allowed_tags = '<p><br><strong><em><u><ol><ul><li><span><h1><h2><h3>';
    $assessment = (isset($_POST['assessment']) && $can_edit_summary) ? strip_tags($_POST['assessment'], $allowed_tags) : $session['assessment'];
    $plan = (isset($_POST['treatment_plan']) && $can_edit_summary) ? strip_tags($_POST['treatment_plan'], $allowed_tags) : $session['treatment_plan'];
    $doctor_id = isset($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : $session['doctor_id'];
    $status = $session['status'];
    
    // Status complete logic
    if (isset($_POST['complete'])) {
        if (!$can_edit_summary) {
            set_flash(__('medical.session.err_complete_doctor_only'), 'error');
            header("Location: session_view.php?id=$session_id");
            exit;
        }
        $status = 'completed';
    }

    // Audit Logging for Data changes
    $old_data = [
        'assessment' => $session['assessment'],
        'treatment_plan' => $session['treatment_plan'],
        'status' => $session['status'],
        'doctor_id' => $session['doctor_id']
    ];
    $new_data = [
        'assessment' => $assessment,
        'treatment_plan' => $plan,
        'status' => $status,
        'doctor_id' => $doctor_id
    ];

    if ($old_data !== $new_data) {
        $stmt = $db->prepare("
            UPDATE medical_sessions 
            SET assessment = ?, treatment_plan = ?, status = ?, doctor_id = ?
            WHERE id = ?
        ");
        $stmt->execute([$assessment, $plan, $status, $doctor_id, $session_id]);
        
        log_audit($_SESSION['user_id'], 'update', 'medical_sessions', $session_id, $old_data, $new_data);
        set_flash(__('medical.session.msg_updated'));
    }
    
    if ($status === 'completed' && $old_data['status'] !== 'completed') {
        // Find today's arrived appointment for this patient and mark as completed
        $today = date('Y-m-d');
        $stmt_app = $db->prepare("
            UPDATE appointments 
            SET status = 'completed'
            WHERE patient_id = ? AND DATE(appointment_date) = ? AND status = 'arrived'
        ");
        $stmt_app->execute([$session['patient_id'], $today]);
        
        redirect("../patients/view.php?id=" . $session['patient_id']);
    }
    // Refresh
    if (!empty($_POST['lang_switch_autosave'])) {
        header("Location: " . $_SERVER['REQUEST_URI']);
    } else {
        header("Location: session_view.php?id=$session_id");
    }
    exit;
}

// 3. Locking Variable
$is_locked = ($session['status'] === 'completed' && !has_role('admin'));
$can_edit_summary = (has_role('doctor') || has_role('admin'));

// 4. Fetch component status
$stmt = $db->prepare("SELECT type, id FROM medical_history WHERE session_id = ?");
$stmt->execute([$session_id]);
$history_records = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);

// Lấy Tiền sử bệnh Chiropractic của bệnh nhân (bất kể buổi khám nào)
$stmt_chiro_hist = $db->prepare("SELECT id, type FROM medical_history WHERE patient_id = ? AND (type = 'chiro_history' OR type = 'chiro_history_v2') ORDER BY id DESC LIMIT 1");
$stmt_chiro_hist->execute([$session['patient_id']]);
$patient_chiro_history = $stmt_chiro_hist->fetch(PDO::FETCH_ASSOC);
if ($patient_chiro_history) {
    if (!isset($history_records['chiro_history']) && !isset($history_records['chiro_history_v2'])) {
        $history_records[$patient_chiro_history['type']] = ['id' => $patient_chiro_history['id']];
    }
}

// Lấy Phiếu Đông Y gần nhất của bệnh nhân
$stmt_latest_dong_y = $db->prepare("
    SELECT h.id, h.created_at, u.full_name as doctor_name 
    FROM medical_history h 
    LEFT JOIN users u ON h.created_by = u.id 
    WHERE h.patient_id = ? AND h.type = 'dong_y' 
    ORDER BY h.id DESC LIMIT 1
");
$stmt_latest_dong_y->execute([$session['patient_id']]);
$latest_dong_y = $stmt_latest_dong_y->fetch(PDO::FETCH_ASSOC);

$stmt = $db->prepare("SELECT id FROM treatments WHERE session_id = ?");
$stmt->execute([$session_id]);
$treatment_records = $stmt->fetchAll();

$page_title = __('medical.session.title_detail');
$current_page = 'medical';
require_once '../../templates/header.php';
?>

<!-- Quill.js CDN -->
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>

<style>
    .ql-container { border-bottom-left-radius: 12px; border-bottom-right-radius: 12px; background: white; }
    .ql-toolbar { border-top-left-radius: 12px; border-top-right-radius: 12px; background: #f8fafc; }
    .editor-wrapper { margin-bottom: 2rem; }
    .editor-label { font-weight: 800; color: var(--primary); font-size: 0.85rem; text-transform: uppercase; margin-bottom: 0.75rem; display: block; }
</style>

<div style="display: flex; gap: 2rem; align-items: flex-start;">
    <!-- Left Column: Components List -->
    <div style="flex: 1; display: flex; flex-direction: column; gap: 1.5rem;">
        <div class="card" style="border-left: 5px solid var(--primary);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="margin: 0;"><?php echo __('medical.session.date_prefix'); ?> <?php echo date('d/m/Y', strtotime($session['session_date'])); ?></h3>
                <div style="display: flex; gap: 0.75rem; align-items: center;">
                    <?php if (has_role('admin')): ?>
                        <form action="session_delete.php" method="POST" style="margin: 0;" onsubmit="return confirm('Bạn có chắc chắn muốn xoá buổi khám này?\n\nHành động này sẽ xoá buổi khám. CHÚ Ý: Hệ thống sẽ chặn xoá nếu buổi khám này đã có phát sinh Ca Điều Trị (có trừ buổi liệu trình).');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="id" value="<?php echo $session_id; ?>">
                            <input type="hidden" name="patient_id" value="<?php echo $session['patient_id']; ?>">
                            <button type="submit" class="btn btn-sm" style="background: #fff1f2; color: #e11d48; border: 1px solid #fecdd3; border-radius: 50px; font-weight: 600;" title="Xoá buổi khám">
                                <i class="fas fa-trash-alt"></i> Xoá
                            </button>
                        </form>
                    <?php endif; ?>
                    <a href="print_session.php?id=<?php echo $session_id; ?>" target="_blank" class="btn btn-outline btn-sm" style="border-radius: 50px;">
                        <i class="fas fa-print"></i> <?php echo __('medical.session.print'); ?>
                    </a>
                    <span class="badge <?php echo $session['status'] === 'completed' ? 'badge-success' : 'badge-warning'; ?>">
                        <?php echo $session['status'] === 'completed' ? __('medical.session.status_completed') : __('medical.session.status_processing'); ?>
                    </span>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.95rem;">
                <div><?php echo __('medical.session.patient_label'); ?> <strong><?php echo e($session['patient_name']); ?></strong></div>
                <div><?php echo __('medical.session.doctor_label'); ?> <strong><?php echo e($session['doctor_name']); ?></strong></div>
            </div>
        </div>

        <div class="card">
            <h4 style="margin: 0 0 1.5rem 0; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.75rem;">
                <i class="fas fa-tasks"></i> <?php echo __('medical.session.components_title'); ?>
            </h4>
            
            <style>
                .component-item {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 1rem;
                    background: #f8fafc;
                    border-radius: 12px;
                    border: 1px solid #e2e8f0;
                    margin-bottom: 0.75rem;
                    transition: all 0.2s;
                }
                .component-item:hover { border-color: var(--primary); transform: translateX(5px); }
                .component-status { font-weight: 700; font-size: 0.85rem; }
                .status-pending { color: #64748b; }
                .status-done { color: #10b981; }
            </style>

            <?php
                // NEW: Logic for V2
                // We already have $history_records which has all medical_history for this session.
                // We already have $patient_chiro_history (from any session).
                
                $has_anamnese_ever = $patient_chiro_history ? true : false;
                $anamnese_type = $patient_chiro_history ? $patient_chiro_history['type'] : 'chiro_history_v2';
                $anamnese_id = $patient_chiro_history ? $patient_chiro_history['id'] : null;
                
                // URLs for Anamnese
                $url_anamnese_create = "chiro_history_v2.php?patient_id=" . $session['patient_id'] . "&session_id=" . $session_id;
                $url_anamnese_edit = "";
                if ($has_anamnese_ever) {
                    $url_anamnese_edit = ($anamnese_type === 'chiro_history_v2' ? 'chiro_history_v2.php' : 'chiro_history.php') . "?patient_id=" . $session['patient_id'] . "&id=" . $anamnese_id . "&session_id=" . $session_id;
                }
                
                // Status for Pathologie in this session
                $has_pathologie = isset($history_records['pathologie_v2']);
                $pathologie_id = $has_pathologie ? $history_records['pathologie_v2']['id'] : null;
                $url_pathologie = "pathologie_v2.php?patient_id=" . $session['patient_id'] . "&session_id=" . $session_id . ($has_pathologie ? "&id=" . $pathologie_id : "");
                
                // Status for Follow-up in this session
                $has_followup = isset($history_records['soap_note_v2']) || isset($history_records['soap_note']);
                $followup_id = isset($history_records['soap_note_v2']) ? $history_records['soap_note_v2']['id'] : (isset($history_records['soap_note']) ? $history_records['soap_note']['id'] : null);
                $url_followup = (isset($history_records['soap_note']) && !isset($history_records['soap_note_v2'])) 
                                ? "follow_up.php?patient_id=" . $session['patient_id'] . "&session_id=" . $session_id . "&id=" . $followup_id 
                                : "follow_up_v2.php?patient_id=" . $session['patient_id'] . "&session_id=" . $session_id . ($has_followup ? "&id=" . $followup_id : "");
                
                // Styling smart logic
                $style_anamnese_box = $has_anamnese_ever ? "background: #f8fafc; border: 1px solid #e2e8f0; opacity: 0.8;" : "background: #faf5ff; border: 1px solid #e9d5ff; box-shadow: 0 4px 6px -1px rgba(168, 85, 247, 0.1);";
                $style_anamnese_icon = $has_anamnese_ever ? "color: #94a3b8; border-color: #e2e8f0;" : "color: #a855f7; border-color: #e9d5ff;";
                $btn_anamnese_class = $has_anamnese_ever ? "btn-outline" : "";
                $btn_anamnese_style = $has_anamnese_ever ? "border-radius: 50px; font-size: 0.8rem; padding: 0.35rem 0.8rem; color: #64748b; border-color: #cbd5e1;" : "background: #a855f7; border-color: #a855f7; color: white; border-radius: 50px; font-weight: 600;";
                
                $style_followup_box = ($has_anamnese_ever && !$has_followup) ? "background: #eff6ff; border: 1px solid #bfdbfe; box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.1);" : "background: #f8fafc; border: 1px solid #e2e8f0;";
                $style_followup_icon = ($has_anamnese_ever && !$has_followup) ? "color: #3b82f6; border-color: #bfdbfe;" : "color: #3b82f6; border-color: #e2e8f0;";
                $btn_followup_class = ($has_anamnese_ever && !$has_followup) ? "btn-primary" : ($has_followup ? "btn-outline" : "btn-primary");
                
                $style_pathologie_box = "background: #f8fafc; border: 1px solid #e2e8f0;";
                $btn_pathologie_class = $has_pathologie ? "btn-outline" : "btn-primary";
            ?>

            <!-- V2 CHIROPRACTIC COMPONENTS -->
            <h4 style="margin: 0 0 1rem 0; color: #1e293b; font-size: 1.1rem; display: flex; align-items: center; gap: 0.5rem; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.75rem;">
                <i class="fas fa-stethoscope text-primary"></i> Khám Chiropractic (V2)
            </h4>

            <!-- 1. ANAMNESE -->
            <div style="border-radius: 12px; padding: 1.25rem; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; gap: 1rem; transition: all 0.3s; <?php echo $style_anamnese_box; ?>">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 48px; height: 48px; background: white; border-radius: 12px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 4px rgba(0,0,0,0.02); <?php echo $style_anamnese_icon; ?>">
                        <i class="fas fa-history fa-lg"></i>
                    </div>
                    <div>
                        <div style="font-weight: 800; font-size: 1.1rem; color: #1e293b;">Khai báo Tiền sử (Anamnese)</div>
                        <?php if (!$has_anamnese_ever): ?>
                            <div style="color: #ea580c; font-size: 0.85rem; font-weight: 600; margin-top: 0.25rem;">
                                <i class="fas fa-exclamation-triangle"></i> Bệnh nhân chưa có tiền sử bệnh
                            </div>
                        <?php else: ?>
                            <div style="color: #10b981; font-size: 0.85rem; font-weight: 600; margin-top: 0.25rem;">
                                <i class="fas fa-check-circle"></i> Đã có tiền sử bệnh
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <?php if ($has_anamnese_ever): ?>
                        <a href="print_record.php?type=history&id=<?php echo $anamnese_id; ?>" target="_blank" class="btn btn-sm" style="background: white; border: 1px solid #10b981; color: #10b981; border-radius: 50px; font-weight: 700; font-size: 0.8rem; padding: 0.35rem 0.6rem;">
                            <i class="fas fa-print"></i> PDF
                        </a>
                        <a href="<?php echo $url_anamnese_edit; ?>" class="btn btn-sm <?php echo $btn_anamnese_class; ?>" style="<?php echo $btn_anamnese_style; ?>">
                            <i class="fas fa-edit"></i> Xem / Sửa
                        </a>
                    <?php else: ?>
                        <a href="<?php echo $url_anamnese_create; ?>" class="btn btn-sm <?php echo $btn_anamnese_class; ?>" style="<?php echo $btn_anamnese_style; ?>">
                            <i class="fas fa-plus"></i> Khai báo tiền sử
                        </a>
                    <?php endif; ?>
                </div>
            </div>


            <!-- 3. FOLLOW-UP -->
            <div style="border-radius: 12px; padding: 1.25rem; margin-bottom: 2.5rem; display: flex; justify-content: space-between; align-items: center; gap: 1rem; transition: all 0.3s; <?php echo $style_followup_box; ?>">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 48px; height: 48px; background: white; border-radius: 12px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 4px rgba(0,0,0,0.02); <?php echo $style_followup_icon; ?>">
                        <i class="fas fa-notes-medical fa-lg"></i>
                    </div>
                    <div>
                        <div style="font-weight: 800; font-size: 1.1rem; color: #1e293b;">Tái khám (Follow-up)</div>
                        <div class="component-status <?php echo $has_followup ? 'status-done' : 'status-pending'; ?>">
                            <i class="fas <?php echo $has_followup ? 'fa-check-circle' : 'fa-hourglass-half'; ?>"></i>
                            <?php echo $has_followup ? __('medical.session.status_done') : __('medical.session.status_pending'); ?>
                        </div>
                    </div>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <?php if ($has_followup): ?>
                        <a href="print_record.php?type=history&id=<?php echo $followup_id; ?>" target="_blank" class="btn btn-sm" style="background: white; border: 1px solid #10b981; color: #10b981; border-radius: 50px; font-weight: 700; font-size: 0.8rem; padding: 0.35rem 0.6rem;">
                            <i class="fas fa-print"></i> PDF
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo $url_followup; ?>" class="btn btn-sm <?php echo $btn_followup_class; ?>" style="border-radius: 50px; font-size: 0.8rem; padding: 0.35rem 0.8rem;">
                        <?php echo $has_followup ? '<i class="fas fa-edit"></i> ' . __('common.edit') : '<i class="fas fa-play"></i> Bắt đầu'; ?>
                    </a>
                </div>
            </div>

            <!-- PHIẾU ĐÔNG Y GẦN NHẤT MÀU XANH LÁ -->
            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; gap: 1rem; box-shadow: 0 4px 6px -1px rgba(34, 197, 94, 0.05);">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 48px; height: 48px; background: white; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #22c55e; border: 1px solid #bbf7d0; box-shadow: 0 2px 4px rgba(0,0,0,0.02)">
                        <i class="fas fa-leaf fa-lg"></i>
                    </div>
                    <div>
                        <div style="font-weight: 800; font-size: 1.1rem; color: #1e293b;"><?php echo __('medical.session.latest_dong_y_title'); ?></div>
                        <?php if (!$latest_dong_y): ?>
                            <div style="color: #ea580c; font-size: 0.85rem; font-weight: 600; margin-top: 0.25rem;">
                                <i class="fas fa-exclamation-triangle"></i> <?php echo __('medical.session.no_dong_y_yet'); ?>
                            </div>
                        <?php else: ?>
                            <div style="color: #10b981; font-size: 0.85rem; font-weight: 600; margin-top: 0.25rem;">
                                <i class="fas fa-clock"></i> <?php echo __('medical.session.date_label'); ?> <?php echo date('d/m/Y H:i', strtotime($latest_dong_y['created_at'])); ?> 
                                <span style="margin: 0 0.5rem; color: #cbd5e1;">|</span> 
                                <i class="fas fa-user-md"></i> <?php echo __('medical.session.doctor_label'); ?> <?php echo e($latest_dong_y['doctor_name'] ?? 'Không rõ'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <?php if ($latest_dong_y): ?>
                        <a href="print_record.php?type=history&id=<?php echo $latest_dong_y['id']; ?>" target="_blank" class="btn btn-sm" style="background: white; border: 1px solid #22c55e; color: #22c55e; border-radius: 50px; font-weight: 700; font-size: 0.8rem; padding: 0.35rem 0.6rem;">
                            <i class="fas fa-print"></i> PDF
                        </a>
                        <a href="view_form.php?id=<?php echo $latest_dong_y['id']; ?>" target="_blank" class="btn btn-sm btn-outline" style="border-radius: 50px; font-size: 0.8rem; padding: 0.35rem 0.8rem; color: #64748b; border-color: #cbd5e1;">
                            <i class="fas fa-eye"></i> Xem
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- CHỈ MỤC CÁC THÀNH PHẦN KHÁC (ĐÔNG Y & DỊCH VỤ) -->
            <h4 style="margin: 0 0 1rem 0; color: #1e293b; font-size: 1.1rem; display: flex; align-items: center; gap: 0.5rem; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.75rem;">
                <i class="fas fa-tasks text-primary"></i> Đông Y & Dịch vụ
            </h4>

            <?php
                $other_components = [
                    'dong_y'        => ['label' => __('medical.type.dong_y_full'), 'url' => 'form.php?type=dong_y', 'icon' => 'fa-leaf'],
                    'treatment'     => ['label' => __('medical.type.treatment_full'), 'url' => 'add_treatment.php', 'icon' => 'fa-file-signature']
                ];
                foreach ($other_components as $type => $info):
                $actual_type_record = $type;

                $is_done = ($type === 'treatment') ? !empty($treatment_records) : isset($history_records[$actual_type_record]);
                
                // Base parameters
                $params = [
                    'patient_id' => $session['patient_id'],
                    'session_id' => $session_id
                ];
                
                // Add ID if already exists (for editing)
                if ($is_done && $type !== 'treatment') {
                    $params['id'] = $history_records[$actual_type_record]['id'];
                }
                
                $btn_label = $is_done ? __('medical.session.btn_edit') : __('medical.session.btn_start');
                
                // Construct URL
                $url_parts = parse_url($info['url']);
                $query = [];
                if (isset($url_parts['query'])) {
                    parse_str($url_parts['query'], $query);
                }
                $final_query = array_merge($query, $params);
                $edit_url = $url_parts['path'] . '?' . http_build_query($final_query);
            ?>
                <div class="component-item">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 40px; height: 40px; background: white; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--primary); border: 1px solid #e2e8f0;">
                            <i class="fas <?php echo $info['icon']; ?>"></i>
                        </div>
                        <div>
                            <div style="font-weight: 700; color: #1e293b;"><?php echo $info['label']; ?></div>
                            <div class="component-status <?php echo $is_done ? 'status-done' : 'status-pending'; ?>">
                                <i class="fas <?php echo $is_done ? 'fa-check-circle' : 'fa-hourglass-half'; ?>"></i>
                                <?php echo $is_done ? __('medical.session.status_done') : __('medical.session.status_pending'); ?>
                            </div>
                        </div>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <?php if ($is_done): 
                            $print_type = ($type === 'treatment') ? 'treatment' : 'history';
                            $print_id = ($type === 'treatment') ? $treatment_records[0]['id'] : $history_records[$actual_type_record]['id'];
                        ?>
                            <a href="print_record.php?type=<?php echo $print_type; ?>&id=<?php echo $print_id; ?>" target="_blank" class="btn btn-sm" style="background: white; border: 1px solid #10b981; color: #10b981; border-radius: 50px; font-weight: 700; font-size: 0.8rem; padding: 0.35rem 0.7rem;">
                                <i class="fas fa-print"></i> PDF
                            </a>
                        <?php endif; ?>
                        <a href="<?php echo $edit_url; ?>" class="btn btn-sm <?php echo $is_done ? 'btn-outline' : 'btn-primary'; ?>" style="border-radius: 50px; font-size: 0.8rem; padding: 0.35rem 0.8rem;">
                            <?php echo $is_done ? '<i class="fas fa-edit"></i> ' . __('common.edit') : '<i class="fas fa-play"></i> Bắt đầu'; ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- IMAGE UPLOAD SECTION -->
        <div class="card" style="margin-top: 0;">
            <h4 style="margin: 0 0 1rem 0; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.75rem;">
                <i class="fas fa-images" style="color: #7c3aed;"></i> <?php echo __('common.attachments'); ?>
            </h4>

            <?php
            // Load existing attachments from all records in this session
            $stmt_att = $db->prepare("SELECT id, attachments FROM medical_history WHERE session_id = ? AND attachments IS NOT NULL AND attachments != '[]'");
            $stmt_att->execute([$session_id]);
            $all_attachments = [];
            while ($row = $stmt_att->fetch()) {
                $atts = json_decode($row['attachments'], true);
                if ($atts) {
                    foreach ($atts as &$att) {
                        $att['record_id'] = $row['id'];
                    }
                    $all_attachments = array_merge($all_attachments, $atts);
                }
            }
            ?>

            <?php if (!empty($all_attachments)): ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 0.75rem; margin-bottom: 1.5rem;">
                <?php foreach ($all_attachments as $att): ?>
                <div style="position: relative; border-radius: 12px; overflow: hidden; border: 2px solid #e2e8f0; aspect-ratio: 1;" class="att-item">
                    <div style="cursor: pointer; width: 100%; height: 100%;" onclick="openLightbox('<?php echo addslashes($att['path']); ?>')">
                        <?php if (strpos(isset($att['type']) ? $att['type'] : '', 'image') !== false): ?>
                        <img src="<?php echo $att['path']; ?>" style="width: 100%; height: 100%; object-fit: cover;" alt="<?php echo e($att['name']); ?>">
                        <?php else: ?>
                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #f1f5f9;">
                            <i class="fas fa-file-pdf" style="font-size: 2rem; color: #ef4444;"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!$is_locked): ?>
                    <button type="button" class="btn-delete-att" onclick="deleteAttachment(event, <?php echo $att['record_id']; ?>, '<?php echo addslashes($att['path']); ?>')" title="Xoá ảnh" style="position: absolute; top: 6px; right: 6px; background: rgba(239, 68, 68, 0.9); border: 2px solid white; width: 28px; height: 28px; border-radius: 50%; color: white; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 10; padding: 0;">
                        <i class="fas fa-trash-alt" style="font-size: 0.7rem;"></i>
                    </button>
                    <button type="button" class="btn-edit-att" onclick="renameAttachment(event, <?php echo $att['record_id']; ?>, '<?php echo addslashes($att['path']); ?>', '<?php echo addslashes($att['name']); ?>')" title="Đổi tên ảnh" style="position: absolute; top: 6px; right: 38px; background: rgba(59, 130, 246, 0.9); border: 2px solid white; width: 28px; height: 28px; border-radius: 50%; color: white; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 10; padding: 0;">
                        <i class="fas fa-pencil-alt" style="font-size: 0.7rem;"></i>
                    </button>
                    <?php endif; ?>
                    <div style="position: absolute; pointer-events: none; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,0.85)); padding: 1.5rem 0.5rem 0.5rem; color: white; font-size: 0.7rem; font-weight: 600; text-shadow: 0 1px 2px rgba(0,0,0,0.8); line-height: 1.25;">
                        <?php echo e(mb_strlen($att['name']) > 35 ? mb_substr($att['name'], 0, 32) . '...' : $att['name']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!$is_locked): ?>
            <div id="upload-zone" style="border: 2px dashed #cbd5e1; border-radius: 16px; padding: 2rem; text-align: center; cursor: pointer; transition: all 0.3s; background: #fafbfc;" ondragover="event.preventDefault(); this.style.borderColor='#6366f1'; this.style.background='#eef2ff'" ondragleave="this.style.borderColor='#cbd5e1'; this.style.background='#fafbfc'" ondrop="handleDrop(event)">
                <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: #94a3b8; margin-bottom: 0.5rem;"></i>
                <div style="font-weight: 700; color: #64748b; font-size: 0.9rem;"><?php echo __('common.upload_images'); ?></div>
                <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem;"><?php echo __('medical.session.upload_hint'); ?></div>
                <input type="file" id="file-input" multiple accept="image/*,.pdf" style="display: none;" onchange="uploadFiles(this.files)">
            </div>
            <div id="upload-progress" style="display: none; margin-top: 1rem;">
                <div style="background: #e2e8f0; border-radius: 8px; overflow: hidden; height: 6px;">
                    <div id="progress-bar" style="height: 100%; background: linear-gradient(90deg, #6366f1, #8b5cf6); width: 0%; transition: width 0.3s;"></div>
                </div>
                <div id="upload-status" style="font-size: 0.75rem; color: #64748b; margin-top: 0.5rem; text-align: center;"></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Assessment & Plan (Rich-Text) -->
    <div style="width: 540px; display: flex; flex-direction: column; gap: 1.5rem;">
        <form method="POST" id="session-form" class="card" style="position: sticky; top: 1.5rem; background: #fcfdfe;">
            <?php echo csrf_field(); ?>
            <?php if ($session['status'] === 'completed'): ?>
                <div style="background: #fef2f2; color: #991b1b; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; border: 1px solid #fecaca; display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-lock"></i>
                    <div style="font-size: 0.85rem; font-weight: 700;">
                        <?php echo __('medical.session.locked_title'); ?>
                        <?php if ($is_locked): ?>
                            <br><span style="font-weight: 500; font-size: 0.75rem;"><?php echo __('medical.session.locked_desc'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; border-bottom: 2px solid #eef2f6; padding-bottom: 1rem;">
                <h4 style="margin: 0; color: var(--primary); font-weight: 800;">
                    <i class="fas fa-user-md"></i> <?php echo __('medical.session.clinical_summary'); ?>
                </h4>
                
                <div style="font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem;">
                    <label style="color: #64748b; font-weight: 700; margin: 0;"><?php echo __('medical.session.dr_prefix'); ?></label>
                    <?php if (!$is_locked): ?>
                        <select name="doctor_id" class="form-input" style="padding: 0.25rem 0.5rem; width: auto; font-size: 0.85rem; font-weight: 700; border-radius: 6px; cursor: pointer;">
                            <?php 
                            $stmt_docs = $db->query("
                                SELECT u.id, u.full_name as name 
                                FROM users u 
                                JOIN roles r ON u.role_id = r.id 
                                WHERE r.name IN ('doctor', 'technician', 'admin') 
                                AND u.status = 'active'
                                ORDER BY name
                            ");
                            while ($doc = $stmt_docs->fetch()): 
                            ?>
                                <option value="<?php echo $doc['id']; ?>" <?php echo $session['doctor_id'] == $doc['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($doc['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    <?php else: ?>
                        <span style="font-weight: 700; color: #1e293b;"><?php echo strtoupper(e($session['doctor_name'])); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="editor-wrapper">
                <label class="editor-label"><?php echo __('medical.session.assessment_label'); ?></label>
                <div id="assessment-editor" style="min-height: 260px; height: 260px; font-size: 0.95rem;"><?php echo $session['assessment']; ?></div>
                <input type="hidden" name="assessment" id="assessment-input">
            </div>

            <div class="editor-wrapper">
                <label class="editor-label"><?php echo __('medical.session.plan_label'); ?></label>
                <div id="plan-editor" style="min-height: 260px; height: 260px; font-size: 0.95rem;"><?php echo $session['treatment_plan']; ?></div>
                <input type="hidden" name="treatment_plan" id="plan-input">
            </div>

            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <?php if (!$is_locked): ?>
                    <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                        <i class="fas fa-save"></i> <?php echo $can_edit_summary ? __('medical.session.btn_update_all') : __('medical.session.btn_update_doc'); ?>
                    </button>
                    <?php if ($session['status'] !== 'completed' && $can_edit_summary): ?>
                        <button type="submit" name="complete" value="1" class="btn" style="width: 100%; justify-content: center; background: #10b981; color: white;">
                            <i class="fas fa-check-double"></i> <?php echo __('medical.session.btn_complete'); ?>
                        </button>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($session['status'] === 'completed' && has_role('admin')): ?>
                    <button type="submit" name="reopen" value="1" class="btn btn-outline" style="width: 100%; justify-content: center; border-color: #f59e0b; color: #b45309;">
                        <i class="fas fa-unlock"></i> <?php echo __('medical.session.btn_reopen'); ?>
                    </button>
                <?php endif; ?>

                <a href="../patients/view.php?id=<?php echo $session['patient_id']; ?>" class="btn" style="width: 100%; justify-content: center; background: #f1f5f9; color: var(--text-main);">
                    <?php echo __('medical.session.btn_back_patient'); ?>
                </a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var isSummaryLocked = <?php echo ($is_locked || !$can_edit_summary) ? 'true' : 'false'; ?>;
    var quillOptions = {
        theme: 'snow',
        readOnly: isSummaryLocked,
        modules: {
            toolbar: isSummaryLocked ? false : [['bold', 'italic', 'underline'], [{ 'list': 'ordered'}, { 'list': 'bullet' }], ['clean']]
        }
    };

    var assessmentEditor = new Quill('#assessment-editor', quillOptions);
    var planEditor = new Quill('#plan-editor', quillOptions);

    var form = document.getElementById('session-form');
    if (form) {
        form.onsubmit = function() {
            var assessmentInput = document.getElementById('assessment-input');
            if (assessmentInput) assessmentInput.value = assessmentEditor.root.innerHTML;

            var planInput = document.getElementById('plan-input');
            if (planInput) planInput.value = planEditor.root.innerHTML;
        };
    }
});

// Upload Functions
var uploadZone = document.getElementById('upload-zone');
if (uploadZone) {
    uploadZone.addEventListener('click', function() {
        document.getElementById('file-input').click();
    });
}

function handleDrop(e) {
    e.preventDefault();
    var zone = document.getElementById('upload-zone');
    zone.style.borderColor = '#cbd5e1';
    zone.style.background = '#fafbfc';
    uploadFiles(e.dataTransfer.files);
}

function uploadFiles(files) {
    if (!files.length) return;
    var fd = new FormData();
    fd.append('patient_id', '<?php echo $session['patient_id']; ?>');
    fd.append('session_id', '<?php echo $session_id; ?>');
    
    for (var i = 0; i < files.length; i++) {
        fd.append('images[]', files[i]);
    }
    
    var progress = document.getElementById('upload-progress');
    var bar = document.getElementById('progress-bar');
    var status = document.getElementById('upload-status');
    progress.style.display = 'block';
    bar.style.width = '30%';
    status.textContent = '<?php echo __('medical.session.uploading'); ?> ' + files.length + '...';
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/includes/upload_handler.php');
    
    xhr.upload.onprogress = function(e) {
        if (e.lengthComputable) {
            bar.style.width = Math.round(e.loaded / e.total * 90) + '%';
        }
    };
    
    xhr.onload = function() {
        bar.style.width = '100%';
        try {
            var res = JSON.parse(xhr.responseText);
            if (res.success) {
                status.textContent = '✅ <?php echo __('medical.session.upload_success'); ?> ' + res.files.length + '!';
                setTimeout(function() { location.reload(); }, 1000);
            } else {
                status.textContent = '❌ ' + (res.error || '<?php echo __('medical.session.err_unknown'); ?>');
            }
        } catch(e) {
            status.textContent = '❌ <?php echo __('medical.session.err_server'); ?>';
        }
    };
    
    xhr.onerror = function() {
        status.textContent = '❌ <?php echo __('medical.session.err_conn'); ?>';
    };
    
    xhr.send(fd);
}

function renameAttachment(event, recordId, path, oldName) {
    if(event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    var overlay = document.createElement('div');
    overlay.style.position = 'fixed';
    overlay.style.top = '0'; overlay.style.left = '0';
    overlay.style.width = '100vw'; overlay.style.height = '100vh';
    overlay.style.backgroundColor = 'rgba(15, 23, 42, 0.6)';
    overlay.style.backdropFilter = 'blur(4px)';
    overlay.style.display = 'flex'; overlay.style.alignItems = 'center'; overlay.style.justifyContent = 'center';
    overlay.style.zIndex = '9999';
    overlay.style.animation = 'fadeIn 0.2s ease-out';
    
    var box = document.createElement('div');
    box.style.background = 'white'; box.style.padding = '2rem';
    box.style.borderRadius = '16px'; box.style.textAlign = 'center';
    box.style.fontFamily = "'Inter', sans-serif";
    box.style.boxShadow = '0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)';
    box.style.transform = 'scale(0.95)';
    box.style.animation = 'popIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards';
    box.style.width = '100%';
    box.style.maxWidth = '400px';
    
    var icon = document.createElement('div');
    icon.innerHTML = '<i class="fas fa-pencil-alt" style="font-size: 2.5rem; color: #3b82f6; margin-bottom: 1rem;"></i>';
    
    var title = document.createElement('h3');
    title.textContent = 'Đổi tên hình ảnh';
    title.style.margin = '0 0 1rem 0'; title.style.color = '#1e293b'; title.style.fontSize = '1.25rem';
    
    var input = document.createElement('input');
    input.type = 'text';
    input.value = oldName;
    input.style.width = '100%';
    input.style.padding = '0.75rem';
    input.style.borderRadius = '8px';
    input.style.border = '1px solid #cbd5e1';
    input.style.marginBottom = '1.5rem';
    input.style.fontSize = '1rem';
    input.style.boxSizing = 'border-box';
    input.style.outline = 'none';
    input.placeholder = 'Nhập tên mô tả ảnh...';
    
    var btnWrapper = document.createElement('div');
    btnWrapper.style.display = 'flex'; btnWrapper.style.gap = '1rem'; btnWrapper.style.justifyContent = 'center';
    
    var btnCancel = document.createElement('button');
    btnCancel.innerHTML = '<i class="fas fa-times"></i> Hủy';
    btnCancel.style.padding = '0.6rem 1.25rem'; btnCancel.style.border = '1px solid #e2e8f0';
    btnCancel.style.background = 'white'; btnCancel.style.borderRadius = '8px';
    btnCancel.style.cursor = 'pointer'; btnCancel.style.fontWeight = '600'; btnCancel.style.color = '#475569';
    btnCancel.style.flex = '1';
    
    var btnOk = document.createElement('button');
    btnOk.innerHTML = '<i class="fas fa-save"></i> Lưu tên';
    btnOk.style.padding = '0.6rem 1.25rem'; btnOk.style.border = 'none';
    btnOk.style.background = '#3b82f6'; btnOk.style.borderRadius = '8px';
    btnOk.style.cursor = 'pointer'; btnOk.style.fontWeight = '600'; btnOk.style.color = 'white';
    btnOk.style.boxShadow = '0 4px 6px -1px rgba(59, 130, 246, 0.3)';
    btnOk.style.flex = '1';
    
    var addStyles = document.createElement('style');
    addStyles.textContent = "@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } } @keyframes popIn { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }";
    document.head.appendChild(addStyles);
    
    btnCancel.onclick = function() { document.body.removeChild(overlay); };
    
    btnOk.onclick = function() {
        var newName = input.value;
        if (newName !== null && newName.trim() !== '') {
            document.body.removeChild(overlay);
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '/includes/rename_attachment.php');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var res = JSON.parse(xhr.responseText);
                        if (res.success) {
                            location.reload();
                        } else {
                            alert('Lỗi: ' + (res.error || 'Không thể đổi tên'));
                        }
                    } catch(e) {
                        alert('Lỗi phản hồi máy chủ!');
                    }
                } else {
                    alert('Lỗi kết nối mạng!');
                }
            };
            
            xhr.send('record_id=' + encodeURIComponent(recordId) + '&path=' + encodeURIComponent(path) + '&new_name=' + encodeURIComponent(newName.trim()));
        } else {
            input.style.border = '2px solid #ef4444';
            input.focus();
        }
    };
    
    btnWrapper.appendChild(btnCancel);
    btnWrapper.appendChild(btnOk);
    box.appendChild(icon);
    box.appendChild(title);
    box.appendChild(input);
    box.appendChild(btnWrapper);
    overlay.appendChild(box);
    document.body.appendChild(overlay);
    
    setTimeout(() => { input.focus(); input.select(); }, 100);
}

function deleteAttachment(event, recordId, path) {
    if(event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    // Custom Modal Design
    var overlay = document.createElement('div');
    overlay.style.position = 'fixed';
    overlay.style.top = '0'; overlay.style.left = '0';
    overlay.style.width = '100vw'; overlay.style.height = '100vh';
    overlay.style.backgroundColor = 'rgba(15, 23, 42, 0.6)';
    overlay.style.backdropFilter = 'blur(4px)';
    overlay.style.display = 'flex'; overlay.style.alignItems = 'center'; overlay.style.justifyContent = 'center';
    overlay.style.zIndex = '9999';
    overlay.style.animation = 'fadeIn 0.2s ease-out';
    
    var box = document.createElement('div');
    box.style.background = 'white'; box.style.padding = '2rem';
    box.style.borderRadius = '16px'; box.style.textAlign = 'center';
    box.style.fontFamily = "'Inter', sans-serif";
    box.style.boxShadow = '0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)';
    box.style.transform = 'scale(0.95)';
    box.style.animation = 'popIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards';
    
    var icon = document.createElement('div');
    icon.innerHTML = '<i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: #f59e0b; margin-bottom: 1rem;"></i>';
    
    var title = document.createElement('h3');
    title.textContent = 'Trọng tài xoá ảnh?';
    title.style.margin = '0 0 0.5rem 0'; title.style.color = '#1e293b'; title.style.fontSize = '1.25rem';
    
    var desc = document.createElement('p');
    desc.textContent = 'Bạn có chắc chắn muốn xoá vĩnh viễn ảnh này?';
    desc.style.margin = '0 0 2rem 0'; desc.style.color = '#64748b'; desc.style.fontSize = '0.9rem';
    
    var btnWrapper = document.createElement('div');
    btnWrapper.style.display = 'flex'; btnWrapper.style.gap = '1rem'; btnWrapper.style.justifyContent = 'center';
    
    var btnCancel = document.createElement('button');
    btnCancel.innerHTML = '<i class="fas fa-times"></i> Hủy';
    btnCancel.style.padding = '0.6rem 1.25rem'; btnCancel.style.border = '1px solid #e2e8f0';
    btnCancel.style.background = 'white'; btnCancel.style.borderRadius = '8px';
    btnCancel.style.cursor = 'pointer'; btnCancel.style.fontWeight = '600'; btnCancel.style.color = '#475569';
    
    var btnOk = document.createElement('button');
    btnOk.innerHTML = '<i class="fas fa-trash-alt"></i> Xoá ngay';
    btnOk.style.padding = '0.6rem 1.25rem'; btnOk.style.border = 'none';
    btnOk.style.background = '#ef4444'; btnOk.style.borderRadius = '8px';
    btnOk.style.cursor = 'pointer'; btnOk.style.fontWeight = '600'; btnOk.style.color = 'white';
    btnOk.style.boxShadow = '0 4px 6px -1px rgba(239, 68, 68, 0.3)';
    
    var addStyles = document.createElement('style');
    addStyles.textContent = "@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } } @keyframes popIn { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }";
    document.head.appendChild(addStyles);
    
    btnCancel.onclick = function() { document.body.removeChild(overlay); };
    
    btnOk.onclick = function() {
        document.body.removeChild(overlay);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/includes/delete_attachment.php');
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        location.reload();
                    } else {
                        alert('Lỗi: ' + (res.error || 'Không thể xóa'));
                    }
                } catch(e) {
                    alert('Lỗi phản hồi máy chủ!');
                }
            } else {
                alert('Lỗi kết nối mạng!');
            }
        };
        
        xhr.send('record_id=' + encodeURIComponent(recordId) + '&path=' + encodeURIComponent(path));
    };
    
    btnWrapper.appendChild(btnCancel);
    btnWrapper.appendChild(btnOk);
    box.appendChild(icon);
    box.appendChild(title);
    box.appendChild(desc);
    box.appendChild(btnWrapper);
    overlay.appendChild(box);
    document.body.appendChild(overlay);
}

// Lightbox
function openLightbox(src) {
    var overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.85);z-index:9999;display:flex;align-items:center;justify-content:center;cursor:pointer;backdrop-filter:blur(5px)';
    overlay.onclick = function() { document.body.removeChild(overlay); };
    
    if (src.toLowerCase().endsWith('.pdf')) {
        var iframe = document.createElement('iframe');
        iframe.src = src;
        iframe.style.cssText = 'width:80%;height:90%;border-radius:12px;border:none';
        overlay.appendChild(iframe);
    } else {
        var img = document.createElement('img');
        img.src = src;
        img.style.cssText = 'max-width:90%;max-height:90%;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,0.5)';
        overlay.appendChild(img);
    }
    
    var closeBtn = document.createElement('div');
    closeBtn.innerHTML = '<i class="fas fa-times"></i>';
    closeBtn.style.cssText = 'position:absolute;top:1.5rem;right:1.5rem;width:40px;height:40px;background:rgba(255,255,255,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:1.2rem;cursor:pointer';
    overlay.appendChild(closeBtn);
    
    document.body.appendChild(overlay);
}
</script>

<?php require_once '../../templates/footer.php'; ?>
