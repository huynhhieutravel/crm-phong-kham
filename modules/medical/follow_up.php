<?php
// modules/medical/follow_up.php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$patient_id = $_GET['patient_id'] ?? 0;
$db = getDB();

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $soap_data = json_encode($_POST['soap'] ?? []);
    
    $stmt = $db->prepare("
        INSERT INTO medical_history (patient_id, type, history_data, created_by)
        VALUES (?, 'chiropractic', ?, ?)
    ");
    $stmt->execute([$patient_id, $soap_data, $_SESSION['user_id']]);
    
    set_flash('Lưu phiếu theo dõi điều trị (SOAP) thành công!');
    redirect("../patients/view.php?id=$patient_id");
}

$stmt = $db->prepare("SELECT full_name FROM patients WHERE id = ?");
$stmt->execute([$patient_id]);
$patient_name = $stmt->fetchColumn();

if (!$patient_name) {
    set_flash('Dữ liệu bệnh nhân không tồn tại!', 'error');
    redirect('index.php');
}

$page_title = 'Bản theo dõi điều trị Chiropractic (SOAP)';
$current_page = 'medical';
require_once '../../templates/header.php';

// Define Spine Nodes for Assessment section
$spine_nodes = [
    'Cervical (C)' => ['C1' => 'Atlas', 'C2' => 'Axis', 'C3' => 'C3', 'C4' => 'C4', 'C5' => 'C5', 'C6' => 'C6', 'C7' => 'C7'],
    'Thoracic (D)' => ['D1' => 'D1', 'D2' => 'D2', 'D3' => 'D3', 'D4' => 'D4', 'D5' => 'D5', 'D6' => 'D6', 'D7' => 'D7', 'D8' => 'D8', 'D9' => 'D9', 'D10' => 'D10', 'D11' => 'D11', 'D12' => 'D12'],
    'Lumbar (L)' => ['L1' => 'L1', 'L2' => 'L2', 'L3' => 'L3', 'L4' => 'L4', 'L5' => 'L5'],
    'Other' => ['Sac' => 'Sacrum', 'Coc' => 'Coccyx', 'Rlli' => 'R Ilium', 'Llli' => 'L Ilium']
];

$joint_nodes = ['Khớp vai', 'Khớp khuỷu tay', 'Khớp cổ tay', 'Khớp háng', 'Khớp gối', 'Khớp cổ chân'];
?>

<div class="card" style="background: var(--glass-bg); backdrop-filter: blur(20px); max-width: 1000px; margin: 0 auto;">
    <div style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: start;">
        <div>
            <h2 style="margin: 0; font-weight: 800; color: var(--primary);"><i class="fas fa-notes-medical"></i> Theo dõi Điều trị Chiro</h2>
            <p style="color: var(--text-muted); margin-top: 0.25rem;">Bệnh nhân: <strong style="color: var(--text-main);"><?php echo e($patient_name); ?></strong></p>
        </div>
        <div style="background: #eef2ff; color: #4f46e5; padding: 0.5rem 1rem; border-radius: 12px; font-weight: 700;">SOAP NOTE</div>
    </div>

    <form method="POST">
        <!-- 1. SUBJECTIVE (S) -->
        <div style="margin-bottom: 3rem;">
            <h3 style="font-size: 1.1rem; color: var(--text-main); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem;">
                <span style="background: var(--primary); color: white; width: 24px; height: 24px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">S</span>
                1. CHỦ QUAN (SUBJECTIVE)
            </h3>
            
            <div style="background: #f8fafc; border-radius: 16px; padding: 1.5rem; border: 1px solid #e2e8f0;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div>
                        <h4 style="font-size: 0.9rem; margin-bottom: 1rem; color: var(--text-muted); text-transform: uppercase;">Tiến triển chung</h4>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                            <?php foreach (['Cải thiện rõ rệt', 'Cải thiện nhẹ', 'Không thay đổi', 'Tệ hơn'] as $progress): ?>
                                <label class="checkbox-tag">
                                    <input type="radio" name="soap[s][progress]" value="<?php echo $progress; ?>">
                                    <span><?php echo $progress; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div style="margin-top: 1.5rem;">
                            <h4 style="font-size: 0.9rem; margin-bottom: 1rem; color: var(--text-muted); text-transform: uppercase;">Tần suất triệu chứng</h4>
                            <div class="medical-form-grid" style="grid-template-columns: 1fr 1fr;">
                                <?php foreach (['Thỉnh thoảng (0-25%)', 'Lúc có lúc không (25-50%)', 'Thường xuyên (50-75%)', 'Liên tục (75-100%)'] as $freq): ?>
                                    <label class="checkbox-card small">
                                        <input type="radio" name="soap[s][frequency]" value="<?php echo $freq; ?>">
                                        <span class="label-text" style="font-size: 0.7rem;"><?php echo $freq; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 style="font-size: 0.9rem; margin-bottom: 1rem; color: var(--text-muted); text-transform: uppercase;">Đau khi hoạt động</h4>
                        <div class="medical-form-grid" style="grid-template-columns: 1fr 1fr;">
                            <?php foreach (['Đứng', 'Ngồi', 'Nằm', 'Đi bộ', 'Cúi người', 'Nâng vật nặng', 'Toàn bộ HĐ'] as $act): ?>
                                <label class="checkbox-card small">
                                    <input type="checkbox" name="soap[s][activities][]" value="<?php echo $act; ?>">
                                    <span class="label-text" style="font-size: 0.8rem;"><?php echo $act; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div style="margin-top: 1.5rem;">
                            <label class="form-label" style="text-transform: uppercase; font-size: 0.85rem; color: var(--text-muted);">Thang điểm đau hiện tại (VAS): <span id="pain-val" style="color: var(--primary); font-weight: 800;">5</span>/10</label>
                            <input type="range" name="soap[s][vas]" min="0" max="10" value="5" class="slider">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. OBJECTIVE (O) -->
        <div style="margin-bottom: 3rem;">
            <h3 style="font-size: 1.1rem; color: var(--text-main); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem;">
                <span style="background: #10b981; color: white; width: 24px; height: 24px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">O</span>
                2. KHÁCH QUAN (OBJECTIVE)
            </h3>
            
            <div style="background: #f8fafc; border-radius: 16px; padding: 1.5rem; border: 1px solid #e2e8f0;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div>
                        <h4 style="font-size: 0.9rem; margin-bottom: 1rem; color: var(--text-muted); text-transform: uppercase;">Trương lực cơ (Hypertonicity)</h4>
                        <textarea name="soap[o][muscle_tone]" class="form-input" rows="2" placeholder="Vị trí cơ co thắt..." style="padding: 0.75rem; border-radius: 12px;"></textarea>
                        
                        <div style="margin-top: 1rem;">
                            <h4 style="font-size: 0.9rem; margin-bottom: 1rem; color: var(--text-muted); text-transform: uppercase;">Mức độ co thắt</h4>
                            <div style="display: flex; gap: 0.5rem;">
                                <?php foreach (['Nhẹ (Mild)', 'Vừa (Mod)', 'Nặng (Sev)'] as $sev): ?>
                                    <label class="checkbox-tag">
                                        <input type="radio" name="soap[o][severity]" value="<?php echo $sev; ?>">
                                        <span><?php echo $sev; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 style="font-size: 0.9rem; margin-bottom: 1rem; color: var(--text-muted); text-transform: uppercase;">Hạn chế tầm vận động (ROM)</h4>
                        <div class="medical-form-grid" style="grid-template-columns: 1fr 1fr 1fr;">
                            <?php foreach (['Cổ', 'Ngực', 'Thắt lưng'] as $rom): ?>
                                <label class="checkbox-card small">
                                    <input type="checkbox" name="soap[o][rom_limit][]" value="<?php echo $rom; ?>">
                                    <span class="label-text"><?php echo $rom; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top: 1rem;">
                            <h4 style="font-size: 0.9rem; margin-bottom: 0.5rem; color: var(--text-muted);">GHI CHÚ O</h4>
                            <input type="text" name="soap[o][notes]" class="form-input" style="padding: 0.5rem;" placeholder="Cố định khớp/Fixation...">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. ASSESSMENT & ADJUSTMENT (A) -->
        <div style="margin-bottom: 3rem;">
            <h3 style="font-size: 1.1rem; color: var(--text-main); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem;">
                <span style="background: #f59e0b; color: white; width: 24px; height: 24px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">A</span>
                3. ĐÁNH GIÁ & NẮN CHỈNH (ASSESSMENT)
            </h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                <?php foreach ($spine_nodes as $group => $nodes): ?>
                    <div style="background: #f8fafc; border-radius: 16px; padding: 1.25rem; border: 1px solid #e2e8f0;">
                        <h4 style="font-size: 0.85rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 1rem; text-align: center;"><?php echo $group; ?></h4>
                        <table style="width: 100%; border-spacing: 0 4px;">
                            <thead>
                                <tr style="font-size: 0.7rem; color: #94a3b8; text-align: center;">
                                    <th style="width: 33%;">L</th>
                                    <th style="width: 33%;">ĐỐT</th>
                                    <th style="width: 33%;">R</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($nodes as $key => $label): ?>
                                    <tr>
                                        <td style="text-align: center;">
                                            <label class="matrix-btn">
                                                <input type="checkbox" name="soap[a][spine][<?php echo $key; ?>][L]" value="1">
                                                <span>L</span>
                                            </label>
                                        </td>
                                        <td style="text-align: center; font-weight: 700; color: var(--text-main); font-size: 0.9rem;"><?php echo $label; ?></td>
                                        <td style="text-align: center;">
                                            <label class="matrix-btn">
                                                <input type="checkbox" name="soap[a][spine][<?php echo $key; ?>][R]" value="1">
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
                <h4 style="font-size: 0.9rem; margin-bottom: 1rem; color: #92400e; text-transform: uppercase;">Vật lý trị liệu / Phục hồi chức năng</h4>
                <div class="medical-form-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
                    <?php foreach ([
                        'Nhiệt/Lạnh', 'Điện xung (DEMS)', 'Siêu âm (Ultrasound)',
                        'Giải cơ (Trigger Point)', 'Massage trị liệu', 'Kéo giãn (Traction)',
                        'Trị liệu bằng tay', 'Bài tập chức năng'
                    ] as $pt): ?>
                        <label class="checkbox-card small" style="background: white;">
                            <input type="checkbox" name="soap[a][physiotherapy][]" value="<?php echo $pt; ?>">
                            <span class="label-text" style="font-size: 0.75rem;"><?php echo $pt; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- 4. PLAN (P) -->
        <div style="margin-bottom: 3rem;">
            <h3 style="font-size: 1.1rem; color: var(--text-main); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem;">
                <span style="background: #6366f1; color: white; width: 24px; height: 24px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">P</span>
                4. KẾ HOẠCH (PLAN)
            </h3>
            
            <div style="background: #f8fafc; border-radius: 16px; padding: 1.5rem; border: 1px solid #e2e8f0;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div>
                        <h4 style="font-size: 0.9rem; margin-bottom: 1rem; color: var(--text-muted); text-transform: uppercase;">Đánh giá hôm nay</h4>
                        <div style="display: flex; gap: 0.5rem;">
                            <?php foreach (['Tiến triển tốt', 'Tiến triển chậm', 'Chưa cải thiện'] as $eval): ?>
                                <label class="checkbox-tag">
                                    <input type="radio" name="soap[p][evaluation]" value="<?php echo $eval; ?>">
                                    <span><?php echo $eval; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div>
                        <h4 style="font-size: 0.9rem; margin-bottom: 1rem; color: var(--text-muted); text-transform: uppercase;">Tần suất đề xuất</h4>
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <?php foreach (['3 lần/tuần', '2 lần/tuần', '1 lần/tuần', 'PRN (Khi cần)'] as $freq): ?>
                                <label class="checkbox-tag">
                                    <input type="radio" name="soap[p][frequency]" value="<?php echo $freq; ?>">
                                    <span><?php echo $freq; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="form-group" style="margin-top: 1.5rem;">
                    <label class="form-label" style="font-size: 0.85rem;">Ghi chú Kế hoạch Tiếp theo</label>
                    <textarea name="soap[p][notes]" class="form-input" rows="3" placeholder="Yêu cầu bài tập về nhà hoặc thay đổi liệu trình..."></textarea>
                </div>
            </div>
        </div>

        <div style="margin-top: 3rem; display: flex; gap: 1rem; justify-content: flex-end;">
            <a href="../patients/view.php?id=<?php echo $patient_id; ?>" class="btn" style="background: #f1f5f9; color: var(--text-main); padding: 1rem 2.5rem;">Hủy bỏ</a>
            <button type="submit" class="btn btn-primary" style="padding: 1rem 3rem; font-weight: 700; font-size: 1.1rem;">
                <i class="fas fa-save"></i> LƯU PHIẾU SOAP
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
