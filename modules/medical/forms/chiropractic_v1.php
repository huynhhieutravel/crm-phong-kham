<?php
// modules/medical/forms/chiropractic_v2.php

// 0. AUTO DATA MIGRATION: TƯƠNG THÍCH NGƯỢC DỮ LIỆU CŨ (V1)
// Kiểm tra nếu $data chứa các key đặc trưng của bản cũ (như 'spine', 's', 'o'...)
if (!empty($data) && (isset($data['spine']) || isset($data['s']) || isset($data['o']) || isset($data['a']))) {
    $v2 = [];
    
    // 1. Phân mục S (Subjective) -> Subjective V2
    if (isset($data['s'])) {
        $v2['s_vas'] = $data['s']['vas'] ?? '5';
        $v2['s_progress'] = $data['s']['progress'] ?? '';
        $v2['s_frequency'] = $data['s']['frequency'] ?? '';
        $v2['s_activities'] = $data['s']['activities'] ?? [];
        $v2['pain_locations'] = [
            ['name' => 'Khu vực đau chính (từ bản cũ)', 'vas' => $v2['s_vas'], 'trend' => '', 'symptoms' => [], 'notes' => '']
        ];
    }
    
    // 2. Phân mục O (Objective) -> Objective V2
    if (isset($data['o'])) {
        $v2['muscle_hypertonicity'] = $data['o']['muscle_tone'] ?? '';
        $v2['muscle_severity'] = $data['o']['severity'] ?? '';
        $v2['rom_limitations'] = $data['o']['rom_limit'] ?? [];
        $v2['symptom_notes'] = $data['o']['notes'] ?? '';
    }
    
    // 3. Phân mục A (Assessment/Physio) -> Physio V2
    if (isset($data['a'])) {
        $pt_map = [
            'Nhiệt/Lạnh' => 'Nhiệt/Lạnh (Heat/Cryo)',
            'Trị liệu bằng tay' => 'Trị liệu bằng tay (Manual Therapy)'
        ];
        $physio = [];
        $old_physio = $data['a']['physiotherapy'] ?? [];
        if (is_array($old_physio)) {
            foreach ($old_physio as $item) {
                $physio[] = $pt_map[$item] ?? $item;
            }
        }
        $v2['physiotherapy'] = $physio;
    }
    
    // 4. Phân mục P (Plan) -> Plan V2
    if (isset($data['p'])) {
        $v2['progress_assessment'] = $data['p']['evaluation'] ?? '';
        $v2['treatment_frequency'] = $data['p']['frequency'] ?? '';
        $v2['plan_notes'] = $data['p']['notes'] ?? '';
    }
    
    // 5. SPINE MATRIX (Subluxation)
    $v2['subluxation'] = [
        'cervical' => [], 'thoracic' => [], 'lumbar' => [], 'sacrum' => [], 'peripheral' => [], 'becken' => []
    ];
    
    if (isset($data['spine'])) {
        foreach ($data['spine'] as $node => $sides) {
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
    
    if (isset($data['becken'])) {
        foreach ($data['becken'] as $type => $sides) {
            $v2['subluxation']['becken'][] = $type; // V2 Becken chỉ xài một chiều (Tên Becken)
        }
    }
    
    if (isset($data['joints'])) {
        $joint_map = [
            'Khớp vai' => 'Khớp cùng đòn (ACG)',
            'Khớp khuỷu tay' => 'Tennisarm',
            'Khớp háng' => 'Khớp háng (Hip)',
            'Khớp gối' => 'Gối (Knee)',
            'Khớp cổ chân' => 'Cổ chân (Ankle)',
            'Khớp cổ tay' => 'Cổ chân (Ankle)' // Bỏ vào tạm vì V2 ko có cổ tay, hoặc có thể custom
        ];
        foreach ($data['joints'] as $joint => $sides) {
            $mapped_joint = $joint_map[$joint] ?? $joint;
            $sides = is_array($sides) ? $sides : [];
            $v2['subluxation']['peripheral'][$mapped_joint] = array_keys(array_filter($sides));
        }
    }
    
    // Gán lại data V2 để form đọc
    $data = $v2;
}

// 1. CHUẨN BỊ BIẾN
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
                                        <input type="radio" name="history[pain_locations][<?php echo $idx; ?>][trend]" value="<?php echo $t; ?>" <?php echo $checked; ?>> <?php echo $t; ?>
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
                                    <input type="checkbox" name="history[pain_locations][<?php echo $idx; ?>][symptoms][]" value="<?php echo $s; ?>" <?php echo $checked; ?>> <?php echo $s; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-group" style="margin: 0;">
                        <label class="form-label" style="font-size: 0.8rem; opacity: 0.8;">Ghi chú nhanh</label>
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
                    <label style="display:flex; align-items:center; gap:0.5rem;"><input type="radio" name="history[pain_locations][${idx}][trend]" value="Giảm"> Giảm</label>
                    <label style="display:flex; align-items:center; gap:0.5rem;"><input type="radio" name="history[pain_locations][${idx}][trend]" value="Tăng"> Tăng</label>
                    <label style="display:flex; align-items:center; gap:0.5rem;"><input type="radio" name="history[pain_locations][${idx}][trend]" value="Không đổi"> Không đổi</label>
                </div>
            </div>
        </div>
        <div style="margin-bottom: 1rem;">
            <label class="form-label" style="font-size: 0.8rem; opacity: 0.8;"><?php echo __('medical.v2.pain_symptoms'); ?></label>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                ${['Đau nhức', 'Tê bì', 'Cứng khớp', 'Chóng mặt/Đau đầu'].map(s => 
                    `<label style="display:flex; align-items:center; gap:0.5rem;"><input type="checkbox" name="history[pain_locations][${idx}][symptoms][]" value="${s}"> ${s}</label>`
                ).join('')}
            </div>
        </div>
        <div class="form-group" style="margin: 0;">
            <label class="form-label" style="font-size: 0.8rem; opacity: 0.8;">Ghi chú nhanh</label>
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
    <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 1.5rem;">Kết quả thăm khám của bác sĩ.</p>

    <!-- 2.1 Sờ nắn & Trương lực cơ -->
    <div class="premium-card" style="margin-bottom: 1.5rem; background: white; padding: 1.5rem; border-radius: 12px; border: 1px solid #e2e8f0;">
        <label class="section-label-premium" style="margin-bottom: 1rem;"><i class="fas fa-hand-holding-medical"></i> Sờ nắn (Palpation) & Trương lực cơ</label>
        
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
                    <input type="radio" name="history[muscle_severity]" value="<?php echo $sev; ?>" <?php echo $checked; ?>> <?php echo $sev; ?>
                </label>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 2.2 Điểm đau / Cố định khớp -->
    <div class="premium-card" style="margin-bottom: 1.5rem; background: white; padding: 1.5rem; border-radius: 12px; border: 1px solid #e2e8f0;">
        <label class="section-label-premium" style="margin-bottom: 1rem;"><i class="fas fa-bone"></i> Điểm đau / Cố định khớp (Fixation)</label>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1rem;">
            <div>
                <label class="form-label" style="font-size: 0.8rem; opacity: 0.8;">Đốt sống cổ (C0-C7)</label>
                <input type="text" name="history[fixation_cervical]" class="form-input" value="<?php echo e($fixation_cervical); ?>">
            </div>
            <div>
                <label class="form-label" style="font-size: 0.8rem; opacity: 0.8;">Đốt sống ngực (T1-T12)</label>
                <input type="text" name="history[fixation_thoracic]" class="form-input" value="<?php echo e($fixation_thoracic); ?>">
            </div>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1rem;">
            <div>
                <label class="form-label" style="font-size: 0.8rem; opacity: 0.8;">Đốt sống thắt lưng (L1-L5)</label>
                <input type="text" name="history[fixation_lumbar]" class="form-input" value="<?php echo e($fixation_lumbar); ?>">
            </div>
            <div>
                <label class="form-label" style="font-size: 0.8rem; opacity: 0.8;">Khớp cùng chậu (SI Joint)</label>
                <input type="text" name="history[fixation_si_joint]" class="form-input" value="<?php echo e($fixation_si_joint); ?>">
            </div>
        </div>
        <div>
            <label class="form-label" style="font-size: 0.8rem; opacity: 0.8;">Khớp ngoại vi (Vai, Khuỷu, Cổ tay, Hông, Gối, Cổ chân)</label>
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
                    <input type="checkbox" name="history[rom_limitations][]" value="<?php echo $r; ?>" <?php echo $checked; ?>> <?php echo $r; ?>
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
        // Handle array format for L/R/In/Pos
        if(is_array($subluxation[$group][$item])) {
            $checked = in_array($side, $subluxation[$group][$item]) ? 'checked' : '';
        } else {
            // Fallback for single depth array if somehow malformed
            $checked = '';
        }
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
        if(is_array($subluxation[$group][$item])) {
            $checked = in_array($side, $subluxation[$group][$item]) ? 'checked' : '';
        } else {
            $checked = '';
        }
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
    <h3 style="font-size: 1.1rem; text-transform: uppercase; color: var(--primary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">
        <i class="fas fa-project-diagram"></i> <?php echo __('medical.v2.ass_title'); ?>
    </h3>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <?php foreach ($spine_groups as $group_label => $group_data): 
            $group_key = $group_data[0];
            $nodes = $group_data[1];
            $is_thoracic = ($group_key === 'thoracic');
        ?>
            <div class="matrix-info-box">
                <h4 style="font-size: 0.85rem; text-align: center; color: #64748b; margin-top: 0; text-transform: uppercase; margin-bottom: 1rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;"><?php echo $group_label; ?></h4>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="font-size: 0.65rem; color: #94a3b8; text-align: center;">
                            <th style="width: <?php echo $is_thoracic ? '20%' : '30%'; ?>;">L</th>
                            <?php if($is_thoracic): ?><th style="width: 20%;">A</th><?php endif; ?>
                            <th style="width: <?php echo $is_thoracic ? '20%' : '40%'; ?>;">ĐỐT</th>
                            <?php if($is_thoracic): ?><th style="width: 20%;">P</th><?php endif; ?>
                            <th style="width: <?php echo $is_thoracic ? '20%' : '30%'; ?>;">R</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($nodes as $key => $label): ?>
                            <tr>
                                <td style="text-align: center; padding: 4px;">
                                    <?php echoMatrixDot($subluxation, $group_key, $key, 'L', 'L'); ?>
                                </td>
                                <?php if($is_thoracic): ?>
                                <td style="text-align: center; padding: 4px;">
                                    <?php echoMatrixDot($subluxation, $group_key, $key, 'Interior', 'A'); ?>
                                </td>
                                <?php endif; ?>
                                
                                <td style="text-align: center; font-weight: 800; font-size: 0.9rem; color: #1e293b;"><?php echo $label; ?></td>
                                
                                <?php if($is_thoracic): ?>
                                <td style="text-align: center; padding: 4px;">
                                    <?php echoMatrixDot($subluxation, $group_key, $key, 'Posterior', 'P'); ?>
                                </td>
                                <?php endif; ?>
                                <td style="text-align: center; padding: 4px;">
                                    <?php echoMatrixDot($subluxation, $group_key, $key, 'R', 'R'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Khung Chậu & Khớp Ngoại Vi -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
        
        <!-- Khung chậu & Xương cùng -->
        <div class="matrix-info-box">
            <h4 style="font-size: 0.85rem; text-align: center; color: #64748b; margin-top: 0; text-transform: uppercase; margin-bottom: 1rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;">Khung chậu & Xương cùng (Pelvis)</h4>
            <table style="width: 100%;">
                <?php 
                $sacrums = ['S1', 'S2', 'S3', 'S4', 'S5'];
                foreach ($sacrums as $s): ?>
                    <tr>
                        <td style="text-align: center; padding: 6px;">
                            <?php echoMatrixDot($subluxation, 'sacrum', $s, 'L', 'L'); ?>
                        </td>
                        <td style="text-align: center; font-weight: 700; font-size: 0.85rem; color: #334155;"><?php echo $s; ?></td>
                        <td style="text-align: center; padding: 6px;">
                            <?php echoMatrixDot($subluxation, 'sacrum', $s, 'R', 'R'); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="3" style="padding-top: 1rem;">
                        <div style="font-size:0.8rem; font-weight:700; margin-bottom:0.5rem;">Xương Cùng (Sacrum):</div>
                        <label style="display:inline-block; margin-right:1rem;"><input type="checkbox" name="history[subluxation][sacrum][general][]" value="Gật đầu" <?php echo in_array("Gật đầu", $subluxation['sacrum']['general'] ?? []) ? 'checked' : ''; ?>> Gật đầu</label>
                        <label style="display:inline-block; margin-right:1rem;"><input type="checkbox" name="history[subluxation][sacrum][general][]" value="Ngửa đầu" <?php echo in_array("Ngửa đầu", $subluxation['sacrum']['general'] ?? []) ? 'checked' : ''; ?>> Ngửa đầu</label>
                        <label style="display:inline-block; margin-right:1rem;"><input type="checkbox" name="history[subluxation][sacrum][general][]" value="Xoay cùng chiều" <?php echo in_array("Xoay cùng chiều", $subluxation['sacrum']['general'] ?? []) ? 'checked' : ''; ?>> Xoay cùng chiều</label>
                    </td>
                </tr>
                <tr>
                    <td colspan="3" style="padding-top: 1rem;">
                        <div style="font-size:0.8rem; font-weight:700; margin-bottom:0.5rem;">Becken (Khung chậu):</div>
                        <?php 
                        $becken_opts = ['AS', 'PI', 'IN-Ilium', 'EX-Ilium', 'Up-Slip', 'Down-Slip'];
                        foreach($becken_opts as $b){
                            echo '<label style="display:inline-block; margin-right:0.75rem;"><input type="checkbox" name="history[subluxation][becken][]" value="'.$b.'" '.(in_array($b, $subluxation['becken'] ?? []) ? 'checked' : '').'> '.$b.'</label>';
                        }
                        ?>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Khớp ngoại vi -->
        <div class="matrix-info-box">
            <h4 style="font-size: 0.85rem; text-align: center; color: #64748b; margin-top: 0; text-transform: uppercase; margin-bottom: 1rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;">Khớp Ngoại vi (Peripheral)</h4>
            <table style="width: 100%;">
                <?php 
                $peripherals = ['Xương quai xanh', 'Khớp cùng đòn (ACG)', 'Tennisarm', 'Khớp háng (Hip)', 'Gối (Knee)', 'Cổ chân (Ankle)'];
                foreach ($peripherals as $p): ?>
                    <tr>
                        <td style="text-align: center; padding: 6px;">
                            <?php echoMatrixDot($subluxation, 'peripheral', $p, 'L', 'L'); ?>
                        </td>
                        <td style="text-align: center; font-weight: 700; font-size: 0.85rem; color: #334155;"><?php echo $p; ?></td>
                        <td style="text-align: center; padding: 6px;">
                            <?php echoMatrixDot($subluxation, 'peripheral', $p, 'R', 'R'); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

    </div>

    <!-- 3.3 Vật lý trị liệu / Ghi chú -->
    <div class="premium-card" style="background: white; padding: 1.5rem; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 2.5rem;">
        <label class="section-label-premium" style="margin-bottom: 1rem;"><i class="fas fa-user-md"></i> Vật lý trị liệu / Phục hồi chức năng</label>
        <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem;">
            <?php 
            $pt_items = ['Nhiệt/Lạnh (Heat/Cryo)', 'Điện xung (DEMS)', 'Siêu âm (Ultrasound)', 'Giải cơ (Trigger Point)', 'Massage trị liệu', 'Kéo giãn (Traction)', 'Trị liệu bằng tay (Manual Therapy)', 'Bài tập chức năng'];
            foreach($pt_items as $pt): 
                $checked = in_array($pt, $physiotherapy) ? 'checked' : '';
            ?>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; background: #f8fafc; padding: 0.5rem 1rem; border-radius: 50px; border: 1px solid #e2e8f0; font-size: 0.85rem;">
                    <input type="checkbox" name="history[physiotherapy][]" value="<?php echo $pt; ?>" <?php echo $checked; ?> style="accent-color: var(--primary);"> 
                    <?php echo $pt; ?>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="form-group" style="margin: 0;">
            <label class="form-label" style="font-size: 0.95rem; font-weight: 700;">Ghi chú thêm (Vị trí khác/Chú thích)</label>
            <textarea name="history[assessment_notes]" class="form-input" rows="5" placeholder="..." style="min-height: 120px; font-size: 0.95rem; line-height: 1.5; resize: vertical;"><?php echo e($assessment_notes); ?></textarea>
        </div>
    </div>

    <!-- PHẦN 4: PLAN -->
    <h3 style="font-size: 1.1rem; text-transform: uppercase; color: var(--primary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">
        <i class="fas fa-clipboard-check"></i> 4. ĐÁNH GIÁ & KẾ HOẠCH (ASSESSMENT & PLAN - A/P)
    </h3>
    
    <div class="premium-card" style="background: white; padding: 1.5rem; border-radius: 12px; border: 1px solid #e2e8f0;">
        <div style="margin-bottom: 1.5rem;">
            <label class="form-label" style="font-weight: 700; margin-bottom: 0.75rem;"><i class="fas fa-chart-line"></i> Đánh giá buổi hôm nay:</label>
            <div style="display: flex; gap: 1.5rem;">
                <?php 
                $progs = ['Tiến triển tốt', 'Tiến triển chậm', 'Chưa cải thiện'];
                foreach($progs as $p): 
                ?>
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="radio" name="history[progress_assessment]" value="<?php echo $p; ?>" <?php echo ($progress_assessment === $p) ? 'checked' : ''; ?>> <?php echo $p; ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="margin-bottom: 1.5rem;">
            <label class="form-label" style="font-weight: 700; margin-bottom: 0.75rem;"><i class="fas fa-calendar-check"></i> Kế hoạch tiếp theo (Tần suất):</label>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <?php 
                $freqs = ['3 lần/tuần', '2 lần/tuần', '1 lần/tuần', '1 lần/2 tuần', '1 lần/tháng', '1 lần/3 tháng', 'Khi cần (PRN)'];
                foreach($freqs as $f): 
                ?>
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="radio" name="history[treatment_frequency]" value="<?php echo $f; ?>" <?php echo ($treatment_frequency === $f) ? 'checked' : ''; ?>> <?php echo $f; ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-group" style="margin: 0;">
            <label class="form-label" style="font-weight: 700; margin-bottom: 0.75rem;"><i class="fas fa-pen-alt"></i> Ghi chú (Notes):</label>
            <textarea name="history[plan_notes]" class="form-input" rows="8" placeholder="Nhập phác đồ, nhắc nhở định kỳ cho bệnh nhân..." style="min-height: 180px; font-size: 0.95rem; line-height: 1.6; resize: vertical;"><?php echo e($plan_notes); ?></textarea>
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
        <label class="form-label" style="font-weight: 700; color: #1e293b; margin-bottom: 0.75rem;"><i class="fas fa-user-md"></i> <?php echo __('medical.v2.doctor_notes_title'); ?></label>
        <textarea name="history[symptom_notes]" class="form-input" rows="5" placeholder="<?php echo __('medical.v2.doctor_notes_ph'); ?>" style="min-height: 120px; font-size: 0.95rem; line-height: 1.5; resize: vertical;"><?php echo e($symptom_notes); ?></textarea>
    </div>
</div>

<script>
// Từ điển Triệu Chứng
const symptomDict = {
    // Cổ
    "Atlas (C1)": "Não, tuyến yên, tai trong, hệ TK giao cảm - Đau đầu, mất ngủ, chóng mặt, huyết áp cao.",
    "Axis (C2)": "Mắt, TK thị giác, xoang, lưỡi - Viêm xoang, dị ứng, đau quanh mắt.",
    "C3": "Má, tai ngoài, răng, dây TK mặt - Đau dây TK, mụn trứng cá, chàm.",
    "C4": "Mũi, môi, miệng, vòi Eustachian - Sổ mũi, điếc nhẹ, vấn đề vùng miệng.",
    "C5": "Dây thanh quản, các tuyến ở cổ - Viêm họng, khàn tiếng.",
    "C6": "Cơ cổ, vai, amidan - Đau vai, cứng cổ, ho mãn tính.",
    "C7": "Tuyến giáp, khuỷu tay - Viêm bao hoạt dịch vai, vấn đề tuyến giáp.",
    // Ngực
    "T1": "Cẳng tay, bàn tay, thực quản, khí quản - Đau tay, khó thở, hen suyễn.",
    "T2": "Tim, động mạch vành - Các vấn đề về ngực, rối loạn nhịp tim.",
    "T3": "Phổi, phế quản, ngực - Viêm phế quản, viêm phổi, khó thở.",
    "T4": "Túi mật, ống mật - Vấn đề túi mật, sỏi mật.",
    "T5": "Gan, hệ tuần hoàn - Huyết áp thấp, vấn đề về gan.",
    "T6": "Dạ dày - Khó tiêu, ợ chua, đau dạ dày.",
    "T7": "Tuyến tụy, tá tràng - Viêm loét tá tràng, vấn đề đường huyết.",
    "T8": "Lá lách - Sức đề kháng kém, vấn đề về máu.",
    "T9": "Tuyến thượng thận - Dị ứng, nổi mề đay.",
    "T10": "Thận - Mệt mỏi mãn tính, vấn đề về thận.",
    "T11": "Thận, niệu quản - Vấn đề về da, tiểu tiện khó.",
    "T12": "Ruột non, hệ bạch huyết - Đau thấp khớp, đầy hơi.",
    // Thắt lưng
    "L1": "Ruột già, đại tràng - Táo bón, tiêu chảy, viêm đại tràng.",
    "L2": "Ruột thừa, bụng, đùi - Đau bụng, chuột rút.",
    "L3": "Cơ quan sinh dục, bàng quang, đầu gối - Vấn đề kinh nguyệt, bàng quang.",
    "L4": "Tuyến tiền liệt, cơ lưng dưới, TK tọa - Đau thần kinh tọa, đau lưng dưới.",
    "L5": "Cẳng chân, cổ chân, bàn chân - Tuần hoàn kém ở chân, sưng mắt cá.",
    // Xương cùng
    "S1": "Xương chậu, mông - Đau khớp cùng chậu, vấn đề vùng chậu.",
    "S2": "Xương chậu, mông - Đau khớp cùng chậu, vấn đề vùng chậu.",
    "S3": "Xương chậu, mông - Đau khớp cùng chậu, vấn đề vùng chậu.",
    "S4": "Xương chậu, mông - Đau khớp cùng chậu, vấn đề vùng chậu.",
    "S5": "Xương chậu, mông - Đau khớp cùng chậu, vấn đề vùng chậu."
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
        box.innerHTML = '<em>Chọn các sai lệch trên ma trận (Cột sống/Khung chậu) để hệ thống tự động gợi ý các chức năng/cơ quan bị ảnh hưởng và triệu chứng liên quan.</em>';
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
        html = '<em>Đã ghi nhận sai lệch ngoại vi/xương sườn (Không nằm trong từ điển gợi ý tự động).</em>';
    }
    
    box.innerHTML = html;
}

function copySymptomsToNotes() {
    const box = document.getElementById('symptom_summary_box');
    const noteArea = document.querySelector('textarea[name="history[plan_notes]"]');
    
    if(!noteArea) return;
    
    const items = box.querySelectorAll('.symptom-tag');
    if(items.length === 0) {
        alert('Không có triệu chứng nào để chép. Vui lòng check vào các đốt sống có sai lệch trước!');
        return;
    }
    
    let textToCopy = "=== GỢI Ý CHẨN ĐOÁN LÂM SÀNG ===\n";
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
        btn.innerHTML = '<i class="fas fa-check"></i> Đã chép thành công';
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
