<?php
// modules/medical/chiro_exam.php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

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

// 1. Fetch Session Status for Locking
$is_locked = false;
if ($session_id) {
    $stmt = $db->prepare("SELECT status, session_date FROM medical_sessions WHERE id = ?");
    $stmt->execute([$session_id]);
    $session = $stmt->fetch();
    if ($session && $session['status'] === 'completed' && !has_role('admin')) {
        $is_locked = true;
    }
}

// 2. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($is_locked) {
        set_flash('Buổi khám đã khóa. Không thể lưu thay đổi.', 'error');
        redirect("session_view.php?id=$session_id");
    }

    $exam = $_POST['exam'] ?? [];
    if (isset($exam['markers']) && is_string($exam['markers'])) {
        $exam['markers'] = json_decode($exam['markers'], true) ?: [];
    }
    $exam_data = json_encode($exam, JSON_UNESCAPED_UNICODE);
    
    if ($record_id) {
        // Fetch old data for audit
        $stmt_old = $db->prepare("SELECT history_data FROM medical_history WHERE id = ?");
        $stmt_old->execute([$record_id]);
        $old_json = $stmt_old->fetchColumn();
        
        $stmt = $db->prepare("UPDATE medical_history SET history_data = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$exam_data, $record_id]);
        
        log_audit($_SESSION['user_id'], 'update', 'medical_history', $record_id, json_decode($old_json, true), $exam);
    } else {
        $stmt = $db->prepare("
            INSERT INTO medical_history (patient_id, session_id, type, history_data, created_by)
            VALUES (?, ?, 'chiro_exam', ?, ?)
        ");
        $stmt->execute([$patient_id, $session_id, $exam_data, $_SESSION['user_id']]);
        $new_id = $db->lastInsertId();
        
        log_audit($_SESSION['user_id'], 'create', 'medical_history', $new_id, null, $exam);
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
        <?php if ($is_locked): ?>
            <div style="background: #fef2f2; color: #991b1b; padding: 1.25rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid #fecaca; display: flex; align-items: center; gap: 1rem;">
                <i class="fas fa-lock fa-2x"></i>
                <div>
                    <div style="font-weight: 800; font-size: 1rem;">HỒ SƠ ĐÃ KHÓA (CHỈ XEM)</div>
                    <div style="font-size: 0.85rem; font-weight: 600; opacity: 0.9;">Buổi khám này đã được hoàn tất. Bạn không thể thay đổi dữ liệu trừ khi được Admin mở lại.</div>
                </div>
            </div>
        <?php endif; ?>

        <fieldset <?php echo $is_locked ? 'disabled' : ''; ?> style="border: none; padding: 0; margin: 0;">
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
            ... (existing content) ...
            
            <div class="form-group" style="margin-top: 2rem;">
                <label class="form-label">Ghi chú lâm sàng & Chẩn đoán</label>
                <textarea name="exam[clinical_notes]" class="form-input" rows="5" placeholder="Ghi chú về các đoạn sai lệch và phát hiện lâm sàng..."><?php echo get_v('clinical_notes'); ?></textarea>
            </div>
        </fieldset>

        <div style="margin-top: 3rem; display: flex; gap: 1rem; justify-content: flex-end;">
            <a href="session_view.php?id=<?php echo $session_id; ?>" class="btn" style="background: #f1f5f9; color: var(--text-main); padding: 1rem 2.5rem;">Quay lại</a>
            <?php if (!$is_locked): ?>
                <button type="submit" class="btn btn-primary" style="padding: 1rem 3rem; font-weight: 700; font-size: 1.1rem;">
                    <i class="fas fa-save"></i> LƯU PHIẾU KHÁM BỆNH
                </button>
            <?php endif; ?>
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
