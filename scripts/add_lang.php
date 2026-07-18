<?php
// Script to append translations to vi.php and en.php
$vi_file = __DIR__ . '/lang/vi.php';
$en_file = __DIR__ . '/lang/en.php';

$vi_keys = [
    'medical.v2.exam_date' => 'Ngày khám',
    'medical.v2.exam_session' => '# Khám lần thứ',
    'medical.v2.exam_session_ph' => 'Ví dụ: 1, 2, 3...',
    'medical.v2.subj_title' => '1. CHỦ QUAN (S - SUBJECTIVE)',
    'medical.v2.subj_progress' => 'Tiến triển chung:',
    'medical.v2.subj_new_injury' => 'Chấn thương mới (nếu có):',
    'medical.v2.new_injury_ph' => 'Ngày bị và lý do ngắn gọn...',
    'medical.v2.subj_freq' => 'Tần suất triệu chứng:',
    'medical.v2.subj_act' => 'Đau khi thực hiện các hoạt động:',
    'medical.v2.subj_vas_total' => 'Thang điểm đau (VAS) Toàn thân:',
    'medical.v2.vas_0' => '0: Không đau',
    'medical.v2.vas_10' => '10: Đau dữ dội',
    'medical.v2.pain_locations' => 'Vị trí Đau (Pain Locations)',
    'medical.v2.add_pain' => 'Thêm vị trí đau',
    'medical.v2.pain_loc' => 'Vị trí đau',
    'medical.v2.pain_loc_ph' => 'VD: Cổ vai gáy, Thắt lưng, Gối trái...',
    'medical.v2.pain_vas' => 'VAS (0-10)',
    'medical.v2.pain_trend' => 'So với lần trước',
    'medical.v2.pain_symptoms' => 'Biểu hiện',
    'medical.v2.pain_trend_down' => 'Giảm',
    'medical.v2.pain_trend_up' => 'Tăng',
    'medical.v2.pain_trend_same' => 'Không đổi',
    'medical.v2.symp_ache' => 'Đau nhức',
    'medical.v2.symp_numb' => 'Tê bì',
    'medical.v2.symp_stiff' => 'Cứng khớp',
    'medical.v2.symp_dizzy' => 'Chóng mặt/Đau đầu',
    'medical.v2.obj_title' => '2. KHÁCH QUAN (O - OBJECTIVE)',
    'medical.v2.obj_muscle_tone' => 'Tone cơ (Muscle Hypertonicity)',
    'medical.v2.obj_muscle_ph' => 'VD: Co thắt cơ cổ rễ T/P, Căng cơ thắt lưng...',
    'medical.v2.obj_severity' => 'Mức độ (Severity):',
    'medical.v2.obj_rom' => 'Hạn chế tầm vận động (ROM)',
    'medical.v2.obj_rom_cervical' => 'Cổ',
    'medical.v2.obj_rom_thoracic' => 'Ngực',
    'medical.v2.obj_rom_lumbar' => 'Thắt lưng',
    'medical.v2.ass_title' => '3. ĐÁNH GIÁ & VỊ TRÍ ĐIỀU TRỊ (A - ASSESSMENT & ADJUSTMENT)',
    'medical.v2.ass_cervical' => 'ĐỐT SỐNG CỔ (CERVICAL)',
    'medical.v2.ass_thoracic' => 'ĐỐT SỐNG NGỰC (THORACIC)',
    'medical.v2.ass_lumbar' => 'ĐỐT SỐNG THẮT LƯNG (LUMBAR)',
    'medical.v2.ass_sacrum' => 'XƯƠNG CÙNG & CỤT (SACRUM/COCCYX)',
    'medical.v2.ass_becken' => 'VÙNG CHẬU (BECKEN)',
    'medical.v2.ass_peripheral' => 'KHỚP NGOẠI VI (PERIPHERAL)',
    'medical.v2.ass_diagnostic_ai' => 'Trợ lý Chẩn đoán (AI Diagnostics)',
    'medical.v2.ass_diagnostic_help' => 'Phân tích các đốt sống bị sai lệch',
    'medical.v2.ass_diagnostic_nodes' => 'Dựa vào đốt',
    'medical.v2.ass_diagnostic_notes' => 'Ghi chú Chẩn đoán của Bác sĩ',
    'medical.v2.ass_diagnostic_notes_ph' => 'Ghi nhận chẩn đoán đặc thù, bất thường cột sống (nếu có)...',
    'medical.v2.plan_title' => '4. CHỈ ĐỊNH ĐIỀU TRỊ (P - PLAN)',
    'medical.v2.plan_physio' => 'Vật lý trị liệu (Physiotherapy)',
    'medical.v2.plan_eval' => 'Cự ly / Đánh giá trong hôm nay:',
    'medical.v2.plan_freq' => 'Tần suất điều trị đề xuất:',
    'medical.v2.plan_notes' => 'Ghi chú liệu trình / Dặn dò',
    'medical.v2.plan_notes_ph' => 'Ví dụ: Cần thực hiện các bài tập cổ tại nhà, kiêng mang vác nặng...',
    'medical.v2.print_title' => 'PHIẾU KHÁM CHIROPRACTIC - KẾT QUẢ ĐÁNH GIÁ LÂM SÀNG',
    'medical.v2.print_patient' => 'Bệnh nhân:',
    'medical.v2.print_date' => 'Ngày khám:',
    'medical.v2.print_diag_ai' => 'Ghi chú Chẩn đoán (AI/Bác sĩ):',
    'medical.v2.print_physio' => 'Vật lý trị liệu:'
];

$en_keys = [
    'medical.v2.exam_date' => 'Examination Date',
    'medical.v2.exam_session' => '# Session Number',
    'medical.v2.exam_session_ph' => 'E.g.: 1, 2, 3...',
    'medical.v2.subj_title' => '1. SUBJECTIVE (S)',
    'medical.v2.subj_progress' => 'General Progress:',
    'medical.v2.subj_new_injury' => 'New Injury (if any):',
    'medical.v2.new_injury_ph' => 'Date and short reason...',
    'medical.v2.subj_freq' => 'Symptom Frequency:',
    'medical.v2.subj_act' => 'Pain during activities:',
    'medical.v2.subj_vas_total' => 'Total Pain Score (VAS):',
    'medical.v2.vas_0' => '0: No pain',
    'medical.v2.vas_10' => '10: Severe pain',
    'medical.v2.pain_locations' => 'Pain Locations',
    'medical.v2.add_pain' => 'Add Pain Location',
    'medical.v2.pain_loc' => 'Location',
    'medical.v2.pain_loc_ph' => 'E.g.: Neck, Lower back, Left knee...',
    'medical.v2.pain_vas' => 'VAS (0-10)',
    'medical.v2.pain_trend' => 'Compare to last visit',
    'medical.v2.pain_symptoms' => 'Symptoms',
    'medical.v2.pain_trend_down' => 'Decreased',
    'medical.v2.pain_trend_up' => 'Increased',
    'medical.v2.pain_trend_same' => 'Unchanged',
    'medical.v2.symp_ache' => 'Aching',
    'medical.v2.symp_numb' => 'Numbness',
    'medical.v2.symp_stiff' => 'Stiffness',
    'medical.v2.symp_dizzy' => 'Dizziness/Headache',
    'medical.v2.obj_title' => '2. OBJECTIVE (O)',
    'medical.v2.obj_muscle_tone' => 'Muscle Hypertonicity',
    'medical.v2.obj_muscle_ph' => 'E.g.: Cervical spasm L/R, Lumbar stiffness...',
    'medical.v2.obj_severity' => 'Severity:',
    'medical.v2.obj_rom' => 'Range of Motion Limit (ROM)',
    'medical.v2.obj_rom_cervical' => 'Cervical',
    'medical.v2.obj_rom_thoracic' => 'Thoracic',
    'medical.v2.obj_rom_lumbar' => 'Lumbar',
    'medical.v2.ass_title' => '3. ASSESSMENT & ADJUSTMENT (A)',
    'medical.v2.ass_cervical' => 'CERVICAL',
    'medical.v2.ass_thoracic' => 'THORACIC',
    'medical.v2.ass_lumbar' => 'LUMBAR',
    'medical.v2.ass_sacrum' => 'SACRUM/COCCYX',
    'medical.v2.ass_becken' => 'BECKEN',
    'medical.v2.ass_peripheral' => 'PERIPHERAL JOINTS',
    'medical.v2.ass_diagnostic_ai' => 'Diagnostic Assistant (AI)',
    'medical.v2.ass_diagnostic_help' => 'Analyze subluxation symptoms',
    'medical.v2.ass_diagnostic_nodes' => 'Based on',
    'medical.v2.ass_diagnostic_notes' => 'Doctor\'s Diagnostic Notes',
    'medical.v2.ass_diagnostic_notes_ph' => 'Record specific clinical diagnosis or abnormalities (if any)...',
    'medical.v2.plan_title' => '4. PLAN (P)',
    'medical.v2.plan_physio' => 'Physiotherapy',
    'medical.v2.plan_eval' => 'Evaluation Today:',
    'medical.v2.plan_freq' => 'Proposed Treatment Frequency:',
    'medical.v2.plan_notes' => 'Plan Notes / Instructions',
    'medical.v2.plan_notes_ph' => 'E.g.: Need to do neck exercises at home, avoid heavy lifting...',
    'medical.v2.print_title' => 'CHIROPRACTIC EXAM - CLINICAL ASSESSMENT',
    'medical.v2.print_patient' => 'Patient:',
    'medical.v2.print_date' => 'Date:',
    'medical.v2.print_diag_ai' => 'Diagnostic Notes (AI/Doc):',
    'medical.v2.print_physio' => 'Physiotherapy:'
];

function appendKeys($filepath, $keys) {
    if (!file_exists($filepath)) return;
    $content = file_get_contents($filepath);
    
    // Check if already added
    if (strpos($content, "'medical.v2.exam_date'") !== false) {
        return; // Already
    }

    $append_str = "\n    // --- CHIROPRACTIC V2 FORM STRINGS ---\n";
    foreach ($keys as $k => $v) {
        $append_str .= "    '" . $k . "' => '" . addslashes($v) . "',\n";
    }

    // Replace the last `];`
    $pos = strrpos($content, '];');
    if ($pos !== false) {
        $content = substr_replace($content, $append_str . "];", $pos, 2);
        file_put_contents($filepath, $content);
        echo "Successfully updated $filepath\n";
    }
}

appendKeys($vi_file, $vi_keys);
appendKeys($en_file, $en_keys);
