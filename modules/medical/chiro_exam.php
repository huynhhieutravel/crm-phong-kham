<?php
// modules/medical/chiro_exam.php
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
    $exam_data = json_encode($exam);
    
    if ($record_id) {
        $stmt = $db->prepare("UPDATE medical_history SET history_data = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$exam_data, $record_id]);
    } else {
        $stmt = $db->prepare("
            INSERT INTO medical_history (patient_id, session_id, type, history_data, created_by)
            VALUES (?, ?, 'chiro_exam', ?, ?)
        ");
        $stmt->execute([$patient_id, $session_id, $exam_data, $_SESSION['user_id']]);
    }
    
    set_flash('Lưu phiếu khám bệnh Chiropractic thành công!');
    
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

$page_title = 'Khám bệnh Chiropractic (Physical Exam)';
$current_page = 'medical';
require_once '../../templates/header.php';

// Define Spine Nodes
$spine_nodes = [
    'Cervical (C)' => ['C1' => 'Atlas', 'C2' => 'Axis', 'C3' => 'C3', 'C4' => 'C4', 'C5' => 'C5', 'C6' => 'C6', 'C7' => 'C7'],
    'Thoracic (D)' => ['D1' => 'D1', 'D2' => 'D2', 'D3' => 'D3', 'D4' => 'D4', 'D5' => 'D5', 'D6' => 'D6', 'D7' => 'D7', 'D8' => 'D8', 'D9' => 'D9', 'D10' => 'D10', 'D11' => 'D11', 'D12' => 'D12'],
    'Lumbar (L)' => ['L1' => 'L1', 'L2' => 'L2', 'L3' => 'L3', 'L4' => 'L4', 'L5' => 'L5'],
    'Other' => ['Sac' => 'Sacrum', 'Coc' => 'Coccyx', 'Rlli' => 'R Ilium', 'Llli' => 'L Ilium']
];

$becken_nodes = ['AS', 'PI', 'IN-Ilium', 'EX-Ilium', 'Up-Slip', 'Down-Slip'];

$joint_nodes = ['Khớp vai', 'Khớp khuỷu tay', 'Khớp cổ tay', 'Khớp háng', 'Khớp gối', 'Khớp cổ chân'];
?>

<div class="card" style="background: var(--glass-bg); backdrop-filter: blur(20px); max-width: 1000px; margin: 0 auto;">
    <div style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: start;">
        <div>
            <h2 style="margin: 0; font-weight: 800; color: var(--primary);"><i class="fas fa-stethoscope"></i> Khám Bệnh Thực Thể</h2>
            <p style="color: var(--text-muted); margin-top: 0.25rem;">Bệnh nhân: <strong style="color: var(--text-main);"><?php echo e($patient_name); ?></strong></p>
        </div>
        <div style="background: #eef2ff; color: #4f46e5; padding: 0.5rem 1rem; border-radius: 12px; font-weight: 700;">EXAMINATION</div>
    </div>

    <form method="POST">
        <!-- SYMPTOM CORRELATION PANEL (Real-time) -->
        <div id="symptom-correlation" style="margin-bottom: 3rem; display: none;">
            <div style="background: #eff6ff; border: 2px solid #3b82f6; border-radius: 16px; padding: 1.5rem;">
                <h4 style="font-size: 0.9rem; color: #1d4ed8; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-lightbulb"></i> GỢI Ý TRIỆU CHỨNG (Dựa trên chẩn đoán sai lệch)
                </h4>
                <div id="symptom-list" style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                    <!-- Symptoms will be injected here via JS -->
                </div>
            </div>
        </div>

        <!-- Spine Section -->
        <div style="margin-bottom: 4rem;">
            <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--primary); margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 2px solid var(--border-color); padding-bottom: 0.75rem;">
                <i class="fas fa-bone"></i> PHẦN 1: MA TRẬN CỘT SỐNG (Subluxation)
            </h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem;">
                <?php foreach ($spine_nodes as $group => $nodes): ?>
                    <div class="form-group">
                        <h4 style="font-size: 0.85rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 1.5rem; text-align: center; font-weight: 800;"><?php echo $group; ?></h4>
                        <table style="width: 100%; border-spacing: 0 8px;">
                            <thead>
                                <tr style="font-size: 0.75rem; color: #94a3b8; text-align: center;">
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
                                                <input type="checkbox" name="exam[spine][<?php echo $key; ?>][L]" value="1" class="spine-node" data-node="<?php echo $key; ?>" <?php echo checked_v('spine.'.$key.'.L', '1'); ?>>
                                                <span>L</span>
                                            </label>
                                        </td>
                                        <td style="text-align: center; font-weight: 700; color: var(--text-main); font-size: 1rem;"><?php echo $label; ?></td>
                                        <td style="text-align: center;">
                                            <label class="matrix-btn">
                                                <input type="checkbox" name="exam[spine][<?php echo $key; ?>][R]" value="1" class="spine-node" data-node="<?php echo $key; ?>" <?php echo checked_v('spine.'.$key.'.R', '1'); ?>>
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
        </div>

        <!-- Becken & Joint Section -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; margin-bottom: 4rem;">
            <!-- Becken (Pelvis) -->
            <div class="form-group">
                <h3 style="font-size: 1.1rem; color: var(--text-main); margin-bottom: 2rem; text-align: center; font-weight: 800; border-bottom: 1px dashed var(--border-color); padding-bottom: 1rem;">
                    <i class="fas fa-venus-mars" style="color: var(--primary);"></i> Vùng Chậu (Becken)
                </h3>
                <table style="width: 100%; border-spacing: 0 12px;">
                    <thead>
                        <tr style="font-size: 0.8rem; color: #94a3b8; text-align: center; text-transform: uppercase;">
                            <th>Trái (L)</th>
                            <th>LOẠI</th>
                            <th>Phải (R)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($becken_nodes as $node): ?>
                            <tr>
                                <td style="text-align: center;">
                                    <label class="matrix-btn">
                                        <input type="checkbox" name="exam[becken][<?php echo $node; ?>][L]" value="1" <?php echo checked_v('becken.'.$node.'.L', '1'); ?>>
                                        <span>L</span>
                                    </label>
                                </td>
                                <td style="text-align: center; font-weight: 800; color: var(--text-main);"><?php echo $node; ?></td>
                                <td style="text-align: center;">
                                    <label class="matrix-btn">
                                        <input type="checkbox" name="exam[becken][<?php echo $node; ?>][R]" value="1" <?php echo checked_v('becken.'.$node.'.R', '1'); ?>>
                                        <span>R</span>
                                    </label>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Joints -->
            <div class="form-group">
                <h3 style="font-size: 1rem; color: var(--text-main); margin-bottom: 2rem; text-align: center; font-weight: 800; border-bottom: 1px dashed var(--border-color); padding-bottom: 1rem;">
                    <i class="fas fa-hand-holding-medical" style="color: var(--primary);"></i> Khớp Ngoại Vi
                </h3>
                <table style="width: 100%; border-spacing: 0 12px;">
                    <thead>
                        <tr style="font-size: 0.8rem; color: #94a3b8; text-align: center; text-transform: uppercase;">
                            <th>Trái (L)</th>
                            <th>KHỚP</th>
                            <th>Phải (R)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($joint_nodes as $node): ?>
                            <tr>
                                <td style="text-align: center;">
                                    <label class="matrix-btn">
                                        <input type="checkbox" name="exam[joints][<?php echo $node; ?>][L]" value="1" <?php echo checked_v('joints.'.$node.'.L', '1'); ?>>
                                        <span>L</span>
                                    </label>
                                </td>
                                <td style="text-align: center; font-weight: 800; color: var(--text-main);"><?php echo $node; ?></td>
                                <td style="text-align: center;">
                                    <label class="matrix-btn">
                                        <input type="checkbox" name="exam[joints][<?php echo $node; ?>][R]" value="1" <?php echo checked_v('joints.'.$node.'.R', '1'); ?>>
                                        <span>R</span>
                                    </label>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- PART 3: GHI CHÚ THÍCH, ĐÁNH DẤU CƠ THỂ -->
        <div style="margin-bottom: 4rem;">
            <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--primary); margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 2px solid var(--border-color); padding-bottom: 0.75rem;">
                <i class="fas fa-edit"></i> PHẦN 3: GHI CHÚ THÍCH, ĐÁNH DẤU CƠ THỂ
            </h3>
            
            <div style="display: flex; gap: 3rem;">
                <div style="flex: 1; position: relative; background: white; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; cursor: crosshair;">
                    <canvas id="exam-anatomy-canvas" width="800" height="800" style="width: 100%; height: auto; display: block;"></canvas>
                    <input type="hidden" name="exam[markers]" id="exam-marking-data" value="<?php echo e(json_encode(get_v('markers', []))); ?>">
                </div>
                
                <div style="width: 350px;">
                    <div style="background: #f0f9ff; padding: 1.25rem; border-radius: 12px; border: 1px solid #bae6fd; margin-bottom: 2rem;">
                        <h4 style="font-size: 0.8rem; color: #0369a1; text-transform: uppercase; margin-bottom: 0.75rem; font-weight: 800;">Chẩn đoán lâm sàng:</h4>
                        <div style="font-size: 0.8rem; color: #0369a1; line-height: 1.6;">
                            Đánh dấu các vị trí sai lệch khớp và ghi chú chi tiết chẩn đoán bên dưới.
                        </div>
                    </div>

                    <div style="display: flex; gap: 0.75rem; margin-bottom: 2rem;">
                        <button type="button" class="tool-btn active" id="tool-marker" title="Điểm đau"><i class="fas fa-pencil-alt"></i></button>
                        <button type="button" class="tool-btn" id="tool-surgery" style="color: #f59e0b; font-weight: 900;">O</button>
                        <button type="button" class="tool-btn" id="tool-fracture" style="color: #ef4444; font-weight: 900;">X</button>
                        <button type="button" class="tool-btn" id="tool-eraser" title="Tẩy"><i class="fas fa-eraser"></i></button>
                        <button type="button" class="tool-btn" id="tool-clear" style="margin-left: auto; color: #ef4444;"><i class="fas fa-trash"></i></button>
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

        <div class="form-group" style="margin-top: 2rem;">
            <label class="form-label">Ghi chú lâm sàng & Chẩn đoán</label>
            <textarea name="exam[clinical_notes]" class="form-input" rows="5" placeholder="Ghi chú về các đoạn sai lệch và phát hiện lâm sàng..."><?php echo get_v('clinical_notes'); ?></textarea>
        </div>

        <div style="margin-top: 3rem; display: flex; gap: 1rem; justify-content: flex-end;">
            <a href="../patients/view.php?id=<?php echo $patient_id; ?>" class="btn" style="background: #f1f5f9; color: var(--text-main); padding: 1rem 2.5rem;">Hủy bỏ</a>
            <button type="submit" class="btn btn-primary" style="padding: 1rem 3rem; font-weight: 700; font-size: 1.1rem;">
                <i class="fas fa-save"></i> LƯU PHIẾU KHÁM BỆNH
            </button>
        </div>
    </form>
</div>

<style>
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

/* Marking Tool Styles */
.tool-btn {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: white;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
}
.tool-btn.active {
    background: #eff6ff;
    border: 1px solid #3b82f6;
    color: #3b82f6;
}

.intensity-btn {
    border: 2px solid transparent;
    border-radius: 10px;
    padding: 0.5rem;
    background: white;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    font-size: 0.65rem;
    font-weight: 800;
    transition: all 0.2s;
    width: 100%;
}
.intensity-btn span {
    width: 16px;
    height: 16px;
    border-radius: 50%;
}
.intensity-btn.active {
    background: #f8fafc;
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
    transform: translateY(-2px);
    border-color: currentColor;
}
</style>

<script src="../../assets/js/medical_marking.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    new MedicalMarking(
        'exam-anatomy-canvas', 
        'exam-marking-data', 
        '../../assets/images/anatomy_4_views_clean.png'
    );
    updateSymptomPanel();
});

// Symptom Correlation Logic
const symptomMap = {
    'C1': ['Đau đầu', 'Mất ngủ', 'Chóng mặt', 'Huyết áp cao'],
    'C2': ['Viêm xoang', 'Dị ứng', 'Đau quanh mắt'],
    'C3': ['Đau dây thần kinh mặt', 'Mụn trứng cá/chàm'],
    'C4': ['Sổ mũi', 'Điếc nhẹ', 'Vấn đề vùng miệng'],
    'C5': ['Viêm họng', 'Khàn tiếng'],
    'C6': ['Đau vai', 'Cứng cổ', 'Ho mãn tính'],
    'C7': ['Viêm bao hoạt dịch vai', 'Vấn đề tuyến giáp'],
    'D1': ['Đau tay', 'Khó thở', 'Hen suyễn'],
    'D2': ['Rối loạn nhịp tim', 'Đau ngực'],
    'D3': ['Viêm phế quản', 'Viêm phổi'],
    'D6': ['Khó tiêu', 'Ợ chua', 'Đau dạ dày'],
    'L1': ['Táo bón', 'Tiêu chảy', 'Viêm đại tràng'],
    'L4': ['Đau thần kinh tọa', 'Đau lưng dưới'],
    'L5': ['Tuần hoàn kém ở chân', 'Sưng mắt cá'],
    'Sac': ['Đau khớp cùng chậu', 'Vấn đề vùng chậu'],
    'Coc': ['Trĩ', 'Đau khi ngồi']
};

document.querySelectorAll('.spine-node').forEach(node => {
    node.addEventListener('change', updateSymptomPanel);
});

function updateSymptomPanel() {
    const activeNodes = Array.from(document.querySelectorAll('.spine-node:checked'))
                            .map(n => n.getAttribute('data-node'));
    
    const uniqueNodes = [...new Set(activeNodes)];
    const panel = document.getElementById('symptom-correlation');
    const list = document.getElementById('symptom-list');
    
    let symptoms = [];
    uniqueNodes.forEach(node => {
        if (symptomMap[node]) {
            symptoms = [...symptoms, ...symptomMap[node]];
        }
    });
    
    const uniqueSymptoms = [...new Set(symptoms)];
    
    if (uniqueSymptoms.length > 0) {
        if(panel) panel.style.display = 'block';
        if(list) list.innerHTML = uniqueSymptoms.map(s => `
            <div style="background: white; border: 1px solid #3b82f6; color: #1d4ed8; padding: 0.25rem 0.75rem; border-radius: 50px; font-size: 0.8rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-check-circle" style="font-size: 0.7rem;"></i> ${s}
            </div>
        `).join('');
    } else {
        if(panel) panel.style.display = 'none';
        if(list) list.innerHTML = '';
    }
}
</script>

<?php require_once '../../templates/footer.php'; ?>
