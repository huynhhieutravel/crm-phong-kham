<?php
// modules/medical/follow_up.php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_medical');

$patient_id = isset($_GET['patient_id']) ? $_GET['patient_id'] : 0;
$session_id = isset($_GET['session_id']) ? $_GET['session_id'] : null;
$record_id = isset($_GET['id']) ? $_GET['id'] : null;
$db = getDB();

// Load existing data if editing
$existing_data = [];
if ($record_id) {
    $stmt = $db->prepare("SELECT history_data FROM medical_history WHERE id = ?");
    $stmt->execute([$record_id]);
    $json = $stmt->fetchColumn();
    $existing_data = json_decode($json, true) ?: [];
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $soap_data = json_encode(isset($_POST['soap']) ? $_POST['soap'] : [], JSON_UNESCAPED_UNICODE);
    
    if ($record_id) {
        $stmt = $db->prepare("UPDATE medical_history SET history_data = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$soap_data, $record_id]);
    } else {
        $stmt = $db->prepare("
            INSERT INTO medical_history (patient_id, session_id, type, history_data, created_by)
            VALUES (?, ?, 'soap_note', ?, ?)
        ");
        $stmt->execute([$patient_id, $session_id, $soap_data, $_SESSION['user_id']]);
    }
    
    set_flash(__('medical.followup.msg_success'));
    
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
    set_flash(__('medical.followup.msg_err_no_patient'), 'error');
    redirect('index.php');
}

$page_title = __('medical.followup.page_title');
$current_page = 'medical';
require_once '../../templates/header.php';

// Define Spine Nodes for Assessment section
$spine_nodes = [
    __('medical.followup.spine_cervical') => ['C1' => 'Atlas', 'C2' => 'Axis', 'C3' => 'C3', 'C4' => 'C4', 'C5' => 'C5', 'C6' => 'C6', 'C7' => 'C7'],
    __('medical.followup.spine_thoracic') => ['D1' => 'D1', 'D2' => 'D2', 'D3' => 'D3', 'D4' => 'D4', 'D5' => 'D5', 'D6' => 'D6', 'D7' => 'D7', 'D8' => 'D8', 'D9' => 'D9', 'D10' => 'D10', 'D11' => 'D11', 'D12' => 'D12'],
    __('medical.followup.spine_lumbar') => ['L1' => 'L1', 'L2' => 'L2', 'L3' => 'L3', 'L4' => 'L4', 'L5' => 'L5'],
    __('medical.followup.spine_other') => ['Sac' => 'Sacrum', 'Coc' => 'Coccyx', 'Rlli' => 'R Ilium', 'Llli' => 'L Ilium']
];

$joint_nodes = [
    'medical.followup.joint_shoulder' => 'Khớp vai',
    'medical.followup.joint_elbow' => 'Khớp khuỷu tay',
    'medical.followup.joint_wrist' => 'Khớp cổ tay',
    'medical.followup.joint_hip' => 'Khớp háng',
    'medical.followup.joint_knee' => 'Khớp gối',
    'medical.followup.joint_ankle' => 'Khớp cổ chân'
];
?>

<div class="card" style="background: var(--glass-bg); backdrop-filter: blur(20px); max-width: 1000px; margin: 0 auto;">
    <div style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: start;">
        <div>
            <h2 style="margin: 0; font-weight: 800; color: var(--primary);"><i class="fas fa-notes-medical"></i> <?php echo __('medical.followup.title'); ?></h2>
            <p style="color: var(--text-muted); margin-top: 0.25rem;"><?php echo __('medical.followup.patient_label'); ?><strong style="color: var(--text-main);"><?php echo e($patient_name); ?></strong></p>
        </div>
        <div style="background: #eef2ff; color: #4f46e5; padding: 0.5rem 1rem; border-radius: 12px; font-weight: 700;"><?php echo __('medical.followup.badge'); ?></div>
    </div>

    <form method="POST">
        <!-- 1. SUBJECTIVE (S) -->
        <div style="margin-bottom: 3rem;">
            <h3 style="font-size: 1.1rem; color: var(--text-main); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem;">
                <span style="background: var(--primary); color: white; width: 24px; height: 24px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">S</span>
                <?php echo __('medical.followup.part1_title'); ?>
            </h3>
            
            <div style="background: #f8fafc; border-radius: 16px; padding: 1.5rem; border: 1px solid #e2e8f0;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div>
                        <h4 style="font-size: 0.9rem; margin-bottom: 1rem; color: var(--text-muted); text-transform: uppercase;"><?php echo __('medical.followup.progress_label'); ?></h4>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                            <?php 
                            $progress_options = [
                                'medical.followup.progress_much_better' => 'Cải thiện rõ rệt',
                                'medical.followup.progress_better' => 'Cải thiện nhẹ',
                                'medical.followup.progress_no_change' => 'Không thay đổi',
                                'medical.followup.progress_worse' => 'Tệ hơn'
                            ];
                            foreach ($progress_options as $pk => $pv): ?>
                                <label class="checkbox-tag">
                                    <input type="radio" name="soap[s][progress]" value="<?php echo $pv; ?>" <?php echo (isset($existing_data['s']['progress']) && $existing_data['s']['progress'] == $pv) ? 'checked' : ''; ?>>
                                    <span><?php echo __($pk); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div style="margin-top: 1.5rem;">
                            <h4 style="font-size: 0.9rem; margin-bottom: 1rem; color: var(--text-muted); text-transform: uppercase;"><?php echo __('medical.followup.freq_label'); ?></h4>
                            <div class="medical-form-grid" style="grid-template-columns: 1fr 1fr;">
                                <?php 
                                $freq_options = [
                                    'medical.followup.freq_0_25' => 'Thỉnh thoảng (0-25%)',
                                    'medical.followup.freq_25_50' => 'Lúc có lúc không (25-50%)',
                                    'medical.followup.freq_50_75' => 'Thường xuyên (50-75%)',
                                    'medical.followup.freq_75_100' => 'Liên tục (75-100%)'
                                ];
                                foreach ($freq_options as $fk => $fv): ?>
                                    <label class="checkbox-card small">
                                        <input type="radio" name="soap[s][frequency]" value="<?php echo $fv; ?>" <?php echo (isset($existing_data['s']['frequency']) && $existing_data['s']['frequency'] == $fv) ? 'checked' : ''; ?>>
                                        <span class="label-text" style="font-size: 0.7rem;"><?php echo __($fk); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 style="font-size: 0.9rem; margin-bottom: 1rem; color: var(--text-muted); text-transform: uppercase;"><?php echo __('medical.followup.pain_act_label'); ?></h4>
                        <div class="medical-form-grid" style="grid-template-columns: 1fr 1fr;">
                            <?php 
                            $act_options = [
                                'medical.followup.act_stand' => 'Đứng',
                                'medical.followup.act_sit' => 'Ngồi',
                                'medical.followup.act_lie' => 'Nằm',
                                'medical.followup.act_walk' => 'Đi bộ',
                                'medical.followup.act_bend' => 'Cúi người',
                                'medical.followup.act_lift' => 'Nâng vật nặng',
                                'medical.followup.act_all' => 'Toàn bộ HĐ'
                            ];
                            foreach ($act_options as $ak => $av): ?>
                                <label class="checkbox-card small">
                                    <input type="checkbox" name="soap[s][activities][]" value="<?php echo $av; ?>" <?php echo (isset($existing_data['s']['activities']) && in_array($av, $existing_data['s']['activities'])) ? 'checked' : ''; ?>>
                                    <span class="label-text" style="font-size: 0.8rem;"><?php echo __($ak); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div style="margin-top: 1.5rem;">
                            <label class="form-label" style="text-transform: uppercase; font-size: 0.85rem; color: var(--text-muted);"><?php echo __('medical.followup.vas_label'); ?><span id="pain-val" style="color: var(--primary); font-weight: 800;"><?php echo isset($existing_data['s']['vas']) ? $existing_data['s']['vas'] : '5'; ?></span>/10</label>
                            <input type="range" name="soap[s][vas]" min="0" max="10" value="<?php echo isset($existing_data['s']['vas']) ? $existing_data['s']['vas'] : '5'; ?>" class="slider">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. OBJECTIVE (O) -->
        <div style="margin-bottom: 3rem;">
            <h3 style="font-size: 1.1rem; color: var(--text-main); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem;">
                <span style="background: #10b981; color: white; width: 24px; height: 24px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">O</span>
                <?php echo __('medical.followup.part2_title'); ?>
            </h3>
            
            <div style="background: #f8fafc; border-radius: 16px; padding: 1.5rem; border: 1px solid #e2e8f0;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div>
                        <h4 style="font-size: 0.9rem; margin-bottom: 1rem; color: var(--text-muted); text-transform: uppercase;"><?php echo __('medical.followup.muscle_tone_label'); ?></h4>
                        <textarea name="soap[o][muscle_tone]" class="form-input" rows="2" placeholder="<?php echo __('medical.followup.muscle_tone_placeholder'); ?>" style="padding: 0.75rem; border-radius: 12px;"><?php echo isset($existing_data['o']['muscle_tone']) ? e($existing_data['o']['muscle_tone']) : ''; ?></textarea>
                        
                        <div style="margin-top: 1rem;">
                            <h4 style="font-size: 0.9rem; margin-bottom: 1rem; color: var(--text-muted); text-transform: uppercase;"><?php echo __('medical.followup.severity_label'); ?></h4>
                            <div style="display: flex; gap: 0.5rem;">
                                <?php 
                                $sev_options = [
                                    'medical.followup.sev_mild' => 'Nhẹ (Mild)',
                                    'medical.followup.sev_mod' => 'Vừa (Mod)',
                                    'medical.followup.sev_sev' => 'Nặng (Sev)'
                                ];
                                foreach ($sev_options as $sk => $sv): ?>
                                    <label class="checkbox-tag">
                                        <input type="radio" name="soap[o][severity]" value="<?php echo $sv; ?>" <?php echo (isset($existing_data['o']['severity']) && $existing_data['o']['severity'] == $sv) ? 'checked' : ''; ?>>
                                        <span><?php echo __($sk); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 style="font-size: 0.9rem; margin-bottom: 1rem; color: var(--text-muted); text-transform: uppercase;"><?php echo __('medical.followup.rom_limit_label'); ?></h4>
                        <div class="medical-form-grid" style="grid-template-columns: 1fr 1fr 1fr;">
                            <?php 
                            $rom_options = [
                                'medical.followup.rom_neck' => 'Cổ',
                                'medical.followup.rom_thoracic' => 'Ngực',
                                'medical.followup.rom_lumbar' => 'Thắt lưng'
                            ];
                            foreach ($rom_options as $rk => $rv): ?>
                                <label class="checkbox-card small">
                                    <input type="checkbox" name="soap[o][rom_limit][]" value="<?php echo $rv; ?>" <?php echo (isset($existing_data['o']['rom_limit']) && in_array($rv, $existing_data['o']['rom_limit'])) ? 'checked' : ''; ?>>
                                    <span class="label-text"><?php echo __($rk); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top: 1rem;">
                            <h4 style="font-size: 0.9rem; margin-bottom: 0.5rem; color: var(--text-muted);"><?php echo __('medical.followup.notes_o_label'); ?></h4>
                            <input type="text" name="soap[o][notes]" class="form-input" style="padding: 0.5rem;" placeholder="<?php echo __('medical.followup.notes_o_placeholder'); ?>" value="<?php echo isset($existing_data['o']['notes']) ? e($existing_data['o']['notes']) : ''; ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. ASSESSMENT & ADJUSTMENT (A) -->
        <div style="margin-bottom: 3rem;">
            <h3 style="font-size: 1.1rem; color: var(--text-main); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem;">
                <span style="background: #f59e0b; color: white; width: 24px; height: 24px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">A</span>
                <?php echo __('medical.followup.part3_title'); ?>
            </h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                <?php foreach ($spine_nodes as $group => $nodes): ?>
                    <div style="background: #f8fafc; border-radius: 16px; padding: 1.25rem; border: 1px solid #e2e8f0;">
                        <h4 style="font-size: 0.85rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 1rem; text-align: center;"><?php echo $group; ?></h4>
                        <table style="width: 100%; border-spacing: 0 4px;">
                            <thead>
                                <tr style="font-size: 0.7rem; color: #94a3b8; text-align: center;">
                                    <th style="width: 33%;">L</th>
                                    <th style="width: 33%;"><?php echo __('medical.followup.vert_label'); ?></th>
                                    <th style="width: 33%;">R</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($nodes as $key => $label): ?>
                                    <tr>
                                        <td style="text-align: center;">
                                            <label class="matrix-btn">
                                                <input type="checkbox" name="soap[a][spine][<?php echo $key; ?>][L]" value="1" <?php echo (isset($existing_data['a']['spine'][$key]['L']) && $existing_data['a']['spine'][$key]['L'] == '1') ? 'checked' : ''; ?>>
                                                <span>L</span>
                                            </label>
                                        </td>
                                        <td style="text-align: center; font-weight: 700; color: var(--text-main); font-size: 0.9rem;"><?php echo $label; ?></td>
                                        <td style="text-align: center;">
                                            <label class="matrix-btn">
                                                <input type="checkbox" name="soap[a][spine][<?php echo $key; ?>][R]" value="1" <?php echo (isset($existing_data['a']['spine'][$key]['R']) && $existing_data['a']['spine'][$key]['R'] == '1') ? 'checked' : ''; ?>>
                                                <span>R</span>
                                            </label>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="margin-top: 1.5rem; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 16px; padding: 1.5rem;">
                <h4 style="font-size: 0.9rem; margin-bottom: 1rem; color: #92400e; text-transform: uppercase;"><?php echo __('medical.followup.physio_rehab_label'); ?></h4>
                <div class="medical-form-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
                    <?php 
                    $pt_options = [
                        'medical.followup.pt_heat_cold' => 'Nhiệt/Lạnh',
                        'medical.followup.pt_dems' => 'Điện xung (DEMS)',
                        'medical.followup.pt_ultrasound' => 'Siêu âm (Ultrasound)',
                        'medical.followup.pt_trigger' => 'Giải cơ (Trigger Point)',
                        'medical.followup.pt_massage' => 'Massage trị liệu',
                        'medical.followup.pt_traction' => 'Kéo giãn (Traction)',
                        'medical.followup.pt_manual' => 'Trị liệu bằng tay',
                        'medical.followup.pt_rehab' => 'Bài tập chức năng'
                    ];
                    foreach ($pt_options as $pk => $pv): ?>
                        <label class="checkbox-card small" style="background: white;">
                            <input type="checkbox" name="soap[a][physiotherapy][]" value="<?php echo $pv; ?>" <?php echo (isset($existing_data['a']['physiotherapy']) && in_array($pv, $existing_data['a']['physiotherapy'])) ? 'checked' : ''; ?>>
                            <span class="label-text" style="font-size: 0.75rem;"><?php echo __($pk); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- 4. PLAN (P) -->
        <div style="margin-bottom: 3rem;">
            <h3 style="font-size: 1.1rem; color: var(--text-main); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem;">
                <span style="background: #6366f1; color: white; width: 24px; height: 24px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">P</span>
                <?php echo __('medical.followup.part4_title'); ?>
            </h3>
            
            <div style="background: #f8fafc; border-radius: 16px; padding: 1.5rem; border: 1px solid #e2e8f0;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div>
                        <h4 style="font-size: 0.9rem; margin-bottom: 1rem; color: var(--text-muted); text-transform: uppercase;"><?php echo __('medical.followup.eval_today_label'); ?></h4>
                        <div style="display: flex; gap: 0.5rem;">
                            <?php 
                            $eval_options = [
                                'medical.followup.eval_good' => 'Tiến triển tốt',
                                'medical.followup.eval_slow' => 'Tiến triển chậm',
                                'medical.followup.eval_no' => 'Chưa cải thiện'
                            ];
                            foreach ($eval_options as $ek => $ev): ?>
                                <label class="checkbox-tag">
                                    <input type="radio" name="soap[p][evaluation]" value="<?php echo $ev; ?>" <?php echo (isset($existing_data['p']['evaluation']) && $existing_data['p']['evaluation'] == $ev) ? 'checked' : ''; ?>>
                                    <span><?php echo __($ek); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div>
                        <h4 style="font-size: 0.9rem; margin-bottom: 1rem; color: var(--text-muted); text-transform: uppercase;"><?php echo __('medical.followup.freq_proposal_label'); ?></h4>
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <?php 
                            $p_freq_options = [
                                'medical.followup.plan_freq_3_week' => '3 lần/tuần',
                                'medical.followup.plan_freq_2_week' => '2 lần/tuần',
                                'medical.followup.plan_freq_1_week' => '1 lần/tuần',
                                'medical.followup.plan_freq_prn' => 'PRN (Khi cần)'
                            ];
                            foreach ($p_freq_options as $fk => $fv): ?>
                                <label class="checkbox-tag">
                                    <input type="radio" name="soap[p][frequency]" value="<?php echo $fv; ?>" <?php echo (isset($existing_data['p']['frequency']) && $existing_data['p']['frequency'] == $fv) ? 'checked' : ''; ?>>
                                    <span><?php echo __($fk); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="form-group" style="margin-top: 1.5rem;">
                    <label class="form-label" style="font-size: 0.85rem;"><?php echo __('medical.followup.plan_notes_label'); ?></label>
                    <textarea name="soap[p][notes]" class="form-input" rows="3" placeholder="<?php echo __('medical.followup.plan_notes_placeholder'); ?>"><?php echo isset($existing_data['p']['notes']) ? e($existing_data['p']['notes']) : ''; ?></textarea>
                </div>
            </div>
        </div>

        <div style="margin-top: 3rem; display: flex; gap: 1rem; justify-content: flex-end;">
            <a href="../patients/view.php?id=<?php echo $patient_id; ?>" class="btn" style="background: #f1f5f9; color: var(--text-main); padding: 1rem 2.5rem;"><?php echo __('common.cancel'); ?></a>
            <button type="submit" class="btn btn-primary" style="padding: 1rem 3rem; font-weight: 700; font-size: 1.1rem;">
                <i class="fas fa-save"></i> <?php echo __('medical.followup.btn_save'); ?>
            </button>
        </div>
    </form>
</div>

<style>
.checkbox-tag { cursor: pointer; }
.checkbox-tag input { position: absolute; opacity: 0; }
.checkbox-tag span {
    display: inline-block;
    padding: 0.5rem 1rem;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    color: #64748b;
    transition: all 0.2s;
}
.checkbox-tag:hover span { border-color: var(--primary); }
.checkbox-tag input:checked + span {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.2);
}

.checkbox-card.small { padding: 0.75rem; margin-bottom: 0; }
.checkbox-card.small .label-text { font-size: 0.85rem; }

.slider {
    -webkit-appearance: none;
    width: 100%;
    height: 8px;
    border-radius: 5px;
    background: #e2e8f0;
    outline: none;
}
.slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: var(--primary);
    cursor: pointer;
    box-shadow: 0 0 10px rgba(99, 102, 241, 0.4);
}

.matrix-btn {
    display: inline-block;
    cursor: pointer;
    width: 44px;
    height: 44px;
}
.matrix-btn input { position: absolute; opacity: 0; }
.matrix-btn span {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-weight: 700;
    color: #94a3b8;
    transition: all 0.2s;
}
.matrix-btn:hover span { border-color: var(--primary); color: var(--primary); }
.matrix-btn:has(input:checked) span {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
}
</style>

<script>
const slider = document.querySelector('.slider');
if (slider) {
    slider.addEventListener('input', function() {
        document.getElementById('pain-val').textContent = this.value;
    });
}
</script>

<?php require_once '../../templates/footer.php'; ?>
