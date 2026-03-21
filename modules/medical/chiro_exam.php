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
// Support for $history_id which might be used in some parts of the code
$history_id = $record_id;

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

$page_title = 'Khám bệnh lần đầu Chiropractic';
$current_page = 'medical';
require_once '../../templates/header.php';

// Define Spine Nodes matching the text file
$spine_nodes = [
    'Đốt sống Cổ (Cervical)' => [
        'C1' => 'Atlas', 
        'C2' => 'Axis 2', 
        'C3' => 'C3', 
        'C4' => 'C4', 
        'C5' => 'C5', 
        'C6' => 'C6', 
        'C7' => 'C7'
    ],
    'Đốt sống Ngực (Thoracic)' => [
        'D1' => 'D1', 'D2' => 'D2', 'D3' => 'D3', 'D4' => 'D4', 'D5' => 'D5', 'D6' => 'D6',
        'D7' => 'D7', 'D8' => 'D8', 'D9' => 'D9', 'D10' => 'D10', 'D11' => 'D11', 'D12' => 'D12'
    ],
    'Đốt sống Thắt lưng (Lumbar)' => [
        'L1' => 'L1', 'L2' => 'L2', 'L3' => 'L3', 'L4' => 'L4', 'L5' => 'L5'
    ],
    'Xương Cùng & Cụt' => [
        'Sac' => 'Sacrum (S1-S5)', 
        'Coc' => 'Coccyx (X. Cụt)'
    ],
    'Vùng Chậu (Becken)' => [
        'Rlli' => 'P. Ilium (R)', 
        'Llli' => 'T. Ilium (L)'
    ]
];

$becken_nodes = ['AS', 'PI', 'IN-Ilium', 'EX-Ilium', 'Up-Slip', 'Down-Slip'];

$joint_nodes = ['Khớp vai', 'Khớp khuỷu tay', 'Khớp cổ tay', 'Khớp háng', 'Khớp gối', 'Khớp cổ chân'];
$markers = $existing_data['markers'] ?? [];
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
            background: #7c3aed;
            border-color: #7c3aed;
            box-shadow: 0 4px 10px rgba(124, 58, 237, 0.3);
        }
        .matrix-dot:has(input:checked) span { color: white; }
        .info-box {
            background: white;
            padding: 1.5rem;
            border-radius: 16px;
            border: 1px solid #eef2f6;
            box-shadow: 0 4px 15px -5px rgba(0,0,0,0.05);
        }
    </style>

    <div class="card" style="background: var(--glass-bg); backdrop-filter: blur(20px);">
        <div style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h2 style="margin: 0; font-weight: 800; color: #7c3aed;"><i class="fas fa-stethoscope"></i> Khám Bệnh Lần Đầu Chiropractic</h2>
                <p style="color: var(--text-muted); margin-top: 0.25rem;">Bệnh nhân: <strong style="color: var(--text-main);"><?php echo e($patient_name); ?></strong></p>
            </div>
            <div style="background: #f5f3ff; color: #7c3aed; padding: 0.5rem 1.25rem; border-radius: 50px; font-weight: 700; font-size: 0.85rem; border: 1px solid #ddd6fe;">
                CHIR-PHYSICAL-EXAM
            </div>
        </div>

        <form method="POST">
            <?php if ($is_locked): ?>
                <div style="background: #fef2f2; color: #991b1b; padding: 1.25rem; border-radius: 20px; margin-bottom: 2rem; border: 1px solid #fecaca; display: flex; align-items: center; gap: 1rem; box-shadow: var(--premium-shadow);">
                    <i class="fas fa-lock fa-2x"></i>
                    <div>
                        <div style="font-weight: 800; font-size: 1rem;">HỒ SƠ ĐÃ KHÓA (CHỈ XEM)</div>
                        <div style="font-size: 0.85rem; font-weight: 600; opacity: 0.9;">Hồ sơ này thuộc buổi khám đã hoàn tất. Vui lòng liên hệ Admin nếu cần chỉnh sửa.</div>
                    </div>
                </div>
            <?php endif; ?>

            <fieldset <?php echo $is_locked ? 'disabled' : ''; ?> style="border: none; padding: 0; margin: 0;">
                <!-- 1. Spine Matrix -->
                <div style="margin-bottom: 3rem;">
                    <h3 style="font-size: 1.1rem; text-transform: uppercase; color: #7c3aed; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-bone"></i> I. MA TRẬN CỘT SỐNG (SUBLUXATION)
                    </h3>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                        <?php foreach ($spine_nodes as $group => $nodes): ?>
                            <div class="info-box">
                                <h4 style="font-size: 0.85rem; text-align: center; color: #64748b; margin-top: 0; text-transform: uppercase; margin-bottom: 1rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;"><?php echo $group; ?></h4>
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="font-size: 0.65rem; color: #94a3b8; text-align: center;">
                                            <th style="width: 30%;">L</th>
                                            <th style="width: 40%;">ĐỐT</th>
                                            <th style="width: 30%;">R</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($nodes as $key => $label): ?>
                                            <tr>
                                                <td style="text-align: center; padding: 4px;">
                                                    <label class="matrix-dot">
                                                        <input type="checkbox" name="exam[spine][<?php echo $key; ?>][L]" value="1" <?php echo isset($existing_data['spine'][$key]['L']) ? 'checked' : ''; ?> class="spine-node" data-node="<?php echo $key; ?>">
                                                        <span>L</span>
                                                    </label>
                                                </td>
                                                <td style="text-align: center; font-weight: 800; font-size: 0.9rem; color: #1e293b;"><?php echo $label; ?></td>
                                                <td style="text-align: center; padding: 4px;">
                                                    <label class="matrix-dot">
                                                        <input type="checkbox" name="exam[spine][<?php echo $key; ?>][R]" value="1" <?php echo isset($existing_data['spine'][$key]['R']) ? 'checked' : ''; ?> class="spine-node" data-node="<?php echo $key; ?>">
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

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                        <!-- Becken section -->
                        <div class="info-box">
                            <h4 style="font-size: 0.9rem; margin-bottom: 1.5rem; color: #64748b; text-transform: uppercase; text-align: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;">
                                <i class="fas fa-venus-mars"></i> Vùng Chậu (Becken)
                            </h4>
                            <table style="width: 100%;">
                                <?php foreach ($becken_nodes as $node): ?>
                                    <tr>
                                        <td style="text-align: center; padding: 6px;">
                                            <label class="matrix-dot">
                                                <input type="checkbox" name="exam[becken][<?php echo $node; ?>][L]" value="1" <?php echo isset($existing_data['becken'][$node]['L']) ? 'checked' : ''; ?>>
                                                <span>L</span>
                                            </label>
                                        </td>
                                        <td style="text-align: center; font-weight: 700; font-size: 0.85rem; color: #334155;"><?php echo $node; ?></td>
                                        <td style="text-align: center; padding: 6px;">
                                            <label class="matrix-dot">
                                                <input type="checkbox" name="exam[becken][<?php echo $node; ?>][R]" value="1" <?php echo isset($existing_data['becken'][$node]['R']) ? 'checked' : ''; ?>>
                                                <span>R</span>
                                            </label>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>

                        <!-- Joints section -->
                        <div class="info-box">
                            <h4 style="font-size: 0.9rem; margin-bottom: 1.5rem; color: #64748b; text-transform: uppercase; text-align: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;">
                                <i class="fas fa-joint"></i> Khớp ngoại vi
                            </h4>
                            <table style="width: 100%;">
                                <?php foreach ($joint_nodes as $node): ?>
                                    <tr>
                                        <td style="text-align: center; padding: 6px;">
                                            <label class="matrix-dot">
                                                <input type="checkbox" name="exam[joints][<?php echo $node; ?>][L]" value="1" <?php echo isset($existing_data['joints'][$node]['L']) ? 'checked' : ''; ?>>
                                                <span>L</span>
                                            </label>
                                        </td>
                                        <td style="text-align: center; font-weight: 700; font-size: 0.85rem; color: #334155;"><?php echo $node; ?></td>
                                        <td style="text-align: center; padding: 6px;">
                                            <label class="matrix-dot">
                                                <input type="checkbox" name="exam[joints][<?php echo $node; ?>][R]" value="1" <?php echo isset($existing_data['joints'][$node]['R']) ? 'checked' : ''; ?>>
                                                <span>R</span>
                                            </label>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 2. Pain Marker section (Existing Logic) -->
                <div style="margin-bottom: 3rem;">
                    <h3 style="font-size: 1.1rem; text-transform: uppercase; color: #7c3aed; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-map-marker-alt"></i> II. SƠ ĐỒ ĐIỂM ĐAU & CẢNH BÁO
                    </h3>

                    <!-- Symptom Correlation Panel (NEW) -->
                    <div id="symptom-correlation" style="display: none; margin-bottom: 2rem; background: #f8fafc; border: 1px solid #e2e8f0; padding: 2rem; border-radius: 20px; box-shadow: var(--premium-shadow);">
                        <h4 style="margin: 0 0 1.5rem 0; font-size: 1rem; color: #1e293b; display: flex; align-items: center; gap: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 800;">
                            <i class="fas fa-microscope" style="color: #7c3aed;"></i> BẢNG TRA CỨU BIỂU HIỆN CƠ THỂ THEO ĐỐT SỐNG
                        </h4>
                        <div id="symptom-list">
                            <!-- Symptoms will be injected here via JS -->
                        </div>
                    </div>

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

// Symptom Correlation Logic matching Text File/DOCX 100%
const symptomMap = {
    'C1': { organs: 'Não, tuyến yên, tai trong, hệ thần kinh giao cảm', symptoms: 'Đau đầu, mất ngủ, chóng mặt, huyết áp cao' },
    'C2': { organs: 'Mắt, thần kinh thị giác, xoang, lưỡi', symptoms: 'Viêm xoang, dị ứng, đau quanh mắt' },
    'C3': { organs: 'Má, tai ngoài, răng, dây thần kinh mặt', symptoms: 'Đau dây thần kinh, mụn trứng cá, chàm' },
    'C4': { organs: 'Mũi, môi, miệng, vòi Eustachian (tai)', symptoms: 'Sổ mũi, điếc nhẹ, các vấn đề về vùng miệng' },
    'C5': { organs: 'Dây thanh quản, các tuyến ở cổ', symptoms: 'Viêm họng, khàn tiếng' },
    'C6': { organs: 'Cơ cổ, vai, amidan', symptoms: 'Đau vai, cứng cổ, ho mãn tính' },
    'C7': { organs: 'Tuyến giáp, khuỷu tay', symptoms: 'Viêm bao hoạt dịch vai, các vấn đề tuyến giáp' },
    'D1': { organs: 'Cẳng tay, bàn tay, thực quản, khí quản', symptoms: 'Đau tay, khó thở, hen suyễn' },
    'D2': { organs: 'Tim, động mạch vành', symptoms: 'Các vấn đề về ngực, rối loạn nhịp tim' },
    'D3': { organs: 'Phổi, phế quản, ngực', symptoms: 'Viêm phế quản, viêm phổi, khó thở' },
    'D4': { organs: 'Túi mật, ống mật', symptoms: 'Các vấn đề về túi mật, sỏi mật' },
    'D5': { organs: 'Gan, hệ tuần hoàn', symptoms: 'Huyết áp thấp, các vấn đề về gan' },
    'D6': { organs: 'Dạ dày', symptoms: 'Khó tiêu, ợ chua, đau dạ dày' },
    'D7': { organs: 'Tuyến tụy, tá tràng', symptoms: 'Viêm loét tá tràng, vấn đề về đường huyết' },
    'D8': { organs: 'Lá lách', symptoms: 'Sức đề kháng kém, vấn đề về máu' },
    'D9': { organs: 'Tuyến thượng thận', symptoms: 'Dị ứng, nổi mề đay' },
    'D10': { organs: 'Thận', symptoms: 'Mệt mỏi mãn tính, các vấn đề về thận' },
    'D11': { organs: 'Thận, niệu quản', symptoms: 'Các vấn đề về da, tiểu tiện khó' },
    'D12': { organs: 'Ruột non, hệ bạch huyết', symptoms: 'Đau thấp khớp, đầy hơi' },
    'L1': { organs: 'Ruột già, đại tràng', symptoms: 'Táo bón, tiêu chảy, viêm đại tràng' },
    'L2': { organs: 'Ruột thừa, bụng, đùi', symptoms: 'Đau bụng, chuột rút' },
    'L3': { organs: 'Cơ quan sinh dục, bàng quang, đầu gối', symptoms: 'Các vấn đề về kinh nguyệt, bàng quang' },
    'L4': { organs: 'Tuyến tiền liệt, cơ lưng dưới, dây thần kinh tọa', symptoms: 'Đau thần kinh tọa, đau lưng dưới' },
    'L5': { organs: 'Cẳng chân, cổ chân, bàn chân', symptoms: 'Tuần hoàn kém ở chân, sưng mắt cá' },
    'Sac': { organs: 'Xương chậu, mông', symptoms: 'Đau khớp cùng chậu, vấn đề vùng chậu' },
    'Coc': { organs: 'Trực tràng, hậu môn', symptoms: 'Trĩ, đau khi ngồi' }
};

document.querySelectorAll('.spine-node').forEach(node => {
    node.addEventListener('change', updateSymptomPanel);
});

function updateSymptomPanel() {
    const activeCheckboxes = Array.from(document.querySelectorAll('.spine-node:checked'));
    const panel = document.getElementById('symptom-correlation');
    const list = document.getElementById('symptom-list');
    
    if (activeCheckboxes.length > 0) {
        if(panel) panel.style.display = 'block';
        
        // Extract unique node info
        const selectedInfo = [];
        const seen = new Set();
        
        activeCheckboxes.forEach(cb => {
            const nodeId = cb.getAttribute('data-node');
            if (!seen.has(nodeId)) {
                seen.add(nodeId);
                const row = cb.closest('tr');
                const label = row ? row.querySelector('td:nth-child(2)').innerText : nodeId;
                selectedInfo.push({ id: nodeId, label: label });
            }
        });

        let tableHtml = `
            <div class="table-responsive" style="margin-top: 1rem;">
                <table class="premium-table-symptom">
                    <thead>
                        <tr>
                            <th style="width: 15%;">Đốt sống</th>
                            <th style="width: 35%;">Cơ quan ảnh hưởng</th>
                            <th>Triệu chứng/Vấn đề có thể gặp phải</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        selectedInfo.forEach(info => {
            const data = symptomMap[info.id];
            if (data) {
                tableHtml += `
                    <tr>
                        <td style="font-weight: 800; color: #7c3aed; text-align: center; vertical-align: middle;">${info.label}</td>
                        <td style="color: #475569; font-size: 0.85rem; vertical-align: middle;">${data.organs}</td>
                        <td style="color: #1e293b; font-weight: 600; font-size: 0.9rem; vertical-align: middle;">${data.symptoms}</td>
                    </tr>
                `;
            }
        });
        
        tableHtml += '</tbody></table></div>';
        if(list) list.innerHTML = tableHtml;
    } else {
        if(panel) panel.style.display = 'none';
        if(list) list.innerHTML = '';
    }
}
</script>

<style>
    .premium-table-symptom {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        border: 1px solid #e2e8f0;
    }
    .premium-table-symptom th {
        background: #f8fafc;
        padding: 1rem;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        border-bottom: 2px solid #e2e8f0;
        text-align: left;
    }
    .premium-table-symptom td {
        padding: 1rem;
        border-bottom: 1px solid #f1f5f9;
        line-height: 1.5;
    }
    .premium-table-symptom tr:last-child td {
        border-bottom: none;
    }
</style>

<?php require_once '../../templates/footer.php'; ?>
