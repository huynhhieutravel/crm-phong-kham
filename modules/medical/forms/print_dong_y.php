<?php
// modules/medical/forms/print_dong_y.php
// Expected variables: $data, $patient

$has_vital = !empty($data['bp_left']) || !empty($data['hr_left']) || !empty($data['bp_right']) || !empty($data['hr_right']);
$has_reason = !empty($data['reason']);

// II. VỌNG CHẨN
$has_vong = !empty($data['spirit']) || !empty($data['face_color']) || !empty($data['tongue_body']) || !empty($data['tongue_shape']) || !empty($data['tongue_tip']) || !empty($data['tongue_coating']) || !empty($data['eyes']) || !empty($data['eyelids']) || !empty($data['lips']);

// III. VĂN CHẨN
$has_van = !empty($data['voice_breath']) || !empty($data['body_odor']);

// IV. VẤN CHẨN
$has_van_chan = !empty($data['lifestyle_history']) || !empty($data['sleep_quality']) || !empty($data['night_wake_times']) || !empty($data['wake_up_state']) || !empty($data['habits']) || !empty($data['work_posture']) || !empty($data['living_env']) || !empty($data['digestion_eating']) || !empty($data['digestion_excretion']) || !empty($data['excretion_frequency']) || !empty($data['urine_color']) || !empty($data['night_urine_count']) || !empty($data['menses_regularity']) || !empty($data['menses_days']) || !empty($data['menses_pain']) || !empty($data['menses_color']) || !empty($data['menses_leucorrhoea']) || !empty($data['feels_cold']) || !empty($data['feels_hot']) || !empty($data['head_body_pain']) || !empty($data['joint_pain']) || !empty($data['chest_abdomen_pain']);

// V. THIẾT CHẨN
$has_thiet = !empty($data['pulse_left_cun']) || !empty($data['pulse_left_guan']) || !empty($data['pulse_left_chi']) || !empty($data['pulse_right_cun']) || !empty($data['pulse_right_guan']) || !empty($data['pulse_right_chi']) || !empty($data['abdominal_exam']);

// VI. KẾT LUẬN & ĐIỀU TRỊ
$has_ketluan_dieutri = !empty($data['diagnosis_syndrome']) || !empty($data['treatment_principle']) || !empty($data['acupuncture_points']) || !empty($data['herbal_prescription']) || !empty($data['other_treatments']) || !empty($data['doctor_advice']);
?>

<style>
    .dy-section { margin-bottom: 20px; }
    .dy-box { padding-bottom: 10px; margin-bottom: 10px; border-bottom: 1px dashed #e2e8f0; font-size: 13px; }
    .dy-box:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
    .dy-label { font-weight: 700; color: #475569; width: 140px; display: inline-block; vertical-align: top; }
    .dy-value { display: inline-block; width: calc(100% - 150px); }
    .dy-tag { display: inline-block; padding: 2px 8px; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 4px; margin: 0 4px 4px 0; font-size: 12px; color: #1e293b; }
</style>

<div class="rich-content" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; background: #f8fafc;">

    <?php if ($has_vital || $has_reason): ?>
    <div class="dy-section">
        <h4 style="margin:0 0 10px 0; color: #2563eb; font-size: 14px; text-transform: uppercase;">I. Tình trạng chung</h4>
        <?php if ($has_vital): ?>
            <div class="dy-box">
                <span class="dy-label">Sinh hiệu:</span>
                <span class="dy-value">
                    <?php if (!empty($data['bp_left']) || !empty($data['hr_left'])): ?>
                        <b>Trái:</b> HA <?php echo $data['bp_left'] ?? '-'; ?> mmHg, Mạch <?php echo $data['hr_left'] ?? '-'; ?> l/p &nbsp;|&nbsp;
                    <?php endif; ?>
                    <?php if (!empty($data['bp_right']) || !empty($data['hr_right'])): ?>
                        <b>Phải:</b> HA <?php echo $data['bp_right'] ?? '-'; ?> mmHg, Mạch <?php echo $data['hr_right'] ?? '-'; ?> l/p
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>
        <?php if (!empty($data['reason'])): ?>
            <div class="dy-box">
                <span class="dy-label">Lý do khám:</span>
                <span class="dy-value"><?php echo nl2br(e($data['reason'])); ?></span>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($has_vong): ?>
    <div class="dy-section">
        <h4 style="margin:0 0 10px 0; color: #d97706; font-size: 14px; text-transform: uppercase;">II. Vọng chẩn (Nhìn)</h4>
        
        <?php if (!empty($data['spirit']) || !empty($data['face_color'])): ?>
        <div class="dy-box">
            <span class="dy-label">Thần sắc & Mặt:</span>
            <span class="dy-value">
                <?php if (!empty($data['spirit'])) echo "Thần: <b>{$data['spirit']}</b> | "; ?>
                <?php if (!empty($data['face_color'])) echo "Sắc mặt: <b>{$data['face_color']}</b>"; ?>
            </span>
        </div>
        <?php endif; ?>

        <?php if (!empty($data['tongue_body']) || !empty($data['tongue_shape']) || !empty($data['tongue_tip']) || !empty($data['tongue_coating'])): ?>
        <div class="dy-box">
            <span class="dy-label">Lưỡi:</span>
            <span class="dy-value">
                <?php if (!empty($data['tongue_body'])) echo "Chất lưỡi: <b>{$data['tongue_body']}</b><br>"; ?>
                <?php if (!empty($data['tongue_shape'])) echo "Hình dáng: <b>{$data['tongue_shape']}</b><br>"; ?>
                <?php if (!empty($data['tongue_tip'])) echo "Đầu lưỡi: <b>{$data['tongue_tip']}</b><br>"; ?>
                <?php if (!empty($data['tongue_coating'])): ?>
                    Rêu lưỡi: <?php foreach($data['tongue_coating'] as $tc) echo "<span class='dy-tag'>$tc</span> "; ?>
                <?php endif; ?>
            </span>
        </div>
        <?php endif; ?>

        <?php if (!empty($data['eyes']) || !empty($data['eyelids'])): ?>
        <div class="dy-box">
            <span class="dy-label">Mắt & Mí:</span>
            <span class="dy-value">
                <?php if (!empty($data['eyes'])) { echo "Mắt: "; foreach($data['eyes'] as $e) echo "<span class='dy-tag'>$e</span> "; echo "<br>"; } ?>
                <?php if (!empty($data['eyelids'])) echo "Mí mắt: <b>{$data['eyelids']}</b>"; ?>
            </span>
        </div>
        <?php endif; ?>

        <?php if (!empty($data['lips'])): ?>
        <div class="dy-box">
            <span class="dy-label">Môi:</span>
            <span class="dy-value"><b><?php echo $data['lips']; ?></b></span>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($has_van): ?>
    <div class="dy-section">
        <h4 style="margin:0 0 10px 0; color: #059669; font-size: 14px; text-transform: uppercase;">III. Văn chẩn (Nghe, Ngửi)</h4>
        <?php if (!empty($data['voice_breath'])): ?>
        <div class="dy-box">
            <span class="dy-label">Tiếng & Hơi thở:</span>
            <span class="dy-value"><?php foreach($data['voice_breath'] as $v) echo "<span class='dy-tag'>$v</span> "; ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($data['body_odor'])): ?>
        <div class="dy-box">
            <span class="dy-label">Mùi cơ thể:</span>
            <span class="dy-value"><?php foreach($data['body_odor'] as $v) echo "<span class='dy-tag'>$v</span> "; ?></span>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($has_van_chan): ?>
    <div class="dy-section">
        <h4 style="margin:0 0 10px 0; color: #0284c7; font-size: 14px; text-transform: uppercase;">IV. Vấn chẩn (Hỏi)</h4>
        
        <?php if (!empty($data['lifestyle_history'])): ?>
        <div class="dy-box"><span class="dy-label">Tiền sử:</span><span class="dy-value"><?php echo nl2br(e($data['lifestyle_history'])); ?></span></div>
        <?php endif; ?>

        <?php if (!empty($data['sleep_quality']) || !empty($data['night_wake_times']) || !empty($data['wake_up_state'])): ?>
        <div class="dy-box">
            <span class="dy-label">Giấc ngủ:</span>
            <span class="dy-value">
                <?php if (!empty($data['sleep_quality'])) { foreach($data['sleep_quality'] as $v) echo "<span class='dy-tag'>$v</span> "; } ?>
                <?php if (!empty($data['night_wake_times'])) echo " (Tỉnh giấc: " . implode(', ', $data['night_wake_times']) . ")<br>"; else echo "<br>"; ?>
                <?php if (!empty($data['wake_up_state'])) echo "Sáng dậy: <b>{$data['wake_up_state']}</b>"; ?>
            </span>
        </div>
        <?php endif; ?>

        <?php if (!empty($data['habits']) || !empty($data['work_posture']) || !empty($data['living_env'])): ?>
        <div class="dy-box">
            <span class="dy-label">Thói quen/Môi trường:</span>
            <span class="dy-value">
                <?php if (!empty($data['habits'])) { foreach($data['habits'] as $v) echo "<span class='dy-tag'>$v</span> "; echo "<br>"; } ?>
                <?php if (!empty($data['work_posture'])) echo "Tư thế: <b>{$data['work_posture']}</b> | "; ?>
                <?php if (!empty($data['living_env'])) echo "Môi trường: <b>{$data['living_env']}</b>"; ?>
            </span>
        </div>
        <?php endif; ?>

        <?php if (!empty($data['digestion_eating']) || !empty($data['digestion_excretion']) || !empty($data['urine_color'])): ?>
        <div class="dy-box">
            <span class="dy-label">Tiêu hóa & Bài tiết:</span>
            <span class="dy-value">
                <?php if (!empty($data['digestion_eating'])) { echo "Ăn uống: "; foreach($data['digestion_eating'] as $v) echo "<span class='dy-tag'>$v</span> "; echo "<br>"; } ?>
                <?php if (!empty($data['digestion_excretion'])) { echo "Đại tiện: "; foreach($data['digestion_excretion'] as $v) echo "<span class='dy-tag'>$v</span> "; echo "({$data['excretion_frequency']})<br>"; } ?>
                <?php if (!empty($data['urine_color'])) { echo "Tiểu tiện: "; foreach($data['urine_color'] as $v) echo "<span class='dy-tag'>$v</span> "; echo "(Đêm: {$data['night_urine_count']})"; } ?>
            </span>
        </div>
        <?php endif; ?>

        <?php if (!empty($data['menses_regularity']) || !empty($data['menses_days']) || !empty($data['menses_pain']) || !empty($data['menses_color']) || !empty($data['menses_leucorrhoea'])): ?>
        <div class="dy-box">
            <span class="dy-label">Kinh nguyệt:</span>
            <span class="dy-value">
                <?php if (!empty($data['menses_regularity'])) echo "Chu kỳ: <b>{$data['menses_regularity']}</b> "; ?>
                <?php if (!empty($data['menses_days'])) echo "({$data['menses_days']} ngày)<br>"; else echo "<br>"; ?>
                <?php if (!empty($data['menses_pain'])) echo "Đau bụng: <b>{$data['menses_pain']}</b> | "; ?>
                <?php if (!empty($data['menses_color'])) echo "Màu: <b>{$data['menses_color']}</b><br>"; else echo "<br>"; ?>
                <?php if (!empty($data['menses_leucorrhoea'])) echo "Huyết trắng: <b>{$data['menses_leucorrhoea']}</b>"; ?>
            </span>
        </div>
        <?php endif; ?>

        <?php if (!empty($data['feels_cold']) || !empty($data['feels_hot']) || !empty($data['head_body_pain']) || !empty($data['joint_pain']) || !empty($data['chest_abdomen_pain'])): ?>
        <div class="dy-box">
            <span class="dy-label">Cảm giác & Đau:</span>
            <span class="dy-value">
                <?php if (!empty($data['feels_cold'])) { echo "Sợ lạnh: "; foreach($data['feels_cold'] as $v) echo "<span class='dy-tag'>$v</span> "; echo "<br>"; } ?>
                <?php if (!empty($data['feels_hot'])) { echo "Sợ nóng: "; foreach($data['feels_hot'] as $v) echo "<span class='dy-tag'>$v</span> "; echo "<br>"; } ?>
                <?php if (!empty($data['head_body_pain'])) { echo "Đầu/Thân: "; foreach($data['head_body_pain'] as $v) echo "<span class='dy-tag'>$v</span> "; echo "<br>"; } ?>
                <?php if (!empty($data['joint_pain'])) { echo "Xương khớp: "; foreach($data['joint_pain'] as $v) echo "<span class='dy-tag'>$v</span> "; echo "<br>"; } ?>
                <?php if (!empty($data['chest_abdomen_pain'])) { echo "Ngực bụng: "; foreach($data['chest_abdomen_pain'] as $v) echo "<span class='dy-tag'>$v</span> "; } ?>
            </span>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($has_thiet): ?>
    <div class="dy-section">
        <h4 style="margin:0 0 10px 0; color: #4f46e5; font-size: 14px; text-transform: uppercase;">V. Thiết chẩn (Sờ, Mạch)</h4>
        
        <?php if (!empty($data['pulse_left_cun']) || !empty($data['pulse_left_guan']) || !empty($data['pulse_left_chi']) || !empty($data['pulse_right_cun']) || !empty($data['pulse_right_guan']) || !empty($data['pulse_right_chi'])): ?>
        <div class="dy-box">
            <span class="dy-label">Mạch chẩn:</span>
            <span class="dy-value">
                <b>Trái:</b> Thốn (<?php echo $data['pulse_left_cun'] ?? '-'; ?>) | Quan (<?php echo $data['pulse_left_guan'] ?? '-'; ?>) | Xích (<?php echo $data['pulse_left_chi'] ?? '-'; ?>)<br>
                <b>Phải:</b> Thốn (<?php echo $data['pulse_right_cun'] ?? '-'; ?>) | Quan (<?php echo $data['pulse_right_guan'] ?? '-'; ?>) | Xích (<?php echo $data['pulse_right_chi'] ?? '-'; ?>)
            </span>
        </div>
        <?php endif; ?>

        <?php if (!empty($data['abdominal_exam'])): ?>
        <div class="dy-box">
            <span class="dy-label">Sờ nắn (Phúc chẩn):</span>
            <span class="dy-value"><?php foreach($data['abdominal_exam'] as $v) echo "<span class='dy-tag'>$v</span> "; ?></span>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($has_ketluan_dieutri): ?>
    <div class="dy-section">
        <h4 style="margin:0 0 10px 0; color: #e11d48; font-size: 14px; text-transform: uppercase;">VI. Chẩn đoán & Điều trị</h4>
        
        <?php if (!empty($data['diagnosis_syndrome'])): ?>
        <div class="dy-box"><span class="dy-label">Chẩn đoán Bát cương:</span><span class="dy-value" style="color: #be123c; font-weight: 700;"><?php echo e($data['diagnosis_syndrome']); ?></span></div>
        <?php endif; ?>

        <?php if (!empty($data['treatment_principle'])): ?>
        <div class="dy-box"><span class="dy-label">Pháp trị:</span><span class="dy-value"><?php echo nl2br(e($data['treatment_principle'])); ?></span></div>
        <?php endif; ?>

        <?php if (!empty($data['acupuncture_points'])): ?>
        <div class="dy-box"><span class="dy-label">Phác đồ Huyệt:</span><span class="dy-value"><b><?php echo e($data['acupuncture_points']); ?></b></span></div>
        <?php endif; ?>

        <?php if (!empty($data['herbal_prescription'])): ?>
        <div class="dy-box"><span class="dy-label">Bài thuốc:</span><span class="dy-value"><?php echo nl2br(e($data['herbal_prescription'])); ?></span></div>
        <?php endif; ?>

        <?php if (!empty($data['other_treatments'])): ?>
        <div class="dy-box"><span class="dy-label">Điều trị khác:</span><span class="dy-value"><?php echo implode(', ', $data['other_treatments']); ?></span></div>
        <?php endif; ?>

        <?php if (!empty($data['doctor_advice'])): ?>
        <div class="dy-box" style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #cbd5e1;">
            <span class="dy-label" style="color:#0f172a;">Lời dặn:</span>
            <span class="dy-value" style="font-style: italic;"><?php echo nl2br(e($data['doctor_advice'])); ?></span>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>
