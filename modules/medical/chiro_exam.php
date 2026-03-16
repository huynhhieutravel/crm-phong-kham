<?php
// modules/medical/chiro_exam.php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$patient_id = $_GET['patient_id'] ?? 0;
$db = getDB();

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exam_data = json_encode($_POST['exam'] ?? []);
    
    $stmt = $db->prepare("
        INSERT INTO medical_history (patient_id, type, history_data, created_by)
        VALUES (?, 'chiro_exam', ?, ?)
    ");
    $stmt->execute([$patient_id, $exam_data, $_SESSION['user_id']]);
    
    set_flash('Lưu phiếu khám bệnh Chiropractic thành công!');
    redirect("../patients/view.php?id=$patient_id");
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
        <div style="margin-bottom: 3rem;">
            <h3 style="font-size: 1.1rem; color: var(--text-main); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem;">
                <i class="fas fa-bone" style="color: var(--primary);"></i> PHẦN 1: MA TRẬN CỘT SỐNG (Subluxation)
            </h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem;">
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
                                                <input type="checkbox" name="exam[spine][<?php echo $key; ?>][L]" value="1" class="spine-node" data-node="<?php echo $key; ?>">
                                                <span>L</span>
                                            </label>
                                        </td>
                                        <td style="text-align: center; font-weight: 700; color: var(--text-main); font-size: 0.9rem;"><?php echo $label; ?></td>
                                        <td style="text-align: center;">
                                            <label class="matrix-btn">
                                                <input type="checkbox" name="exam[spine][<?php echo $key; ?>][R]" value="1" class="spine-node" data-node="<?php echo $key; ?>">
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
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 3rem;">
            <!-- Becken (Pelvis) -->
            <div style="background: #f8fafc; border-radius: 16px; padding: 1.5rem; border: 1px solid #e2e8f0;">
                <h3 style="font-size: 1rem; color: var(--text-main); margin-bottom: 1.5rem; text-align: center;">
                    <i class="fas fa-venus-mars" style="color: var(--primary);"></i> Vùng Chậu (Becken)
                </h3>
                <table style="width: 100%; border-spacing: 0 8px;">
                    <thead>
                        <tr style="font-size: 0.75rem; color: #94a3b8; text-align: center;">
                            <th> trái (L)</th>
                            <th>TYPE</th>
                            <th> phải (R)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($becken_nodes as $node): ?>
                            <tr>
                                <td style="text-align: center;">
                                    <label class="matrix-btn">
                                        <input type="checkbox" name="exam[becken][<?php echo $node; ?>][L]" value="1">
                                        <span>L</span>
                                    </label>
                                </td>
                                <td style="text-align: center; font-weight: 700;"><?php echo $node; ?></td>
                                <td style="text-align: center;">
                                    <label class="matrix-btn">
                                        <input type="checkbox" name="exam[becken][<?php echo $node; ?>][R]" value="1">
                                        <span>R</span>
                                    </label>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Joints -->
            <div style="background: #f8fafc; border-radius: 16px; padding: 1.5rem; border: 1px solid #e2e8f0;">
                <h3 style="font-size: 1rem; color: var(--text-main); margin-bottom: 1.5rem; text-align: center;">
                    <i class="fas fa-hand-holding-medical" style="color: var(--primary);"></i> Khớp Ngoại Vi
                </h3>
                <table style="width: 100%; border-spacing: 0 8px;">
                    <thead>
                        <tr style="font-size: 0.75rem; color: #94a3b8; text-align: center;">
                            <th> trái (L)</th>
                            <th>JOINT</th>
                            <th> phải (R)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($joint_nodes as $node): ?>
                            <tr>
                                <td style="text-align: center;">
                                    <label class="matrix-btn">
                                        <input type="checkbox" name="exam[joints][<?php echo $node; ?>][L]" value="1">
                                        <span>L</span>
                                    </label>
                                </td>
                                <td style="text-align: center; font-weight: 700;"><?php echo $node; ?></td>
                                <td style="text-align: center;">
                                    <label class="matrix-btn">
                                        <input type="checkbox" name="exam[joints][<?php echo $node; ?>][R]" value="1">
                                        <span>R</span>
                                    </label>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Ghi chú lâm sàng & Chẩn đoán</label>
            <textarea name="exam[clinical_notes]" class="form-input" rows="5" placeholder="Ghi chú về các đoạn sai lệch và phát hiện lâm sàng..."></textarea>
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
</style>

<script>
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
        panel.style.display = 'block';
        list.innerHTML = uniqueSymptoms.map(s => `
            <div style="background: white; border: 1px solid #3b82f6; color: #1d4ed8; padding: 0.25rem 0.75rem; border-radius: 50px; font-size: 0.8rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-check-circle" style="font-size: 0.7rem;"></i> ${s}
            </div>
        `).join('');
    } else {
        panel.style.display = 'none';
        list.innerHTML = '';
    }
}
</script>

<?php require_once '../../templates/footer.php'; ?>
