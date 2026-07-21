<?php
// modules/medical/follow_up.php

require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_medical');

$patient_id = (int)($_GET['patient_id'] ?? 0);
$session_id = isset($_GET['session_id']) ? (int)$_GET['session_id'] : null;
$record_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$db = getDB();

// Load existing data if editing
$existing_data = [];
if ($record_id) {
    $stmt = $db->prepare("SELECT history_data FROM medical_history WHERE id = ?");
    $stmt->execute([$record_id]);
    $json = $stmt->fetchColumn();
    $existing_data = json_decode($json, true) ?: [];
}

// Fetch previous follow-up (SOAP) record if it exists for comparison
$prev_data = [];
$prev_date = '';
$stmt = $db->prepare("
    SELECT h.history_data, h.created_at 
    FROM medical_history h 
    WHERE h.patient_id = ? AND (h.type = 'soap_note' OR h.type = 'soap_note_v2') " . ($record_id ? "AND h.id < ?" : "") . "
    ORDER BY h.id DESC LIMIT 1
");
if ($record_id) {
    $stmt->execute([$patient_id, $record_id]);
} else {
    $stmt->execute([$patient_id]);
}
$prev_record = $stmt->fetch(PDO::FETCH_ASSOC);
if ($prev_record) {
    $prev_data = json_decode($prev_record['history_data'], true) ?: [];
    $prev_date = date('d/m/Y', strtotime($prev_record['created_at']));
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $soap_data = json_encode(isset($_POST['soap']) ? $_POST['soap'] : [], JSON_UNESCAPED_UNICODE);
    
    if ($record_id) {
        $stmt = $db->prepare("UPDATE medical_history SET history_data = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$soap_data, $record_id]);
    } else {
        $stmt = $db->prepare("
            INSERT INTO medical_history (patient_id, session_id, type, history_data, created_by)
            VALUES (?, ?, 'soap_note_v2', ?, ?)
        ");
        $stmt->execute([$patient_id, $session_id, $soap_data, $_SESSION['user_id']]);
        $new_id = $db->lastInsertId();
    }
    
    set_flash(__('medical.followup.msg_success'));
    
    if (!empty($_POST['lang_switch_autosave'])) {
        $redir_url = $_SERVER['REQUEST_URI'];
        if (!$record_id && isset($new_id)) {
            $redir_url .= (strpos($redir_url, '?') !== false ? '&' : '?') . 'id=' . $new_id;
        }
        redirect($redir_url);
    } elseif ($session_id) {
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

$page_title = __('medical.followup.page_title') . ' (Mẫu Mới)';
$current_page = 'medical';
require_once '../../templates/header.php';

// Define Behandlungsliste (Treatment List) Nodes
$spine_nodes = [
    'HWS (Cổ)' => ['C1' => 'Atlas', 'C2' => 'Axis', 'C3' => 'C3', 'C4' => 'C4', 'C5' => 'C5', 'C6' => 'C6', 'C7' => 'C7'],
    'BWS (Ngực)' => ['D1' => 'D1', 'D2' => 'D2', 'D3' => 'D3', 'D4' => 'D4', 'D5' => 'D5', 'D6' => 'D6', 'D7' => 'D7', 'D8' => 'D8', 'D9' => 'D9', 'D10' => 'D10', 'D11' => 'D11', 'D12' => 'D12'],
    'LWS (Thắt lưng)' => ['L1' => 'L1', 'L2' => 'L2', 'L3' => 'L3', 'L4' => 'L4', 'L5' => 'L5'],
    'Becken (Khung chậu)' => ['Sac' => 'Sacrum', 'Coc' => 'Coccyx', 'Rlli' => 'R Ilium', 'Llli' => 'L Ilium'],
    'Obere Extremitäten (Chi trên)' => ['Schulter' => 'Schulter (Vai)', 'Ellbogen' => 'Ellbogen (Khuỷu tay)', 'Handgelenk' => 'Handgelenk (Cổ tay)'],
    'Untere Extremitäten (Chi dưới)' => ['Hüfte' => 'Hüfte (Khớp háng)', 'Knie' => 'Knie (Đầu gối)', 'Sprunggelenk' => 'Sprunggelenk (Cổ chân)']
];
?>

<div class="card" style="background: var(--glass-bg); backdrop-filter: blur(20px); max-width: 1000px; margin: 0 auto;">
    <div style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: start;">
        <div>
            <h2 style="margin: 0; font-weight: 800; color: var(--primary);"><i class="fas fa-notes-medical"></i> <?php echo __('medical.followup.title'); ?> <span style="font-size: 0.6em; background: #ef4444; color: white; padding: 2px 6px; border-radius: 4px; vertical-align: middle;">V2</span></h2>
            <p style="color: var(--text-muted); margin-top: 0.25rem;"><?php echo __('medical.followup.patient_label'); ?><strong style="color: var(--text-main);"><?php echo e($patient_name); ?></strong></p>
        </div>
        <div style="background: #eef2ff; color: #4f46e5; padding: 0.5rem 1rem; border-radius: 12px; font-weight: 700;"><?php echo __('medical.followup.badge'); ?></div>
    </div>

    <?php if (!empty($prev_data)): ?>
        <div style="background: #fffbeb; border: 1px solid #fef3c7; border-radius: 12px; padding: 1.25rem; margin-bottom: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="margin: 0; font-size: 0.95rem; font-weight: 800; color: #b45309;"><i class="fas fa-history"></i> Thông tin buổi khám trước (<?php echo $prev_date; ?>)</h3>
                <button type="button" onclick="document.getElementById('prev-notes-content').style.display = document.getElementById('prev-notes-content').style.display === 'none' ? 'block' : 'none';" class="btn btn-sm" style="background: white; border: 1px solid #fcd34d; color: #b45309; border-radius: 6px; padding: 0.25rem 0.5rem; font-size: 0.8rem;">Ẩn/Hiện</button>
            </div>
            <div id="prev-notes-content" style="display: block; font-size: 0.85rem; color: #78350f;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div>
                        <strong>Tiến triển (S):</strong> <?php echo isset($prev_data['s']['progress']) ? e(__($prev_data['s']['progress'])) : '-'; ?><br>
                        <strong>Mức độ đau:</strong> <?php echo isset($prev_data['s']['vas']) ? e($prev_data['s']['vas']) : '-'; ?>/5<br>
                        <strong>Ghi chú (S):</strong> <?php echo isset($prev_data['s']['notes']) ? nl2br(e($prev_data['s']['notes'])) : '-'; ?>
                    </div>
                    <div>
                        <strong>Trương lực cơ (O):</strong> <?php echo isset($prev_data['o']['muscle_tone']) ? e($prev_data['o']['muscle_tone']) : '-'; ?><br>
                        <strong>Ghi chú (O):</strong> <?php echo isset($prev_data['o']['notes']) ? e($prev_data['o']['notes']) : '-'; ?><br>
                        <strong>Kế hoạch (P):</strong> <?php echo isset($prev_data['p']['notes']) ? e($prev_data['p']['notes']) : '-'; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST">
        <?php echo csrf_field(); ?>
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
                                'Viel besser (Cải thiện rõ rệt) 📈' => 'Viel besser',
                                'Besser (Có cải thiện) 📈' => 'Besser',
                                'Unverändert (Không thay đổi) 📊' => 'Unverändert',
                                'Schlechter (Tệ hơn) 📉' => 'Schlechter'
                            ];
                            foreach ($progress_options as $label => $pv): ?>
                                <label class="checkbox-tag">
                                    <input type="radio" name="soap[s][progress]" value="<?php echo $pv; ?>" <?php echo (isset($existing_data['s']['progress']) && $existing_data['s']['progress'] == $pv) ? 'checked' : ''; ?>>
                                    <span><?php echo $label; ?></span>
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
                            <label class="form-label" style="text-transform: uppercase; font-size: 0.85rem; color: var(--text-muted);">Mức độ đau (Degree of Pain) (Xanh -> Đỏ)</label>
                            <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem;">
                                <?php 
                                $pain_colors = [
                                    1 => ['#dcfce7', '#16a34a'], // Xanh lá nhạt
                                    2 => ['#bbf7d0', '#15803d'], // Xanh lá đậm
                                    3 => ['#fef08a', '#a16207'], // Vàng
                                    4 => ['#fed7aa', '#c2410c'], // Cam
                                    5 => ['#fecaca', '#b91c1c']  // Đỏ
                                ];
                                $current_vas = isset($existing_data['s']['vas']) ? (int)$existing_data['s']['vas'] : 3;
                                foreach ($pain_colors as $val => $color): 
                                    $is_checked = ($current_vas == $val);
                                ?>
                                    <label style="flex: 1; cursor: pointer; text-align: center; position: relative;">
                                        <input type="radio" name="soap[s][vas]" value="<?php echo $val; ?>" <?php echo $is_checked ? 'checked' : ''; ?> style="position: absolute; opacity: 0; pointer-events: none;">
                                        <div style="
                                            background: <?php echo $is_checked ? $color[1] : $color[0]; ?>; 
                                            color: <?php echo $is_checked ? 'white' : $color[1]; ?>; 
                                            border: 2px solid <?php echo $color[1]; ?>;
                                            padding: 0.5rem; 
                                            border-radius: 8px; 
                                            font-weight: 800; 
                                            transition: all 0.2s;
                                        " onmouseover="if(!this.previousElementSibling.checked){this.style.background='<?php echo $color[1]; ?>'; this.style.color='white';}" onmouseout="if(!this.previousElementSibling.checked){this.style.background='<?php echo $color[0]; ?>'; this.style.color='<?php echo $color[1]; ?>';}">
                                            <?php echo $val; ?>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div style="margin-top: 1.5rem; padding: 1rem; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px;">
                            <label style="display: flex; align-items: flex-start; gap: 0.5rem; cursor: pointer; font-weight: 600; color: #166534;">
                                <input type="checkbox" name="soap[s][schmerzfrei]" value="1" <?php echo (isset($existing_data['s']['schmerzfrei']) && $existing_data['s']['schmerzfrei'] == 1) ? 'checked' : ''; ?> style="margin-top: 3px;">
                                <span style="line-height: 1.4;">Patient war nach der Behandlung schmerzfrei / oder ist auf einem sehr guten Heilungsweg. <br><small style="font-weight: 400; opacity: 0.8;">(Bệnh nhân hết đau sau điều trị hoặc đang trong quá trình hồi phục rất tốt)</small></span>
                            </label>
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
