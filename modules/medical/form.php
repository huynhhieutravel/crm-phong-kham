<?php
// modules/medical/form.php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
$type = $_GET['type'] ?? 'chiropractic';
$patient_id = $_GET['patient_id'] ?? 0;
$session_id = $_GET['session_id'] ?? null;

$page_title = ($type === 'dong_y' ? __('medical.form.dong_y_title') : __('medical.form.chiro_title'));
if ($type === 'initial_exam') $page_title = __('medical.form.initial_exam_title');
$current_page = 'medical';
$db = getDB();
$history_id = $_GET['id'] ?? 0;

// 1. Fetch Session Status for Locking
$is_locked = false;
if ($session_id) {
    $stmt = $db->prepare("SELECT status, session_date FROM medical_sessions WHERE id = ?");
    $stmt->execute([$session_id]);
    $session = $stmt->fetch();
    if ($session && $session['status'] === 'completed' && !has_role('admin')) {
        $is_locked = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($is_locked) {
        set_flash(__('medical.form.err_locked'), 'error');
        redirect("session_view.php?id=$session_id");
    }

    $history_data = json_encode($_POST['history'] ?? [], JSON_UNESCAPED_UNICODE);
    
    if ($history_id) {
        // Fetch old data for audit
        $stmt_old = $db->prepare("SELECT history_data FROM medical_history WHERE id = ?");
        $stmt_old->execute([$history_id]);
        $old_json = $stmt_old->fetchColumn();

        $stmt = $db->prepare("UPDATE medical_history SET history_data = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$history_data, $history_id]);

        log_audit($_SESSION['user_id'], 'update', 'medical_history', $history_id, json_decode($old_json, true), $_POST['history'] ?? []);
    } else {
        $stmt = $db->prepare("
            INSERT INTO medical_history (patient_id, session_id, type, history_data, created_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$patient_id, $session_id, $type, $history_data, $_SESSION['user_id']]);
        $new_id = $db->lastInsertId();

        log_audit($_SESSION['user_id'], 'create', 'medical_history', $new_id, null, $_POST['history'] ?? []);
    }
    
    set_flash(__('medical.form.msg_success'));
    
    if ($session_id) {
        redirect("session_view.php?id=$session_id");
    } else {
        redirect("../patients/view.php?id=$patient_id");
    }
}

require_once '../../templates/header.php';

// Initialize $data for pre-filling
$data = [];
if ($history_id) {
    $stmt = $db->prepare("SELECT history_data FROM medical_history WHERE id = ?");
    $stmt->execute([$history_id]);
    $record = $stmt->fetch();
    if ($record) {
        $data = json_decode($record['history_data'], true);
        if (!$data) $data = [];
    }
}
?>

<style>
    :root {
        --premium-shadow: 0 4px 20px -5px rgba(0, 0, 0, 0.05), 0 8px 32px -12px rgba(0, 0, 0, 0.08);
        --premium-border: 1px solid #eef2f6;
        --accent-heat: #ef4444;
        --accent-cold: #3b82f6;
    }

    .premium-card {
        background: white;
        padding: 1.5rem;
        border-radius: 20px;
        border: var(--premium-border);
        box-shadow: var(--premium-shadow);
        transition: all 0.3s ease;
    }
    .premium-card:hover {
        box-shadow: 0 12px 40px -10px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }

    .checkbox-tag {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        user-select: none;
    }
    .checkbox-tag:hover {
        border-color: var(--primary);
        background: white;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.08);
    }
    .checkbox-tag input {
        width: 1.1rem;
        height: 1.1rem;
        accent-color: var(--primary);
    }
    .checkbox-tag span {
        font-weight: 600;
        color: #475569;
        font-size: 0.9rem;
    }
    .checkbox-tag:has(input:checked) {
        background: white;
        border-color: var(--primary);
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.15);
    }
    .checkbox-tag:has(input:checked) span {
        color: var(--primary);
    }

    /* Circular Toggle Group */
    .toggle-group-premium {
        display: flex;
        gap: 0.5rem;
        background: #f1f5f9;
        padding: 0.25rem;
        border-radius: 50px;
        width: fit-content;
    }
    .toggle-item-premium {
        position: relative;
        cursor: pointer;
    }
    .toggle-item-premium input {
        position: absolute;
        opacity: 0;
        cursor: pointer;
    }
    .toggle-item-premium span {
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 32px;
        height: 32px;
        padding: 0 10px;
        border-radius: 50px;
        font-size: 0.8rem;
        font-weight: 800;
        color: #64748b;
        transition: all 0.2s ease;
        white-space: nowrap;
    }
    .toggle-item-premium input:checked + span {
        background: var(--primary);
        color: white;
        box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
    }

    .section-label-premium {
        font-size: 0.85rem;
        font-weight: 800;
        color: var(--primary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }

    .pulse-pair {
        background: #f8fafc;
        padding: 1.25rem;
        border-radius: 18px;
        border: 1px solid #eef2f6;
    }
    .medical-form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 0.75rem;
    }

    .checkbox-card {
        background: white;
        border: 2px solid #eef2f6;
        border-radius: 20px;
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1rem;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        text-align: center;
    }
    .checkbox-card i {
        font-size: 2rem;
        color: #94a3b8;
        transition: all 0.3s ease;
    }
    .checkbox-card:hover {
        border-color: var(--primary);
        transform: translateY(-3px);
    }
    .checkbox-card:has(input:checked) {
        border-color: var(--primary);
        background: rgba(99, 102, 241, 0.05);
        transform: translateY(-5px);
        box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.2);
    }
    .checkbox-card:has(input:checked) i {
        color: var(--primary);
    }
    .checkbox-card input {
        display: none;
    }
    .label-text {
        font-weight: 800;
        font-size: 0.85rem;
        color: #1e293b;
    }
</style>

<?php
$stmt = $db->prepare("SELECT full_name, birthday, occupation FROM patients WHERE id = ?");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch();
$patient_name = $patient['full_name'] ?? 'Bệnh nhân';
$patient_birthday = $patient['birthday'] ?? '';
$patient_occupation = $patient['occupation'] ?? '';
$patient_birth_year = $patient_birthday ? date('Y', strtotime($patient_birthday)) : '';

// Define questions for the forms with icons
$questions = [];
if ($type === 'chiropractic' || $type === 'initial_exam') {
    $questions = [
        'medical.form.sec_lifestyle' => [
            ['label' => 'Ngồi nhiều', 'key' => 'medical.form.opt_sitting', 'icon' => 'fa-chair'],
            ['label' => 'Đứng nhiều', 'key' => 'medical.form.opt_standing', 'icon' => 'fa-user-tie'],
            ['label' => 'Lao động nặng', 'key' => 'medical.form.opt_heavy_labor', 'icon' => 'fa-weight-hanging'],
            ['label' => 'Ngủ nghiêng', 'key' => 'medical.form.opt_side_sleeping', 'icon' => 'fa-bed'],
            ['label' => 'Tư thế sai', 'key' => 'medical.form.opt_poor_posture', 'icon' => 'fa-user-slash'],
            ['label' => 'Căng thẳng', 'key' => 'medical.form.opt_stress', 'icon' => 'fa-brain'],
            ['label' => 'Ít vận động', 'key' => 'medical.form.opt_sedentary', 'icon' => 'fa-walking']
        ],
        'medical.form.sec_current_status' => [
            ['label' => 'Đau nhói', 'key' => 'medical.form.opt_sharp_pain', 'icon' => 'fa-bolt'],
            ['label' => 'Đau âm ỉ', 'key' => 'medical.form.opt_dull_pain', 'icon' => 'fa-wave-square'],
            ['label' => 'Tê bì', 'key' => 'medical.form.opt_numbness', 'icon' => 'fa-hands'],
            ['label' => 'Yếu cơ', 'key' => 'medical.form.opt_weakness', 'icon' => 'fa-fist-raised'],
            ['label' => 'Hạn chế vận động', 'key' => 'medical.form.opt_limited_rom', 'icon' => 'fa-lock']
        ],
        'medical.form.sec_ortho_history' => [
            ['label' => 'Thoát vị đĩa đệm', 'key' => 'medical.form.opt_herniated_disc', 'icon' => 'fa-spine'],
            ['label' => 'Thoái hóa cột sống', 'key' => 'medical.form.opt_spinal_degen', 'icon' => 'fa-bone'],
            ['label' => 'Vẹo cột sống (Skoliose)', 'key' => 'medical.form.opt_scoliosis', 'icon' => 'fa-bezier-curve'],
            ['label' => 'Viêm khớp dạng thấp', 'key' => 'medical.form.opt_rheumatoid', 'icon' => 'fa-hand-dots'],
            ['label' => 'Loãng xương', 'key' => 'medical.form.opt_osteoporosis', 'icon' => 'fa-skeleton'],
            ['label' => 'Viêm cột sống dính khớp', 'key' => 'medical.form.opt_ankylosing', 'icon' => 'fa-link']
        ],
        'medical.form.sec_medical_history' => [
            ['label' => 'Đã từng phẫu thuật', 'key' => 'medical.form.opt_surgery', 'icon' => 'fa-procedures'],
            ['label' => 'Từng bị gãy xương', 'key' => 'medical.form.opt_fracture', 'icon' => 'fa-crutch'],
            ['label' => 'Đã có phim chụp X-Ray', 'key' => 'medical.form.opt_xray', 'icon' => 'fa-x-ray'],
            ['label' => 'Đã có phim MRI/CT', 'key' => 'medical.form.opt_mri_ct', 'icon' => 'fa-microscope'],
            ['label' => 'Rối loạn đông máu', 'key' => 'medical.form.opt_clotting_disorder', 'icon' => 'fa-droplet-slash'],
            ['label' => 'Huyết áp cao', 'key' => 'medical.form.opt_high_bp', 'icon' => 'fa-heart-pulse']
        ],
        'medical.form.sec_ros' => [
            ['label' => 'Đau đầu / Chóng mặt', 'key' => 'medical.form.opt_headache_dizzy', 'icon' => 'fa-head-side-virus'],
            ['label' => 'Ù tai', 'key' => 'medical.form.opt_tinnitus', 'icon' => 'fa-ear-listen'],
            ['label' => 'Tê lan xuống tay', 'key' => 'medical.form.opt_numb_arms', 'icon' => 'fa-hand-sparkles'],
            ['label' => 'Tê lan xuống chân', 'key' => 'medical.form.opt_numb_legs', 'icon' => 'fa-shoe-prints'],
            ['label' => 'Mất kiểm soát ruột/bàng quang', 'key' => 'medical.form.opt_bowel_control', 'icon' => 'fa-person-circle-exclamation']
        ],
        'medical.form.sec_goals' => [
            ['label' => 'Giảm đau nhanh', 'key' => 'medical.form.opt_fast_relief', 'icon' => 'fa-fire-extinguisher'],
            ['label' => 'Phục hồi vận động', 'key' => 'medical.form.opt_restore_rom', 'icon' => 'fa-running'],
            ['label' => 'Phòng ngừa lâu dài', 'key' => 'medical.form.opt_prevention', 'icon' => 'fa-shield-heart']
        ]
    ];
} else {
    $questions = [
        'medical.form.sec_general' => [
            ['label' => 'Ăn uống kém', 'key' => 'medical.form.opt_eating_poor', 'icon' => 'fa-utensils'],
            ['label' => 'Mệt mỏi', 'key' => 'medical.form.opt_fatigue', 'icon' => 'fa-tired'],
            ['label' => 'Hay ra mồ hôi', 'key' => 'medical.form.opt_sweating', 'icon' => 'fa-tint'],
            ['label' => 'Sợ lạnh', 'key' => 'medical.form.opt_fear_cold', 'icon' => 'fa-snowflake'],
            ['label' => 'Sợ nóng', 'key' => 'medical.form.opt_fear_heat', 'icon' => 'fa-fire'],
            ['label' => 'Cơ thể suy nhược', 'key' => 'medical.form.opt_body_weak', 'icon' => 'fa-battery-empty']
        ],
        'medical.form.sec_digestion' => [
            ['label' => 'Đầy hơi', 'key' => 'medical.form.opt_bloating', 'icon' => 'fa-wind'],
            ['label' => 'Táo bón', 'key' => 'medical.form.opt_constipation', 'icon' => 'fa-poop'],
            ['label' => 'Tiêu chảy', 'key' => 'medical.form.opt_diarrhea', 'icon' => 'fa-water'],
            ['label' => 'Đau dạ dày', 'key' => 'medical.form.opt_stomach_ache', 'icon' => 'fa-band-aid']
        ]
    ];
}

?>

<div class="card" style="background: var(--glass-bg); backdrop-filter: blur(20px);">
    <div style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 style="margin: 0; font-weight: 800; color: var(--primary);"><?php echo $page_title; ?></h2>
            <p style="color: var(--text-muted); margin-top: 0.25rem;"><?php echo __('medical.form.patient_label'); ?> <strong style="color: var(--text-main);"><?php echo e($patient_name); ?></strong></p>
        </div>
        <div style="background: rgba(99, 102, 241, 0.1); padding: 0.5rem 1.25rem; border-radius: 50px; color: var(--primary); font-weight: 700; font-size: 0.85rem;">
            <?php echo __('medical.form.type_' . $type); ?>
        </div>
    </div>

    <form method="POST">
        <?php if ($is_locked): ?>
            <div style="background: #fef2f2; color: #991b1b; padding: 1.25rem; border-radius: 20px; margin-bottom: 2rem; border: 1px solid #fecaca; display: flex; align-items: center; gap: 1rem; box-shadow: var(--premium-shadow);">
                <i class="fas fa-lock fa-2x"></i>
                <div>
                    <div style="font-weight: 800; font-size: 1rem;"><?php echo __('medical.form.locked_title'); ?></div>
                    <div style="font-size: 0.85rem; font-weight: 600; opacity: 0.9;"><?php echo __('medical.form.locked_desc'); ?></div>
                </div>
            </div>
        <?php endif; ?>

        <fieldset <?php echo $is_locked ? 'disabled' : ''; ?> style="border: none; padding: 0; margin: 0;">
        <?php if ($type === 'dong_y'): ?>
            <!-- I. THÔNG TIN CƠ BẢN & HUYẾT ÁP -->
            <div style="margin-bottom: 3rem; padding-bottom: 2rem; border-bottom: 2px solid #f1f5f9;">
                <h3 style="font-size: 1.1rem; text-transform: uppercase; color: var(--primary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-id-card"></i> <?php echo __('medical.form.part1_title'); ?>
                </h3>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 1.5rem;">
                    <div class="form-group">
                        <label class="form-label"><?php echo __('medical.form.fullname_label'); ?></label>
                        <input type="text" class="form-input" value="<?php echo e($patient_name); ?>" readonly style="background: #f8fafc;">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo __('medical.form.birth_year_label'); ?></label>
                        <input type="text" class="form-input" value="<?php echo e($patient_birth_year); ?>" readonly style="background: #f8fafc;">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo __('medical.form.occupation_label'); ?></label>
                        <input type="text" class="form-input" value="<?php echo e($patient_occupation); ?>" readonly style="background: #f8fafc;">
                    </div>
                </div>
                <!-- Hết phần thông tin cơ bản -->

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 1.5rem;">
                    <div class="pulse-pair">
                        <span style="font-size: 0.85rem; font-weight: 800; color: var(--primary); display: block; margin-bottom: 1rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.5rem;"><?php echo __('medical.form.bp_left_title'); ?></span>
                        <div style="display: flex; gap: 1rem;">
                            <div style="flex: 1;">
                                <label style="font-size: 0.7rem; color: var(--text-muted); display: block; margin-bottom: 0.25rem;"><?php echo __('medical.form.bp_index_label'); ?></label>
                                <input type="text" name="history[bp_left]" class="form-input" value="<?php echo e($data['bp_left'] ?? ''); ?>" placeholder="<?php echo __('medical.form.bp_placeholder'); ?>">
                            </div>
                            <div style="flex: 1;">
                                <label style="font-size: 0.7rem; color: var(--text-muted); display: block; margin-bottom: 0.25rem;"><?php echo __('medical.form.heart_rate_label'); ?></label>
                                <input type="text" name="history[hr_left]" class="form-input" value="<?php echo e($data['hr_left'] ?? ''); ?>" placeholder="<?php echo __('medical.form.hr_placeholder'); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="pulse-pair">
                        <span style="font-size: 0.85rem; font-weight: 800; color: var(--primary); display: block; margin-bottom: 1rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.5rem;"><?php echo __('medical.form.bp_right_title'); ?></span>
                        <div style="display: flex; gap: 1rem;">
                            <div style="flex: 1;">
                                <label style="font-size: 0.7rem; color: var(--text-muted); display: block; margin-bottom: 0.25rem;"><?php echo __('medical.form.bp_index_label'); ?></label>
                                <input type="text" name="history[bp_right]" class="form-input" value="<?php echo e($data['bp_right'] ?? ''); ?>" placeholder="<?php echo __('medical.form.bp_placeholder'); ?>">
                            </div>
                            <div style="flex: 1;">
                                <label style="font-size: 0.7rem; color: var(--text-muted); display: block; margin-bottom: 0.25rem;"><?php echo __('medical.form.heart_rate_label'); ?></label>
                                <input type="text" name="history[hr_right]" class="form-input" value="<?php echo e($data['hr_right'] ?? ''); ?>" placeholder="<?php echo __('medical.form.hr_placeholder'); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"><?php echo __('medical.form.reason_label'); ?></label>
                    <textarea name="history[reason]" class="form-input" rows="2" placeholder="<?php echo __('medical.form.reason_placeholder'); ?>"><?php echo e($data['reason'] ?? ''); ?></textarea>
                </div>
            </div>

            <!-- II. VỌNG CHẨN (Nhìn) -->
            <div style="margin-bottom: 3rem; padding-bottom: 2rem; border-bottom: 2px solid #f1f5f9;">
                <h3 style="font-size: 1.1rem; text-transform: uppercase; color: var(--primary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-eye"></i> <?php echo __('medical.form.part2_title'); ?>
                </h3>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                    <div class="form-group">
                        <label class="form-label"><?php echo __('medical.form.spirit_label'); ?></label>
                        <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
                            <?php 
                            $spirit_opts = [
                                'medical.dong_y.spirit_good' => 'Còn thần (Tươi nhuận)',
                                'medical.dong_y.spirit_lost' => 'Thất thần (Mệt mỏi, lờ đờ)'
                            ];
                            foreach ($spirit_opts as $k => $v): ?>
                                <label class="checkbox-tag">
                                    <input type="radio" name="history[spirit]" value="<?php echo $v; ?>" <?php echo ($data['spirit'] ?? '') == $v ? 'checked' : ''; ?>>
                                    <span><?php echo __($k); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <span style="font-size: 0.85rem; opacity: 0.7; display: block; margin-bottom: 0.5rem;"><?php echo __('medical.form.face_color_label'); ?></span>
                        <div class="medical-form-grid" style="grid-template-columns: repeat(4, 1fr);">
                            <?php 
                            $face_opts = [
                                'medical.dong_y.face_white' => 'Trắng bệch',
                                'medical.dong_y.face_yellow' => 'Vàng vọt',
                                'medical.dong_y.face_red' => 'Đỏ gay',
                                'medical.dong_y.face_dark' => 'Sạm đen'
                            ];
                            foreach ($face_opts as $k => $v): ?>
                                <label class="checkbox-tag">
                                    <input type="radio" name="history[face_color]" value="<?php echo $v; ?>" <?php echo ($data['face_color'] ?? '') == $v ? 'checked' : ''; ?>>
                                    <span><?php echo __($k); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div style="background: #fff; padding: 1.5rem; border-radius: 16px; border: 1px solid #e2e8f0;">
                        <span class="form-label" style="color: #6366f1; font-size: 0.9rem; margin-bottom: 1rem; display: block;">
                            <i class="fas fa-tongue"></i> <?php echo __('medical.form.tongue_title'); ?>
                        </span>
                        
                        <!-- 1. Chất lưỡi -->
                        <div style="margin-bottom: 1.25rem;">
                            <div style="font-size: 0.85rem; font-weight: 700; color: #475569; margin-bottom: 0.75rem;"><?php echo __('medical.form.tongue_body_label'); ?></div>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 0.75rem;">
                                <?php 
                                $tongue_body_opts = [
                                    'medical.dong_y.tongue_body_pink' => 'Hồng đều',
                                    'medical.dong_y.tongue_body_dark_red' => 'Đỏ sẫm',
                                    'medical.dong_y.tongue_body_pale_blue' => 'Tím tái',
                                    'medical.dong_y.tongue_body_spots' => 'Có điểm ứ huyết'
                                ];
                                foreach ($tongue_body_opts as $k => $v): ?>
                                    <label class="checkbox-tag">
                                        <input type="radio" name="history[tongue_body]" value="<?php echo $v; ?>" <?php echo ($data['tongue_body'] ?? '') == $v ? 'checked' : ''; ?>>
                                        <span><?php echo __($k); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- 2. Hình dáng -->
                        <div style="margin-bottom: 1.25rem;">
                            <div style="font-size: 0.85rem; font-weight: 700; color: #475569; margin-bottom: 0.75rem;"><?php echo __('medical.form.tongue_shape_label'); ?></div>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem;">
                                <?php 
                                $tongue_shape_opts = [
                                    'medical.dong_y.tongue_shape_slim' => 'Thon gọn',
                                    'medical.dong_y.tongue_shape_swollen' => 'Bệu béo (có vết răng)',
                                    'medical.dong_y.tongue_shape_cracked' => 'Nứt ngang/dọc'
                                ];
                                foreach ($tongue_shape_opts as $k => $v): ?>
                                    <label class="checkbox-tag">
                                        <input type="radio" name="history[tongue_shape]" value="<?php echo $v; ?>" <?php echo ($data['tongue_shape'] ?? '') == $v ? 'checked' : ''; ?>>
                                        <span><?php echo __($k); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- 3. Rêu lưỡi -->
                        <div style="margin-bottom: 1.25rem;">
                            <div style="font-size: 0.85rem; font-weight: 700; color: #475569; margin-bottom: 0.75rem;"><?php echo __('medical.form.tongue_coating_label'); ?></div>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 0.75rem;">
                                <?php 
                                $tongue_coat_opts = [
                                    'medical.dong_y.tongue_coating_white_thin' => 'Trắng mỏng',
                                    'medical.dong_y.tongue_coating_white_thick' => 'Trắng dày',
                                    'medical.dong_y.tongue_coating_yellow_thin' => 'Vàng mỏng',
                                    'medical.dong_y.tongue_coating_yellow_thick' => 'Vàng dày',
                                    'medical.dong_y.tongue_coating_sticky' => 'Nhớt/Dính'
                                ];
                                foreach ($tongue_coat_opts as $k => $v): ?>
                                    <label class="checkbox-tag">
                                        <input type="checkbox" name="history[tongue_coating][]" value="<?php echo $v; ?>" <?php echo in_array($v, $data['tongue_coating'] ?? []) ? 'checked' : ''; ?>>
                                        <span><?php echo __($k); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- 4. Đầu lưỡi -->
                        <div>
                            <div style="font-size: 0.85rem; font-weight: 700; color: #475569; margin-bottom: 0.75rem;"><?php echo __('medical.form.tongue_tip_label'); ?></div>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 0.75rem;">
                                <?php 
                                $tongue_tip_opts = [
                                    'medical.dong_y.tongue_tip_pink' => 'Hồng',
                                    'medical.dong_y.tongue_tip_red' => 'Đỏ',
                                    'medical.dong_y.tongue_tip_pale' => 'Nhạt'
                                ];
                                foreach ($tongue_tip_opts as $k => $v): ?>
                                    <label class="checkbox-tag">
                                        <input type="radio" name="history[tongue_tip]" value="<?php echo $v; ?>" <?php echo ($data['tongue_tip'] ?? '') == $v ? 'checked' : ''; ?>>
                                        <span><?php echo __($k); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                    <div class="premium-card">
                        <label class="section-label-premium"><i class="fas fa-eye"></i> <?php echo __('medical.form.eyes_label'); ?></label>
                        <div class="medical-form-grid" style="grid-template-columns: 1fr; gap: 0.75rem;">
                            <?php 
                            $eyes_opts = [
                                'medical.dong_y.eyes_red' => 'Lòng trắng đỏ (Can hỏa)',
                                'medical.dong_y.eyes_circles' => 'Quầng thâm mắt (Thận hư)',
                                'medical.dong_y.eyes_swollen' => 'Mắt sưng nề (Tỳ thấp)'
                            ];
                            foreach ($eyes_opts as $k => $v): ?>
                                <label class="checkbox-tag" style="width: 100%;">
                                    <input type="checkbox" name="history[eyes][]" value="<?php echo $v; ?>" <?php echo in_array($v, $data['eyes'] ?? []) ? 'checked' : ''; ?>>
                                    <span><?php echo __($k); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px dashed #e2e8f0;">
                            <span style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.5rem; display: block;"><?php echo __('medical.form.eyelids_label'); ?></span>
                            <div class="medical-form-grid" style="grid-template-columns: 1fr; gap: 0.5rem;">
                                <?php 
                                $eyelid_opts = [
                                    'medical.dong_y.eyelids_pink' => 'Hồng đều',
                                    'medical.dong_y.eyelids_pale_pink' => 'Trong nhạt ngoài hồng',
                                    'medical.dong_y.eyelids_pale_red' => 'Trong nhạt ngoài đỏ',
                                    'medical.dong_y.eyelids_all_red' => 'Đỏ toàn bộ'
                                ];
                                foreach ($eyelid_opts as $k => $v): ?>
                                    <label class="checkbox-tag" style="width: 100%;">
                                        <input type="radio" name="history[eyelids]" value="<?php echo $v; ?>" <?php echo ($data['eyelids'] ?? '') == $v ? 'checked' : ''; ?>>
                                        <span><?php echo __($k); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="premium-card">
                        <label class="section-label-premium"><i class="fas fa-lips"></i> <?php echo __('medical.form.lips_label'); ?></label>
                        <div class="medical-form-grid" style="grid-template-columns: 1fr; gap: 0.5rem;">
                            <?php 
                            $lip_opts = [
                                'medical.dong_y.lips_pink' => 'Hồng tươi',
                                'medical.dong_y.lips_yellowish' => 'Ẩn vàng',
                                'medical.dong_y.lips_brownish' => 'Ẩn nâu',
                                'medical.dong_y.lips_veins' => 'Có tia máu',
                                'medical.dong_y.lips_cyanotic' => 'Ẩn xanh tím tái',
                                'medical.dong_y.lips_pale' => 'Nhạt'
                            ];
                            foreach ($lip_opts as $k => $v): ?>
                                <label class="checkbox-tag" style="width: 100%;">
                                    <input type="radio" name="history[lips]" value="<?php echo $v; ?>" <?php echo ($data['lips'] ?? '') == $v ? 'checked' : ''; ?>>
                                    <span><?php echo __($k); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- III. VĂN CHẨN (Nghe) -->
            <div style="margin-bottom: 3rem;">
                <h3 style="font-size: 1.1rem; text-transform: uppercase; color: var(--primary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-volume-up"></i> <?php echo __('medical.form.part3_title'); ?>
                </h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div class="premium-card">
                        <label class="section-label-premium"><i class="fas fa-comment-medical"></i> <?php echo __('medical.form.voice_breath_label'); ?></label>
                        <div class="medical-form-grid" style="grid-template-columns: 1fr;">
                            <?php 
                            $voice_opts = [
                                'medical.dong_y.voice_loud' => 'Tiếng nói to, vang (Thực)',
                                'medical.dong_y.voice_weak' => 'Tiếng nói nhỏ, thào thào (Hư)',
                                'medical.dong_y.voice_short_breath' => 'Hơi thở ngắn (Đoản hơi)',
                                'medical.dong_y.voice_fast' => 'Nhanh',
                                'medical.dong_y.voice_slow' => 'Chậm',
                                'medical.dong_y.voice_wheezing' => 'Khò khè / Có đờm'
                            ];
                            foreach ($voice_opts as $k => $v): ?>
                                <label class="checkbox-tag">
                                    <input type="checkbox" name="history[voice_breath][]" value="<?php echo $v; ?>" <?php echo in_array($v, $data['voice_breath'] ?? []) ? 'checked' : ''; ?>>
                                    <span><?php echo __($k); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="premium-card">
                        <label class="section-label-premium"><i class="fas fa-wind"></i> <?php echo __('medical.form.body_odor_label'); ?></label>
                        <div class="medical-form-grid" style="grid-template-columns: 1fr;">
                            <?php 
                            $odor_opts = [
                                'medical.dong_y.odor_breath_bad' => 'Hơi thở hôi (Vị nhiệt)',
                                'medical.dong_y.odor_body_sharp' => 'Cơ thể có mùi hăng/chua'
                            ];
                            foreach ($odor_opts as $k => $v): ?>
                                <label class="checkbox-tag">
                                    <input type="checkbox" name="history[body_odor][]" value="<?php echo $v; ?>" <?php echo in_array($v, $data['body_odor'] ?? []) ? 'checked' : ''; ?>>
                                    <span><?php echo __($k); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- IV. VẤN CHẨN (Hỏi) -->
            <div style="margin-bottom: 3rem;">
                <h3 style="font-size: 1.1rem; text-transform: uppercase; color: var(--primary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-comments"></i> <?php echo __('medical.form.part4_title'); ?>
                </h3>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                    <div class="premium-card">
                        <label class="section-label-premium"><i class="fas fa-history"></i> <?php echo __('medical.form.history_gyn_label'); ?></label>
                        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                            <?php 
                            $birth_opts = [
                                'medical.dong_y.birth_natural' => 'Sinh thường',
                                'medical.dong_y.birth_c_section' => 'Sinh mổ',
                                'medical.dong_y.birth_surgery' => 'Phẫu thuật khác'
                            ];
                            foreach ($birth_opts as $k => $v): ?>
                                <label class="checkbox-tag">
                                    <input type="radio" name="history[lifestyle_history]" value="<?php echo $v; ?>" <?php echo ($data['lifestyle_history'] ?? '') == $v ? 'checked' : ''; ?>>
                                    <span><?php echo __($k); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="premium-card">
                        <label class="section-label-premium"><i class="fas fa-bed"></i> <?php echo __('medical.form.sleep_label'); ?></label>
                        <div class="medical-form-grid" style="grid-template-columns: 1fr 1fr;">
                            <?php 
                            $sleep_opts = [
                                'medical.dong_y.sleep_easy' => 'Dễ',
                                'medical.dong_y.sleep_hard' => 'Khó',
                                'medical.dong_y.sleep_continuous' => 'Thẳng giấc',
                                'medical.dong_y.sleep_interrupted' => 'Trở giấc',
                                'medical.dong_y.sleep_dreamy' => 'Hay mơ (Mộng mị)',
                                'medical.dong_y.sleep_sweat' => 'Đạo hãn (Mồ hôi trộm)',
                                'medical.dong_y.sleep_enough' => 'Đủ giờ',
                                'medical.dong_y.sleep_lack' => 'Thiếu giờ'
                            ];
                            foreach ($sleep_opts as $k => $v): ?>
                                <label class="checkbox-tag">
                                    <input type="checkbox" name="history[sleep_quality][]" value="<?php echo $v; ?>" <?php echo in_array($v, $data['sleep_quality'] ?? []) ? 'checked' : ''; ?>>
                                    <span><?php echo __($k); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="premium-card">
                        <label class="section-label-premium"><i class="fas fa-sun"></i> <?php echo __('medical.form.wake_label'); ?></label>
                        <div style="display: flex; gap: 1rem;">
                            <?php 
                            $wake_opts = [
                                'medical.dong_y.wake_alert' => 'Tỉnh táo',
                                'medical.dong_y.wake_drowsy' => 'Lờ đờ'
                            ];
                            foreach($wake_opts as $k => $v): ?>
                                <label class="checkbox-tag">
                                    <input type="radio" value="<?php echo $v; ?>" name="history[wake_up_state]" <?php echo ($data['wake_up_state'] ?? '') == $v ? 'checked' : ''; ?>>
                                    <span><?php echo __($k); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="premium-card">
                        <label class="section-label-premium"><i class="fas fa-clock"></i> <?php echo __('medical.form.wake_time_label'); ?></label>
                        <div class="medical-form-grid" style="grid-template-columns: 1fr; gap: 0.5rem;">
                            <?php 
                            $wake_time_opts = [
                                'medical.dong_y.wake_21_23' => '21h-23h: Khó vào giấc (Tam Tiêu)',
                                'medical.dong_y.wake_23_01' => '23h-01h: Hay giật mình, lo sợ (Đởm)',
                                'medical.dong_y.wake_01_03' => '01h-03h: Tỉnh giấc bứt rứt, nóng nảy (Can)',
                                'medical.dong_y.wake_03_05' => '03h-05h: Tỉnh giấc kèm ho, buồn rầu (Phế)',
                                'medical.dong_y.wake_05_07' => '05h-07h: Tỉnh giấc đi ngoài ngay (Đại Trường)'
                            ];
                            foreach ($wake_time_opts as $k => $v): ?>
                                <label class="checkbox-tag" style="width: 100%;">
                                    <input type="checkbox" name="history[night_wake_times][]" value="<?php echo $v; ?>" <?php echo in_array($v, $data['night_wake_times'] ?? []) ? 'checked' : ''; ?>>
                                    <span><?php echo __($k); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- PHẦN IV: VẤN CHẨN - Thói Quen (Layout Image 4) -->
                <div class="premium-card" style="margin-bottom: 2.5rem;">
                    <label class="section-label-premium"><i class="fas fa-user-clock"></i> <?php echo __('medical.form.habits_env_posture_label'); ?></label>
                    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                        <!-- Row 1 -->
                        <div>
                            <span style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.75rem; display: block;"><?php echo __('medical.form.lifestyle_label'); ?></span>
                            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                                <?php 
                                $habit_opts = [
                                    'medical.dong_y.habit_night_eat' => 'Ăn đêm sau 20h',
                                    'medical.dong_y.habit_late_bath' => 'Tắm sau 19h',
                                    'medical.dong_y.habit_cold_water' => 'Uống nước đá lạnh',
                                    'medical.dong_y.habit_ac_below_25' => 'Dùng điều hòa nhiệt độ dưới 25 độ',
                                    'medical.dong_y.habit_stress' => 'Stress'
                                ];
                                foreach ($habit_opts as $k => $v): ?>
                                    <label class="checkbox-tag">
                                        <input type="checkbox" name="history[habits][]" value="<?php echo $v; ?>" <?php echo in_array($v, ($data['habits'] ?? [])) ? 'checked' : ''; ?>>
                                        <span><?php echo __($k); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Row 2 -->
                        <div style="padding-top: 1.25rem; border-top: 1px dashed #e2e8f0;">
                            <span style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.75rem; display: block;"><?php echo __('medical.form.before_sleep_label'); ?></span>
                            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                                <?php 
                                $sleep_habit_opts = [
                                    'medical.dong_y.habit_devices' => 'Sử dụng thiết bị điện tử sát giờ ngủ',
                                    'medical.dong_y.habit_late_sleep' => 'Ngủ sau 23h'
                                ];
                                foreach ($sleep_habit_opts as $k => $v): ?>
                                    <label class="checkbox-tag">
                                        <input type="checkbox" name="history[habits][]" value="<?php echo $v; ?>" <?php echo in_array($v, ($data['habits'] ?? [])) ? 'checked' : ''; ?>>
                                        <span><?php echo __($k); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Row 3 & 4 Grid -->
                        <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 2rem; padding-top: 1.25rem; border-top: 1px dashed #e2e8f0;">
                            <div>
                                <span style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.75rem; display: block;"><?php echo __('medical.form.work_posture_label'); ?></span>
                                <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                                    <?php 
                                    $work_opts = [
                                        'medical.dong_y.work_stand' => 'Đứng nhiều',
                                        'medical.dong_y.work_sit' => 'Ngồi nhiều',
                                        'medical.dong_y.work_move' => 'Đi nhiều'
                                    ];
                                    foreach ($work_opts as $k => $v): ?>
                                        <label class="checkbox-tag">
                                            <input type="radio" value="<?php echo $v; ?>" name="history[work_posture]" <?php echo ($data['work_posture'] ?? '') == $v ? 'checked' : ''; ?>>
                                            <span><?php echo __($k); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div>
                                <span style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.75rem; display: block;"><?php echo __('medical.form.living_env_label'); ?></span>
                                <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                                    <?php 
                                    $living_opts = [
                                        'medical.dong_y.living_normal' => 'Bình thường',
                                        'medical.dong_y.living_humid' => 'Ẩm ướt'
                                    ];
                                    foreach ($living_opts as $k => $v): ?>
                                        <label class="checkbox-tag">
                                            <input type="radio" value="<?php echo $v; ?>" name="history[living_env]" <?php echo ($data['living_env'] ?? '') == $v ? 'checked' : ''; ?>>
                                            <span><?php echo __($k); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PHẦN V: TIÊU HÓA & BÀI TIẾT (Premium Redesign) -->
                <div class="premium-card" style="margin-bottom: 2.5rem;">
                    <label class="section-label-premium"><i class="fas fa-utensils"></i> <?php echo __('medical.form.part5_title'); ?></label>
                    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                            <!-- Ăn uống -->
                            <div style="background: #f8fafc; padding: 1.25rem; border-radius: 16px; border: 1px solid #eef2f6;">
                                <span style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 1rem; display: block;"><?php echo __('medical.form.digestion_eating_label'); ?></span>
                                <div class="medical-form-grid" style="grid-template-columns: 1fr 1fr;">
                                    <?php 
                                    $eat_opts = [
                                        'medical.dong_y.eat_good' => 'Ngon miệng',
                                        'medical.dong_y.eat_prefer_cold' => 'Thích đồ mát',
                                        'medical.dong_y.eat_prefer_hot' => 'Thích đồ nóng',
                                        'medical.dong_y.eat_loss_appetite' => 'Sợ ăn/Chán ăn'
                                    ];
                                    foreach ($eat_opts as $k => $v): ?>
                                        <label class="checkbox-tag">
                                            <input type="checkbox" name="history[digestion_eating][]" value="<?php echo $v; ?>" <?php echo in_array($v, $data['digestion_eating'] ?? []) ? 'checked' : ''; ?>>
                                            <span><?php echo __($k); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Đại tiện -->
                            <div style="background: #f8fafc; padding: 1.25rem; border-radius: 16px; border: 1px solid #eef2f6;">
                                <span style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 1rem; display: block;"><?php echo __('medical.form.digestion_excretion_label'); ?></span>
                                <div class="medical-form-grid" style="grid-template-columns: 1fr 1fr;">
                                    <?php 
                                    $excrete_opts = [
                                        'medical.dong_y.excre_constipation' => 'Táo bón',
                                        'medical.dong_y.excre_loose' => 'Sống phân/Nát',
                                        'medical.dong_y.excre_diarrhea' => 'Tiêu chảy',
                                        'medical.dong_y.excre_normal' => 'Bình thường'
                                    ];
                                    foreach ($excrete_opts as $k => $v): ?>
                                        <label class="checkbox-tag">
                                            <input type="checkbox" name="history[digestion_excretion][]" value="<?php echo $v; ?>" <?php echo in_array($v, $data['digestion_excretion'] ?? []) ? 'checked' : ''; ?>>
                                            <span><?php echo __($k); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Frequency Rows -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; padding-top: 1.25rem; border-top: 1px dashed #e2e8f0;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <span style="font-size: 0.85rem; font-weight: 700; color: #475569;"><?php echo __('medical.form.excretion_frequency_label'); ?></span>
                                <div class="toggle-group-premium">
                                    <?php 
                                    $freq_opts = [
                                        'medical.dong_y.freq_1' => '1',
                                        'medical.dong_y.freq_2' => '2',
                                        'medical.dong_y.freq_3' => '3',
                                        'medical.dong_y.freq_more' => 'Nhiều hơn'
                                    ];
                                    foreach ($freq_opts as $k => $v): ?>
                                        <label class="toggle-item-premium">
                                            <input type="radio" value="<?php echo $v; ?>" name="history[excretion_frequency]" <?php echo ($data['excretion_frequency'] ?? '') == $v ? 'checked' : ''; ?>>
                                            <span><?php echo __($k); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <span style="font-size: 0.85rem; font-weight: 700; color: #475569;"><?php echo __('medical.form.night_urine_label'); ?></span>
                                <div class="toggle-group-premium">
                                    <?php 
                                    foreach ($freq_opts as $k => $v): ?>
                                        <label class="toggle-item-premium">
                                            <input type="radio" value="<?php echo $v; ?>" name="history[night_urine_count]" <?php echo ($data['night_urine_count'] ?? '') == $v ? 'checked' : ''; ?>>
                                            <span><?php echo __($k); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Urine Color -->
                        <div style="padding-top: 1.25rem; border-top: 1px dashed #e2e8f0;">
                            <span style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.75rem; display: block;"><?php echo __('medical.form.urine_color_label'); ?></span>
                            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                                <?php 
                                $urine_opts = [
                                    'medical.dong_y.urine_yellowish' => 'Hơi vàng',
                                    'medical.dong_y.urine_clear' => 'Trắng, trong',
                                    'medical.dong_y.urine_dark_yellow' => 'Vàng sẫm',
                                    'medical.dong_y.urine_cloudy' => 'Đục',
                                    'medical.dong_y.urine_painful' => 'Đau, xót'
                                ];
                                foreach ($urine_opts as $k => $v): ?>
                                    <label class="checkbox-tag">
                                        <input type="checkbox" name="history[urine_color][]" value="<?php echo $v; ?>" <?php echo in_array($v, $data['urine_color'] ?? []) ? 'checked' : ''; ?>>
                                        <span><?php echo __($k); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                </div>

                <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 1.5rem; margin-bottom: 2.5rem;">
                    <div class="premium-card">
                        <label class="section-label-premium"><i class="fas fa-venus text-pink-500"></i> <?php echo __('medical.form.menses_label'); ?></label>
                        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 1.25rem;">
                            <?php 
                            $mense_reg_opts = [
                                'medical.dong_y.menses_regular' => 'Đều',
                                'medical.dong_y.menses_irregular' => 'Không đều'
                            ];
                            foreach ($mense_reg_opts as $k => $v): ?>
                                <label class="checkbox-tag">
                                    <input type="radio" name="history[menses_regularity]" value="<?php echo $v; ?>" <?php echo ($data['menses_regularity'] ?? '') == $v ? 'checked' : ''; ?>>
                                    <span><?php echo __($k); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-bottom: 1.25rem;">
                            <input type="text" name="history[menses_days]" class="form-input" value="<?php echo e($data['menses_days'] ?? ''); ?>" placeholder="<?php echo __('medical.form.menses_days_placeholder'); ?>" style="width: 100%; height: 42px; border-radius: 12px; border: 1px solid #e2e8f0; padding: 0 1rem; font-weight: 600;">
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                            <div>
                                <span style="font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 0.5rem;"><?php echo __('medical.form.menses_pain_label'); ?></span>
                                <div style="display: flex; gap: 0.5rem;">
                                    <?php 
                                    $yes_no_opts = [
                                        'medical.dong_y.yes' => 'Có',
                                        'medical.dong_y.no' => 'Không'
                                    ];
                                    foreach ($yes_no_opts as $k => $v): ?>
                                        <label class="checkbox-tag" style="padding: 0.4rem 0.75rem;">
                                            <input type="radio" name="history[menses_pain]" value="<?php echo $v; ?>" <?php echo ($data['menses_pain'] ?? '') == $v ? 'checked' : ''; ?>>
                                            <span><?php echo __($k); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div>
                                <span style="font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 0.5rem;"><?php echo __('medical.form.menses_leucorrhoea_label'); ?></span>
                                <div style="display: flex; gap: 0.5rem;">
                                    <?php 
                                    foreach ($yes_no_opts as $k => $v): ?>
                                        <label class="checkbox-tag" style="padding: 0.4rem 0.75rem;">
                                            <input type="radio" name="history[menses_leucorrhoea]" value="<?php echo $v; ?>" <?php echo ($data['menses_leucorrhoea'] ?? '') == $v ? 'checked' : ''; ?>>
                                            <span><?php echo __($k); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div>
                            <span style="font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 0.5rem;"><?php echo __('medical.form.menses_color_label'); ?></span>
                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                <?php 
                                $mense_color_opts = [
                                    'medical.dong_y.menses_color_bright' => 'Đỏ tươi',
                                    'medical.dong_y.menses_color_clots' => 'Có cục / Thẫm màu'
                                ];
                                foreach ($mense_color_opts as $k => $v): ?>
                                    <label class="checkbox-tag" style="padding: 0.4rem 0.75rem;">
                                        <input type="radio" name="history[menses_color]" value="<?php echo $v; ?>" <?php echo ($data['menses_color'] ?? '') == $v ? 'checked' : ''; ?>>
                                        <span><?php echo __($k); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="premium-card">
                        <label class="section-label-premium"><i class="fas fa-thermometer-half"></i> <?php echo __('medical.form.sensation_label'); ?></label>
                        <div style="margin-bottom: 1.25rem; padding: 1.25rem; background: #fff1f2; border-radius: 16px; border: 1px solid #fecaca;">
                            <span style="font-size: 0.75rem; font-weight: 800; color: #dc2626; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.75rem;">
                                <i class="fas fa-fire"></i> <?php echo __('medical.form.heat_label'); ?>
                            </span>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                                <?php 
                                $heat_sens_opts = [
                                    'medical.dong_y.sens_pain' => 'Đau',
                                    'medical.dong_y.sens_itchy' => 'Ngứa',
                                    'medical.dong_y.sens_tired' => 'Mỏi',
                                    'medical.dong_y.sens_hot' => 'Nóng'
                                ];
                                foreach ($heat_sens_opts as $k => $v): ?>
                                    <label class="checkbox-tag" style="padding: 0.5rem; background: white; border-color: #fecaca;">
                                        <input type="checkbox" name="history[sensation_heat][]" value="<?php echo $v; ?>" <?php echo in_array($v, $data['sensation_heat'] ?? []) ? 'checked' : ''; ?>>
                                        <span><?php echo __($k); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div style="padding: 1.25rem; background: #eff6ff; border-radius: 16px; border: 1px solid #bfdbfe;">
                            <span style="font-size: 0.75rem; font-weight: 800; color: #2563eb; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.75rem;">
                                <i class="fas fa-snowflake"></i> <?php echo __('medical.form.cold_label'); ?>
                            </span>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                                <?php 
                                $cold_sens_opts = [
                                    'medical.dong_y.sens_ache' => 'Nhức',
                                    'medical.dong_y.sens_numb' => 'Tê',
                                    'medical.dong_y.sens_heavy' => 'Nặng nề',
                                    'medical.dong_y.sens_cold' => 'Lạnh'
                                ];
                                foreach ($cold_sens_opts as $k => $v): ?>
                                    <label class="checkbox-tag" style="padding: 0.5rem; background: white; border-color: #bfdbfe;">
                                        <input type="checkbox" name="history[sensation_cold][]" value="<?php echo $v; ?>" <?php echo in_array($v, $data['sensation_cold'] ?? []) ? 'checked' : ''; ?>>
                                        <span><?php echo __($k); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

                <!-- V. THIẾT CHẨN (Bắt mạch & Sờ nắn) -->
                <div style="margin-bottom: 3.5rem;">
                    <h3 style="font-size: 1.1rem; text-transform: uppercase; color: var(--primary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-hand-holding-heart"></i> <?php echo __('medical.form.part6_title'); ?>
                    </h3>
                    
                    <div class="premium-card" style="margin-bottom: 2rem;">
                        <label class="section-label-premium"><i class="fas fa-wave-square"></i> <?php echo __('medical.form.pulse_label'); ?></label>
                        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
                            <div class="pulse-pair">
                                <span style="font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 0.75rem;"><?php echo __('medical.form.pulse_depth_label'); ?></span>
                                <label class="checkbox-tag" style="width: 100%; margin-bottom: 0.5rem;"><input type="radio" name="history[pulse_depth]" value="Phù (Nổi)" <?php echo ($data['pulse_depth'] ?? '') == 'Phù (Nổi)' ? 'checked' : ''; ?>><span><?php echo __('medical.dong_y.pulse_depth_floating'); ?></span></label>
                                <label class="checkbox-tag" style="width: 100%;"><input type="radio" name="history[pulse_depth]" value="Trầm (Chìm)" <?php echo ($data['pulse_depth'] ?? '') == 'Trầm (Chìm)' ? 'checked' : ''; ?>><span><?php echo __('medical.dong_y.pulse_depth_deep'); ?></span></label>
                            </div>
                            <div class="pulse-pair">
                                <span style="font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 0.75rem;"><?php echo __('medical.form.pulse_speed_label'); ?></span>
                                <label class="checkbox-tag" style="width: 100%; margin-bottom: 0.5rem;"><input type="radio" name="history[pulse_speed]" value="Trì (Chậm)" <?php echo ($data['pulse_speed'] ?? '') == 'Trì (Chậm)' ? 'checked' : ''; ?>><span><?php echo __('medical.dong_y.pulse_speed_slow'); ?></span></label>
                                <label class="checkbox-tag" style="width: 100%;"><input type="radio" name="history[pulse_speed]" value="Sác (Nhanh)" <?php echo ($data['pulse_speed'] ?? '') == 'Sác (Nhanh)' ? 'checked' : ''; ?>><span><?php echo __('medical.dong_y.pulse_speed_fast'); ?></span></label>
                            </div>
                            <div class="pulse-pair">
                                <span style="font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 0.75rem;"><?php echo __('medical.form.pulse_texture_label'); ?></span>
                                <label class="checkbox-tag" style="width: 100%; margin-bottom: 0.5rem;"><input type="radio" name="history[pulse_texture]" value="Hoạt (Trơn)" <?php echo ($data['pulse_texture'] ?? '') == 'Hoạt (Trơn)' ? 'checked' : ''; ?>><span><?php echo __('medical.dong_y.pulse_text_slippery'); ?></span></label>
                                <label class="checkbox-tag" style="width: 100%;"><input type="radio" name="history[pulse_texture]" value="Sáp (Rít)" <?php echo ($data['pulse_texture'] ?? '') == 'Sáp (Rít)' ? 'checked' : ''; ?>><span><?php echo __('medical.dong_y.pulse_text_choppy'); ?></span></label>
                            </div>
                            <div class="pulse-pair">
                                <span style="font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 0.75rem;"><?php echo __('medical.form.pulse_strength_label'); ?></span>
                                <label class="checkbox-tag" style="width: 100%; margin-bottom: 0.5rem;"><input type="radio" name="history[pulse_strength]" value="Có lực (Thực)" <?php echo ($data['pulse_strength'] ?? '') == 'Có lực (Thực)' ? 'checked' : ''; ?>><span><?php echo __('medical.dong_y.pulse_str_force'); ?></span></label>
                                <label class="checkbox-tag" style="width: 100%;"><input type="radio" name="history[pulse_strength]" value="Không lực (Hư)" <?php echo ($data['pulse_strength'] ?? '') == 'Không lực (Hư)' ? 'checked' : ''; ?>><span><?php echo __('medical.dong_y.pulse_str_weak'); ?></span></label>
                            </div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                        <div class="premium-card">
                            <label class="section-label-premium"><i class="fas fa-hand-paper"></i> <?php echo __('medical.form.palpation_label'); ?></label>
                            <div class="medical-form-grid" style="grid-template-columns: 1fr; gap: 0.5rem;">
                                <?php 
                                $palp_opts = [
                                    'medical.dong_y.palp_cold_limbs' => 'Chân tay lạnh (Dương hư)',
                                    'medical.dong_y.palp_hot_palms' => 'Lòng bàn tay chân nóng (Âm hư)',
                                    'medical.dong_y.palp_tender_abd' => 'Ấn bụng đau tăng (Cự án)',
                                    'medical.dong_y.palp_relieved_abd' => 'Ấn bụng thấy dễ chịu (Thiện án)',
                                    'medical.dong_y.palp_head_warm_feet_cold' => 'Đầu ấm chân lạnh'
                                ];
                                foreach ($palp_opts as $k => $v): ?>
                                    <label class="checkbox-tag">
                                        <input type="checkbox" name="history[palpation][]" value="<?php echo $v; ?>" <?php echo in_array($v, $data['palpation'] ?? []) ? 'checked' : ''; ?>>
                                        <span><?php echo __($k); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="premium-card">
                            <label class="section-label-premium"><i class="fas fa-child"></i> <?php echo __('medical.form.muscle_temp_label'); ?></label>
                            <div style="margin-bottom: 1.5rem;">
                                <span style="font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 0.75rem;"><?php echo __('medical.form.muscle_state_label'); ?></span>
                                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                    <?php 
                                    $muscle_opts = [
                                        'medical.dong_y.muscle_firm' => 'Săn chắc',
                                        'medical.dong_y.muscle_stiff' => 'Co cứng',
                                        'medical.dong_y.muscle_loose' => 'Lỏng lẽo'
                                    ];
                                    foreach ($muscle_opts as $k => $v): ?>
                                        <label class="checkbox-tag">
                                            <input type="radio" name="history[palpation_muscle]" value="<?php echo $v; ?>" <?php echo ($data['palpation_muscle'] ?? '') == $v ? 'checked' : ''; ?>>
                                            <span><?php echo __($k); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div>
                                <span style="font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 0.75rem;"><?php echo __('medical.form.body_temp_label'); ?></span>
                                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                    <?php 
                                    $temp_opts = [
                                        'medical.dong_y.body_temp_normal' => 'Bình thường',
                                        'medical.dong_y.body_temp_hot' => 'Nóng',
                                        'medical.dong_y.body_temp_cold' => 'Lạnh'
                                    ];
                                    foreach ($temp_opts as $k => $v): ?>
                                        <label class="checkbox-tag">
                                            <input type="radio" name="history[body_temp]" value="<?php echo $v; ?>" <?php echo ($data['body_temp'] ?? '') == $v ? 'checked' : ''; ?>>
                                            <span><?php echo __($k); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <!-- VI. TỔNG KẾT NHANH -->
            <div style="margin-bottom: 3.5rem;">
                <h3 style="font-size: 1.1rem; text-transform: uppercase; color: var(--primary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-clipboard-check"></i> <?php echo __('medical.form.part7_title'); ?>
                </h3>
                <div class="premium-card">
                    <label class="section-label-premium"><i class="fas fa-tags"></i> <?php echo __('medical.form.bat_cuong_label'); ?></label>
                    <div class="medical-form-grid" style="grid-template-columns: repeat(4, 1fr); gap: 1rem;">
                        <?php 
                        $bat_cuong_opts = [
                            'medical.dong_y.bat_cuong_bieu' => 'Biểu',
                            'medical.dong_y.bat_cuong_ly' => 'Lý',
                            'medical.dong_y.bat_cuong_han' => 'Hàn',
                            'medical.dong_y.bat_cuong_nhiet' => 'Nhiệt',
                            'medical.dong_y.bat_cuong_hu' => 'Hư',
                            'medical.dong_y.bat_cuong_thuc' => 'Thực',
                            'medical.dong_y.bat_cuong_am' => 'Âm',
                            'medical.dong_y.bat_cuong_duong' => 'Dương'
                        ];
                        foreach ($bat_cuong_opts as $k => $v): ?>
                            <label class="checkbox-tag" style="justify-content: center; padding: 1rem;">
                                <input type="checkbox" name="history[bat_cuong][]" value="<?php echo $v; ?>" <?php echo in_array($v, $data['bat_cuong'] ?? []) ? 'checked' : ''; ?>>
                                <span><?php echo __($k); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Chiropractic / Generic Sections -->
            <?php foreach ($questions as $section_key => $options): ?>
                <div style="margin-bottom: 2.5rem;">
                    <h3 style="font-size: 1rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                        <span style="width: 4px; height: 16px; background: var(--primary); border-radius: 2px;"></span>
                        <?php echo __($section_key); ?>
                    </h3>
                    <div class="medical-form-grid">
                        <?php foreach ($options as $opt): ?>
                            <label class="checkbox-card">
                                <input type="checkbox" name="history[<?php echo $section_key; ?>][]" value="<?php echo $opt['label']; ?>" <?php echo in_array($opt['label'], $data[$section_key] ?? []) ? 'checked' : ''; ?>>
                                <i class="fas <?php echo $opt['icon']; ?>"></i>
                                <span class="label-text"><?php echo __($opt['key']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        </fieldset>

        <div style="margin-top: 3rem; display: flex; gap: 1rem; justify-content: flex-end;">
            <?php if (!$is_locked): ?>
                <button type="submit" class="btn btn-primary" style="padding: 1rem 2.5rem; border-radius: 12px; font-weight: 800; min-width: 200px;">
                    <i class="fas fa-save"></i> 
                    <?php 
                        if ($history_id) {
                            echo ($session['status'] === 'completed' ? __('medical.form.btn_update_admin') : __('medical.form.btn_update'));
                        } else {
                            echo __('medical.form.btn_save');
                        }
                    ?>
                </button>
            <?php endif; ?>
            <a href="session_view.php?id=<?php echo $session_id; ?>" class="btn" style="background: #f1f5f9; color: var(--text-main); padding: 1rem 2.5rem; border-radius: 12px; font-weight: 800;">
                <?php echo __('common.back'); ?>
            </a>
        </div>
    </form>
</div>

<?php require_once '../../templates/footer.php'; ?>
