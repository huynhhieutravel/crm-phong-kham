<?php
// modules/medical/chiro_history.php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$patient_id = $_GET['patient_id'] ?? 0;
$db = getDB();

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $history_data = json_encode($_POST['exam'] ?? []);
    
    $stmt = $db->prepare("
        INSERT INTO medical_history (patient_id, type, history_data, created_by)
        VALUES (?, 'chiro_history', ?, ?)
    ");
    $stmt->execute([$patient_id, $history_data, $_SESSION['user_id']]);
    
    set_flash('Lưu phiếu tiền sử bệnh Chiropractic thành công!');
    redirect("../patients/view.php?id=$patient_id");
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
            <h2 style="margin: 0; font-weight: 800; color: var(--primary);"><i class="fas fa-history"></i> Khám Tiền Sử Bệnh</h2>
            <p style="color: var(--text-muted); margin-top: 0.25rem;">Bệnh nhân: <strong style="color: var(--text-main);"><?php echo e($patient_name); ?></strong></p>
        </div>
        <div style="background: #f5f3ff; color: #7c3aed; padding: 0.5rem 1rem; border-radius: 12px; font-weight: 700;">HISTORY</div>
    </div>

    <form method="POST">
        <!-- PART 1: BIOMETRICS & LIFESTYLE -->
        <div style="margin-bottom: 3rem;">
            <h3 style="font-size: 1.1rem; color: var(--text-main); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem;">
                <i class="fas fa-user-check" style="color: var(--primary);"></i> PHẦN 1: THÔNG TIN CƠ BẢN & LỐI SỐNG
            </h3>

            <!-- Biometrics Grid -->
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                <div class="form-group">
                    <label class="form-label">Chiều cao (cm)</label>
                    <input type="number" name="exam[biometrics][height]" class="form-input" placeholder="ví dụ: 170">
                </div>
                <div class="form-group">
                    <label class="form-label">Cân nặng (kg)</label>
                    <input type="number" name="exam[biometrics][weight]" class="form-input" placeholder="ví dụ: 65">
                </div>
                <div class="form-group">
                    <label class="form-label">Huyết áp (mmHg)</label>
                    <input type="text" name="exam[biometrics][blood_pressure]" class="form-input" placeholder="ví dụ: 120/80">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <!-- Job Characteristics -->
                <div style="background: #f8fafc; border-radius: 16px; padding: 1.5rem; border: 1px solid #e2e8f0;">
                    <h4 style="font-size: 0.9rem; margin-bottom: 1rem; color: var(--text-muted); text-transform: uppercase;">Đặc thù công việc</h4>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
                        <?php foreach (['Ngồi nhiều', 'Đứng nhiều', 'Lao động tay chân', 'Di chuyển nhiều'] as $job): ?>
                            <label class="checkbox-tag">
                                <input type="checkbox" name="exam[lifestyle][job][]" value="<?php echo $job; ?>">
                                <span><?php echo $job; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Exercise -->
                <div style="background: #f8fafc; border-radius: 16px; padding: 1.5rem; border: 1px solid #e2e8f0;">
                    <h4 style="font-size: 0.9rem; margin-bottom: 1rem; color: var(--text-muted); text-transform: uppercase;">Lối sống</h4>
                    <div class="form-group">
                        <label class="form-label" style="font-size: 0.8rem;">Tần suất vận động:</label>
                        <div style="display: flex; gap: 1rem;">
                            <?php foreach (['Không tập', 'Thỉnh thoảng', 'Thường xuyên'] as $freq): ?>
                                <label style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; cursor: pointer;">
                                    <input type="radio" name="exam[lifestyle][exercise]" value="<?php echo $freq; ?>"> <?php echo $freq; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Trauma & Previous Interventions -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 2rem;">
                <div style="background: #f8fafc; border-radius: 16px; padding: 1.5rem; border: 1px solid #e2e8f0;">
                    <h4 style="font-size: 0.9rem; margin-bottom: 1rem; color: var(--text-muted); text-transform: uppercase;">Tiền sử Chấn thương & Can thiệp</h4>
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <label class="checkbox-tag">
                            <input type="checkbox" name="exam[history][trauma][]" value="Tai nạn xe">
                            <span>Tai nạn xe</span>
                        </label>
                        <label class="checkbox-tag">
                            <input type="checkbox" name="exam[history][trauma][]" value="Ngã/Chấn thương thể thao">
                            <span>Ngã/Chấn thương</span>
                        </label>
                        <div class="form-group" style="margin-top: 0.5rem;">
                            <label class="form-label" style="font-size: 0.8rem;">Đã từng phẫu thuật?</label>
                            <input type="text" name="exam[history][surgery]" class="form-input" style="padding: 0.4rem;" placeholder="Vị trí & thời gian...">
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-size: 0.8rem;">Từng gãy xương?</label>
                            <input type="text" name="exam[history][fracture]" class="form-input" style="padding: 0.4rem;" placeholder="Vị trí & thời gian...">
                        </div>
                        <label class="checkbox-tag">
                            <input type="checkbox" name="exam[history][implants]" value="1">
                            <span>Có niềng răng / Implant nha khoa</span>
                        </label>
                    </div>
                </div>

                <div style="background: #fff1f2; border-radius: 16px; padding: 1.5rem; border: 1px solid #fecaca;">
                    <h4 style="font-size: 0.9rem; margin-bottom: 1rem; color: #991b1b; text-transform: uppercase;">Cảnh báo Lâm sàng</h4>
                    <div class="medical-form-grid" style="grid-template-columns: 1fr;">
                        <?php foreach ([
                            'Thoát vị đĩa đệm (đã chẩn đoán)',
                            'Viêm khớp dạng thấp / Tự miễn',
                            'Loãng xương (Osteoporosis)',
                            'Vẹo cột sống (Skoliose)',
                            'Huyết áp cao / Tim mạch',
                            'Ung thư / Di căn xương'
                        ] as $warning): ?>
                            <label class="checkbox-card small" style="background: white;">
                                <input type="checkbox" name="exam[history][warnings][]" value="<?php echo $warning; ?>">
                                <span class="label-text" style="font-size: 0.8rem;"><?php echo $warning; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- PART 2: CURRENT PATHOLOGY -->
        <div style="margin-bottom: 3rem;">
            <h3 style="font-size: 1.1rem; color: var(--text-main); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem;">
                <i class="fas fa-file-waveform" style="color: var(--primary);"></i> PHẦN 2: TÌNH TRẠNG BỆNH LÝ HIỆN TẠI
            </h3>

            <div style="background: #f8fafc; border-radius: 16px; padding: 2rem; border: 1px solid #e2e8f0;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2.5rem;">
                    <!-- Pain Locations -->
                    <div>
                        <h4 style="font-size: 0.9rem; margin-bottom: 1rem; color: var(--text-muted); text-transform: uppercase;">Vị trí đau chính</h4>
                        <div class="medical-form-grid" style="grid-template-columns: 1fr 1fr;">
                            <?php foreach (['Cổ', 'Ngực/Lưng trên', 'Thắt lưng', 'Khớp Tay/Chân', 'Khác'] as $loc): ?>
                                <label class="checkbox-card small">
                                    <input type="checkbox" name="exam[pathology][location][]" value="<?php echo $loc; ?>">
                                    <span class="label-text"><?php echo $loc; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div style="margin-top: 2rem;">
                            <h4 style="font-size: 0.9rem; margin-bottom: 1rem; color: var(--text-muted); text-transform: uppercase;">Tính chất cơn đau</h4>
                            <div class="medical-form-grid" style="grid-template-columns: 1fr 1fr;">
                                <?php foreach (['Đau nhói', 'Đau âm ỉ', 'Tê bì', 'Yêu cơ', 'Hạn chế vận động'] as $char): ?>
                                    <label class="checkbox-card small">
                                        <input type="checkbox" name="exam[pathology][character][]" value="<?php echo $char; ?>">
                                        <span class="label-text text-xs"><?php echo $char; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Pain Intensity & Duration -->
                    <div>
                        <div style="margin-bottom: 2rem;">
                            <h4 style="font-size: 0.9rem; margin-bottom: 1rem; color: var(--text-muted); text-transform: uppercase;">Mức độ đau (0-10)</h4>
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <span style="font-weight: 700; color: #10b981;">0</span>
                                <input type="range" name="exam[pathology][intensity]" min="0" max="10" value="5" class="slider" style="flex: 1;">
                                <span style="font-weight: 700; color: #ef4444;">10</span>
                                <div id="pain-val" style="width: 40px; height: 40px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.1rem;">5</div>
                            </div>
                        </div>

                        <div>
                            <h4 style="font-size: 0.9rem; margin-bottom: 1rem; color: var(--text-muted); text-transform: uppercase;">Thời gian triệu chứng</h4>
                            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                <?php foreach (['Cấp tính (vài ngày)', 'Mãn tính (vài tháng/năm)', 'Tái phát nhiều lần'] as $dur): ?>
                                    <label style="display: flex; align-items: center; gap: 0.75rem; background: white; padding: 0.75rem 1rem; border-radius: 10px; border: 1px solid #e2e8f0; cursor: pointer;">
                                        <input type="radio" name="exam[pathology][duration]" value="<?php echo $dur; ?>">
                                        <span style="font-size: 0.9rem;"><?php echo $dur; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Review of Systems (ROS) -->
            <div style="margin-top: 2rem; background: #f8fafc; border-radius: 16px; padding: 1.5rem; border: 1px solid #e2e8f0;">
                <h4 style="font-size: 0.9rem; margin-bottom: 1rem; color: var(--text-muted); text-transform: uppercase;">Rà soát Hệ thống</h4>
                <div class="medical-form-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                    <?php foreach ([
                        'Đau đầu', 'Chóng mặt', 'Ù tai', 'Vấn đề hàm/niềng răng',
                        'Tê lan xuống tay', 'Đau tức ngực', 'Tê lan xuống chân',
                        'Vẹo cột sống (S-form)', 'Chênh lệch chiều dài chân',
                        'Hay bị lật sơ mi', 'Mất kiểm soát đại/tiểu tiện'
                    ] as $sys): ?>
                        <label class="checkbox-card small">
                            <input type="checkbox" name="exam[pathology][systems][]" value="<?php echo $sys; ?>">
                            <span class="label-text" style="font-size: 0.75rem;"><?php echo $sys; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- PART 4: TREATMENT GOALS -->
        <div style="margin-bottom: 3rem;">
            <h3 style="font-size: 1.1rem; color: var(--text-main); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem;">
                <i class="fas fa-bullseye" style="color: var(--primary);"></i> PHẦN 3: MỤC TIÊU ĐIỀU TRỊ
            </h3>
            <div style="background: #f8fafc; border-radius: 16px; padding: 1.5rem; border: 1px solid #e2e8f0;">
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <?php foreach (['Giảm đau nhanh chóng', 'Phục hồi chức năng vận động', 'Chăm sóc sức khỏe lâu dài / Phòng ngừa'] as $goal): ?>
                        <label class="checkbox-tag">
                            <input type="radio" name="exam[goals]" value="<?php echo $goal; ?>">
                            <span><?php echo $goal; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Ghi chú bổ sung</label>
            <textarea name="exam[additional_notes]" class="form-input" rows="3" placeholder="Ghi chú thêm về tiền sử..."></textarea>
        </div>

        <div style="margin-top: 3rem; display: flex; gap: 1rem; justify-content: flex-end;">
            <a href="../patients/view.php?id=<?php echo $patient_id; ?>" class="btn" style="background: #f1f5f9; color: var(--text-main); padding: 1rem 2.5rem;">Hủy bỏ</a>
            <button type="submit" class="btn btn-primary" style="padding: 1rem 3rem; font-weight: 700; font-size: 1.1rem;">
                <i class="fas fa-save"></i> LƯU TIỀN SỬ BỆNH
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
}

.checkbox-card.small { padding: 0.75rem; }
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
