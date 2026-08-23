<?php
// modules/medical/forms/chiropractic_v2.php

// 0. AUTO DATA MIGRATION: TƯƠNG THÍCH NGƯỢC DỮ LIỆU CŨ (V1)
function migrate_to_v2($input_data) {
    if (!empty($input_data) && (isset($input_data['spine']) || isset($input_data['s']) || isset($input_data['o']) || isset($input_data['a']))) {
        $v2 = [];
        
        // 1. Phân mục S (Subjective) -> Subjective V2
        if (isset($input_data['s'])) {
            $v2['s_vas'] = $input_data['s']['vas'] ?? '5';
            $v2['s_progress'] = $input_data['s']['progress'] ?? '';
            $v2['s_frequency'] = $input_data['s']['frequency'] ?? '';
            $v2['s_activities'] = $input_data['s']['activities'] ?? [];
            $v2['pain_locations'] = [
                ['name' => 'Khu vực đau chính (từ bản cũ)', 'vas' => $v2['s_vas'], 'trend' => '', 'symptoms' => [], 'notes' => '']
            ];
        }
        
        // 2. Phân mục O (Objective) -> Objective V2
        if (isset($input_data['o'])) {
            $v2['muscle_hypertonicity'] = $input_data['o']['muscle_tone'] ?? '';
            $v2['muscle_severity'] = $input_data['o']['severity'] ?? '';
            $v2['rom_limitations'] = $input_data['o']['rom_limit'] ?? [];
            $v2['symptom_notes'] = $input_data['o']['notes'] ?? '';
        }
        
        // 3. Phân mục A (Assessment/Physio) -> Physio V2
        if (isset($input_data['a'])) {
            $pt_map = [
                'Nhiệt/Lạnh' => 'Nhiệt/Lạnh (Heat/Cryo)',
                'Trị liệu bằng tay' => 'Trị liệu bằng tay (Manual Therapy)'
            ];
            $physio = [];
            $old_physio = $input_data['a']['physiotherapy'] ?? [];
            if (is_array($old_physio)) {
                foreach ($old_physio as $item) {
                    $physio[] = $pt_map[$item] ?? $item;
                }
            }
            $v2['physiotherapy'] = $physio;
        }
        
        // 4. Phân mục P (Plan) -> Plan V2
        if (isset($input_data['p'])) {
            $v2['progress_assessment'] = $input_data['p']['evaluation'] ?? '';
            $v2['treatment_frequency'] = $input_data['p']['frequency'] ?? '';
            $v2['plan_notes'] = $input_data['p']['notes'] ?? '';
        }
        
        // 5. SPINE MATRIX (Subluxation)
        $v2['subluxation'] = [
            'cervical' => [], 'thoracic' => [], 'lumbar' => [], 'sacrum' => [], 'peripheral' => [], 'becken' => []
        ];
        
        if (isset($input_data['spine'])) {
            foreach ($input_data['spine'] as $node => $sides) {
                $node = str_replace(['D'], ['T'], $node); // Đề phòng D1-D12 cũ
                $sides = is_array($sides) ? $sides : [];
                $sides_keys = array_keys(array_filter($sides)); // Lấy ['L', 'R', 'A', 'P']
                
                // Cervical
                if ($node === 'C1') $v2['subluxation']['cervical']['Atlas (C1)'] = $sides_keys;
                elseif ($node === 'C2') $v2['subluxation']['cervical']['Axis (C2)'] = $sides_keys;
                elseif (in_array($node, ['C3','C4','C5','C6','C7'])) $v2['subluxation']['cervical'][$node] = $sides_keys;
                
                // Thoracic (Cần map A -> Interior, P -> Posterior)
                elseif (preg_match('/^T\d+$/', $node)) {
                    $mapped = [];
                    if (in_array('L', $sides_keys)) $mapped[] = 'L';
                    if (in_array('R', $sides_keys)) $mapped[] = 'R';
                    if (in_array('A', $sides_keys)) $mapped[] = 'Interior';
                    if (in_array('P', $sides_keys)) $mapped[] = 'Posterior';
                    $v2['subluxation']['thoracic'][$node] = $mapped;
                }
                
                // Lumbar
                elseif (preg_match('/^L\d+$/', $node)) {
                    $v2['subluxation']['lumbar'][$node] = $sides_keys;
                }
                
                // Sac (Xương cùng)
                elseif ($node === 'Sac' || $node === 'Coc') {
                    $v2['subluxation']['sacrum']['S1'] = array_keys(array_filter($sides)); // Gom tạm vào S1
                }
            }
        }
        
        if (isset($input_data['becken'])) {
            foreach ($input_data['becken'] as $type => $sides) {
                $v2['subluxation']['becken'][] = $type; // V2 Becken chỉ xài một chiều (Tên Becken)
            }
        }
        
        if (isset($input_data['joints'])) {
            $joint_map = [
                'Khớp vai' => 'Khớp cùng đòn (ACG)',
                'Khớp khuỷu tay' => 'Tennisarm',
                'Khớp háng' => 'Khớp háng (Hip)',
                'Khớp gối' => 'Gối (Knee)',
                'Khớp cổ chân' => 'Cổ chân (Ankle)',
                'Khớp cổ tay' => 'Cổ chân (Ankle)' // Bỏ vào tạm vì V2 ko có cổ tay, hoặc có thể custom
            ];
            foreach ($input_data['joints'] as $joint => $sides) {
                $mapped_joint = $joint_map[$joint] ?? $joint;
                $sides = is_array($sides) ? $sides : [];
                $v2['subluxation']['peripheral'][$mapped_joint] = array_keys(array_filter($sides));
            }
        }
        
        return $v2;
    }
    return $input_data;
}

$data = migrate_to_v2($data);

// Fetch previous session data for historical overlay
$prev_data = [];
$bone_history = [];
if (!empty($patient_id)) {
    global $db;
    if (!empty($history_id)) {
        $stmt_prev = $db->prepare("SELECT created_at, history_data FROM medical_history WHERE patient_id = ? AND type = 'chiropractic' AND id < ? ORDER BY id DESC LIMIT 10");
        $stmt_prev->execute([$patient_id, $history_id]);
    } else {
        $stmt_prev = $db->prepare("SELECT created_at, history_data FROM medical_history WHERE patient_id = ? AND type = 'chiropractic' ORDER BY id DESC LIMIT 10");
        $stmt_prev->execute([$patient_id]);
    }
    
    $all_prev = $stmt_prev->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($all_prev)) {
        $prev_data = migrate_to_v2(json_decode($all_prev[0]['history_data'], true) ?: []);
        
        $flatten = function($array, $prefix, $ds) use (&$flatten, &$bone_history) {
            foreach ($array as $k => $v) {
                $new_prefix = $prefix === '' ? $k : $prefix . '||' . $k;
                $is_assoc = false;
                if (is_array($v)) {
                    foreach(array_keys($v) as $key) {
                        if (!is_int($key)) { $is_assoc = true; break; }
                    }
                }
                if (is_array($v) && $is_assoc) {
                    $flatten($v, $new_prefix, $ds);
                } else if (is_array($v)) {
                    foreach ($v as $val) {
                        $full_path = $new_prefix . '||' . ltrim(trim($val), '||');
                        if (!isset($bone_history[$full_path])) $bone_history[$full_path] = [];
                        $bone_history[$full_path][] = $ds;
                    }
                } else if ($v !== null && $v !== '') {
                    $full_path = $new_prefix . '||' . ltrim(trim($v), '||');
                    if (!isset($bone_history[$full_path])) $bone_history[$full_path] = [];
                    $bone_history[$full_path][] = $ds;
                }
            }
        };
        
        foreach ($all_prev as $row) {
            $date_str = date('d/m/Y', strtotime($row['created_at']));
            $data_v2 = migrate_to_v2(json_decode($row['history_data'], true) ?: []);
            if (isset($data_v2['subluxation'])) {
                $flatten(['subluxation' => $data_v2['subluxation']], '', $date_str);
            }
        }
    }
}

$exam_date_val = $data['exam_date'] ?? date('Y-m-d');
$session_num_val = $data['exam_session_number'] ?? '';
$new_injury_status = $data['new_injury_status'] ?? 'Không';
$new_injury_date = $data['new_injury_date'] ?? '';
$pain_locations = $data['pain_locations'] ?? [
    ['name' => '', 'vas' => '', 'trend' => '', 'symptoms' => [], 'notes' => '']
];
$s_progress = $data['s_progress'] ?? '';
$s_frequency = $data['s_frequency'] ?? '';
$s_activities = $data['s_activities'] ?? [];
$s_vas = $data['s_vas'] ?? '5';
?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2.5rem; background: #f8fafc; padding: 1.5rem; border-radius: 18px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
    <div class="form-group" style="margin: 0;">
        <label class="form-label" style="font-weight: 800; color: var(--primary);"><i class="fas fa-calendar-alt"></i> <?php echo __('medical.v2.exam_date'); ?></label>
        <input type="date" name="history[exam_date]" class="form-input" value="<?php echo e($exam_date_val); ?>">
    </div>
    <div class="form-group" style="margin: 0;">
        <label class="form-label" style="font-weight: 800; color: var(--primary);"><i class="fas fa-hashtag"></i> <?php echo __('medical.v2.exam_session'); ?></label>
        <input type="text" name="history[exam_session_number]" class="form-input" placeholder="<?php echo __('medical.v2.exam_session_ph'); ?>" value="<?php echo e($session_num_val); ?>">
    </div>
</div>

<!-- PHẦN 1: CHỦ QUAN -->
<div style="margin-bottom: 3rem;">
    <h3 style="font-size: 1.1rem; text-transform: uppercase; color: var(--primary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">
        <i class="fas fa-user-md"></i> <?php echo __('medical.v2.subj_title'); ?>
    </h3>
    
    <!-- Khối Tình trạng chung (Missing UI from Word Doc) -->
    <div class="premium-card" style="margin-bottom: 1.5rem; background: white; padding: 1.5rem; border-radius: 12px; border: 1px solid #e2e8f0;">

        <!-- Tiến triển chung -->
        <h4 style="font-size: 0.9rem; margin-bottom: 0.75rem; color: #1e293b;"><i class="fas fa-chart-line"></i> <?php echo __('medical.v2.subj_progress'); ?></h4>
        <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 1.5rem;">
            <?php 
            $prog_options = ['medical.followup.progress_much_better'=>'Cải thiện rõ rệt', 'medical.followup.progress_better'=>'Cải thiện nhẹ', 'medical.followup.progress_no_change'=>'Không thay đổi', 'medical.followup.progress_worse'=>'Tệ hơn'];
            foreach($prog_options as $pk => $po): ?>
                <label class="checkbox-tag">
                    <input type="radio" name="history[s_progress]" value="<?php echo $po; ?>" <?php echo $s_progress === $po ? 'checked' : ''; ?>>
                    <span><?php echo __($pk ?? $po); ?></span>
                </label>
            <?php endforeach; ?>
        </div>

        <!-- Chấn thương mới -->
        <h4 style="font-size: 0.9rem; margin-bottom: 0.75rem; color: #1e293b;"><i class="fas fa-crutch"></i> <?php echo __('medical.v2.subj_new_injury'); ?></h4>
        <div style="display: flex; gap: 1.5rem; align-items: center; flex-wrap: wrap; margin-bottom: 1.5rem;">
            <label class="checkbox-tag">
                <input type="radio" name="history[new_injury_status]" value="Không" <?php echo $new_injury_status === 'Không' ? 'checked' : ''; ?> onchange="document.getElementById('div_injury_date').style.display='none';">
                <span><?php echo __('common.no'); ?></span>
            </label>
            <label class="checkbox-tag">
                <input type="radio" name="history[new_injury_status]" value="Có" <?php echo $new_injury_status === 'Có' ? 'checked' : ''; ?> onchange="document.getElementById('div_injury_date').style.display='block';">
                <span><?php echo __('common.yes'); ?></span>
            </label>
            <div id="div_injury_date" style="display: <?php echo $new_injury_status === 'Có' ? 'block' : 'none'; ?>; flex: 1;">
                <input type="text" name="history[new_injury_date]" class="form-input" placeholder="<?php echo __('medical.v2.new_injury_ph'); ?>" value="<?php echo e($new_injury_date); ?>">
            </div>
        </div>

        <!-- Tần suất triệu chứng -->
        <h4 style="font-size: 0.9rem; margin-bottom: 0.75rem; color: #1e293b;"><i class="fas fa-clock"></i> <?php echo __('medical.v2.subj_freq'); ?></h4>
        <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 1.5rem;">
            <?php 
            $freq_options = [
        'medical.followup.freq_0_25' => 'Thỉnh thoảng (0-25%)',
        'medical.followup.freq_25_50' => 'Lúc có lúc không (25-50%)',
        'medical.followup.freq_50_75' => 'Thường xuyên (50-75%)',
        'medical.followup.freq_75_100' => 'Liên tục (75-100%)'
    ];
            foreach($freq_options as $fk => $fo): ?>
                <label class="checkbox-tag">
                    <input type="radio" name="history[s_frequency]" value="<?php echo $fo; ?>" <?php echo $s_frequency === $fo ? 'checked' : ''; ?>>
                    <span><?php echo __($fk); ?></span>
                </label>
            <?php endforeach; ?>
        </div>

        <!-- Đau khi thực hiện các hoạt động -->
        <h4 style="font-size: 0.9rem; margin-bottom: 0.75rem; color: #1e293b;"><i class="fas fa-running"></i> <?php echo __('medical.v2.subj_act'); ?></h4>
        <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem;">
            <?php 
            $act_options = [
        'medical.followup.act_lift' => 'Nâng vật nặng',
        'medical.followup.act_stand' => 'Đứng',
        'medical.followup.act_sit' => 'Ngồi',
        'medical.followup.act_lie' => 'Nằm',
        'medical.followup.act_bend' => 'Cúi người',
        'medical.v2.act_sleep' => 'Ngủ',
        'medical.v2.act_drive' => 'Lái xe',
        'medical.followup.act_walk' => 'Đi bộ',
        'medical.followup.act_all' => 'Mọi hoạt động hàng ngày'
    ];
            foreach($act_options as $ak => $ao): ?>
                <label class="checkbox-tag">
                    <input type="checkbox" name="history[s_activities][]" value="<?php echo $ao; ?>" <?php echo in_array($ao, $s_activities) ? 'checked' : ''; ?>>
                    <span><?php echo __($ak); ?></span>
                </label>
            <?php endforeach; ?>
        </div>

        <!-- Thang điểm đau VAS TỔNG -->
        <h4 style="font-size: 0.9rem; margin-bottom: 0.75rem; color: #1e293b;"><i class="fas fa-tachometer-alt"></i> <?php echo __('medical.v2.subj_vas_total'); ?> <span id="val_s_vas" style="color:red; font-weight:800; font-size:1.1rem;"><?php echo $s_vas; ?></span>/10</h4>
        <div style="width: 100%; max-width: 500px; padding: 0.5rem 0;">
            <input type="range" name="history[s_vas]" min="0" max="10" value="<?php echo $s_vas; ?>" class="slider" style="width: 100%;" oninput="document.getElementById('val_s_vas').innerText = this.value;">
            <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: #64748b; margin-top: 5px;">
                <span><?php echo __('medical.v2.vas_0'); ?></span>
                <span><?php echo __('medical.v2.vas_10'); ?></span>
            </div>
        </div>

    </div>

    <!-- Danh sách Vị trí đau -->
    <div class="premium-card" style="background: white; padding: 1.5rem; border-radius: 12px; border: 1px solid #e2e8f0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <label class="form-label" style="margin: 0; font-weight: 700;"><i class="fas fa-child"></i> <?php echo __('medical.v2.pain_locations'); ?></label>
            <button type="button" class="btn btn-sm" onclick="addPainLocation()" style="background: rgba(99, 102, 241, 0.1); color: var(--primary); font-weight: 800;">
                <i class="fas fa-plus"></i> <?php echo __('medical.v2.add_pain'); ?>
            </button>
        </div>
        
        <div id="pain_locations_container" style="display: flex; flex-direction: column; gap: 1.5rem;">
            <?php foreach($pain_locations as $idx => $pain): ?>
                <div class="pain-item" style="border: 1px solid #eef2f6; border-radius: 12px; padding: 1.25rem; position: relative;">
                    <button type="button" onclick="this.parentElement.remove()" style="position: absolute; top: 1rem; right: 1rem; background: #fee2e2; color: #ef4444; border: none; padding: 0.5rem; border-radius: 6px; cursor: pointer;">
                        <i class="fas fa-trash"></i>
                    </button>
                    
                    <div class="form-group" style="margin-bottom: 1rem; padding-right: 3rem;">
                        <label class="form-label" style="font-size: 0.8rem; opacity: 0.8;"><?php echo __('medical.v2.pain_loc'); ?></label>
                        <input type="text" name="history[pain_locations][<?php echo $idx; ?>][name]" class="form-input" placeholder="<?php echo __('medical.v2.pain_loc_ph'); ?>" value="<?php echo e($pain['name'] ?? ''); ?>">
                    </div>

                    <div style="display: grid; grid-template-columns: 80px 1fr; gap: 1.5rem; margin-bottom: 1rem;">
                        <div>
                            <label class="form-label" style="font-size: 0.8rem; opacity: 0.8;"><?php echo __('medical.v2.pain_vas'); ?></label>
                            <input type="number" name="history[pain_locations][<?php echo $idx; ?>][vas]" class="form-input" min="0" max="10" value="<?php echo e($pain['vas'] ?? ''); ?>">
                        </div>
                        <div>
                            <label class="form-label" style="font-size: 0.8rem; opacity: 0.8;"><?php echo __('medical.v2.pain_trend'); ?></label>
                            <div style="display: flex; gap: 1rem;">
                                <?php 
                                $trends = [
        'medical.v2.pain_trend_down' => 'Giảm',
        'medical.v2.pain_trend_up' => 'Tăng',
        'medical.v2.pain_trend_same' => 'Không đổi'
    ];
                                foreach($trends as $tk => $t): 
                                    $checked = (($pain['trend'] ?? '') == $t) ? 'checked' : '';
                                ?>
                                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                        <input type="radio" name="history[pain_locations][<?php echo $idx; ?>][trend]" value="<?php echo $t; ?>" <?php echo $checked; ?>> <?php echo __($t); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <label class="form-label" style="font-size: 0.8rem; opacity: 0.8;"><?php echo __('medical.v2.pain_symptoms'); ?></label>
                        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                            <?php 
                            $syms = ['Đau nhức', 'Tê bì', 'Cứng khớp', 'Chóng mặt/Đau đầu'];
                            $current_syms = $pain['symptoms'] ?? [];
                            foreach($syms as $s): 
                                $checked = in_array($s, $current_syms) ? 'checked' : '';
                            ?>
                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                    <input type="checkbox" name="history[pain_locations][<?php echo $idx; ?>][symptoms][]" value="<?php echo $s; ?>" <?php echo $checked; ?>> <?php echo __($s); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-group" style="margin: 0;">
                        <label class="form-label" style="font-size: 0.8rem; opacity: 0.8;"><?php echo __('Ghi chú nhanh'); ?></label>
                        <input type="text" name="history[pain_locations][<?php echo $idx; ?>][notes]" class="form-input" placeholder="..." value="<?php echo e($pain['notes'] ?? ''); ?>">
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
let painIndexCount = <?php echo count($pain_locations); ?>;

function addPainLocation() {
    const container = document.getElementById('pain_locations_container');
    const idx = painIndexCount++;
    const html = `
    <div class="pain-item" style="border: 1px solid #eef2f6; border-radius: 12px; padding: 1.25rem; position: relative;">
        <button type="button" onclick="this.parentElement.remove()" style="position: absolute; top: 1rem; right: 1rem; background: #fee2e2; color: #ef4444; border: none; padding: 0.5rem; border-radius: 6px; cursor: pointer;">
            <i class="fas fa-trash"></i>
        </button>
        <div class="form-group" style="margin-bottom: 1rem; padding-right: 3rem;">
            <label class="form-label" style="font-size: 0.8rem; opacity: 0.8;"><?php echo __('medical.v2.pain_loc'); ?></label>
            <input type="text" name="history[pain_locations][${idx}][name]" class="form-input" placeholder="<?php echo __('medical.v2.pain_loc_ph'); ?>">
        </div>
        <div style="display: grid; grid-template-columns: 80px 1fr; gap: 1.5rem; margin-bottom: 1rem;">
            <div>
                <label class="form-label" style="font-size: 0.8rem; opacity: 0.8;"><?php echo __('medical.v2.pain_vas'); ?></label>
                <input type="number" name="history[pain_locations][${idx}][vas]" class="form-input" min="0" max="10">
            </div>
            <div>
                <label class="form-label" style="font-size: 0.8rem; opacity: 0.8;"><?php echo __('medical.v2.pain_trend'); ?></label>
                <div style="display: flex; gap: 1rem;">
                    <label style="display:flex; align-items:center; gap:0.5rem;"><input type="radio" name="history[pain_locations][${idx}][trend]" value="Giảm"> <?php echo __('Giảm'); ?></label>
                    <label style="display:flex; align-items:center; gap:0.5rem;"><input type="radio" name="history[pain_locations][${idx}][trend]" value="Tăng"> <?php echo __('Tăng'); ?></label>
                    <label style="display:flex; align-items:center; gap:0.5rem;"><input type="radio" name="history[pain_locations][${idx}][trend]" value="Không đổi"> <?php echo __('Không đổi'); ?></label>
                </div>
            </div>
        </div>
        <div style="margin-bottom: 1rem;">
            <label class="form-label" style="font-size: 0.8rem; opacity: 0.8;"><?php echo __('medical.v2.pain_symptoms'); ?></label>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                ${['Đau nhức', 'Tê bì', 'Cứng khớp', 'Chóng mặt/Đau đầu'].map(s => 
                    `<label style="display:flex; align-items:center; gap:0.5rem;"><input type="checkbox" name="history[pain_locations][${idx}][symptoms][]" value="${s}"> ${s === 'Đau nhức' ? '<?php echo __('Đau nhức'); ?>' : s === 'Tê bì' ? '<?php echo __('Tê bì'); ?>' : s === 'Cứng khớp' ? '<?php echo __('Cứng khớp'); ?>' : '<?php echo __('Chóng mặt/Đau đầu'); ?>'}</label>`
                ).join('')}
            </div>
        </div>
        <div class="form-group" style="margin: 0;">
            <label class="form-label" style="font-size: 0.8rem; opacity: 0.8;"><?php echo __('Ghi chú nhanh'); ?></label>
            <input type="text" name="history[pain_locations][${idx}][notes]" class="form-input" placeholder="...">
        </div>
    </div>`;
    container.insertAdjacentHTML('beforeend', html);
}
</script>

<?php
// PHẦN 2 BIẾN
$muscle_hypertonicity = $data['muscle_hypertonicity'] ?? '';
$muscle_severity = $data['muscle_severity'] ?? '';
$fixation_cervical = $data['fixation_cervical'] ?? '';
$fixation_thoracic = $data['fixation_thoracic'] ?? '';
$fixation_lumbar = $data['fixation_lumbar'] ?? '';
$fixation_si_joint = $data['fixation_si_joint'] ?? '';
$fixation_peripheral = $data['fixation_peripheral'] ?? '';
$rom_limitations = $data['rom_limitations'] ?? [];
?>

<!-- PHẦN 2: KHÁCH QUAN -->
<div style="margin-bottom: 3rem;">
    <h3 style="font-size: 1.1rem; text-transform: uppercase; color: var(--primary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">
        <i class="fas fa-stethoscope"></i> <?php echo __('medical.v2.obj_title'); ?>
    </h3>
    <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 1.5rem;"><?php echo __('Kết quả thăm khám của bác sĩ.'); ?></p>

    <!-- 2.1 Sờ nắn & Trương lực cơ -->
    <div class="premium-card" style="margin-bottom: 1.5rem; background: white; padding: 1.5rem; border-radius: 12px; border: 1px solid #e2e8f0;">
        <label class="section-label-premium" style="margin-bottom: 1rem;"><i class="fas fa-hand-holding-medical"></i> <?php echo __('Sờ nắn (Palpation) & Trương lực cơ'); ?></label>
        
        <div class="form-group" style="margin-bottom: 1rem;">
            <label class="form-label" style="font-size: 0.85rem; opacity:0.8;"><?php echo __('medical.v2.obj_hypertonicity'); ?></label>
            <input type="text" name="history[muscle_hypertonicity]" class="form-input" placeholder="..." value="<?php echo e($muscle_hypertonicity); ?>">
        </div>

        <div style="display: flex; gap: 1.5rem; align-items: center;">
            <label class="form-label" style="font-size: 0.85rem; opacity:0.8; margin: 0;"><?php echo __('medical.v2.obj_level'); ?></label>
            <?php 
            $severities = ['Nhẹ (Mild)', 'Vừa (Mod)', 'Nặng (Sev)'];
            foreach($severities as $sev): 
                $checked = ($muscle_severity === $sev) ? 'checked' : '';
            ?>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="radio" name="history[muscle_severity]" value="<?php echo $sev; ?>" <?php echo $checked; ?>> <?php echo __($sev); ?>
                </label>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 2.2 Điểm đau / Cố định khớp -->
    <div class="premium-card" style="margin-bottom: 1.5rem; background: white; padding: 1.5rem; border-radius: 12px; border: 1px solid #e2e8f0;">
        <label class="section-label-premium" style="margin-bottom: 1rem;"><i class="fas fa-bone"></i> <?php echo __('Điểm đau / Cố định khớp (Fixation)'); ?></label>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1rem;">
            <div>
                <label class="form-label" style="font-size: 0.8rem; opacity: 0.8;"><?php echo __('Đốt sống cổ (C0-C7)'); ?></label>
                <input type="text" name="history[fixation_cervical]" class="form-input" value="<?php echo e($fixation_cervical); ?>">
            </div>
            <div>
                <label class="form-label" style="font-size: 0.8rem; opacity: 0.8;"><?php echo __('Đốt sống ngực (T1-T12)'); ?></label>
                <input type="text" name="history[fixation_thoracic]" class="form-input" value="<?php echo e($fixation_thoracic); ?>">
            </div>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1rem;">
            <div>
                <label class="form-label" style="font-size: 0.8rem; opacity: 0.8;"><?php echo __('Đốt sống thắt lưng (L1-L5)'); ?></label>
                <input type="text" name="history[fixation_lumbar]" class="form-input" value="<?php echo e($fixation_lumbar); ?>">
            </div>
            <div>
                <label class="form-label" style="font-size: 0.8rem; opacity: 0.8;"><?php echo __('Khớp cùng chậu (SI Joint)'); ?></label>
                <input type="text" name="history[fixation_si_joint]" class="form-input" value="<?php echo e($fixation_si_joint); ?>">
            </div>
        </div>
        <div>
            <label class="form-label" style="font-size: 0.8rem; opacity: 0.8;"><?php echo __('Khớp ngoại vi (Vai, Khuỷu, Cổ tay, Hông, Gối, Cổ chân)'); ?></label>
            <input type="text" name="history[fixation_peripheral]" class="form-input" value="<?php echo e($fixation_peripheral); ?>">
        </div>
    </div>

    <!-- 2.3 Hạn chế tầm vận động -->
    <div class="premium-card" style="background: white; padding: 1.5rem; border-radius: 12px; border: 1px solid #e2e8f0;">
        <label class="section-label-premium" style="margin-bottom: 1rem;"><i class="fas fa-running"></i> <?php echo __('medical.v2.obj_rom'); ?></label>
        <div style="display: flex; gap: 1.5rem; align-items: center;">
            <?php 
            $rom_items = ['Cổ', 'Ngực', 'Thắt lưng'];
            foreach($rom_items as $r): 
                $checked = in_array($r, $rom_limitations) ? 'checked' : '';
            ?>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" name="history[rom_limitations][]" value="<?php echo $r; ?>" <?php echo $checked; ?>> <?php echo __($r); ?>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php
// PHẦN 3 BIẾN & DATA CHUẨN BỊ
$subluxation = $data['subluxation'] ?? [];
$physiotherapy = $data['physiotherapy'] ?? [];
$assessment_notes = $data['assessment_notes'] ?? '';
$progress_assessment = $data['progress_assessment'] ?? '';
$treatment_frequency = $data['treatment_frequency'] ?? '';
$plan_notes = $data['plan_notes'] ?? '';

// Helper function để echo checkbox subluxation
function echoSubCheckbox($subluxation, $group, $item, $side) {
    if(isset($subluxation[$group]) && isset($subluxation[$group][$item])) {
        $val = $subluxation[$group][$item];
        if(!is_array($val)) $val = [$val]; // Normalize string to array
        $checked = in_array($side, $val) ? 'checked' : '';
    } else {
        $checked = '';
    }
    echo '<input type="checkbox" name="history[subluxation]['.$group.']['.$item.'][]" value="'.$side.'" '.$checked.' class="sub-cb" data-bone="'.$item.'">';
}
?>

<style>
    .matrix-dot {
        display: inline-block;
        width: 32px;
        height: 32px;
        border: 2px solid #e2e8f0;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
        position: relative;
    }
    .matrix-dot input { position: absolute; opacity: 0; cursor: pointer; width: 100%; height: 100%; z-index: 2; }
    .matrix-dot span {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
        font-size: 0.75rem;
        font-weight: 800;
        color: #94a3b8;
    }
    .matrix-dot:has(input:checked) {
        background: var(--primary);
        border-color: var(--primary);
        box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
    }
    .matrix-dot:has(input:checked) span { color: white; }
    .matrix-info-box {
        background: white;
        padding: 1.5rem;
        border-radius: 16px;
        border: 1px solid #eef2f6;
        box-shadow: 0 4px 15px -5px rgba(0,0,0,0.05);
    }
</style>

<?php
// Định nghĩa các Node Cột sống để render vòng lặp đẹp như V1
$spine_groups = [
    'Đốt sống Cổ (Cervical)' => [
        'cervical', // Nhóm trong DB của V2
        ['Occiput' => 'Occiput', 'TMJ' => 'TMJ', 'Atlas (C1)' => 'Atlas', 'Axis (C2)' => 'Axis 2', 'C3' => 'C3', 'C4' => 'C4', 'C5' => 'C5', 'C6' => 'C6', 'C7' => 'C7']
    ],
    'Đốt sống Ngực (Thoracic)' => [
        'thoracic',
        ['T1' => 'T1', 'T2' => 'T2', 'T3' => 'T3', 'T4' => 'T4', 'T5' => 'T5', 'T6' => 'T6', 'T7' => 'T7', 'T8' => 'T8', 'T9' => 'T9', 'T10' => 'T10', 'T11' => 'T11', 'T12' => 'T12']
    ],
    'Đốt sống Thắt lưng (Lumbar)' => [
        'lumbar',
        ['L1' => 'L1', 'L2' => 'L2', 'L3' => 'L3', 'L4' => 'L4', 'L5' => 'L5']
    ]
];

function echoMatrixDot($subluxation, $group, $item, $side, $label) {
    if(isset($subluxation[$group]) && isset($subluxation[$group][$item])) {
        $val = $subluxation[$group][$item];
        if(!is_array($val)) $val = [$val]; // Normalize string to array
        $checked = in_array($side, $val) ? 'checked' : '';
    } else {
        $checked = '';
    }
    echo '<label class="matrix-dot">';
    echo '<input type="checkbox" name="history[subluxation]['.$group.']['.$item.'][]" value="'.$side.'" '.$checked.' class="sub-cb" data-bone="'.$item.'">';
    echo '<span>'.$label.'</span>';
    echo '</label>';
}
?>

<!-- PHẦN 3: ĐÁNH GIÁ & VỊ TRÍ ĐIỀU TRỊ -->
<div style="margin-bottom: 3rem;">
    <h3 style="font-size: 1.1rem; font-weight: bold; color: black; margin-bottom: 1rem;">
        <?php echo __('medical.v3.ass_adj_title'); ?>
    </h3>
    <div style="font-weight: bold; margin-bottom: 0.5rem; color: black;"><?php echo __('medical.v3.adjustment'); ?></div>
    <div style="font-style: italic; margin-bottom: 1.5rem; color: black;"><?php echo __('medical.v3.mark_x'); ?></div>

    <style>
        .v2-table {
            border-collapse: collapse;
            width: 100%;
            max-width: 650px;
            margin-bottom: 2rem;
            color: black;
            font-size: 0.95rem;
        }
        .v2-table th, .v2-table td {
            border: 1px solid black;
            padding: 6px 10px;
            vertical-align: middle;
        }
        .v2-table th {
            font-weight: bold;
            text-align: center;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .v2-title { font-weight: bold; margin-bottom: 10px; color: black; margin-top: 1.5rem; font-size: 1.05rem; }
        .v2-checkbox-list label { display: block; margin-bottom: 8px; color: black; cursor: pointer; }

        @keyframes pulseRing {
            0% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.4); }
            70% { box-shadow: 0 0 0 6px rgba(99, 102, 241, 0); }
            100% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0); }
        }
        .prev-checked-ring {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            background: rgba(99, 102, 241, 0.15); /* light indigo */
            border-radius: 50%;
            animation: pulseRing 2s infinite;
            border: 1px solid rgba(99, 102, 241, 0.3);
            vertical-align: middle;
        }
        .prev-checked-ring i.fa-history {
            position: absolute;
            top: -6px;
            right: -10px;
            font-size: 10px;
            color: #4f46e5;
            background: white;
            border-radius: 50%;
            width: 16px;
            height: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 1px 2px rgba(0,0,0,0.15);
        }
        .normal-checkbox-wrap {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            vertical-align: middle;
        }
    </style>

    <?php 
    // Helper to render checkbox inside table
    function renderV2Cb($name, $val, $arr, $bone = '') {
        global $bone_history;
        if(!is_array($arr)) $arr = $arr ? [$arr] : [];
        $checked = in_array($val, $arr) ? 'checked' : '';
        
        $path_str = str_replace(['][', ']', '['], '||', $name);
        $path_str = trim($path_str, '||');
        $full_path = $path_str . '||' . $val;
        
        $dates = $bone_history[$full_path] ?? [];
        $was_checked = !empty($dates);
        
        $dataBone = $bone ? ' class="sub-cb" data-bone="'.htmlspecialchars($bone).'"' : '';
        
        if ($was_checked) {
            $title = 'Đã nắn chỉnh các ngày: ' . implode(', ', array_unique($dates));
            $html = '<div class="prev-checked-ring" title="'.htmlspecialchars($title).'">';
            $html .= '<input type="checkbox" name="history['.$name.'][]" value="'.$val.'" '.$checked.' style="width:16px; height:16px; cursor:pointer; position:relative; z-index:2; margin:0;"'.$dataBone.'>';
            $html .= '<i class="fas fa-history"></i>';
            $html .= '</div>';
        } else {
            $html = '<div class="normal-checkbox-wrap">';
            $html .= '<input type="checkbox" name="history['.$name.'][]" value="'.$val.'" '.$checked.' style="width:16px; height:16px; cursor:pointer; position:relative; z-index:2; margin:0;"'.$dataBone.'>';
            $html .= '</div>';
        }
        return $html;
    }
    ?>

    <div class="v2-title"><?php echo __('medical.v3.cervical'); ?></div>
    <label style="display:inline-flex; align-items:center; margin-bottom: 15px; color: black; cursor: pointer; gap: 0.5rem; position: relative;">
        <?php 
        $occ_path = 'subluxation||cervical||Occiput_general||1';
        $occ_dates = $bone_history[$occ_path] ?? [];
        $was_checked_occ = !empty($occ_dates); 
        ?>
        <?php if ($was_checked_occ): ?>
            <div class="prev-checked-ring" title="Đã nắn chỉnh các ngày: <?php echo htmlspecialchars(implode(', ', array_unique($occ_dates))); ?>">
                <input type="checkbox" name="history[subluxation][cervical][Occiput_general][]" value="1" <?php echo isset($data['subluxation']['cervical']['Occiput_general']) && in_array(1, (array)$data['subluxation']['cervical']['Occiput_general']) ? 'checked' : ''; ?> style="width:16px; height:16px; position:relative; z-index:2; margin:0;" class="sub-cb" data-bone="Occiput">
                <i class="fas fa-history"></i>
            </div>
        <?php else: ?>
            <div class="normal-checkbox-wrap">
                <input type="checkbox" name="history[subluxation][cervical][Occiput_general][]" value="1" <?php echo isset($data['subluxation']['cervical']['Occiput_general']) && in_array(1, (array)$data['subluxation']['cervical']['Occiput_general']) ? 'checked' : ''; ?> style="width:16px; height:16px; position:relative; z-index:2; margin:0;" class="sub-cb" data-bone="Occiput">
            </div>
        <?php endif; ?>
        <span>Occiput</span>
    </label>

    <table class="v2-table">
        <thead>
            <tr>
                <th style="width:60px;">L</th>
                <th style="width:60px;">R</th>
                <th></th>
                <th style="width:40px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $c_items = [
                ['name' => 'Khớp thái dương hàm (TMJ)', 'key' => 'TMJ', 'suffix' => ''],
                ['name' => 'Atlas', 'key' => 'Atlas (C1)', 'suffix' => ''],
                ['name' => 'Axis 2', 'key' => 'Axis (C2)', 'suffix' => 'C'],
                ['name' => '3', 'key' => 'C3', 'suffix' => ''],
                ['name' => '4', 'key' => 'C4', 'suffix' => ''],
                ['name' => '5', 'key' => 'C5', 'suffix' => ''],
                ['name' => '6', 'key' => 'C6', 'suffix' => ''],
                ['name' => '7', 'key' => 'C7', 'suffix' => ''],
            ];
            foreach($c_items as $item):
                $arr = $data['subluxation']['cervical'][$item['key']] ?? [];
            ?>
            <tr>
                <td class="text-center"><?php echo renderV2Cb('subluxation][cervical]['.$item['key'].']', 'L', $arr, $item['key']); ?></td>
                <td class="text-center"><?php echo renderV2Cb('subluxation][cervical]['.$item['key'].']', 'R', $arr, $item['key']); ?></td>
                <td style="padding-left:15px;"><?php echo __($item['name']); ?></td>
                <td class="text-center"><?php echo $item['suffix']; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="v2-title"><?php echo __('medical.v3.thoracic'); ?></div>
    <table class="v2-table">
        <thead>
            <tr>
                <th style="width:60px;">L</th>
                <th style="width:60px;">R</th>
                <th></th>
                <th style="width:100px;"><?php echo __('medical.v3.interior'); ?></th>
                <th style="width:100px;"><?php echo __('medical.v3.posterior'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php 
            for($i = 1; $i <= 12; $i++):
                $arr = $data['subluxation']['thoracic']['T'.$i] ?? [];
                $name = ($i == 1) ? 'T1' : $i;
            ?>
            <tr>
                <td class="text-center"><?php echo renderV2Cb('subluxation][thoracic][T'.$i.']', 'L', $arr, 'T'.$i); ?></td>
                <td class="text-center"><?php echo renderV2Cb('subluxation][thoracic][T'.$i.']', 'R', $arr, 'T'.$i); ?></td>
                <td class="text-right" style="padding-right: 15px;"><?php echo __($name); ?></td>
                <td class="text-center"><?php echo renderV2Cb('subluxation][thoracic][T'.$i.']', 'Interior', $arr, 'T'.$i); ?></td>
                <td class="text-center"><?php echo renderV2Cb('subluxation][thoracic][T'.$i.']', 'Posterior', $arr, 'T'.$i); ?></td>
            </tr>
            <?php endfor; ?>
        </tbody>
    </table>

    <table class="v2-table">
        <thead>
            <tr>
                <th style="width:60px;">L</th>
                <th style="width:60px;">R</th>
                <th></th>
                <th style="width:100px;"><?php echo __('medical.v3.interior'); ?></th>
                <th style="width:100px;"><?php echo __('medical.v3.posterior'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php 
            for($i = 1; $i <= 12; $i++):
                $arr = $data['subluxation']['ribs']['Rib'.$i] ?? [];
                $name = ($i == 1) ? 'Xương sườn số 1' : $i;
            ?>
            <tr>
                <td class="text-center"><?php echo renderV2Cb('subluxation][ribs][Rib'.$i.']', 'L', $arr, 'Rib'.$i); ?></td>
                <td class="text-center"><?php echo renderV2Cb('subluxation][ribs][Rib'.$i.']', 'R', $arr, 'Rib'.$i); ?></td>
                <td class="text-right" style="padding-right: 15px;"><?php echo __($name); ?></td>
                <td class="text-center"><?php echo renderV2Cb('subluxation][ribs][Rib'.$i.']', 'Interior', $arr, 'Rib'.$i); ?></td>
                <td class="text-center"><?php echo renderV2Cb('subluxation][ribs][Rib'.$i.']', 'Posterior', $arr, 'Rib'.$i); ?></td>
            </tr>
            <?php endfor; ?>
        </tbody>
    </table>

    <table class="v2-table">
        <tbody>
            <?php 
            $arm_items = [
                'Xương quai xanh' => 'Xương quai xanh', 
                'Khớp cùng đòn (ACG)' => 'ACG', 
                'Cơ nhị đầu' => 'Cơ nhị đầu', 
                'Golferarm' => 'Golferarm', 
                'Tennisarm' => 'Tennisarm', 
                'Cổ tay' => 'Cổ tay', 
                'Bàn tay' => 'Bàn tay'
            ];
            foreach($arm_items as $key => $name):
                $arr = $data['subluxation']['peripheral'][$key] ?? [];
            ?>
            <tr>
                <td style="width:60px;" class="text-center"><?php echo renderV2Cb('subluxation][peripheral]['.$key.']', 'L', $arr, $key); ?></td>
                <td style="width:60px;" class="text-center"><?php echo renderV2Cb('subluxation][peripheral]['.$key.']', 'R', $arr, $key); ?></td>
                <td class="text-right" style="padding-right: 15px;"><?php echo __($name); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <table class="v2-table">
        <tbody>
            <?php 
            $lumbar_items = [
                ['name' => '1', 'key' => 'L1', 'suffix' => 'L'],
                ['name' => '2', 'key' => 'L2', 'suffix' => ''],
                ['name' => '3', 'key' => 'L3', 'suffix' => ''],
                ['name' => '4', 'key' => 'L4', 'suffix' => ''],
                ['name' => '5', 'key' => 'L5', 'suffix' => ''],
                ['name' => 'Sac', 'key' => 'S1', 'suffix' => ''],
                ['name' => 'Coc', 'key' => 'Coc', 'suffix' => '']
            ];
            foreach($lumbar_items as $item):
                $arr = $data['subluxation']['lumbar'][$item['key']] ?? [];
            ?>
            <tr>
                <td style="width:60px;" class="text-center"><?php echo renderV2Cb('subluxation][lumbar]['.$item['key'].']', 'L', $arr, $item['key']); ?></td>
                <td style="width:60px;" class="text-center"><?php echo renderV2Cb('subluxation][lumbar]['.$item['key'].']', 'R', $arr, $item['key']); ?></td>
                <td class="text-right" style="padding-right: 15px;"><?php echo __($item['name']); ?></td>
                <td style="width:40px;" class="text-center"><?php echo $item['suffix']; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="v2-checkbox-list" style="margin-top: 2rem;">
        <?php 
        $sacral_opts = [
            'sacral_nutation' => 'medical.v3.sacral_nutation',
            'sacral_counternutation' => 'medical.v3.sacral_counternutation',
            'forward_torsion' => 'medical.v3.forward_torsion',
            'backward_torsion' => 'medical.v3.backward_torsion',
            'posterior_sacrum' => 'medical.v3.posterior_sacrum',
            'anterior_flexion' => 'medical.v3.anterior_flexion'
        ];
        foreach($sacral_opts as $key => $name): ?>
            <label style="display: flex; align-items: flex-start; gap: 10px;">
                <input type="checkbox" name="history[subluxation][sacrum_general][]" value="<?php echo $key; ?>" <?php echo (in_array($key, $data['subluxation']['sacrum_general'] ?? [])) ? 'checked' : ''; ?> style="margin-top:2px;">
                <?php echo __($name); ?>
            </label>
        <?php endforeach; ?>
        
        <label style="display: flex; align-items: center; gap: 10px; margin-top: 10px;">
            <input type="checkbox" name="history[subluxation][sacrococcygeal_lateral][L]" value="L" <?php echo isset($data['subluxation']['sacrococcygeal_lateral']['L']) ? 'checked' : ''; ?>> L 
            <input type="checkbox" name="history[subluxation][sacrococcygeal_lateral][R]" value="R" <?php echo isset($data['subluxation']['sacrococcygeal_lateral']['R']) ? 'checked' : ''; ?>> R 
            <?php echo __('medical.v3.lateral_deviation'); ?>
        </label>
    </div>

    <table class="v2-table" style="margin-top: 2rem; max-width: 800px;">
        <thead>
            <tr>
                <th style="width: 100px; text-align: left; padding-left: 15px;">Becken</th>
                <th>AS</th>
                <th>PI</th>
                <th>IN-Ilium</th>
                <th>EX-Ilium</th>
                <th>Up-Slip</th>
                <th>Down-Slip</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $becken_sides = ['links' => 'L', 'rechts' => 'R'];
            $becken_cols = ['AS', 'PI', 'IN-Ilium', 'EX-Ilium', 'Up-Slip', 'Down-Slip'];
            foreach($becken_sides as $label => $val): ?>
            <tr>
                <td style="padding-left: 15px;"><?php echo __($label); ?></td>
                <?php foreach($becken_cols as $col): 
                    $arr = $data['subluxation']['becken'][$val] ?? [];
                ?>
                <td class="text-center"><?php echo renderV2Cb('subluxation][becken]['.$val.']', $col, $arr); ?></td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <label style="display:flex; align-items:center; gap: 10px; margin-bottom: 20px; color: black; margin-top: 2rem;">
        <input type="checkbox" name="history[subluxation][symphysis_pubica]" value="1" <?php echo isset($data['subluxation']['symphysis_pubica']) ? 'checked' : ''; ?> style="width:16px; height:16px;"> <?php echo __('medical.v3.symphysis'); ?>
    </label>

    <table class="v2-table">
        <thead>
            <tr>
                <th style="width:60px;">L</th>
                <th style="width:60px;">R</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $leg_items = [
                'Khớp háng' => 'Khớp háng / Hüfgelenk',
                'Hông' => 'Hông',
                'Gối (Knee)' => 'Khớp gối',
                'Cổ chân (Ankle)' => 'Khớp cổ chân',
                'Bàn chân' => 'Bàn chân'
            ];
            foreach($leg_items as $key => $name):
                $arr = $data['subluxation']['peripheral'][$key] ?? [];
            ?>
            <tr>
                <td class="text-center"><?php echo renderV2Cb('subluxation][peripheral]['.$key.']', 'L', $arr, $key); ?></td>
                <td class="text-center"><?php echo renderV2Cb('subluxation][peripheral]['.$key.']', 'R', $arr, $key); ?></td>
                <td style="padding-left: 15px;"><?php echo __($name); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="margin-top: 2rem; margin-bottom: 3rem;">
        <label style="display: block; margin-bottom: 10px; color: black; font-weight: 700;"><?php echo __('medical.v3.extra_notes'); ?></label>
        <textarea name="history[assessment_notes]" class="form-input" rows="6" style="border: 1px solid black; border-radius: 8px; background: transparent; color: black; resize: vertical; min-height: 140px; font-size: 0.95rem; line-height: 1.5;"><?php echo e($data['assessment_notes'] ?? ''); ?></textarea>
    </div>

    <!-- VẬT LÝ TRỊ LIỆU -->
    <h3 style="font-size: 1.1rem; font-weight: bold; color: black; margin-bottom: 1rem;">
        <?php echo __('medical.v3.pt_title'); ?>
    </h3>
    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 1rem; align-items: center;">
        <?php 
        $pt_items = [
            'Nhiệt/Lạnh (Heat/Cryo)' => 'medical.v3.pt_heat',
            'Điện xung (DEMS)' => 'medical.v3.pt_stim',
            'Siêu âm (Ultrasound)' => 'medical.v3.pt_us',
            'Giải cơ (Trigger Point)' => 'medical.v3.pt_trigger',
            'Massage trị liệu' => 'medical.v3.pt_massage',
            'Kéo giãn (Traction)' => 'medical.v3.pt_traction',
            'Trị liệu bằng tay (Manual Therapy)' => 'medical.v3.pt_manual',
            'Bài tập chức năng' => 'medical.v3.pt_exercise'
        ];
        foreach($pt_items as $pt => $lang_key): 
            $checked = in_array($pt, $data['physiotherapy'] ?? []) ? 'checked' : '';
        ?>
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: black;">
                <input type="checkbox" name="history[physiotherapy][]" value="<?php echo $pt; ?>" <?php echo $checked; ?> style="width: 16px; height: 16px;"> 
                <?php echo __($lang_key); ?>
            </label>
        <?php endforeach; ?>
    </div>

</div>
<!-- HẾT PHẦN 3 -->

    <!-- PHẦN 4: PLAN -->
    <h3 style="font-size: 1.1rem; text-transform: uppercase; color: var(--primary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">
        <i class="fas fa-clipboard-check"></i> <?php echo __('4. ĐÁNH GIÁ & KẾ HOẠCH (ASSESSMENT & PLAN - A/P)'); ?>
    </h3>
    
    <div class="premium-card" style="background: white; padding: 1.5rem; border-radius: 12px; border: 1px solid #e2e8f0;">
        <div style="margin-bottom: 1.5rem;">
            <label class="form-label" style="font-weight: 700; margin-bottom: 0.75rem;"><i class="fas fa-chart-line"></i> <?php echo __('Đánh giá buổi hôm nay:'); ?></label>
            <div style="display: flex; gap: 1.5rem;">
                <?php 
                $progs = ['Tiến triển tốt', 'Tiến triển chậm', 'Chưa cải thiện'];
                foreach($progs as $p): 
                ?>
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="radio" name="history[progress_assessment]" value="<?php echo $p; ?>" <?php echo ($progress_assessment === $p) ? 'checked' : ''; ?>> <?php echo __($p); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="margin-bottom: 1.5rem;">
            <label class="form-label" style="font-weight: 700; margin-bottom: 0.75rem;"><i class="fas fa-calendar-check"></i> <?php echo __('Kế hoạch tiếp theo (Tần suất):'); ?></label>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <?php 
                $freqs = ['3 lần/tuần', '2 lần/tuần', '1 lần/tuần', '1 lần/2 tuần', '1 lần/tháng', '1 lần/3 tháng', 'Khi cần (PRN)'];
                foreach($freqs as $f): 
                ?>
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="radio" name="history[treatment_frequency]" value="<?php echo $f; ?>" <?php echo ($treatment_frequency === $f) ? 'checked' : ''; ?>> <?php echo __($f); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-group" style="margin: 0;">
            <label class="form-label" style="font-weight: 700; margin-bottom: 0.75rem;"><i class="fas fa-pen-alt"></i> <?php echo __('Ghi chú (Notes):'); ?></label>
            <textarea name="history[plan_notes]" class="form-input" rows="8" placeholder="<?php echo __('Nhập phác đồ, nhắc nhở định kỳ cho bệnh nhân...'); ?>" style="min-height: 180px; font-size: 0.95rem; line-height: 1.6; resize: vertical;"><?php echo e($plan_notes); ?></textarea>
        </div>
    </div>
</div>

<?php $symptom_notes = $data['symptom_notes'] ?? ''; ?>
<!-- GỢI Ý TRIỆU CHỨNG (AI ASSISTANT) -->
<div class="premium-card" style="margin-bottom: 2rem; background: linear-gradient(to right, #f8fafc, #eff6ff); padding: 1.5rem; border-radius: 12px; border: 1px solid #bfdbfe; box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.1);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <label class="section-label-premium" style="margin: 0; color: #1d4ed8;">
            <i class="fas fa-robot"></i> <?php echo __('medical.v2.symptom_correlation'); ?>
        </label>
        <button type="button" class="btn btn-sm" onclick="copySymptomsToNotes()" style="background: #3b82f6; color: white; font-weight: 700; border-radius: 8px;">
            <i class="fas fa-copy"></i> <?php echo __('medical.v2.copy_plan_notes'); ?>
        </button>
    </div>
    <div id="symptom_summary_box" style="font-size: 0.85rem; color: #334155; line-height: 1.5; min-height: 40px; display: flex; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1.5rem;">
        <em><?php echo __('medical.v2.ai_hint'); ?></em>
    </div>

    <!-- Khung Ghi chú thêm cho Bác sĩ -->
    <div style="border-top: 1px dashed #bfdbfe; padding-top: 1.5rem;">
        <label class="form-label" style="font-weight: 700; color: #1e293b; margin-bottom: 0.75rem;"><i class="fas fa-user-md"></i> <?php echo __('Doctor\'s Diagnostic Notes (Optional):'); ?></label>
        <textarea name="history[symptom_notes]" class="form-input" rows="5" placeholder="<?php echo __('The doctor can enter additional professional diagnoses or edit the suggestions above...'); ?>" style="min-height: 120px; font-size: 0.95rem; line-height: 1.5; resize: vertical;"><?php echo e($symptom_notes); ?></textarea>
    </div>
</div>

<script>
// Từ điển Triệu Chứng
const symptomDict = {
    // Cổ
    "Atlas (C1)": "<?php echo __('Não, tuyến yên, tai trong, hệ TK giao cảm - Đau đầu, mất ngủ, chóng mặt, huyết áp cao.'); ?>",
    "Axis (C2)": "<?php echo __('Mắt, TK thị giác, xoang, lưỡi - Viêm xoang, dị ứng, đau quanh mắt.'); ?>",
    "C3": "<?php echo __('Má, tai ngoài, răng, dây TK mặt - Đau dây TK, mụn trứng cá, chàm.'); ?>",
    "C4": "<?php echo __('Mũi, môi, miệng, vòi Eustachian - Sổ mũi, điếc nhẹ, vấn đề vùng miệng.'); ?>",
    "C5": "<?php echo __('Dây thanh quản, các tuyến ở cổ - Viêm họng, khàn tiếng.'); ?>",
    "C6": "<?php echo __('Cơ cổ, vai, amidan - Đau vai, cứng cổ, ho mãn tính.'); ?>",
    "C7": "<?php echo __('Tuyến giáp, khuỷu tay - Viêm bao hoạt dịch vai, vấn đề tuyến giáp.'); ?>",
    // Ngực
    "T1": "<?php echo __('Cẳng tay, bàn tay, thực quản, khí quản - Đau tay, khó thở, hen suyễn.'); ?>",
    "T2": "<?php echo __('Tim, động mạch vành - Các vấn đề về ngực, rối loạn nhịp tim.'); ?>",
    "T3": "<?php echo __('Phổi, phế quản, ngực - Viêm phế quản, viêm phổi, khó thở.'); ?>",
    "T4": "<?php echo __('Túi mật, ống mật - Vấn đề túi mật, sỏi mật.'); ?>",
    "T5": "<?php echo __('Gan, hệ tuần hoàn - Huyết áp thấp, vấn đề về gan.'); ?>",
    "T6": "<?php echo __('Dạ dày - Khó tiêu, ợ chua, đau dạ dày.'); ?>",
    "T7": "<?php echo __('Tuyến tụy, tá tràng - Viêm loét tá tràng, vấn đề đường huyết.'); ?>",
    "T8": "<?php echo __('Lá lách - Sức đề kháng kém, vấn đề về máu.'); ?>",
    "T9": "<?php echo __('Tuyến thượng thận - Dị ứng, nổi mề đay.'); ?>",
    "T10": "<?php echo __('Thận - Mệt mỏi mãn tính, vấn đề về thận.'); ?>",
    "T11": "<?php echo __('Thận, niệu quản - Vấn đề về da, tiểu tiện khó.'); ?>",
    "T12": "<?php echo __('Ruột non, hệ bạch huyết - Đau thấp khớp, đầy hơi.'); ?>",
    // Thắt lưng
    "L1": "<?php echo __('Ruột già, đại tràng - Táo bón, tiêu chảy, viêm đại tràng.'); ?>",
    "L2": "<?php echo __('Ruột thừa, bụng, đùi - Đau bụng, chuột rút.'); ?>",
    "L3": "<?php echo __('Cơ quan sinh dục, bàng quang, đầu gối - Vấn đề kinh nguyệt, bàng quang.'); ?>",
    "L4": "<?php echo __('Tuyến tiền liệt, cơ lưng dưới, TK tọa - Đau thần kinh tọa, đau lưng dưới.'); ?>",
    "L5": "<?php echo __('Cẳng chân, cổ chân, bàn chân - Tuần hoàn kém ở chân, sưng mắt cá.'); ?>",
    // Xương cùng
    "S1": "<?php echo __('Xương chậu, mông - Đau khớp cùng chậu, vấn đề vùng chậu.'); ?>",
    "S2": "<?php echo __('Xương chậu, mông - Đau khớp cùng chậu, vấn đề vùng chậu.'); ?>",
    "S3": "<?php echo __('Xương chậu, mông - Đau khớp cùng chậu, vấn đề vùng chậu.'); ?>",
    "S4": "<?php echo __('Xương chậu, mông - Đau khớp cùng chậu, vấn đề vùng chậu.'); ?>",
    "S5": "<?php echo __('Xương chậu, mông - Đau khớp cùng chậu, vấn đề vùng chậu.'); ?>"
};

function updateSymptomSummary() {
    const checkboxes = document.querySelectorAll('.sub-cb:checked');
    const checkedBones = new Set();
    
    checkboxes.forEach(cb => {
        const boneStr = cb.getAttribute('data-bone');
        if(boneStr) checkedBones.add(boneStr);
    });

    const box = document.getElementById('symptom_summary_box');
    if(checkedBones.size === 0) {
        box.innerHTML = '<em><?php echo __('Chọn các sai lệch trên ma trận (Cột sống/Khung chậu) để hệ thống tự động gợi ý các chức năng/cơ quan bị ảnh hưởng và triệu chứng liên quan.'); ?></em>';
        return;
    }

    let html = '';
    let addedCount = 0;
    checkedBones.forEach(bone => {
        if(symptomDict[bone]) {
            html += `
            <div class="symptom-tag" style="background: white; border: 1px solid #93c5fd; padding: 0.5rem 0.75rem; border-radius: 8px; display: flex; align-items: flex-start; gap: 0.5rem; max-width: 100%; box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: all 0.2s;">
                <div style="flex: 1;">
                    <strong style="color: #1d4ed8;">${bone}:</strong> <span class="symptom-text" style="color: #334155;">${symptomDict[bone]}</span>
                </div>
                <button type="button" onclick="this.parentElement.remove()" style="background: none; border: none; color: #ef4444; cursor: pointer; padding: 0 0.25rem; font-size: 1rem; opacity: 0.7; transition: opacity 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.7">
                    <i class="fas fa-times"></i>
                </button>
            </div>`;
            addedCount++;
        }
    });
    
    if(addedCount === 0) {
        html = '<em><?php echo __('Đã ghi nhận sai lệch ngoại vi/xương sườn (Không nằm trong từ điển gợi ý tự động).'); ?></em>';
    }
    
    box.innerHTML = html;
}

function copySymptomsToNotes() {
    const box = document.getElementById('symptom_summary_box');
    const noteArea = document.querySelector('textarea[name="history[plan_notes]"]');
    
    if(!noteArea) return;
    
    const items = box.querySelectorAll('.symptom-tag');
    if(items.length === 0) {
        alert('<?php echo __('Không có triệu chứng nào để chép. Vui lòng check vào các đốt sống có sai lệch trước!'); ?>');
        return;
    }
    
    let textToCopy = "=== <?php echo __('GỢI Ý CHẨN ĐOÁN LÂM SÀNG'); ?> ===\n";
    items.forEach(tag => {
        const bone = tag.querySelector('strong').innerText;
        const desc = tag.querySelector('.symptom-text').innerText;
        textToCopy += "- " + bone + " " + desc + "\n";
    });
    
    let currentVal = noteArea.value;
    if(currentVal && !currentVal.endsWith('\n')) currentVal += "\n\n";
    noteArea.value = currentVal + textToCopy;
    
    // Show flash effect
    const btn = document.querySelector('button[onclick="copySymptomsToNotes()"]');
    if(btn) {
        const oldHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> <?php echo __('Đã chép thành công'); ?>';
        btn.style.background = '#10b981';
        setTimeout(() => {
            btn.innerHTML = oldHtml;
            btn.style.background = '#3b82f6';
        }, 2000);
    }
}

// Attach event listeners to all matrix checkboxes
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.sub-cb').forEach(cb => {
        cb.addEventListener('change', updateSymptomSummary);
    });
    // Khởi tạo trạng thái ban đầu nếu form có data cũ được nạp
    updateSymptomSummary();
});
</script>
