<?php
// modules/medical/chiro_history.php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

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
    
    set_flash('Lưu phiếu tiền sử bệnh Chiropractic thành công!');
    
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
    set_flash('Dữ liệu bệnh nhân không tồn tại!', 'error');
    redirect('index.php');
}

$page_title = 'Khám tiền sử bệnh Chiropractic';
$current_page = 'medical';
require_once '../../templates/header.php';
?>

<div class="card" style="background: var(--glass-bg); backdrop-filter: blur(20px); max-width: 1000px; margin: 0 auto;">
    <div style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: start;">
        <div>
            <h2 style="margin: 0; font-weight: 800; color: var(--primary);"><i class="fas fa-history"></i> Khám Tiền Sử Bệnh Chiropractic</h2>
            <p style="color: var(--text-muted); margin-top: 0.25rem;">Bệnh nhân: <strong style="color: var(--text-main);"><?php echo e($patient_name); ?></strong></p>
        </div>
        <div style="background: #f5f3ff; color: #7c3aed; padding: 0.5rem 1rem; border-radius: 12px; font-weight: 700;">HISTORY</div>
    </div>

    <form method="POST">
        <!-- PART 1: THÔNG TIN CƠ BẢN & LỐI SỐNG -->
        <div style="margin-bottom: 4rem;">
            <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--primary); margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 2px solid var(--border-color); padding-bottom: 0.75rem;">
                <i class="fas fa-user-check"></i> PHẦN 1: THÔNG TIN CƠ BẢN & LỐI SỐNG
            </h3>

            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem; margin-bottom: 3rem;">
                <div class="form-group">
                    <label class="form-label">Chiều cao (cm)</label>
                    <input type="number" name="exam[biometrics][height]" class="form-input" placeholder="..." value="<?php echo get_v('biometrics.height'); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Cân nặng (kg)</label>
                    <input type="number" name="exam[biometrics][weight]" class="form-input" placeholder="..." value="<?php echo get_v('biometrics.weight'); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Huyết áp (mmHg)</label>
                    <input type="text" name="exam[biometrics][blood_pressure]" class="form-input" placeholder="120/80" value="<?php echo get_v('biometrics.blood_pressure'); ?>">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3rem;">
                <div class="form-group">
                    <label class="form-label">Đặc thù công việc</label>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 0.5rem;">
                        <?php foreach (['Ngồi nhiều', 'Đứng nhiều', 'Lao động tay chân', 'Di chuyển nhiều'] as $job): ?>
                            <label class="checkbox-tag">
                                <input type="checkbox" name="exam[lifestyle][job][]" value="<?php echo $job; ?>" <?php echo checked_v('lifestyle.job', $job); ?>>
                                <span><?php echo $job; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Tần suất vận động</label>
                    <div style="display: flex; gap: 1.5rem; margin-top: 1rem;">
                        <?php foreach (['Không tập', 'Thỉnh thoảng', 'Thường xuyên'] as $freq): ?>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 600;">
                                <input type="radio" name="exam[lifestyle][exercise]" value="<?php echo $freq; ?>" <?php echo checked_v('lifestyle.exercise', $freq); ?>> <?php echo $freq; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="form-group" style="margin-top: 2rem;">
                <label class="form-label">Tiền sử bản thân (Birth History)</label>
                <div style="display: flex; flex-wrap: wrap; gap: 2rem; margin-top: 0.75rem; align-items: center;">
                    <?php foreach (['Sinh thường', 'Sinh mổ', 'Có dùng kẹp/giác hút'] as $birth): ?>
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 600;">
                            <input type="radio" name="exam[lifestyle][birth_history]" value="<?php echo $birth; ?>" <?php echo checked_v('lifestyle.birth_history', $birth); ?>> <?php echo $birth; ?>
                        </label>
                    <?php endforeach; ?>
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 600;">
                        <input type="radio" name="exam[lifestyle][birth_history]" value="Khác" <?php echo checked_v('lifestyle.birth_history', 'Khác'); ?>> Khác
                    </label>
                    <input type="text" name="exam[lifestyle][birth_history_other]" placeholder="Ghi chú thêm..." class="form-input" style="width: 300px;" value="<?php echo get_v('lifestyle.birth_history_other'); ?>">
                </div>
            </div>
        </div>

        <!-- PART 2: TÌNH TRẠNG BỆNH LÝ HIỆN TẠI -->
        <div style="margin-bottom: 4rem;">
            <h3 style="font-size: 1.1rem; color: var(--text-main); margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem;">
                <i class="fas fa-file-waveform" style="color: var(--primary);"></i> PHẦN 2: TÌNH TRẠNG BỆNH LÝ HIỆN TẠI
            </h3>
            
            <div class="form-group" style="margin-bottom: 2.5rem;">
                <label class="form-label">4. Vị trí đau chính (Có thể chọn nhiều)</label>
                <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 1rem; align-items: center;">
                    <?php foreach ([
                        'Cổ (Halswirbelsäule)', 
                        'Ngực/Lưng trên (Brustwirbelsäule)', 
                        'Thắt lưng (Lendenwirbelsäule)', 
                        'Khớp (Vai/Khuỷu tay/Cổ tay/Háng/Gối/Cổ chân)'
                    ] as $loc): ?>
                        <label class="checkbox-tag">
                            <input type="checkbox" name="exam[pathology][locations][]" value="<?php echo $loc; ?>" <?php echo checked_v('pathology.locations', $loc); ?>>
                            <span><?php echo $loc; ?></span>
                        </label>
                    <?php endforeach; ?>
                    <label class="checkbox-tag">
                        <input type="checkbox" name="exam[pathology][locations][]" value="Khác" <?php echo checked_v('pathology.locations', 'Khác'); ?>>
                        <span>Khác</span>
                    </label>
                    <input type="text" name="exam[pathology][locations_other]" placeholder="Vị trí khác..." class="form-input" style="width: 250px;" value="<?php echo get_v('pathology.locations_other'); ?>">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 2.5rem;">
                <label class="form-label">5. Tính chất cơn đau</label>
                <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 1rem;">
                    <?php foreach (['Đau nhói', 'Đau âm ỉ', 'Tê bì', 'Yêu cơ', 'Hạn chế vận động'] as $nature): ?>
                        <label class="checkbox-tag">
                            <input type="checkbox" name="exam[pathology][nature][]" value="<?php echo $nature; ?>" <?php echo checked_v('pathology.nature', $nature); ?>>
                            <span><?php echo $nature; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 2.5rem;">
                <label class="form-label">6. Yếu tố kích hoạt / Làm tăng đau</label>
                <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 1rem;">
                    <?php foreach (['Đi bộ', 'Ngồi lâu', 'Đứng lâu', 'Lúc ngủ', 'Sau khi ngủ dậy', 'Vận động mạnh'] as $trigger): ?>
                        <label class="checkbox-tag">
                            <input type="checkbox" name="exam[pathology][triggers][]" value="<?php echo $trigger; ?>" <?php echo checked_v('pathology.triggers', $trigger); ?>>
                            <span><?php echo $trigger; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <div class="form-group">
                    <label class="form-label">7. Mức độ đau (0-10)</label>
                    <div style="display: flex; align-items: center; gap: 1.5rem; margin-top: 1.5rem;">
                        <span style="color: #10b981; font-weight: 700;">0</span>
                        <input type="range" name="exam[pathology][intensity]" min="0" max="10" value="<?php echo get_v('pathology.intensity', 5); ?>" class="slider" style="flex-grow: 1;" oninput="document.getElementById('pain-val').innerText = this.value">
                        <span style="color: #ef4444; font-weight: 700;">10</span>
                        <span id="pain-val" style="background: var(--primary); color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.875rem;"><?php echo get_v('pathology.intensity', 5); ?></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">8. Thời gian triệu chứng</label>
                    <div style="display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 1rem;">
                        <?php foreach ([
                            'Cấp tính (vài ngày)', 
                            'Mạn tính (vài tháng/năm)', 
                            'Tái phát nhiều lần'
                        ] as $duration): ?>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 600;">
                                <input type="radio" name="exam[pathology][duration]" value="<?php echo $duration; ?>" <?php echo checked_v('pathology.duration', $duration); ?>> <?php echo $duration; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">9. Nguyên nhân kích hoạt (Có thể chọn nhiều)</label>
                <div style="display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 1rem;">
                    <?php foreach ([
                        'Ngã/Va chạm', 
                        'Tai nạn xe', 
                        'Tự nhiên bị'
                    ] as $cause): ?>
                        <label class="checkbox-tag">
                            <input type="checkbox" name="exam[pathology][activating_causes][]" value="<?php echo $cause; ?>" <?php echo checked_v('pathology.activating_causes', $cause); ?>>
                            <span><?php echo $cause; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">10. Mô tả triệu chứng chính</label>
                <textarea name="exam[pathology][description]" class="form-input" rows="4" placeholder="Nhập chi tiết về cơn đau, vị trí, tính chất..."><?php echo get_v('pathology.description'); ?></textarea>
            </div>

            <!-- SƠ ĐỒ ĐIỂM ĐAU / CẢNH BÁO -->
            <div style="margin-top: 3rem; margin-bottom: 3rem;">
                <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--primary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-edit"></i> SƠ ĐỒ ĐIỂM ĐAU / CẢNH BÁO
                </h3>
                
                <div style="display: flex; gap: 3rem;">
                    <div style="flex: 1; position: relative; background: white; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; cursor: crosshair;">
                        <canvas id="anatomy-canvas" width="800" height="800" style="width: 100%; height: auto; display: block;"></canvas>
                        <input type="hidden" name="exam[markers]" id="marking-data" value="<?php echo e(json_encode(get_v('markers', []))); ?>">
                    </div>
                    
                    <div style="width: 350px;">
                        <div style="background: #fff9f0; padding: 1.25rem; border-radius: 12px; border: 1px solid #ffedd5; margin-bottom: 2rem;">
                            <h4 style="font-size: 0.8rem; color: #9a3412; text-transform: uppercase; margin-bottom: 0.75rem; font-weight: 800;">Hướng dẫn:</h4>
                            <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.8rem; color: #9a3412; line-height: 1.6;">
                                <li><strong>O:</strong> Phẫu thuật (Màu vàng)</li>
                                <li><strong>X:</strong> Gãy xương (Màu đỏ)</li>
                                <li><strong>M:</strong> Điểm đau (Theo cường độ)</li>
                            </ul>
                        </div>

                        <div style="display: flex; gap: 0.75rem; margin-bottom: 2rem;">
                            <button type="button" class="tool-btn active" id="tool-marker" title="Điểm đau"><i class="fas fa-pencil-alt"></i></button>
                            <button type="button" class="tool-btn" id="tool-surgery" style="color: #f59e0b; font-weight: 900;">O</button>
                            <button type="button" class="tool-btn" id="tool-fracture" style="color: #ef4444; font-weight: 900;">X</button>
                            <button type="button" class="tool-btn" id="marker-eraser" title="Tẩy"><i class="fas fa-eraser"></i></button>
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

        <!-- PART 3: TIỀN SỬ Y KHOA & CHẤN THƯƠNG -->
        <div style="margin-bottom: 4rem;">
            <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--primary); margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 2px solid var(--border-color); padding-bottom: 0.75rem;">
                <i class="fas fa-history"></i> PHẦN 3: TIỀN SỬ Y KHOA & CHẤN THƯƠNG
            </h3>
            <p style="font-style: italic; color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.9rem;">(Kiểm tra các yếu tố ảnh hưởng đến cột sống)</p>

            <!-- Suspected Causes -->
            <div class="form-group" style="margin-bottom: 2.5rem;">
                <label class="form-label">8. Nguyên nhân nghi ngờ (nếu có): (nếu chọn thì hiện ra khung trống để điền thời gian đã xảy ra)</label>
                <div style="display: flex; flex-wrap: wrap; gap: 1.5rem; margin-top: 0.75rem;">
                    <?php foreach ([
                        'Tai nhận xe' => 'tai_nan_xe', 
                        'Ngã/Chấn thương thể thao' => 'nga_chan_thuong', 
                        'Không rõ nguyên nhân' => 'khong_ro'
                    ] as $label => $val): ?>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <label style="display: flex; align-items: center; gap: 0.4rem; cursor: pointer; font-weight: 600; font-size: 0.9rem;">
                                <input type="checkbox" name="exam[medical_history][causes][]" value="<?php echo $label; ?>" <?php echo checked_v('medical_history.causes', $label); ?>> <?php echo $label; ?>
                            </label>
                            <?php if ($val !== 'khong_ro'): ?>
                                <input type="text" name="exam[medical_history][cause_time][<?php echo $label; ?>]" class="form-input" placeholder="Thời gian..." style="width: 120px; padding: 0.25rem 0.5rem; font-size: 0.8rem;" value="<?php echo get_v('medical_history.cause_time.'.$label); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Intervention History -->
            <div class="form-group" style="margin-bottom: 2.5rem;">
                <label class="form-label">9. Tiền sử can thiệp:</label>
                <div style="display: flex; flex-direction: column; gap: 1.25rem; margin-top: 1rem; padding-left: 1rem;">
                    <!-- Surgery -->
                    <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; font-weight: 600;">
                            <input type="checkbox" name="exam[medical_history][surgery_flag]" value="1" <?php echo checked_v('medical_history.surgery_flag', '1'); ?>> Đã từng phẫu thuật
                        </label>
                        <span style="font-size: 0.9rem;">(Vùng: <input type="text" name="exam[medical_history][surgery_area]" class="form-input" style="display: inline-block; width: 140px;" value="<?php echo get_v('medical_history.surgery_area'); ?>"></span>
                        <span style="font-size: 0.9rem;">thời gian: <input type="text" name="exam[medical_history][surgery_time]" class="form-input" style="display: inline-block; width: 140px;" value="<?php echo get_v('medical_history.surgery_time'); ?>">)</span>
                        <span style="font-size: 0.75rem; color: #b45309; font-weight: 600;">(Đánh dấu O vàng lên hình trên)</span>
                    </div>
                    
                    <!-- Fracture -->
                    <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; font-weight: 600;">
                            <input type="checkbox" name="exam[medical_history][fracture_flag]" value="1" <?php echo checked_v('medical_history.fracture_flag', '1'); ?>> Từng bị gãy xương
                        </label>
                        <span style="font-size: 0.9rem;">(từ lúc nào: <input type="text" name="exam[medical_history][fracture_time]" class="form-input" style="display: inline-block; width: 140px;" value="<?php echo get_v('medical_history.fracture_time'); ?>">)</span>
                        <span style="font-size: 0.9rem;">Vị trí nào? <input type="text" name="exam[medical_history][fracture_area]" class="form-input" style="display: inline-block; width: 140px;" value="<?php echo get_v('medical_history.fracture_area'); ?>"></span>
                        <span style="font-size: 0.75rem; color: #b91c1c; font-weight: 600;">(Đánh dấu X đỏ vào hình trên)</span>
                    </div>

                    <!-- Dental -->
                    <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; font-weight: 600;">
                            <input type="checkbox" name="exam[medical_history][implants]" value="1" <?php echo checked_v('medical_history.implants', '1'); ?>> Đang đeo răng niềng/Có cấy ghép implant nha khoa
                        </label>
                        <span style="font-size: 0.9rem;">(từ lúc nào: <input type="text" name="exam[medical_history][implant_time]" class="form-input" style="display: inline-block; width: 140px;" value="<?php echo get_v('medical_history.implant_time'); ?>">)</span>
                    </div>

                    <!-- Imaging -->
                    <div style="display: flex; align-items: center; gap: 1.5rem;">
                         <span style="font-size: 0.9rem; font-weight: 800;">Đã có phim chụp: *</span>
                         <?php foreach (['X-Ray', 'MRI/CT'] as $p): ?>
                            <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; cursor: pointer; font-weight: 600;">
                                <input type="checkbox" name="exam[medical_history][imaging][]" value="<?php echo $p; ?>" <?php echo checked_v('medical_history.imaging', $p); ?>> <?php echo $p; ?>
                            </label>
                         <?php endforeach; ?>
                    </div>

                     <!-- Treatments -->
                    <div style="display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap;">
                         <span style="font-size: 0.9rem; font-weight: 800;">Đã từng điều trị tại: *</span>
                         <?php foreach (['Chiropractic khác', 'Vật lý trị liệu', 'Osteopath'] as $t): ?>
                            <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; cursor: pointer; font-weight: 600;">
                                <input type="checkbox" name="exam[medical_history][prev_treatments][]" value="<?php echo $t; ?>" <?php echo checked_v('medical_history.prev_treatments', $t); ?>> <?php echo $t; ?>
                            </label>
                         <?php endforeach; ?>
                    </div>

                    <!-- Accident (Moved here from redundant section) -->
                    <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; font-weight: 600;">
                            <input type="checkbox" name="exam[medical_history][accident_flag]" value="1" <?php echo checked_v('medical_history.accident_flag', '1'); ?>> Từng bị tai nạn xe cộ/ngã mạnh
                        </label>
                        <span style="font-size: 0.9rem;">(Vùng chấn thương: <input type="text" name="exam[medical_history][accident_area]" class="form-input" style="display: inline-block; width: 250px;" value="<?php echo get_v('medical_history.accident_area'); ?>">)</span>
                    </div>
                </div>
            </div>

            <!-- Disease Groups (Restored) -->
            <div class="form-group" style="margin-bottom: 2.5rem;">
                <label class="form-label">Nhóm bệnh Cơ - Xương - Khớp (Cực kỳ quan trọng)</label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                    <?php foreach ([
                        'Thoát vị đĩa đệm (Bandscheibenvorfall): Đã có chẩn đoán xác định.',
                        'Viêm khớp dạng thấp (Rheumatoid Polyarthritis): Hoặc các bệnh tự miễn về khớp.',
                        'Loãng xương (Osteoporosis): Nguy cơ gãy xương khi nắn chỉnh lực mạnh.',
                        'Thoái hóa cột sống nặng: Gây hẹp ống sống hoặc gai xương lớn.',
                        'Vẹo cột sống (Skoliose): Cột sống hình chữ S đã biết.',
                        'Viêm cột sống dính khớp: Gây cứng hóa các đốt sống.'
                    ] as $disease): ?>
                        <label style="display: flex; align-items: start; gap: 0.75rem; font-size: 0.85rem; cursor: pointer; border: 1px solid #e2e8f0; padding: 0.75rem; border-radius: 12px; background: #fff; transition: all 0.2s; line-height: 1.4;">
                            <input type="checkbox" name="exam[medical_history][ortho][]" value="<?php echo $disease; ?>" style="margin-top: 0.15rem;" <?php echo checked_v('medical_history.ortho', $disease); ?>> 
                            <span><?php echo $disease; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 2.5rem;">
                <label class="form-label">Nhóm bệnh Nội khoa & Hệ thống</label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                    <?php foreach ([
                        'Ung thư (Krebs): Bất kỳ loại nào, đặc biệt là ung thư xương hoặc di cá.',
                        'Huyết áp cao (Bluthochdruck): Liên quan đến nguy cơ lưu thông máu lên não.',
                        'Tiểu đường (Diabetes): Ảnh hưởng đến tốc độ phục hồi thần kinh và mạch máu.',
                        'Rối loạn đông máu: Hoặc đang sử dụng thuốc làm loãng máu (nguy cơ xuất huyết nội).',
                        'Bệnh lý tim mạch: Đã từng đặt stent, phẫu thuật tim hoặc dùng máy tạo nhịp.'
                    ] as $disease): ?>
                        <label style="display: flex; align-items: start; gap: 0.75rem; font-size: 0.85rem; cursor: pointer; border: 1px solid #e2e8f0; padding: 0.75rem; border-radius: 12px; background: #fff; transition: all 0.2s; line-height: 1.4;">
                            <input type="checkbox" name="exam[medical_history][internal][]" value="<?php echo $disease; ?>" style="margin-top: 0.15rem;" <?php echo checked_v('medical_history.internal', $disease); ?>> 
                            <span><?php echo $disease; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Medications -->
            <div class="form-group" style="margin-bottom: 2.5rem;">
                <label class="form-label">Thuốc/Thực phẩm chức năng đang dùng (Ghi rõ tên thuốc)</label>
                <div style="display: flex; gap: 1.5rem; align-items: center; margin-bottom: 1rem;">
                    <span style="font-size: 0.9rem; font-weight: 800; color: var(--primary);">Dùng từ:</span>
                    <input type="text" name="exam[medical_history][meds_time]" class="form-input" placeholder="ví dụ: 6 tháng, 2 năm..." style="display: inline-block; width: 200px;" value="<?php echo get_v('medical_history.meds_time'); ?>">
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 0.75rem; margin-bottom: 1.25rem;">
                    <?php foreach ([
                        'Giảm đau / Chống viêm', 
                        'Thuốc huyết áp / Tim mạch', 
                        'Thuốc tiểu đường', 
                        'Thuốc chống đông máu', 
                        'Thực phẩm chức năng (Xương khớp, Vitamin...)'
                    ] as $med): ?>
                        <label class="checkbox-tag" style="background: white; width: 100%; justify-content: start; text-align: left;">
                            <input type="checkbox" name="exam[medical_history][meds_common][]" value="<?php echo $med; ?>" <?php echo checked_v('medical_history.meds_common', $med); ?>>
                            <span style="padding: 0.75rem 1rem; width: 100%; box-sizing: border-box;"><?php echo $med; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <label class="form-label" style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.5rem;">Ghi chú thuốc cụ thể / thuốc khác:</label>
                <textarea name="exam[medical_history][meds_list]" class="form-input" rows="2" placeholder="Tên thuốc đang sử dụng..."><?php echo get_v('medical_history.meds_list'); ?></textarea>
            </div>

            <!-- Red Flags -->
            <div class="form-group" style="margin-top: 2rem;">
                <label class="form-label" style="color: #991b1b;"><i class="fas fa-exclamation-triangle"></i> Dấu hiệu thần kinh cấp cứu (Red Flags)</label>
                <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.5rem;">
                    <?php foreach (['Mất kiểm soát đại/tiểu tiện', 'Tê vùng yên ngựa', 'Yếu liệt chi tiến triển nhanh'] as $flag): ?>
                        <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: #991b1b; font-weight: 600; cursor: pointer;">
                            <input type="checkbox" name="exam[medical_history][red_flags][]" value="<?php echo $flag; ?>" <?php echo checked_v('medical_history.red_flags', $flag); ?>> <?php echo $flag; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- PART 4: RÀ SOÁT HỆ THỐNG (ROS) -->
        <div style="margin-bottom: 4rem;">
            <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--primary); margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 2px solid var(--border-color); padding-bottom: 0.75rem;">
                <i class="fas fa-stethoscope"></i> PHẦN 4: RÀ SOÁT HỆ THỐNG (ROS)
            </h3>
            <div class="form-group">
                <label class="form-label">Chọn các triệu chứng liên quan:</label>
                
                <!-- Vùng đầu mặt -->
                <div style="margin-bottom: 2rem; margin-top: 1rem;">
                    <div style="font-size: 0.9rem; font-weight: 800; color: var(--primary); margin-bottom: 0.75rem; border-left: 4px solid var(--primary); padding-left: 0.75rem;">Vùng đầu mặt:</div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 0.75rem;">
                        <?php foreach ([
                            'Đau đầu', 'Chóng mặt', 'Ù tai', 
                            'Vấn đề hàm (Khớp thái dương hàm)', 'Đang niềng răng'
                        ] as $item): ?>
                            <label class="checkbox-tag" style="background: white; width: 100%; justify-content: start; text-align: left;">
                                <input type="checkbox" name="exam[medical_history][ros][]" value="<?php echo $item; ?>" <?php echo checked_v('medical_history.ros', $item); ?>>
                                <span style="padding: 0.8rem 1rem; width: 100%; box-sizing: border-box; display: inline-block; border-radius: 12px;"><?php echo $item; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Cơ quan liên quan -->
                <div style="margin-bottom: 2rem;">
                    <div style="font-size: 0.9rem; font-weight: 800; color: var(--primary); margin-bottom: 0.75rem; border-left: 4px solid var(--primary); padding-left: 0.75rem;">Cơ quan liên quan:</div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 0.75rem;">
                        <?php foreach ([
                            'Tê lan xuống ngón tay', 'Đau tức ngực (không do tim)', 
                            'Đau/Tê lan xuống mông/chân', 'Có tiền sử Vẹo cột sống (S-form)'
                        ] as $item): ?>
                            <label class="checkbox-tag" style="background: white; width: 100%; justify-content: start; text-align: left;">
                                <input type="checkbox" name="exam[medical_history][ros][]" value="<?php echo $item; ?>" <?php echo checked_v('medical_history.ros', $item); ?>>
                                <span style="padding: 0.8rem 1rem; width: 100%; box-sizing: border-box; display: inline-block; border-radius: 12px;"><?php echo $item; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Cơ sở hạ tầng (Bàn chân) -->
                <div>
                    <div style="font-size: 0.9rem; font-weight: 800; color: var(--primary); margin-bottom: 0.75rem; border-left: 4px solid var(--primary); padding-left: 0.75rem;">Cơ sở hạ tầng (Bàn chân):</div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 0.75rem;">
                        <?php foreach ([
                            'Chênh lệch chiều dài chân', 
                            'Hay bị lật sơ mi (bong gân cổ chân)', 
                            'Đang dùng miếng lót giày/đế nâng'
                        ] as $item): ?>
                            <label class="checkbox-tag" style="background: white; width: 100%; justify-content: start; text-align: left;">
                                <input type="checkbox" name="exam[medical_history][ros][]" value="<?php echo $item; ?>" <?php echo checked_v('medical_history.ros', $item); ?>>
                                <span style="padding: 0.8rem 1rem; width: 100%; box-sizing: border-box; display: inline-block; border-radius: 12px;"><?php echo $item; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>


        <!-- PART 5: MỤC TIÊU ĐIỀU TRỊ -->
        <div style="margin-bottom: 4rem;">
            <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--primary); margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 2px solid var(--border-color); padding-bottom: 0.75rem;">
                <i class="fas fa-bullseye"></i> PHẦN 5: MỤC TIÊU ĐIỀU TRỊ
            </h3>
            <div class="form-group">
                <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; margin-top: 1rem;">
                    <?php foreach (['Giảm đau nhanh chóng', 'Phục hồi chức năng vận động', 'Chăm sóc sức khỏe lâu dài'] as $goal): ?>
                        <label class="checkbox-tag">
                            <input type="radio" name="exam[goals]" value="<?php echo $goal; ?>" <?php echo checked_v('goals', $goal); ?>>
                            <span><?php echo $goal; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="form-group" style="margin-top: 2rem; margin-bottom: 3rem;">
            <label class="form-label text-xs uppercase text-muted font-weight-800">Ghi chú bổ sung khác</label>
            <textarea name="exam[additional_notes]" class="form-input" rows="3" placeholder="Ghi chú thêm về tiền sử..."><?php echo get_v('additional_notes'); ?></textarea>
        </div>

        <div style="margin-top: 3.5rem; display: flex; gap: 1.5rem; justify-content: flex-end; border-top: 2px solid #f1f5f9; padding-top: 2rem;">
            <a href="../patients/view.php?id=<?php echo $patient_id; ?>" class="btn" style="background: #f1f5f9; color: var(--text-main); padding: 1.25rem 3rem; font-weight: 700; border-radius: 16px;">HỦY BỎ</a>
            <button type="submit" class="btn btn-primary" style="padding: 1.25rem 5rem; font-weight: 800; font-size: 1.25rem; border-radius: 16px; box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.4);">
                <i class="fas fa-save" style="margin-right: 0.5rem;"></i> LƯU GIỮ HỒ SƠ
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
document.addEventListener('DOMContentLoaded', () => {
    new MedicalMarking(
        'anatomy-canvas', 
        'marking-data', 
        '../../assets/images/anatomy_4_views_clean.png'
    );
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
