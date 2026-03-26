<?php
// modules/medical/chiro_history.php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_medical');

$patient_id = $_GET['patient_id'] ?? 0;
$session_id = $_GET['session_id'] ?? null;
$record_id = $_GET['id'] ?? null;
$db = getDB();

$existing_data = [];
if ($record_id) {
    $stmt = $db->prepare("SELECT history_data FROM medical_history WHERE id = ?");
    $stmt->execute([$record_id]);
    $json = $stmt->fetchColumn();
    $existing_data = json_decode($json, true) ?: [];
}

function get_v($path, $default = '') {
    global $existing_data;
    $keys = explode('.', $path);
    $val = $existing_data;
    foreach ($keys as $key) {
        if (!isset($val[$key])) return $default;
        $val = $val[$key];
    }
    return $val;
}

function checked_v($path, $value) {
    $val = get_v($path);
    if (is_array($val)) return in_array($value, $val) ? 'checked' : '';
    return $val == $value ? 'checked' : '';
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exam = $_POST['exam'] ?? [];
    if (isset($exam['markers']) && is_string($exam['markers'])) {
        $exam['markers'] = json_decode($exam['markers'], true) ?: [];
    }
    $history_data = json_encode($exam);
    
    if ($record_id) {
        $stmt = $db->prepare("UPDATE medical_history SET history_data = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$history_data, $record_id]);
    } else {
        $stmt = $db->prepare("
            INSERT INTO medical_history (patient_id, session_id, type, history_data, created_by)
            VALUES (?, ?, 'chiro_history', ?, ?)
        ");
        $stmt->execute([$patient_id, $session_id, $history_data, $_SESSION['user_id']]);
    }
    
    set_flash(__('medical.history.msg_success'));
    
    if ($session_id) {
        redirect("session_view.php?id=$session_id");
    } else {
        redirect("../patients/view.php?id=$patient_id");
    }
}

$stmt = $db->prepare("SELECT full_name FROM patients WHERE id = ?");
$stmt->execute([$patient_id]);
$patient_name = $stmt->fetchColumn();

if (!$patient_name) {
    set_flash(__('medical.exam.err_no_patient'), 'error');
    redirect('index.php');
}

$page_title = __('medical.type.chiro_history_full');
$current_page = 'medical';
require_once '../../templates/header.php';
?>

<div class="card" style="background: var(--glass-bg); backdrop-filter: blur(20px); max-width: 1000px; margin: 0 auto;">
    <div style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: start;">
        <div>
            <h2 style="margin: 0; font-weight: 800; color: var(--primary);"><i class="fas fa-history"></i> <?php echo __('medical.type.chiro_history_full'); ?></h2>
            <p style="color: var(--text-muted); margin-top: 0.25rem;"><?php echo __('medical.exam.patient_label'); ?> <strong style="color: var(--text-main);"><?php echo e($patient_name); ?></strong></p>
        </div>
        <div style="background: #f5f3ff; color: #7c3aed; padding: 0.5rem 1rem; border-radius: 12px; font-weight: 700;"><?php echo __('medical.history.badge_history'); ?></div>
    </div>

    <form method="POST">
        <!-- PART 1: THÔNG TIN CƠ BẢN & LỐI SỐNG -->
        <div style="margin-bottom: 4rem;">
            <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--primary); margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 2px solid var(--border-color); padding-bottom: 0.75rem;">
                <i class="fas fa-user-check"></i> <?php echo __('medical.history.part1_title'); ?>
            </h3>

            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem; margin-bottom: 3rem;">
                <div class="form-group">
                    <label class="form-label"><?php echo __('medical.history.height_label'); ?></label>
                    <input type="number" name="exam[biometrics][height]" class="form-input" placeholder="..." value="<?php echo get_v('biometrics.height'); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('medical.history.weight_label'); ?></label>
                    <input type="number" name="exam[biometrics][weight]" class="form-input" placeholder="..." value="<?php echo get_v('biometrics.weight'); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('medical.history.blood_pressure_label'); ?></label>
                    <input type="text" name="exam[biometrics][blood_pressure]" class="form-input" placeholder="120/80" value="<?php echo get_v('biometrics.blood_pressure'); ?>">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3rem;">
                <div class="form-group">
                    <label class="form-label"><?php echo __('medical.history.job_label'); ?></label>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 0.5rem;">
                        <?php 
                        $job_options = [
                            'Ngồi nhiều'      => __('medical.history.job_sitting'),
                            'Đứng nhiều'      => __('medical.history.job_standing'),
                            'Lao động tay chân' => __('medical.history.job_manual'),
                            'Di chuyển nhiều' => __('medical.history.job_moving')
                        ];
                        foreach ($job_options as $val => $label): ?>
                            <label class="checkbox-tag">
                                <input type="checkbox" name="exam[lifestyle][job][]" value="<?php echo $val; ?>" <?php echo checked_v('lifestyle.job', $val); ?>>
                                <span><?php echo $label; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('medical.history.exercise_freq_label'); ?></label>
                    <div style="display: flex; gap: 1.5rem; margin-top: 1rem;">
                        <?php 
                        $exercise_options = [
                            'Không tập'      => __('medical.history.exercise_never'),
                            'Thỉnh thoảng'  => __('medical.history.exercise_sometimes'),
                            'Thường xuyên'   => __('medical.history.exercise_often')
                        ];
                        foreach ($exercise_options as $val => $label): ?>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 600;">
                                <input type="radio" name="exam[lifestyle][exercise]" value="<?php echo $val; ?>" <?php echo checked_v('lifestyle.exercise', $val); ?>> <?php echo $label; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="form-group" style="margin-top: 2rem;">
                <label class="form-label"><?php echo __('medical.history.birth_history_label'); ?></label>
                <div style="display: flex; flex-wrap: wrap; gap: 2rem; margin-top: 0.75rem; align-items: center;">
                    <?php 
                    $birth_options = [
                        'Sinh thường'        => __('medical.history.birth_natural'),
                        'Sinh mổ'           => __('medical.history.birth_c_section'),
                        'Có dùng kẹp/giác hút' => __('medical.history.birth_assisted')
                    ];
                    foreach ($birth_options as $val => $label): ?>
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 600;">
                            <input type="radio" name="exam[lifestyle][birth_history]" value="<?php echo $val; ?>" <?php echo checked_v('lifestyle.birth_history', $val); ?>> <?php echo $label; ?>
                        </label>
                    <?php endforeach; ?>
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 600;">
                        <input type="radio" name="exam[lifestyle][birth_history]" value="Khác" <?php echo checked_v('lifestyle.birth_history', 'Khác'); ?>> <?php echo __('common.other'); ?>
                    </label>
                    <input type="text" name="exam[lifestyle][birth_history_other]" placeholder="<?php echo __('medical.history.other_note'); ?>" class="form-input" style="width: 300px;" value="<?php echo get_v('lifestyle.birth_history_other'); ?>">
                </div>
            </div>
        </div>

        <!-- PART 2: TÌNH TRẠNG BỆNH LÝ HIỆN TẠI -->
        <div style="margin-bottom: 4rem;">
            <h3 style="font-size: 1.1rem; color: var(--text-main); margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem;">
                <i class="fas fa-file-waveform" style="color: var(--primary);"></i> <?php echo __('medical.history.part2_title'); ?>
            </h3>
            
            <div class="form-group" style="margin-bottom: 2.5rem;">
                <label class="form-label"><?php echo __('medical.history.pain_locations_label'); ?></label>
                <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 1rem; align-items: center;">
                    <?php 
                    $loc_options = [
                        'Cổ (Halswirbelsäule)'             => __('medical.history.loc_neck'),
                        'Ngực/Lưng trên (Brustwirbelsäule)' => __('medical.history.loc_thoracic'),
                        'Thắt lưng (Lendenwirbelsäule)'    => __('medical.history.loc_lumbar'),
                        'Khớp Vai'                         => __('medical.exam.joint_shoulder'),
                        'Khớp Khuỷu tay'                   => __('medical.exam.joint_elbow'),
                        'Khớp Cổ tay'                      => __('medical.exam.joint_wrist'),
                        'Khớp Háng'                        => __('medical.exam.joint_hip'),
                        'Khớp Gối'                         => __('medical.exam.joint_knee'),
                        'Khớp Cổ chân'                     => __('medical.exam.joint_ankle'),
                        'Khác'                             => __('common.other')
                    ];
                    foreach ($loc_options as $val => $label): ?>
                        <label class="checkbox-tag">
                            <input type="checkbox" name="exam[pathology][locations][]" value="<?php echo $val; ?>" <?php echo checked_v('pathology.locations', $val); ?>>
                            <span><?php echo $label; ?></span>
                        </label>
                    <?php endforeach; ?>
                    <input type="text" name="exam[pathology][locations_other]" placeholder="<?php echo __('medical.history.locations_other_placeholder'); ?>" class="form-input" style="width: 250px;" value="<?php echo get_v('pathology.locations_other'); ?>">
                </div>
            </div>

            <div id="pain-details-section" style="display: none;">
                <div class="form-group" style="margin-bottom: 2.5rem;">
                    <label class="form-label"><?php echo __('medical.history.pain_nature_label'); ?></label>
                <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 1rem;">
                    <?php 
                    $nature_options = [
                        'Đau nhói'          => __('medical.history.pain_nature_sharp'),
                        'Đau âm ỉ'          => __('medical.history.pain_nature_dull'),
                        'Tê bì'            => __('medical.history.pain_nature_numb'),
                        'Yêu cơ'           => __('medical.history.pain_nature_weak'),
                        'Hạn chế vận động' => __('medical.history.pain_nature_limited')
                    ];
                    foreach ($nature_options as $val => $label): ?>
                        <label class="checkbox-tag">
                            <input type="checkbox" name="exam[pathology][nature][]" value="<?php echo $val; ?>" <?php echo checked_v('pathology.nature', $val); ?>>
                            <span><?php echo $label; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 2.5rem;">
                <label class="form-label"><?php echo __('medical.history.pain_triggers_label'); ?></label>
                <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 1rem;">
                    <?php 
                    $trigger_options = [
                        'Đi bộ'          => __('medical.history.trigger_walk'),
                        'Ngồi lâu'       => __('medical.history.trigger_sit'),
                        'Đứng lâu'       => __('medical.history.trigger_stand'),
                        'Lúc ngủ'        => __('medical.history.trigger_sleep'),
                        'Sau khi ngủ dậy' => __('medical.history.trigger_wake'),
                        'Vận động mạnh'  => __('medical.history.trigger_active')
                    ];
                    foreach ($trigger_options as $val => $label): ?>
                        <label class="checkbox-tag">
                            <input type="checkbox" name="exam[pathology][triggers][]" value="<?php echo $val; ?>" <?php echo checked_v('pathology.triggers', $val); ?>>
                            <span><?php echo $label; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <div class="form-group">
                    <label class="form-label"><?php echo __('medical.history.pain_intensity_label'); ?></label>
                    <div style="display: flex; align-items: center; gap: 1.5rem; margin-top: 1.5rem;">
                        <span style="color: #10b981; font-weight: 700;">0</span>
                        <input type="range" name="exam[pathology][intensity]" min="0" max="10" value="<?php echo get_v('pathology.intensity', 5); ?>" class="slider" style="flex-grow: 1;" oninput="document.getElementById('pain-val').innerText = this.value">
                        <span style="color: #ef4444; font-weight: 700;">10</span>
                        <span id="pain-val" style="background: var(--primary); color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.875rem;"><?php echo get_v('pathology.intensity', 5); ?></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('medical.history.symptom_duration_label'); ?></label>
                    <div style="display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 1rem;">
                        <?php 
                        $duration_options = [
                            'Cấp tính (vài ngày)'      => __('medical.history.duration_acute'),
                            'Mạn tính (vài tháng/năm)' => __('medical.history.duration_chronic'),
                            'Tái phát nhiều lần'      => __('medical.history.duration_recurrent')
                        ];
                        foreach ($duration_options as $val => $label): ?>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 600;">
                                <input type="radio" name="exam[pathology][duration]" value="<?php echo $val; ?>" <?php echo checked_v('pathology.duration', $val); ?>> <?php echo $label; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label"><?php echo __('medical.history.activating_causes_label'); ?></label>
                <div style="display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 1rem;">
                    <?php 
                    $cause_options = [
                        'Ngã/Va chạm' => __('medical.history.cause_fall'),
                        'Tai nạn xe'  => __('medical.history.cause_accident'),
                        'Tự nhiên bị' => __('medical.history.cause_natural')
                    ];
                    foreach ($cause_options as $val => $label): ?>
                        <label class="checkbox-tag">
                            <input type="checkbox" name="exam[pathology][activating_causes][]" value="<?php echo $val; ?>" <?php echo checked_v('pathology.activating_causes', $val); ?>>
                            <span><?php echo $label; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label"><?php echo __('medical.history.description_label'); ?></label>
                <textarea name="exam[pathology][description]" class="form-input" rows="4" placeholder="<?php echo __('medical.history.description_placeholder'); ?>"><?php echo get_v('pathology.description'); ?></textarea>
            </div>

            <!-- SƠ ĐỒ ĐIỂM ĐAU / CẢNH BÁO -->
            <div style="margin-top: 3rem; margin-bottom: 3rem;">
                <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--primary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-edit"></i> <?php echo __('medical.history.pain_map_title'); ?>
                </h3>
                
                <div style="display: flex; gap: 3rem;">
                    <div style="flex: 1; position: relative; background: white; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; cursor: crosshair;">
                        <canvas id="anatomy-canvas" width="800" height="800" style="width: 100%; height: auto; display: block;"></canvas>
                        <input type="hidden" name="exam[markers]" id="marking-data" value="<?php echo e(json_encode(get_v('markers', []))); ?>">
                    </div>
                    
                    <div style="width: 350px;">
                        <div style="background: #fff9f0; padding: 1.25rem; border-radius: 12px; border: 1px solid #ffedd5; margin-bottom: 2rem;">
                            <h4 style="font-size: 0.8rem; color: #9a3412; text-transform: uppercase; margin-bottom: 0.75rem; font-weight: 800;"><?php echo __('medical.history.guide_title'); ?></h4>
                            <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.8rem; color: #9a3412; line-height: 1.6;">
                                <li><strong>O:</strong> <?php echo __('medical.history.guide_surgery'); ?></li>
                                <li><strong>X:</strong> <?php echo __('medical.history.guide_fracture'); ?></li>
                                <li><strong>M:</strong> <?php echo __('medical.history.guide_pain'); ?></li>
                            </ul>
                        </div>

                        <div style="display: flex; gap: 0.75rem; margin-bottom: 2rem;">
                            <button type="button" class="tool-btn active" id="tool-marker" title="<?php echo __('medical.history.tool_pain'); ?>"><i class="fas fa-pencil-alt"></i></button>
                            <button type="button" class="tool-btn" id="tool-surgery" style="color: #f59e0b; font-weight: 900;">O</button>
                            <button type="button" class="tool-btn" id="tool-fracture" style="color: #ef4444; font-weight: 900;">X</button>
                            <button type="button" class="tool-btn" id="marker-eraser" title="<?php echo __('medical.history.tool_eraser'); ?>"><i class="fas fa-eraser"></i></button>
                            <button type="button" class="tool-btn" id="marker-clear" style="margin-left: auto; color: #ef4444;"><i class="fas fa-trash"></i></button>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 0.75rem;">
                            <?php 
                            $colors = [
                                'M1' => '#d9f99d', 'M2' => '#84cc16', 'M3' => '#22c55e', 'M4' => '#15803d',
                                'M5' => '#60a5fa', 'M6' => '#2563eb', 
                                'M7' => '#fca5a5', 'M8' => '#f97316', 'M9' => '#ef4444', 'M10' => '#b91c1c'
                            ];
                            foreach($colors as $m => $color): ?>
                                <button type="button" class="intensity-btn <?php echo $m === 'M5' ? 'active' : ''; ?>" 
                                        data-intensity="<?php echo $m; ?>" 
                                        style="color: <?php echo $color; ?>"
                                        title="<?php echo $m; ?>">
                                    <span style="background: <?php echo $color; ?>;"></span> <?php echo $m; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- PART 3: TIỀN SỬ Y KHOA & CHẤN THƯƠNG -->
        <div style="margin-bottom: 4rem;">
            <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--primary); margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 2px solid var(--border-color); padding-bottom: 0.75rem;">
                <i class="fas fa-history"></i> <?php echo __('medical.history.part3_title'); ?>
            </h3>
            <p style="font-style: italic; color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.9rem;"><?php echo __('medical.history.part3_desc'); ?></p>

            <div class="form-group" style="margin-bottom: 2.5rem;">
                <label class="form-label"><?php echo __('medical.history.suspected_causes_label'); ?></label>
                <div style="display: flex; flex-wrap: wrap; gap: 1.5rem; margin-top: 0.75rem;">
                    <?php 
                    $cause_options = [
                        'Tai nhận xe'              => __('medical.history.cause_accident'),
                        'Ngã/Chấn thương thể thao' => __('medical.history.cause_sports'),
                        'Không rõ nguyên nhân'     => __('medical.history.cause_unknown')
                    ];
                    foreach ($cause_options as $val => $label): ?>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <label style="display: flex; align-items: center; gap: 0.4rem; cursor: pointer; font-weight: 600; font-size: 0.9rem;">
                                <input type="checkbox" name="exam[medical_history][causes][]" value="<?php echo $val; ?>" <?php echo checked_v('medical_history.causes', $val); ?>> <?php echo $label; ?>
                            </label>
                            <?php if ($val !== 'Không rõ nguyên nhân'): ?>
                                <input type="text" name="exam[medical_history][cause_time][<?php echo $val; ?>]" class="form-input" placeholder="<?php echo __('medical.history.time_placeholder'); ?>" style="width: 120px; padding: 0.25rem 0.5rem; font-size: 0.8rem;" value="<?php echo get_v('medical_history.cause_time.'.$val); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Intervention History -->
            <div class="form-group" style="margin-bottom: 2.5rem;">
                <label class="form-label"><?php echo __('medical.history.intervention_history_label'); ?></label>
                <div style="display: flex; flex-direction: column; gap: 1.25rem; margin-top: 1rem; padding-left: 1rem;">
                    <!-- Surgery -->
                    <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; font-weight: 600;">
                            <input type="checkbox" name="exam[medical_history][surgery_flag]" value="1" <?php echo checked_v('medical_history.surgery_flag', '1'); ?>> <?php echo __('medical.history.surgery_flag_label'); ?>
                        </label>
                        <span style="font-size: 0.9rem;">(<?php echo __('medical.history.surgery_area_label'); ?> <input type="text" name="exam[medical_history][surgery_area]" class="form-input" style="display: inline-block; width: 140px;" value="<?php echo get_v('medical_history.surgery_area'); ?>"></span>
                        <span style="font-size: 0.9rem;"><?php echo __('medical.history.surgery_time_label'); ?> <input type="text" name="exam[medical_history][surgery_time]" class="form-input" style="display: inline-block; width: 140px;" value="<?php echo get_v('medical_history.surgery_time'); ?>">)</span>
                        <span style="font-size: 0.75rem; color: #b45309; font-weight: 600;"><?php echo __('medical.history.surgery_mark_help'); ?></span>
                    </div>
                    
                    <!-- Fracture -->
                    <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; font-weight: 600;">
                            <input type="checkbox" name="exam[medical_history][fracture_flag]" value="1" <?php echo checked_v('medical_history.fracture_flag', '1'); ?>> <?php echo __('medical.history.fracture_flag_label'); ?>
                        </label>
                        <span style="font-size: 0.9rem;">(<?php echo __('medical.history.fracture_time_label'); ?> <input type="text" name="exam[medical_history][fracture_time]" class="form-input" style="display: inline-block; width: 140px;" value="<?php echo get_v('medical_history.fracture_time'); ?>">)</span>
                        <span style="font-size: 0.9rem;"><?php echo __('medical.history.fracture_area_label'); ?> <input type="text" name="exam[medical_history][fracture_area]" class="form-input" style="display: inline-block; width: 140px;" value="<?php echo get_v('medical_history.fracture_area'); ?>"></span>
                        <span style="font-size: 0.75rem; color: #b91c1c; font-weight: 600;"><?php echo __('medical.history.fracture_mark_help'); ?></span>
                    </div>

                    <!-- Dental -->
                    <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; font-weight: 600;">
                            <input type="checkbox" name="exam[medical_history][implants]" value="1" <?php echo checked_v('medical_history.implants', '1'); ?>> <?php echo __('medical.history.implants_flag_label'); ?>
                        </label>
                        <span style="font-size: 0.9rem;">(<?php echo __('medical.history.fracture_time_label'); ?> <input type="text" name="exam[medical_history][implant_time]" class="form-input" style="display: inline-block; width: 140px;" value="<?php echo get_v('medical_history.implant_time'); ?>">)</span>
                    </div>

                    <!-- Imaging -->
                    <div style="display: flex; align-items: center; gap: 1.5rem;">
                         <span style="font-size: 0.9rem; font-weight: 800;"><?php echo __('medical.history.imaging_label'); ?></span>
                         <?php 
                         $imaging_options = [
                             'X-Ray' => 'X-Ray',
                             'MRI/CT' => 'MRI/CT'
                         ];
                         foreach ($imaging_options as $val => $label): ?>
                            <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; cursor: pointer; font-weight: 600;">
                                <input type="checkbox" name="exam[medical_history][imaging][]" value="<?php echo $val; ?>" <?php echo checked_v('medical_history.imaging', $val); ?>> <?php echo $label; ?>
                            </label>
                         <?php endforeach; ?>
                    </div>

                     <!-- Treatments -->
                    <div style="display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap;">
                         <span style="font-size: 0.9rem; font-weight: 800;"><?php echo __('medical.history.prev_treatments_label'); ?></span>
                         <?php 
                         $prev_treatment_options = [
                             'Chiropractic khác' => __('medical.history.prev_chiro'),
                             'Vật lý trị liệu'  => __('medical.history.prev_physio'),
                             'Osteopath'        => 'Osteopath'
                         ];
                         foreach ($prev_treatment_options as $val => $label): ?>
                            <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; cursor: pointer; font-weight: 600;">
                                <input type="checkbox" name="exam[medical_history][prev_treatments][]" value="<?php echo $val; ?>" <?php echo checked_v('medical_history.prev_treatments', $val); ?>> <?php echo $label; ?>
                            </label>
                         <?php endforeach; ?>
                    </div>

                    <!-- Accident (Moved here from redundant section) -->
                    <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; font-weight: 600;">
                            <input type="checkbox" name="exam[medical_history][accident_flag]" value="1" <?php echo checked_v('medical_history.accident_flag', '1'); ?>> <?php echo __('medical.history.accident_flag_label'); ?>
                        </label>
                        <span style="font-size: 0.9rem;">(<?php echo __('medical.history.accident_area_label'); ?> <input type="text" name="exam[medical_history][accident_area]" class="form-input" style="display: inline-block; width: 250px;" value="<?php echo get_v('medical_history.accident_area'); ?>">)</span>
                    </div>
                </div>
            </div>

            <!-- Disease Groups (Restored) -->
            <div class="form-group" style="margin-bottom: 2.5rem;">
                <label class="form-label"><?php echo __('medical.history.ortho_diseases_label'); ?></label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                    <?php 
                    $ortho_diseases = [
                        'Thoát vị đĩa đệm (Bandscheibenvorfall): Đã có chẩn đoán xác định.' => __('medical.history.ortho_herniation'),
                        'Viêm khớp dạng thấp (Rheumatoid Polyarthritis): Hoặc các bệnh tự miễn về khớp.' => __('medical.history.ortho_arthritis'),
                        'Loãng xương (Osteoporosis): Nguy cơ gãy xương khi nắn chỉnh lực mạnh.' => __('medical.history.ortho_osteoporosis'),
                        'Thoái hóa cột sống nặng: Gây hẹp ống sống hoặc gai xương lớn.' => __('medical.history.ortho_degeneration'),
                        'Vẹo cột sống (Skoliose): Cột sống hình chữ S đã biết.' => __('medical.history.ortho_scoliosis'),
                        'Viêm cột sống dính khớp: Gây cứng hóa các đốt sống.' => __('medical.history.ortho_ankylosing')
                    ];
                    foreach ($ortho_diseases as $val => $label): ?>
                        <label style="display: flex; align-items: start; gap: 0.75rem; font-size: 0.85rem; cursor: pointer; border: 1px solid #e2e8f0; padding: 0.75rem; border-radius: 12px; background: #fff; transition: all 0.2s; line-height: 1.4;">
                            <input type="checkbox" name="exam[medical_history][ortho][]" value="<?php echo $val; ?>" style="margin-top: 0.15rem;" <?php echo checked_v('medical_history.ortho', $val); ?>> 
                            <span><?php echo $label; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 2.5rem;">
                <label class="form-label"><?php echo __('medical.history.internal_diseases_label'); ?></label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                    <?php 
                    $internal_diseases = [
                        'Ung thư (Krebs): Bất kỳ loại nào, đặc biệt là ung thư xương hoặc di cá.' => __('medical.history.internal_cancer'),
                        'Huyết áp cao (Bluthochdruck): Liên quan đến nguy cơ lưu thông máu lên não.' => __('medical.history.internal_bp'),
                        'Tiểu đường (Diabetes): Ảnh hưởng đến tốc độ phục hồi thần kinh và mạch máu.' => __('medical.history.internal_diabetes'),
                        'Rối loạn đông máu: Hoặc đang sử dụng thuốc làm loãng máu (nguy cơ xuất huyết nội).' => __('medical.history.internal_clotting'),
                        'Bệnh lý tim mạch: Đã từng đặt stent, phẫu thuật tim hoặc dùng máy tạo nhịp.' => __('medical.history.internal_cardiac')
                    ];
                    foreach ($internal_diseases as $val => $label): ?>
                        <label style="display: flex; align-items: start; gap: 0.75rem; font-size: 0.85rem; cursor: pointer; border: 1px solid #e2e8f0; padding: 0.75rem; border-radius: 12px; background: #fff; transition: all 0.2s; line-height: 1.4;">
                            <input type="checkbox" name="exam[medical_history][internal][]" value="<?php echo $val; ?>" style="margin-top: 0.15rem;" <?php echo checked_v('medical_history.internal', $val); ?>> 
                            <span><?php echo $label; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Medications -->
            <div class="form-group" style="margin-bottom: 2.5rem;">
                <label class="form-label"><?php echo __('medical.history.medications_label'); ?></label>
                <div style="display: flex; gap: 1.5rem; align-items: center; margin-bottom: 1rem;">
                    <span style="font-size: 0.9rem; font-weight: 800; color: var(--primary);"><?php echo __('medical.history.meds_time_label'); ?></span>
                    <input type="text" name="exam[medical_history][meds_time]" class="form-input" placeholder="<?php echo __('medical.history.meds_time_placeholder'); ?>" style="display: inline-block; width: 200px;" value="<?php echo get_v('medical_history.meds_time'); ?>">
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 0.75rem; margin-bottom: 1.25rem;">
                    <?php 
                    $med_options = [
                        'Giảm đau / Chống viêm' => __('medical.history.meds_pain'),
                        'Thuốc huyết áp / Tim mạch' => __('medical.history.meds_bp'),
                        'Thuốc tiểu đường' => __('medical.history.meds_diabetes'),
                        'Thuốc chống đông máu' => __('medical.history.meds_blood'),
                        'Thực phẩm chức năng (Xương khớp, Vitamin...)' => __('medical.history.meds_supplements')
                    ];
                    foreach ($med_options as $val => $label): ?>
                        <label class="checkbox-tag" style="background: white; width: 100%; justify-content: start; text-align: left;">
                            <input type="checkbox" name="exam[medical_history][meds_common][]" value="<?php echo $val; ?>" <?php echo checked_v('medical_history.meds_common', $val); ?>>
                            <span style="padding: 0.75rem 1rem; width: 100%; box-sizing: border-box;"><?php echo $label; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <label class="form-label" style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.5rem;"><?php echo __('medical.history.meds_list_label'); ?></label>
                <textarea name="exam[medical_history][meds_list]" class="form-input" rows="2" placeholder="<?php echo __('medical.history.meds_list_placeholder'); ?>"><?php echo get_v('medical_history.meds_list'); ?></textarea>
            </div>

            <!-- Red Flags -->
            <div class="form-group" style="margin-top: 2rem;">
                <label class="form-label" style="color: #991b1b;"><i class="fas fa-exclamation-triangle"></i> <?php echo __('medical.history.red_flags_label'); ?></label>
                <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.5rem;">
                    <?php 
                    $flag_options = [
                        'Mất kiểm soát đại/tiểu tiện' => __('medical.history.red_flag_control'),
                        'Tê vùng yên ngựa'          => __('medical.history.red_flag_saddle'),
                        'Yếu liệt chi tiến triển nhanh' => __('medical.history.red_flag_weakness')
                    ];
                    foreach ($flag_options as $val => $label): ?>
                        <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: #991b1b; font-weight: 600; cursor: pointer;">
                            <input type="checkbox" name="exam[medical_history][red_flags][]" value="<?php echo $val; ?>" <?php echo checked_v('medical_history.red_flags', $val); ?>> <?php echo $label; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- PART 4: RÀ SOÁT HỆ THỐNG (ROS) -->
        <div style="margin-bottom: 4rem;">
            <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--primary); margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 2px solid var(--border-color); padding-bottom: 0.75rem;">
                <i class="fas fa-stethoscope"></i> <?php echo __('medical.history.part4_title'); ?>
            </h3>
            <div class="form-group">
                <label class="form-label"><?php echo __('medical.history.ros_label'); ?></label>
                
                <!-- Vùng đầu mặt -->
                <div style="margin-bottom: 2rem; margin-top: 1rem;">
                    <div style="font-size: 0.9rem; font-weight: 800; color: var(--primary); margin-bottom: 0.75rem; border-left: 4px solid var(--primary); padding-left: 0.75rem;"><?php echo __('medical.history.ros_head_face'); ?></div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 0.75rem;">
                        <?php 
                        $ros_head_options = [
                            'Đau đầu' => __('medical.history.ros_headache'),
                            'Chóng mặt' => __('medical.history.ros_dizzy'),
                            'Ù tai' => __('medical.history.ros_tinnitus'),
                            'Vấn đề hàm (Khớp thái dương hàm)' => __('medical.history.ros_tmj'),
                            'Đang niềng răng' => __('medical.history.ros_braces')
                        ];
                        foreach ($ros_head_options as $val => $label): ?>
                            <label class="checkbox-tag" style="background: white; width: 100%; justify-content: start; text-align: left;">
                                <input type="checkbox" name="exam[medical_history][ros][]" value="<?php echo $val; ?>" <?php echo checked_v('medical_history.ros', $val); ?>>
                                <span style="padding: 0.8rem 1rem; width: 100%; box-sizing: border-box; display: inline-block; border-radius: 12px;"><?php echo $label; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Cơ quan liên quan -->
                <div style="margin-bottom: 2rem;">
                    <div style="font-size: 0.9rem; font-weight: 800; color: var(--primary); margin-bottom: 0.75rem; border-left: 4px solid var(--primary); padding-left: 0.75rem;"><?php echo __('medical.history.ros_organs'); ?></div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 0.75rem;">
                        <?php 
                        $ros_organ_options = [
                            'Tê lan xuống ngón tay' => __('medical.history.ros_numb_fingers'),
                            'Đau tức ngực (không do tim)' => __('medical.history.ros_chest_pain'),
                            'Đau/Tê lan xuống mông/chân' => __('medical.history.ros_sciatica'),
                            'Có tiền sử Vẹo cột sống (S-form)' => __('medical.history.ros_scoliosis')
                        ];
                        foreach ($ros_organ_options as $val => $label): ?>
                            <label class="checkbox-tag" style="background: white; width: 100%; justify-content: start; text-align: left;">
                                <input type="checkbox" name="exam[medical_history][ros][]" value="<?php echo $val; ?>" <?php echo checked_v('medical_history.ros', $val); ?>>
                                <span style="padding: 0.8rem 1rem; width: 100%; box-sizing: border-box; display: inline-block; border-radius: 12px;"><?php echo $label; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Cơ sở hạ tầng (Bàn chân) -->
                <div>
                    <div style="font-size: 0.9rem; font-weight: 800; color: var(--primary); margin-bottom: 0.75rem; border-left: 4px solid var(--primary); padding-left: 0.75rem;"><?php echo __('medical.history.ros_feet'); ?></div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 0.75rem;">
                        <?php 
                        $ros_foot_options = [
                            'Chênh lệch chiều dài chân'            => __('medical.history.ros_leg_length'),
                            'Hay bị lật sơ mi (bong gân cổ chân)' => __('medical.history.ros_ankle_sprain'),
                            'Đang dùng miếng lót giày/đế nâng'   => __('medical.history.ros_insoles')
                        ];
                        foreach ($ros_foot_options as $val => $label): ?>
                            <label class="checkbox-tag" style="background: white; width: 100%; justify-content: start; text-align: left;">
                                <input type="checkbox" name="exam[medical_history][ros][]" value="<?php echo $val; ?>" <?php echo checked_v('medical_history.ros', $val); ?>>
                                <span style="padding: 0.8rem 1rem; width: 100%; box-sizing: border-box; display: inline-block; border-radius: 12px;"><?php echo $label; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>


        <!-- PART 5: MỤC TIÊU ĐIỀU TRỊ -->
        <div style="margin-bottom: 4rem;">
            <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--primary); margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 2px solid var(--border-color); padding-bottom: 0.75rem;">
                <i class="fas fa-bullseye"></i> <?php echo __('medical.history.part5_title'); ?>
            </h3>
            <div class="form-group">
                <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; margin-top: 1rem;">
                    <?php 
                    $goal_options = [
                        'Giảm đau nhanh chóng'          => __('medical.history.goal_relief'),
                        'Phục hồi chức năng vận động' => __('medical.history.goal_restore'),
                        'Chăm sóc sức khỏe lâu dài'    => __('medical.history.goal_wellness')
                    ];
                    foreach ($goal_options as $val => $label): ?>
                        <label class="checkbox-tag">
                            <input type="radio" name="exam[goals]" value="<?php echo $val; ?>" <?php echo checked_v('goals', $val); ?>>
                            <span><?php echo $label; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="form-group" style="margin-top: 2rem; margin-bottom: 3rem;">
            <label class="form-label text-xs uppercase text-muted font-weight-800"><?php echo __('medical.history.additional_notes_label'); ?></label>
            <textarea name="exam[additional_notes]" class="form-input" rows="3" placeholder="<?php echo __('medical.history.additional_notes_placeholder'); ?>"><?php echo get_v('additional_notes'); ?></textarea>
        </div>

        <div style="margin-top: 3.5rem; display: flex; gap: 1.5rem; justify-content: flex-end; border-top: 2px solid #f1f5f9; padding-top: 2rem;">
            <a href="../patients/view.php?id=<?php echo $patient_id; ?>" class="btn" style="background: #f1f5f9; color: var(--text-main); padding: 1.25rem 3rem; font-weight: 700; border-radius: 16px;"><?php echo __('common.cancel'); ?></a>
            <button type="submit" class="btn btn-primary" style="padding: 1.25rem 5rem; font-weight: 800; font-size: 1.25rem; border-radius: 16px; box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.4);">
                <i class="fas fa-save" style="margin-right: 0.5rem;"></i> <?php echo __('medical.history.btn_save'); ?>
            </button>
        </div>
    </form>
</div>

<style>
.form-input {
    width: 100%;
    padding: 0.85rem 1.25rem;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 0.95rem;
    font-weight: 500;
    line-height: 1.5;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    outline: none;
    background: #fff;
    color: #1e293b;
}
.form-input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
    background: #fff;
    transform: translateY(-1px);
}
.form-input:hover {
    border-color: #cbd5e1;
}
.form-input::placeholder {
    color: #94a3b8;
    font-size: 0.9rem;
}

.checkbox-tag { cursor: pointer; }
.checkbox-tag input { position: absolute; opacity: 0; }
.checkbox-tag span {
    display: inline-block;
    padding: 0.6rem 1.25rem;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 700;
    color: #64748b;
    transition: all 0.2s;
}
.checkbox-tag:hover span { 
    border-color: var(--primary);
    background: #f8fafc;
}
.checkbox-tag input:checked + span {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
    box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.3);
}

.slider {
    -webkit-appearance: none;
    width: 100%;
    height: 10px;
    border-radius: 5px;
    background: #e2e8f0;
    outline: none;
}
.slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: var(--primary);
    cursor: pointer;
    box-shadow: 0 0 15px rgba(99, 102, 241, 0.4);
    border: 3px solid white;
}

/* Marking Tool Styles */
.tool-btn {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    border: 2px solid #e2e8f0;
    background: white;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 1.2rem;
}
.tool-btn.active {
    background: #eff6ff;
    border-color: #3b82f6;
    color: #3b82f6;
    box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.2);
}
.tool-btn.btn-danger:hover {
    background: #fef2f2;
    border-color: #ef4444;
    color: #ef4444;
}

.intensity-btn {
    border: 2px solid transparent;
    border-radius: 12px;
    padding: 0.75rem;
    background: white;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    font-size: 0.75rem;
    font-weight: 800;
    transition: all 0.2s;
    width: 100%;
    border: 1px solid #e2e8f0;
}
.intensity-btn span {
    width: 18px;
    height: 18px;
    border-radius: 50%;
}
.intensity-btn.active {
    background: #f8fafc;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
    border-color: currentColor;
    border-width: 2px;
}
</style>

<script src="../../assets/js/medical_marking.js"></script>
<script>
function togglePainDetails() {
    const locations = document.querySelectorAll('input[name="exam[pathology][locations][]"]:checked');
    const section = document.getElementById('pain-details-section');
    if (section) {
        section.style.display = locations.length > 0 ? 'block' : 'none';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Existing logic
    new MedicalMarking(
        'anatomy-canvas', 
        'marking-data', 
        '../../assets/images/anatomy_4_views_clean.png'
    );

    // Visibility logic
    const locationCheckboxes = document.querySelectorAll('input[name="exam[pathology][locations][]"]');
    locationCheckboxes.forEach(input => {
        input.addEventListener('change', togglePainDetails);
    });
    togglePainDetails();
});

const slider = document.querySelector('.slider');
if (slider) {
    slider.addEventListener('input', function() {
        const val = document.getElementById('pain-val');
        if(val) val.textContent = this.value;
    });
}
</script>

<?php require_once '../../templates/footer.php'; ?>
