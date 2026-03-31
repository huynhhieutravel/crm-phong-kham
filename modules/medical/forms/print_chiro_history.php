<?php
// modules/medical/forms/print_chiro_history.php
// Expected variables: $data, $patient

// Group data checks
$bio = $data['biometrics'] ?? [];
$life = $data['lifestyle'] ?? [];
$patho = $data['pathology'] ?? [];
$mh = $data['medical_history'] ?? [];
$ros = $mh['ros'] ?? [];
$goals = $data['goals'] ?? '';
$notes = $data['additional_notes'] ?? '';

$has_part1 = !empty($bio['height']) || !empty($bio['weight']) || !empty($bio['blood_pressure']) || !empty($life['job']) || !empty($life['exercise']) || !empty($life['birth_history']);

$has_part2 = !empty($patho['locations']) || !empty($patho['locations_other']);

$has_part3 = !empty($mh['causes']) || !empty($mh['surgery_flag']) || !empty($mh['fracture_flag']) || !empty($mh['implants']) || !empty($mh['accident_flag']) || !empty($mh['imaging']) || !empty($mh['prev_treatments']) || !empty($mh['ortho']) || !empty($mh['internal']) || !empty($mh['meds_common']) || !empty($mh['meds_list']) || !empty($mh['red_flags']);

$has_part4 = !empty($ros);
$has_part5 = !empty($goals) || !empty($notes);
?>

<style>
    .dy-section { margin-bottom: 25px; }
    .dy-box { padding-bottom: 12px; margin-bottom: 12px; border-bottom: 1px dashed #e2e8f0; font-size: 13px; line-height: 1.6; }
    .dy-box:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
    .dy-label { font-weight: 800; color: #334155; width: 160px; display: inline-block; vertical-align: top; }
    .dy-value { display: inline-block; width: calc(100% - 170px); }
    .dy-tag { display: inline-block; padding: 2px 8px; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 4px; margin: 0 4px 4px 0; font-size: 12px; color: #0f172a; }
    ul.check-list { list-style: none; padding: 0; margin: 0; }
    ul.check-list li { position: relative; padding-left: 20px; margin-bottom: 4px; }
    ul.check-list li:before { content: "✓"; position: absolute; left: 0; color: #10b981; font-weight: bold; }
</style>

<div class="rich-content" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 25px; background: #f8fafc;">

    <!-- TIỀN SỬ BỆNH & LỐI SỐNG -->
    <?php if ($has_part1): ?>
    <div class="dy-section">
        <h4 style="margin:0 0 10px 0; color: #2563eb; font-size: 14px; text-transform: uppercase; border-bottom: 2px solid #bfdbfe; padding-bottom: 5px;">I. CHỈ SỐ CƠ BẢN & LỐI SỐNG</h4>
        
        <?php if (!empty($bio['height']) || !empty($bio['weight']) || !empty($bio['blood_pressure'])): ?>
        <div class="dy-box">
            <span class="dy-label">Sinh hiệu:</span>
            <span class="dy-value">
                <?php if (!empty($bio['height'])) echo "Cao: <b>{$bio['height']}</b> cm &nbsp;|&nbsp; "; ?>
                <?php if (!empty($bio['weight'])) echo "Cân nặng: <b>{$bio['weight']}</b> kg &nbsp;|&nbsp; "; ?>
                <?php if (!empty($bio['blood_pressure'])) echo "Huyết áp: <b>{$bio['blood_pressure']}</b>"; ?>
            </span>
        </div>
        <?php endif; ?>

        <?php if (!empty($life['job']) || !empty($life['exercise'])): ?>
        <div class="dy-box">
            <span class="dy-label">Sinh hoạt / Công việc:</span>
            <span class="dy-value">
                <?php if (!empty($life['job'])) { echo "Đặc thù công việc: "; foreach($life['job'] as $v) echo "<span class='dy-tag'>$v</span> "; echo "<br>"; } ?>
                <?php if (!empty($life['exercise'])) echo "Vận động: <b>{$life['exercise']}</b>"; ?>
            </span>
        </div>
        <?php endif; ?>

        <?php if (!empty($life['birth_history'])): ?>
        <div class="dy-box">
            <span class="dy-label">Lịch sử sinh sản (Bản thân):</span>
            <span class="dy-value">
                <b><?php echo $life['birth_history']; ?></b>
                <?php if ($life['birth_history'] === 'Khác' && !empty($life['birth_history_other'])) echo " ({$life['birth_history_other']})"; ?>
            </span>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- TÌNH TRẠNG BỆNH LÝ HIỆN TẠI -->
    <?php if ($has_part2): ?>
    <div class="dy-section">
        <h4 style="margin:0 0 10px 0; color: #ea580c; font-size: 14px; text-transform: uppercase; border-bottom: 2px solid #fed7aa; padding-bottom: 5px;">II. TÌNH TRẠNG BỆNH LÝ HIỆN TẠI</h4>
        
        <div class="dy-box" style="border-bottom: none;">
            <span class="dy-label">Vị trí đau chính:</span>
            <span class="dy-value">
                <?php 
                $locs = $patho['locations'] ?? [];
                foreach ($locs as $loc) {
                    if ($loc === 'Khác') {
                        echo "<span class='dy-tag'>Khác: " . e($patho['locations_other'] ?? '') . "</span> ";
                    } else {
                        echo "<span class='dy-tag'>$loc</span> ";
                    }
                }
                ?>
            </span>
        </div>

        <?php 
        // Iterate details for locations
        $details = $patho['details'] ?? [];
        if (!empty($details)):
        ?>
            <div style="margin-left: 20px; padding-left: 15px; border-left: 3px solid #f97316;">
            <?php foreach ($details as $loc => $d): ?>
                <div style="margin-bottom: 15px;">
                    <strong style="color: #c2410c; display: block; margin-bottom: 5px;"><i class="fas fa-map-pin"></i> <?php echo $loc; ?></strong>
                    <div style="font-size: 13px;">
                        <?php if (!empty($d['intensity'])) echo "Cường độ đau (VAS): <strong style='color:#ef4444;'>{$d['intensity']}/10</strong> &nbsp;|&nbsp; "; ?>
                        <?php if (!empty($d['duration'])) echo "Thời gian: <strong>{$d['duration']}</strong><br>"; else echo "<br>"; ?>
                        
                        <?php if (!empty($d['nature'])) { echo "Tính chất: "; foreach($d['nature'] as $v) echo "<span class='dy-tag' style='background:#fffbeb; border-color:#fde68a;'>$v</span> "; echo "<br>"; } ?>
                        <?php if (!empty($d['triggers'])) { echo "Yếu tố kích phát: "; foreach($d['triggers'] as $v) echo "<span class='dy-tag' style='background:#fef2f2; border-color:#fecaca;'>$v</span> "; echo "<br>"; } ?>
                        <?php if (!empty($d['activating_causes'])) { echo "Hoàn cảnh xuất hiện: "; foreach($d['activating_causes'] as $v) echo "<span class='dy-tag'>$v</span> "; echo "<br>"; } ?>
                        
                        <?php if (!empty($d['description'])) echo "<div style='margin-top: 5px;'><i>Mô tả: ".nl2br(e($d['description']))."</i></div>"; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- TIỀN SỬ Y KHOA & CHẤN THƯƠNG -->
    <?php if ($has_part3): ?>
    <div class="dy-section">
        <h4 style="margin:0 0 10px 0; color: #6366f1; font-size: 14px; text-transform: uppercase; border-bottom: 2px solid #a5b4fc; padding-bottom: 5px;">III. TIỀN SỬ Y KHOA & CHẤN THƯƠNG</h4>
        
        <?php if (!empty($mh['causes'])): ?>
        <div class="dy-box">
            <span class="dy-label">Nguyên nhân chấn thương:</span>
            <span class="dy-value">
                <ul class="check-list">
                <?php foreach ($mh['causes'] as $c): 
                    $t = $mh['cause_time'][$c] ?? '';
                    echo "<li>$c" . ($t ? " (Thời gian: $t)" : "") . "</li>";
                endforeach; ?>
                </ul>
            </span>
        </div>
        <?php endif; ?>

        <?php if (!empty($mh['surgery_flag']) || !empty($mh['fracture_flag']) || !empty($mh['accident_flag']) || !empty($mh['implants'])): ?>
        <div class="dy-box">
            <span class="dy-label">Can thiệp Y khoa / Tai nạn:</span>
            <span class="dy-value">
                <ul class="check-list">
                    <?php if (!empty($mh['surgery_flag'])) echo "<li>Đã từng Phẫu thuật: vùng <b>" . ($mh['surgery_area'] ?? 'N/A') . "</b> (Vào: " . ($mh['surgery_time'] ?? 'N/A') . ")</li>"; ?>
                    <?php if (!empty($mh['fracture_flag'])) echo "<li>Đã từng Gãy xương: vùng <b>" . ($mh['fracture_area'] ?? 'N/A') . "</b> (Vào: " . ($mh['fracture_time'] ?? 'N/A') . ")</li>"; ?>
                    <?php if (!empty($mh['accident_flag'])) echo "<li>Tai nạn mạnh: vùng <b>" . ($mh['accident_area'] ?? 'N/A') . "</b></li>"; ?>
                    <?php if (!empty($mh['implants'])) echo "<li>Có cấy ghép Dental/Implant (Vào: " . ($mh['implant_time'] ?? 'N/A') . ")</li>"; ?>
                </ul>
            </span>
        </div>
        <?php endif; ?>

        <?php if (!empty($mh['imaging'])): ?>
        <div class="dy-box">
            <span class="dy-label">Chẩn đoán hình ảnh đã có:</span>
            <span class="dy-value"><?php foreach($mh['imaging'] as $v) echo "<span class='dy-tag'>$v</span> "; ?></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($mh['prev_treatments'])): ?>
        <div class="dy-box">
            <span class="dy-label">Đã từng điều trị:</span>
            <span class="dy-value"><?php foreach($mh['prev_treatments'] as $v) echo "<span class='dy-tag'>$v</span> "; ?></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($mh['ortho']) || !empty($mh['internal'])): ?>
        <div class="dy-box">
            <span class="dy-label">Bệnh lý nền (Nội khoa/Cơ xương khớp):</span>
            <span class="dy-value">
                <ul class="check-list">
                    <?php if (!empty($mh['ortho'])) { foreach ($mh['ortho'] as $v) echo "<li>$v</li>"; } ?>
                    <?php if (!empty($mh['internal'])) { foreach ($mh['internal'] as $v) echo "<li>$v</li>"; } ?>
                </ul>
            </span>
        </div>
        <?php endif; ?>

        <?php if (!empty($mh['meds_common']) || !empty($mh['meds_list'])): ?>
        <div class="dy-box">
            <span class="dy-label">Đang dùng thuốc:</span>
            <span class="dy-value">
                <?php if (!empty($mh['meds_common'])) { foreach($mh['meds_common'] as $v) echo "<span class='dy-tag'>$v</span> "; echo "<br>"; } ?>
                <?php if (!empty($mh['meds_list'])) echo "<div style='margin-top:5px; font-style:italic;'>Chi tiết: ".nl2br(e($mh['meds_list']))."</div>"; ?>
            </span>
        </div>
        <?php endif; ?>

        <?php if (!empty($mh['red_flags'])): ?>
        <div class="dy-box" style="border: 2px solid #fca5a5; padding: 10px; border-radius: 8px; background: #fef2f2;">
            <span class="dy-label" style="color: #b91c1c;">⚠️ Red Flags (Cảnh báo đỏ):</span>
            <span class="dy-value" style="color: #b91c1c; font-weight: 700;">
                <ul style="margin:0; padding-left: 20px;">
                    <?php foreach ($mh['red_flags'] as $v) echo "<li>$v</li>"; ?>
                </ul>
            </span>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- RÀ SOÁT HỆ THỐNG -->
    <?php if ($has_part4): ?>
    <div class="dy-section">
        <h4 style="margin:0 0 10px 0; color: #059669; font-size: 14px; text-transform: uppercase; border-bottom: 2px solid #6ee7b7; padding-bottom: 5px;">IV. RÀ SOÁT HỆ THỐNG (ROS)</h4>
        <div class="dy-box">
            <span class="dy-label">Triệu chứng liên quan:</span>
            <span class="dy-value">
                <ul class="check-list">
                    <?php foreach ($ros as $v) echo "<li>$v</li>"; ?>
                </ul>
            </span>
        </div>
    </div>
    <?php endif; ?>

    <!-- MỤC TIÊU -->
    <?php if ($has_part5): ?>
    <div class="dy-section">
        <h4 style="margin:0 0 10px 0; color: #475569; font-size: 14px; text-transform: uppercase;">V. MỤC TIÊU & GHI CHÚ</h4>
        <?php if (!empty($goals)): ?>
        <div class="dy-box">
            <span class="dy-label">Mục tiêu điều trị:</span>
            <span class="dy-value"><b><?php echo $goals; ?></b></span>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($notes)): ?>
        <div class="dy-box">
            <span class="dy-label">Ghi chú bổ sung:</span>
            <span class="dy-value"><?php echo nl2br(e($notes)); ?></span>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>
