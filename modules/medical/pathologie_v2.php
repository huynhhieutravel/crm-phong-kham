<?php
// modules/medical/pathologie_v2.php

require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_medical');

$patient_id = (int)($_GET['patient_id'] ?? 0);
$session_id = isset($_GET['session_id']) ? (int)$_GET['session_id'] : null;
$record_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$db = getDB();

$existing_data = [];
if ($record_id) {
    $stmt = $db->prepare("SELECT history_data FROM medical_history WHERE id = ?");
    $stmt->execute([$record_id]);
    $json = $stmt->fetchColumn();
    $existing_data = json_decode($json, true) ?: [];

    // MIGRATION: Move old global details into the first selected location
    if (!isset($existing_data['pathology']['details']) && !empty($existing_data['pathology']['locations'])) {
        $first_loc = $existing_data['pathology']['locations'][0];
        $existing_data['pathology']['details'] = [];
        $existing_data['pathology']['details'][$first_loc] = [
            'nature' => $existing_data['pathology']['nature'] ?? [],
            'triggers' => $existing_data['pathology']['triggers'] ?? [],
            'intensity' => $existing_data['pathology']['intensity'] ?? 5,
            'duration' => $existing_data['pathology']['duration'] ?? '',
            'activating_causes' => $existing_data['pathology']['activating_causes'] ?? [],
            'description' => $existing_data['pathology']['description'] ?? ''
        ];
    }
}

function get_detail_v($loc, $key, $default = '') {
    global $existing_data;
    return $existing_data['pathology']['details'][$loc][$key] ?? $default;
}

function checked_detail_v($loc, $key, $value) {
    global $existing_data;
    $val = $existing_data['pathology']['details'][$loc][$key] ?? null;
    if (is_array($val)) return in_array($value, $val) ? 'checked' : '';
    return $val === $value ? 'checked' : '';
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
    verify_csrf();
    $exam = $_POST['exam'] ?? [];
    if (isset($exam['markers']) && is_string($exam['markers'])) {
        $exam['markers'] = json_decode($exam['markers'], true) ?: [];
    }
    // Images are now handled via AJAX and submitted as hidden inputs in $_POST['exam']['images']
    $history_data = json_encode($exam);
    
    if ($record_id) {
        $stmt = $db->prepare("UPDATE medical_history SET history_data = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$history_data, $record_id]);
    } else {
        $stmt = $db->prepare("
            INSERT INTO medical_history (patient_id, session_id, type, history_data, created_by)
            VALUES (?, ?, 'pathologie_v2', ?, ?)
        ");
        $stmt->execute([$patient_id, $session_id, $history_data, $_SESSION['user_id']]);
        $new_id = $db->lastInsertId();
    }
    
    set_flash(__('medical.history.msg_success'));
    
    if (!empty($_POST['lang_switch_autosave'])) {
        $redir_url = $_SERVER['REQUEST_URI'];
        if (!$record_id && isset($new_id)) {
            $redir_url .= (strpos($redir_url, '?') !== false ? '&' : '?') . 'id=' . $new_id;
        }
        redirect($redir_url);
    } elseif ($session_id) {
        redirect("session_view.php?id=$session_id");
    } else {
        redirect("../patients/view.php?id=$patient_id");
    }
}

$stmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch();

$patient_name = $patient['full_name'] ?? '';
$relationship = $patient['relationship'] ?: ($patient['guardian_relationship'] ?: 'Bản thân');
$birth_year = !empty($patient['birthday']) && $patient['birthday'] != '0000-00-00' ? date('Y', strtotime($patient['birthday'])) : 'Chưa rõ';

$stmt = $db->prepare("SELECT treatment_date FROM treatments WHERE patient_id = ? ORDER BY treatment_date DESC LIMIT 1");
$stmt->execute([$patient_id]);
$last_treatment = $stmt->fetchColumn();
$last_visit = $last_treatment ? date('d/m/Y', strtotime($last_treatment)) : 'Lần đầu';

$all_patients_stmt = $db->query("SELECT id, full_name, phone FROM patients ORDER BY full_name");
$all_patients = $all_patients_stmt->fetchAll();

if (!$patient_name) {
    set_flash(__('medical.exam.err_no_patient'), 'error');
    redirect('index.php');
}

$page_title = 'Bệnh lý hiện tại (Aktuelle Pathologie) V2';
$current_page = 'medical';
require_once '../../templates/header.php';
?>
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
/* Apple style Select2 */
.select2-container--default .select2-selection--single {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    height: 38px;
    padding: 4px;
    background: #f8fafc;
    font-size: 0.95rem;
    font-weight: 500;
    color: #1d1d1f;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 36px;
}
</style>

<div class="card" style="background: var(--glass-bg); backdrop-filter: blur(20px); max-width: 1300px; margin: 0 auto; border-radius: 24px;">
    <div style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: start;">
        <div>
            <h2 style="margin: 0; font-weight: 700; color: #1d1d1f; letter-spacing: -0.5px; display: flex; align-items: center; gap: 0.5rem; font-size: 1.5rem;">
                <?php echo __('medical.type.chiro_history_full'); ?> 
                <span style="font-size: 0.75rem; background: #fff1f2; color: #e11d48; padding: 2px 8px; border-radius: 6px; font-weight: 700; border: 1px solid #fecdd3; letter-spacing: 0;">V2</span>
            </h2>
            <p style="color: #86868b; margin-top: 0.35rem; font-size: 0.95rem; font-weight: 500;">
                <?php echo __('medical.exam.patient_label'); ?> <strong style="color: #1d1d1f; font-weight: 600;"><?php echo e($patient_name); ?></strong>
            </p>
        </div>
        <div style="background: #f5f5f7; color: #86868b; padding: 0.4rem 1rem; border-radius: 8px; font-weight: 600; font-size: 0.8rem; letter-spacing: 0.5px; border: 1px solid rgba(0,0,0,0.05);">
            <i class="fas fa-history" style="margin-right: 4px;"></i> HISTORY
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <!-- PART 1: THÔNG TIN CƠ BẢN & LỐI SỐNG -->
        <div style="margin-bottom: 4rem;">
            <h3 style="font-size: 1.15rem; font-weight: 700; color: #1d1d1f; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 1px solid rgba(0,0,0,0.06); padding-bottom: 1rem; letter-spacing: -0.2px;">
                <div style="width: 32px; height: 32px; background: #f5f5f7; border-radius: 8px; display: flex; justify-content: center; align-items: center; color: #1d1d1f; font-size: 0.9rem;">
                    <i class="fas fa-user-check"></i>
                </div>
                <?php echo __('medical.history.part1_title'); ?>
            </h3>

            <div style="display: flex; flex-wrap: wrap; gap: 2rem 3rem; margin-bottom: 3.5rem; background: rgba(255,255,255,0.95); padding: 2rem; border-radius: 20px; box-shadow: 0 4px 24px rgba(0,0,0,0.04); border: 1px solid rgba(0,0,0,0.05); align-items: center; backdrop-filter: blur(10px);">
                
                <!-- 1. HỌ TÊN -->
                <div style="display: flex; align-items: center; gap: 1rem; flex: 1 1 280px; min-width: 250px;">
                    <div style="width: 46px; height: 46px; border-radius: 50%; background: #f5f5f7; color: #1d1d1f; display: flex; justify-content: center; align-items: center; font-size: 1.15rem; flex-shrink: 0;">
                        <i class="fas fa-user"></i>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="color: #86868b; font-size: 0.65rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 0.2rem;">Họ Tên</div>
                        <div style="font-size: 1.05rem; font-weight: 600; color: #1d1d1f; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo e($patient_name); ?>"><?php echo e($patient_name); ?></div>
                    </div>
                </div>

                <!-- 2. NĂM SINH -->
                <div style="display: flex; align-items: center; gap: 1rem; flex: 1 1 200px; min-width: 180px;">
                    <div style="width: 46px; height: 46px; border-radius: 50%; background: #f5f5f7; color: #1d1d1f; display: flex; justify-content: center; align-items: center; font-size: 1.15rem; flex-shrink: 0;">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div>
                        <div style="color: #86868b; font-size: 0.65rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 0.2rem;">Năm Sinh</div>
                        <div style="font-size: 1.05rem; font-weight: 600; color: #1d1d1f; line-height: 1.2; display: flex; align-items: center; gap: 0.5rem;">
                            <?php echo $birth_year; ?>
                            <label style="display: flex; align-items: center; gap: 0.35rem; cursor: pointer; background: #fff1f2; padding: 0.35rem 0.75rem; border-radius: 8px; color: #e11d48; font-weight: 700; font-size: 0.9rem; transition: all 0.2s; white-space: nowrap;">
                                <input type="checkbox" id="is_under_1_check" name="exam[is_under_1]" value="1" <?php echo checked_v('is_under_1', '1'); ?> style="transform: scale(1.1); margin: 0;">
                                < 1T
                            </label>
                        </div>
                    </div>
                </div>

                <!-- 3. MỐI QUAN HỆ -->
                <div style="display: flex; align-items: center; gap: 1rem; flex: 1 1 200px; min-width: 150px;">
                    <div style="width: 46px; height: 46px; border-radius: 50%; background: #f5f5f7; color: #1d1d1f; display: flex; justify-content: center; align-items: center; font-size: 1.15rem; flex-shrink: 0;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="color: #86868b; font-size: 0.65rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 0.2rem;">Quan Hệ</div>
                        <div style="font-size: 1.05rem; font-weight: 600; color: #1d1d1f; line-height: 1.2;" id="relationship_display">
                            <?php echo e($relationship); ?>
                        </div>
                        <div id="relationship_select_area" style="display: none; margin-top: 4px;">
                            <select name="exam[guardian_patient_id]" id="guardian_patient_select" style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 6px; font-size: 0.95rem; font-weight: 500; color: #1d1d1f; background: #f8fafc; outline: none;">
                                <option value="">-- Chọn khách hàng --</option>
                                <?php foreach($all_patients as $p): ?>
                                    <option value="<?php echo $p['id']; ?>" <?php echo get_v('guardian_patient_id') == $p['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($p['full_name']); ?> (<?php echo e($p['phone']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- 4. GIỚI TÍNH -->
                <div style="display: flex; align-items: center; gap: 1rem; flex: 1 1 200px; min-width: 150px;">
                    <div style="width: 46px; height: 46px; border-radius: 50%; background: #f5f5f7; color: #1d1d1f; display: flex; justify-content: center; align-items: center; font-size: 1.15rem; flex-shrink: 0;">
                        <i class="fas fa-venus-mars"></i>
                    </div>
                    <div>
                        <div style="color: #86868b; font-size: 0.65rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 0.2rem;">Giới Tính</div>
                        <div style="font-size: 1.05rem; font-weight: 600; color: #1d1d1f; line-height: 1.2;"><?php echo $patient['gender'] == 'male' ? 'Nam' : ($patient['gender'] == 'female' ? 'Nữ' : 'Chưa rõ'); ?></div>
                    </div>
                </div>
                
                <!-- 5. LẦN KHÁM -->
                <div style="display: flex; align-items: center; gap: 1rem; flex: 1 1 200px; min-width: 150px;">
                    <div style="width: 46px; height: 46px; border-radius: 50%; background: #f5f5f7; color: #1d1d1f; display: flex; justify-content: center; align-items: center; font-size: 1.15rem; flex-shrink: 0;">
                        <i class="far fa-clock"></i>
                    </div>
                    <div>
                        <div style="color: #86868b; font-size: 0.65rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 0.2rem;">Lần Khám</div>
                        <div style="font-size: 1.05rem; font-weight: 600; color: #1d1d1f; line-height: 1.2;"><?php echo $last_visit; ?></div>
                    </div>
                </div>
            </div>

            
            <!-- ============================================== -->
            <!-- PHẦN 2: BỆNH LÝ HIỆN TẠI (AKTUELLE PATHOLOGIE) -->
            <!-- ============================================== -->
            <div style="margin-top: 3.5rem; margin-bottom: 2rem; border-top: 1px solid #e2e8f0; padding-top: 2.5rem;">
                <h3 style="font-weight: 800; font-size: 1.15rem; color: #1d1d1f; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem;">
                    <i class="fas fa-stethoscope" style="color: #3b82f6;"></i> PHẦN 2: BỆNH LÝ HIỆN TẠI (AKTUELLE PATHOLOGIE)
                </h3>

                <!-- Tabs Navigation -->
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1.5rem; background: rgba(248,250,252,0.8); padding: 0.5rem; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <button type="button" class="tab-btn active" data-target="tab-kopf">Đầu (Kopf)</button>
                    <button type="button" class="tab-btn" data-target="tab-hws">Cột sống cổ</button>
                    <button type="button" class="tab-btn" data-target="tab-bws">CS ngực</button>
                    <button type="button" class="tab-btn" data-target="tab-lws">CS thắt lưng</button>
                    <button type="button" class="tab-btn" data-target="tab-schulter">Vai</button>
                    <button type="button" class="tab-btn" data-target="tab-obere">Chi trên</button>
                    <button type="button" class="tab-btn" data-target="tab-untere">Chi dưới</button>
                    <button type="button" class="tab-btn" data-target="tab-fuss">Bàn & cổ chân</button>
                </div>

                <style>
                    .tab-btn { background: transparent; border: none; padding: 0.6rem 1.25rem; border-radius: 10px; font-weight: 600; font-size: 0.85rem; color: #64748b; cursor: pointer; transition: all 0.2s ease; }
                    .tab-btn:hover { color: #1d1d1f; }
                    .tab-btn.active { background: white; color: #1d1d1f; box-shadow: 0 2px 6px rgba(0,0,0,0.06), 0 0 1px rgba(0,0,0,0.1); }
                    .tab-content { display: none; background: white; border: 1px solid #e2e8f0; border-radius: 16px; padding: 1.5rem; box-shadow: 0 4px 24px rgba(0,0,0,0.04); }
                    .tab-content.active { display: block; animation: fadeIn 0.35s ease-out; }
                    @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
                    .path-table { width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
                    .path-table th { background: #f8fafc; padding: 1rem 1.25rem; text-align: left; font-size: 0.8rem; text-transform: uppercase; color: #64748b; border-bottom: 2px solid #e2e8f0; border-right: 1px solid #f1f5f9; font-weight: 700; letter-spacing: 0.5px; }
                    .path-table th:last-child { border-right: none; }
                    .path-table td { padding: 1.25rem; border-bottom: 1px solid #f1f5f9; border-right: 1px solid #f1f5f9; vertical-align: top; font-size: 0.9rem; color: #334155; line-height: 1.5; }
                    .path-table td:last-child { border-right: none; }
                    .path-table tr:last-child td { border-bottom: none; }
                    .path-table strong { color: #0f172a; display: block; margin-bottom: 0.6rem; font-weight: 700; font-size: 0.95rem; }
                    .path-table label { display: inline-flex; align-items: center; gap: 0.6rem; cursor: pointer; margin-bottom: 0.6rem; margin-right: 1.2rem; color: #1e293b; font-weight: 500; transition: color 0.2s; user-select: none; }
                    .path-table label:hover { color: #007aff; }
                    .path-table input[type="checkbox"], .path-table input[type="radio"] { 
                        -webkit-appearance: none; appearance: none;
                        width: 22px; height: 22px; border: 2px solid #cbd5e1; border-radius: 6px; 
                        margin: 0; cursor: pointer; position: relative; background: #f8fafc; 
                        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); flex-shrink: 0;
                    }
                    .path-table input[type="radio"] { border-radius: 50%; }
                    .path-table input[type="checkbox"]:checked, .path-table input[type="radio"]:checked { 
                        background-color: #007aff; border-color: #007aff; box-shadow: 0 2px 6px rgba(0, 122, 255, 0.25); 
                    }
                    .path-table input[type="checkbox"]:checked::after { 
                        content: ''; position: absolute; left: 6px; top: 2px; width: 6px; height: 11px; 
                        border: solid white; border-width: 0 2.5px 2.5px 0; transform: rotate(45deg); 
                    }
                    .path-table input[type="radio"]:checked::after { 
                        content: ''; position: absolute; left: 6px; top: 6px; width: 6px; height: 6px; 
                        border-radius: 50%; background: white; 
                    }
                    .path-table input[type="checkbox"]:hover, .path-table input[type="radio"]:hover { border-color: #007aff; }
                    .path-table input[type="text"] { border: 1px solid #cbd5e1; border-radius: 8px; padding: 0.5rem 0.75rem; font-size: 0.95rem; outline: none; transition: all 0.2s; background: #f8fafc; box-shadow: inset 0 1px 2px rgba(0,0,0,0.02); color: #0f172a; }
                    .path-table input[type="text"]:focus { border-color: #007aff; background: white; box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.15); }
                    .path-table input[type="number"] { border: 1px solid #cbd5e1; border-radius: 8px; padding: 0.5rem 0.5rem; font-size: 0.95rem; outline: none; transition: all 0.2s; background: #f8fafc; width: 68px; text-align: center; box-shadow: inset 0 1px 2px rgba(0,0,0,0.02); color: #0f172a; }
                    .path-table input[type="number"]:focus { border-color: #007aff; background: white; box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.15); }
                    textarea.form-premium-input { width: 100%; border: 1px solid #cbd5e1; border-radius: 12px; padding: 1rem; font-size: 0.95rem; outline: none; transition: all 0.2s; background: #f8fafc; box-shadow: inset 0 1px 2px rgba(0,0,0,0.02); font-family: inherit; color: #1e293b; line-height: 1.5; resize: vertical; }
                    textarea.form-premium-input:focus { border-color: #007aff; background: white; box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.15); }
                </style>

                <div id="tab-kopf" class="tab-content active">
                    <table class="path-table">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Triệu chứng / Vùng</th>
                                <th style="width: 40%;">Tính chất / Vị trí</th>
                                <th style="width: 40%;">Tần suất / Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Đầu</strong></td>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        <label><input type="checkbox" name="exam[akt_path][kopf][vitri][]" value="sau_csc" <?php echo checked_v('exam[akt_path][kopf][vitri]', 'sau_csc'); ?>> phía sau cột sống cổ</label>
                                        <label><input type="checkbox" name="exam[akt_path][kopf][vitri][]" value="truoc_tran" <?php echo checked_v('exam[akt_path][kopf][vitri]', 'truoc_tran'); ?>> phía trước (trán)</label>
                                        <div>một bên: <label><input type="checkbox" name="exam[akt_path][kopf][vitri][]" value="mot_ben_p" <?php echo checked_v('exam[akt_path][kopf][vitri]', 'mot_ben_p'); ?>> P</label> <label><input type="checkbox" name="exam[akt_path][kopf][vitri][]" value="mot_ben_t" <?php echo checked_v('exam[akt_path][kopf][vitri]', 'mot_ben_t'); ?>> T</label></div>
                                    </div>
                                </td>
                                <td>
                                    <div style="margin-bottom: 0.5rem; font-weight: 600;">Tần suất:</div>
                                    <div style="display: grid; grid-template-columns: max-content auto; gap: 0.5rem 1rem;">
                                        <label><input type="checkbox" name="exam[akt_path][kopf][tansuat][]" value="hang_ngay" <?php echo checked_v('exam[akt_path][kopf][tansuat]', 'hang_ngay'); ?>> hằng ngày</label>
                                        <label><input type="checkbox" name="exam[akt_path][kopf][tansuat][]" value="2_3_lan_tuan" <?php echo checked_v('exam[akt_path][kopf][tansuat]', '2_3_lan_tuan'); ?>> 2-3 lần/tuần</label>
                                        
                                        <label><input type="checkbox" name="exam[akt_path][kopf][tansuat][]" value="1_lan_tuan" <?php echo checked_v('exam[akt_path][kopf][tansuat]', '1_lan_tuan'); ?>> 1 lần/tuần</label>
                                        <label><input type="checkbox" name="exam[akt_path][kopf][tansuat][]" value="1_lan_thang" <?php echo checked_v('exam[akt_path][kopf][tansuat]', '1_lan_thang'); ?>> 1 lần/tháng</label>
                                        
                                        <label><input type="checkbox" name="exam[akt_path][kopf][tansuat][]" value="thinh_thoang" <?php echo checked_v('exam[akt_path][kopf][tansuat]', 'thinh_thoang'); ?>> thỉnh thoảng</label>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Chóng mặt</strong></td>
                                <td><input type="text" name="exam[akt_path][chongmat][vitri]" placeholder="Ghi chú vị trí..." value="<?php echo get_v('exam[akt_path][chongmat][vitri]'); ?>"></td>
                                <td><input type="text" name="exam[akt_path][chongmat][tansuat]" placeholder="Ghi chú tần suất..." value="<?php echo get_v('exam[akt_path][chongmat][tansuat]'); ?>"></td>
                            </tr>
                            <tr>
                                <td><strong>Ù tai</strong></td>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        <label><input type="checkbox" name="exam[akt_path][utai][vitri][]" value="tieng_u" <?php echo checked_v('exam[akt_path][utai][vitri]', 'tieng_u'); ?>> tiếng ù (rào rào)</label>
                                        <label><input type="checkbox" name="exam[akt_path][utai][vitri][]" value="tieng_rit" <?php echo checked_v('exam[akt_path][utai][vitri]', 'tieng_rit'); ?>> tiếng rít (huýt)</label>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">Từ khi nào: <input type="text" name="exam[akt_path][utai][tu_khi_nao]" placeholder="" value="<?php echo get_v('exam[akt_path][utai][tu_khi_nao]'); ?>"></div>
                                    <div style="display: flex; gap: 1rem; margin-bottom: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][utai][donvi][]" value="ngay" <?php echo checked_v('exam[akt_path][utai][donvi]', 'ngay'); ?>> ngày</label> <label><input type="checkbox" name="exam[akt_path][utai][donvi][]" value="tuan" <?php echo checked_v('exam[akt_path][utai][donvi]', 'tuan'); ?>> tuần</label> <label><input type="checkbox" name="exam[akt_path][utai][donvi][]" value="thang" <?php echo checked_v('exam[akt_path][utai][donvi]', 'thang'); ?>> tháng</label> <label><input type="checkbox" name="exam[akt_path][utai][donvi][]" value="nam" <?php echo checked_v('exam[akt_path][utai][donvi]', 'nam'); ?>> năm</label></div>
                                    <div style="display: flex; flex-direction: column;">
                                        <label><input type="checkbox" name="exam[akt_path][utai][trangthai][]" value="lien_tuc" <?php echo checked_v('exam[akt_path][utai][trangthai]', 'lien_tuc'); ?>> liên tục</label>
                                        <label><input type="checkbox" name="exam[akt_path][utai][trangthai][]" value="luc_nhieu_luc_it" <?php echo checked_v('exam[akt_path][utai][trangthai]', 'luc_nhieu_luc_it'); ?>> lúc nhiều lúc ít</label>
                                        <label><input type="checkbox" name="exam[akt_path][utai][trangthai][]" value="co_luc_het_han" <?php echo checked_v('exam[akt_path][utai][trangthai]', 'co_luc_het_han'); ?>> có lúc hết hẳn</label>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Khớp thái dương hàm<br>(TMJ)</strong></td>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        <div>lục cục: <label><input type="checkbox" name="exam[akt_path][tmj][trieuchung][]" value="luc_cuc_p" <?php echo checked_v('exam[akt_path][tmj][trieuchung]', 'luc_cuc_p'); ?>> P</label> <label><input type="checkbox" name="exam[akt_path][tmj][trieuchung][]" value="luc_cuc_t" <?php echo checked_v('exam[akt_path][tmj][trieuchung]', 'luc_cuc_t'); ?>> T</label></div>
                                        <div>đau: <label><input type="checkbox" name="exam[akt_path][tmj][trieuchung][]" value="dau_p" <?php echo checked_v('exam[akt_path][tmj][trieuchung]', 'dau_p'); ?>> P</label> <label><input type="checkbox" name="exam[akt_path][tmj][trieuchung][]" value="dau_t" <?php echo checked_v('exam[akt_path][tmj][trieuchung]', 'dau_t'); ?>> T</label></div>
                                        <div>hạn chế vận động: <label><input type="checkbox" name="exam[akt_path][tmj][trieuchung][]" value="hc_p" <?php echo checked_v('exam[akt_path][tmj][trieuchung]', 'hc_p'); ?>> P</label> <label><input type="checkbox" name="exam[akt_path][tmj][trieuchung][]" value="hc_t" <?php echo checked_v('exam[akt_path][tmj][trieuchung]', 'hc_t'); ?>> T</label></div>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">Từ khi nào: <input type="text" name="exam[akt_path][tmj][tu_khi_nao]" placeholder="" value="<?php echo get_v('exam[akt_path][tmj][tu_khi_nao]'); ?>"></div>
                                    <div style="display: flex; flex-direction: column;">
                                        <label><input type="checkbox" name="exam[akt_path][tmj][trangthai][]" value="dang_nieng" <?php echo checked_v('exam[akt_path][tmj][trangthai]', 'dang_nieng'); ?>> đang niềng răng</label>
                                        <label><input type="checkbox" name="exam[akt_path][tmj][trangthai][]" value="da_tung" <?php echo checked_v('exam[akt_path][tmj][trangthai]', 'da_tung'); ?>> đã từng niềng răng</label>
                                        <div style="display: flex; gap: 1rem;"><label><input type="checkbox" name="exam[akt_path][tmj][trangthai][]" value="cau_rang" <?php echo checked_v('exam[akt_path][tmj][trangthai]', 'cau_rang'); ?>> có cầu răng</label> <label><input type="checkbox" name="exam[akt_path][tmj][trangthai][]" value="mao_rang" <?php echo checked_v('exam[akt_path][tmj][trangthai]', 'mao_rang'); ?>> có mão răng</label></div>
                                        <label><input type="checkbox" name="exam[akt_path][tmj][trangthai][]" value="cay_ghep" <?php echo checked_v('exam[akt_path][tmj][trangthai]', 'cay_ghep'); ?>> có cấy ghép răng</label>
                                        <label><input type="checkbox" name="exam[akt_path][tmj][trangthai][]" value="dieu_tri_tuy" <?php echo checked_v('exam[akt_path][tmj][trangthai]', 'dieu_tri_tuy'); ?>> có răng đã điều trị tủy</label>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div id="tab-hws" class="tab-content">
                    <table class="path-table">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Vùng</th>
                                <th style="width: 40%;">Triệu chứng & chi tiết</th>
                                <th style="width: 40%;">Trạng thái / Lan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <strong>CSC – Cột sống cổ</strong>
                                    <div style="font-size: 0.8rem; color: #64748b;">Halswirbelsäule (HWS)</div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;"><label><input type="checkbox" name="exam[akt_path][hws][trieuchung][]" value="thang_tren" <?php echo checked_v('exam[akt_path][hws][trieuchung]', 'thang_tren'); ?>> </label> thang trên <input type="number" min="1" max="10" name="exam[akt_path][hws][thang_tren_val]" value="<?php echo get_v('akt_path.hws.thang_tren_val'); ?>">/10</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;"><label><input type="checkbox" name="exam[akt_path][hws][trieuchung][]" value="thang_giua" <?php echo checked_v('exam[akt_path][hws][trieuchung]', 'thang_giua'); ?>> </label> thang giữa <input type="number" min="1" max="10" name="exam[akt_path][hws][thang_giua_val]" value="<?php echo get_v('akt_path.hws.thang_giua_val'); ?>">/10</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;"><label><input type="checkbox" name="exam[akt_path][hws][trieuchung][]" value="thang_duoi" <?php echo checked_v('exam[akt_path][hws][trieuchung]', 'thang_duoi'); ?>> </label> thang dưới <input type="number" min="1" max="10" name="exam[akt_path][hws][thang_duoi_val]" value="<?php echo get_v('akt_path.hws.thang_duoi_val'); ?>">/10</div>
                                        <div style="margin-top: 0.5rem; display: flex; gap: 1rem;"><label><input type="checkbox" name="exam[akt_path][hws][trieuchung][]" value="p" <?php echo checked_v('exam[akt_path][hws][trieuchung]', 'p'); ?>> P</label> <label><input type="checkbox" name="exam[akt_path][hws][trieuchung][]" value="t" <?php echo checked_v('exam[akt_path][hws][trieuchung]', 't'); ?>> T</label></div>
                                        <div style="margin-top: 0.5rem; display: flex; gap: 1rem; align-items: center;">khi: <label><input type="checkbox" name="exam[akt_path][hws][trieuchung][]" value="khi_vd" <?php echo checked_v('exam[akt_path][hws][trieuchung]', 'khi_vd'); ?>> vận động</label> <label><input type="checkbox" name="exam[akt_path][hws][trieuchung][]" value="khi_nghi" <?php echo checked_v('exam[akt_path][hws][trieuchung]', 'khi_nghi'); ?>> nghỉ</label></div>
                                        <div style="margin-top: 0.5rem; display: flex; gap: 1rem; flex-wrap: wrap;"><label><input type="checkbox" name="exam[akt_path][hws][trieuchung][]" value="dau_nhoi" <?php echo checked_v('exam[akt_path][hws][trieuchung]', 'dau_nhoi'); ?>> đau nhói</label> <label><input type="checkbox" name="exam[akt_path][hws][trieuchung][]" value="dau_am_i" <?php echo checked_v('exam[akt_path][hws][trieuchung]', 'dau_am_i'); ?>> đau âm ỉ</label> <label><input type="checkbox" name="exam[akt_path][hws][trieuchung][]" value="te_bi" <?php echo checked_v('exam[akt_path][hws][trieuchung]', 'te_bi'); ?>> tê bì</label> <label><input type="checkbox" name="exam[akt_path][hws][trieuchung][]" value="liet" <?php echo checked_v('exam[akt_path][hws][trieuchung]', 'liet'); ?>> liệt</label></div>
                                        <div style="display: flex; gap: 1rem; align-items: center;"><i class="fas fa-arrows-alt-h" style="color: #94a3b8;"></i> <label><input type="checkbox" name="exam[akt_path][hws][trieuchung][]" value="yeu_co" <?php echo checked_v('exam[akt_path][hws][trieuchung]', 'yeu_co'); ?>> yếu cơ</label></div>
                                        <div style="margin-top: 0.5rem; display: flex; gap: 1rem; align-items: center; font-style: italic;">Hạn chế vận động về phía: <label><input type="checkbox" name="exam[akt_path][hws][trieuchung][]" value="hc_p" <?php echo checked_v('exam[akt_path][hws][trieuchung]', 'hc_p'); ?>> P</label> <label><input type="checkbox" name="exam[akt_path][hws][trieuchung][]" value="hc_t" <?php echo checked_v('exam[akt_path][hws][trieuchung]', 'hc_t'); ?>> T</label></div>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][hws][trangthai][]" value="cap_tinh" <?php echo checked_v('exam[akt_path][hws][trangthai]', 'cap_tinh'); ?>> </label> cấp tính [từ <input type="text" name="exam[akt_path][hws][cap_tinh_tu]" placeholder="" value="<?php echo get_v('exam[akt_path][hws][cap_tinh_tu]'); ?>">]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][hws][trangthai][]" value="man_tinh" <?php echo checked_v('exam[akt_path][hws][trangthai]', 'man_tinh'); ?>> </label> mạn tính [từ <input type="text" name="exam[akt_path][hws][man_tinh_tu]" placeholder="" value="<?php echo get_v('exam[akt_path][hws][man_tinh_tu]'); ?>">]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][hws][trangthai][]" value="tai_phat" <?php echo checked_v('exam[akt_path][hws][trangthai]', 'tai_phat'); ?>> </label> tái phát [từ <input type="text" name="exam[akt_path][hws][tai_phat_tu]" placeholder="" value="<?php echo get_v('exam[akt_path][hws][tai_phat_tu]'); ?>">]</div>
                                        <div style="margin-top: 0.5rem; font-weight: 700;">Đau lan: <label><input type="checkbox" name="exam[akt_path][hws][lan][]" value="lan_p" <?php echo checked_v('exam[akt_path][hws][lan]', 'lan_p'); ?>> P</label> <label><input type="checkbox" name="exam[akt_path][hws][lan][]" value="lan_t" <?php echo checked_v('exam[akt_path][hws][lan]', 'lan_t'); ?>> T</label></div>
                                        <div style="margin-left: 0.5rem;">- <label><input type="checkbox" name="exam[akt_path][hws][lan][]" value="lan_canh_tay" <?php echo checked_v('exam[akt_path][hws][lan]', 'lan_canh_tay'); ?>> </label> đến cánh tay trên</div>
                                        <div style="margin-left: 0.5rem;">- <label><input type="checkbox" name="exam[akt_path][hws][lan][]" value="lan_ban_tay" <?php echo checked_v('exam[akt_path][hws][lan]', 'lan_ban_tay'); ?>> </label> đến bàn tay</div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div id="tab-bws" class="tab-content">
                    <table class="path-table">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Vùng</th>
                                <th style="width: 40%;">Triệu chứng & chi tiết</th>
                                <th style="width: 40%;">Trạng thái / Lan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <strong>CSN – Cột sống ngực</strong>
                                    <div style="font-size: 0.8rem; color: #64748b;">Brustwirbelsäule (BWS)</div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;"><label><input type="checkbox" name="exam[akt_path][bws][trieuchung][]" value="thang_tren" <?php echo checked_v('exam[akt_path][bws][trieuchung]', 'thang_tren'); ?>> </label> thang trên <input type="number" min="1" max="10" name="exam[akt_path][bws][thang_tren_val]" value="<?php echo get_v('akt_path.bws.thang_tren_val'); ?>">/10</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;"><label><input type="checkbox" name="exam[akt_path][bws][trieuchung][]" value="thang_giua" <?php echo checked_v('exam[akt_path][bws][trieuchung]', 'thang_giua'); ?>> </label> thang giữa <input type="number" min="1" max="10" name="exam[akt_path][bws][thang_giua_val]" value="<?php echo get_v('akt_path.bws.thang_giua_val'); ?>">/10</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;"><label><input type="checkbox" name="exam[akt_path][bws][trieuchung][]" value="thang_duoi" <?php echo checked_v('exam[akt_path][bws][trieuchung]', 'thang_duoi'); ?>> </label> thang dưới <input type="number" min="1" max="10" name="exam[akt_path][bws][thang_duoi_val]" value="<?php echo get_v('akt_path.bws.thang_duoi_val'); ?>">/10</div>
                                        <div style="margin-top: 0.5rem; display: flex; gap: 1rem;"><label><input type="checkbox" name="exam[akt_path][bws][trieuchung][]" value="p" <?php echo checked_v('exam[akt_path][bws][trieuchung]', 'p'); ?>> P</label> <label><input type="checkbox" name="exam[akt_path][bws][trieuchung][]" value="t" <?php echo checked_v('exam[akt_path][bws][trieuchung]', 't'); ?>> T</label></div>
                                        <div style="margin-top: 0.5rem; display: flex; gap: 1rem; align-items: center;">khi: <label><input type="checkbox" name="exam[akt_path][bws][trieuchung][]" value="khi_vd" <?php echo checked_v('exam[akt_path][bws][trieuchung]', 'khi_vd'); ?>> vận động</label> <label><input type="checkbox" name="exam[akt_path][bws][trieuchung][]" value="khi_nghi" <?php echo checked_v('exam[akt_path][bws][trieuchung]', 'khi_nghi'); ?>> nghỉ</label></div>
                                        <div style="margin-top: 0.5rem; display: flex; gap: 1rem; flex-wrap: wrap;"><label><input type="checkbox" name="exam[akt_path][bws][trieuchung][]" value="dau_nhoi" <?php echo checked_v('exam[akt_path][bws][trieuchung]', 'dau_nhoi'); ?>> đau nhói</label> <label><input type="checkbox" name="exam[akt_path][bws][trieuchung][]" value="dau_am_i" <?php echo checked_v('exam[akt_path][bws][trieuchung]', 'dau_am_i'); ?>> đau âm ỉ</label> <label><input type="checkbox" name="exam[akt_path][bws][trieuchung][]" value="te_bi" <?php echo checked_v('exam[akt_path][bws][trieuchung]', 'te_bi'); ?>> tê bì</label> <label><input type="checkbox" name="exam[akt_path][bws][trieuchung][]" value="liet" <?php echo checked_v('exam[akt_path][bws][trieuchung]', 'liet'); ?>> liệt</label></div>
                                        <div style="display: flex; gap: 1rem; align-items: center;"><i class="fas fa-arrows-alt-h" style="color: #94a3b8;"></i> <label><input type="checkbox" name="exam[akt_path][bws][trieuchung][]" value="yeu_co" <?php echo checked_v('exam[akt_path][bws][trieuchung]', 'yeu_co'); ?>> yếu cơ</label></div>
                                        <div style="margin-top: 0.5rem; display: flex; gap: 1rem; align-items: center; font-style: italic;">Hạn chế vận động về phía: <label><input type="checkbox" name="exam[akt_path][bws][trieuchung][]" value="hc_p" <?php echo checked_v('exam[akt_path][bws][trieuchung]', 'hc_p'); ?>> P</label> <label><input type="checkbox" name="exam[akt_path][bws][trieuchung][]" value="hc_t" <?php echo checked_v('exam[akt_path][bws][trieuchung]', 'hc_t'); ?>> T</label></div>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][bws][trangthai][]" value="cap_tinh" <?php echo checked_v('exam[akt_path][bws][trangthai]', 'cap_tinh'); ?>> </label> cấp tính [từ <input type="text" name="exam[akt_path][bws][cap_tinh_tu]" placeholder="" value="<?php echo get_v('exam[akt_path][bws][cap_tinh_tu]'); ?>">]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][bws][trangthai][]" value="man_tinh" <?php echo checked_v('exam[akt_path][bws][trangthai]', 'man_tinh'); ?>> </label> mạn tính [từ <input type="text" name="exam[akt_path][bws][man_tinh_tu]" placeholder="" value="<?php echo get_v('exam[akt_path][bws][man_tinh_tu]'); ?>">]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][bws][trangthai][]" value="tai_phat" <?php echo checked_v('exam[akt_path][bws][trangthai]', 'tai_phat'); ?>> </label> tái phát [từ <input type="text" name="exam[akt_path][bws][tai_phat_tu]" placeholder="" value="<?php echo get_v('exam[akt_path][bws][tai_phat_tu]'); ?>">]</div>
                                        <div style="margin-top: 0.5rem; font-weight: 700;">Đau lan: <label><input type="checkbox" name="exam[akt_path][bws][lan][]" value="lan_p" <?php echo checked_v('exam[akt_path][bws][lan]', 'lan_p'); ?>> P</label> <label><input type="checkbox" name="exam[akt_path][bws][lan][]" value="lan_t" <?php echo checked_v('exam[akt_path][bws][lan]', 'lan_t'); ?>> T</label></div>
                                        <div style="margin-left: 0.5rem;">- <label><input type="checkbox" name="exam[akt_path][bws][lan][]" value="lan_canh_tay" <?php echo checked_v('exam[akt_path][bws][lan]', 'lan_canh_tay'); ?>> </label> đến cánh tay trên</div>
                                        <div style="margin-left: 0.5rem;">- <label><input type="checkbox" name="exam[akt_path][bws][lan][]" value="lan_ban_tay" <?php echo checked_v('exam[akt_path][bws][lan]', 'lan_ban_tay'); ?>> </label> đến bàn tay</div>
                                        <div style="margin-top: 0.5rem; font-weight: 700;">Đau TK liên sườn (ICN): <label><input type="checkbox" name="exam[akt_path][bws][lan][]" value="icn_p" <?php echo checked_v('exam[akt_path][bws][lan]', 'icn_p'); ?>> P</label> <label><input type="checkbox" name="exam[akt_path][bws][lan][]" value="icn_t" <?php echo checked_v('exam[akt_path][bws][lan]', 'icn_t'); ?>> T</label></div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div id="tab-lws" class="tab-content">
                    <table class="path-table">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Vùng</th>
                                <th style="width: 40%;">Triệu chứng & chi tiết</th>
                                <th style="width: 40%;">Trạng thái / Lan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <strong>CSTL – Cột sống thắt lưng</strong>
                                    <div style="font-size: 0.8rem; color: #64748b;">Lendenwirbelsäule (LWS)</div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;"><label><input type="checkbox" name="exam[akt_path][lws][trieuchung][]" value="thang_tren" <?php echo checked_v('exam[akt_path][lws][trieuchung]', 'thang_tren'); ?>> </label> thang trên <input type="number" min="1" max="10" name="exam[akt_path][lws][thang_tren_val]" value="<?php echo get_v('akt_path.lws.thang_tren_val'); ?>">/10</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;"><label><input type="checkbox" name="exam[akt_path][lws][trieuchung][]" value="thang_giua" <?php echo checked_v('exam[akt_path][lws][trieuchung]', 'thang_giua'); ?>> </label> thang giữa <input type="number" min="1" max="10" name="exam[akt_path][lws][thang_giua_val]" value="<?php echo get_v('akt_path.lws.thang_giua_val'); ?>">/10</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;"><label><input type="checkbox" name="exam[akt_path][lws][trieuchung][]" value="thang_duoi" <?php echo checked_v('exam[akt_path][lws][trieuchung]', 'thang_duoi'); ?>> </label> thang dưới <input type="number" min="1" max="10" name="exam[akt_path][lws][thang_duoi_val]" value="<?php echo get_v('akt_path.lws.thang_duoi_val'); ?>">/10</div>
                                        <div style="margin-top: 0.5rem; display: flex; gap: 1rem;"><label><input type="checkbox" name="exam[akt_path][lws][trieuchung][]" value="p" <?php echo checked_v('exam[akt_path][lws][trieuchung]', 'p'); ?>> P</label> <label><input type="checkbox" name="exam[akt_path][lws][trieuchung][]" value="t" <?php echo checked_v('exam[akt_path][lws][trieuchung]', 't'); ?>> T</label></div>
                                        <div style="margin-top: 0.5rem; display: flex; gap: 1rem; align-items: center;">khi: <label><input type="checkbox" name="exam[akt_path][lws][trieuchung][]" value="khi_vd" <?php echo checked_v('exam[akt_path][lws][trieuchung]', 'khi_vd'); ?>> vận động</label> <label><input type="checkbox" name="exam[akt_path][lws][trieuchung][]" value="khi_nghi" <?php echo checked_v('exam[akt_path][lws][trieuchung]', 'khi_nghi'); ?>> nghỉ</label></div>
                                        <div style="margin-top: 0.5rem; display: flex; gap: 1rem; flex-wrap: wrap;"><label><input type="checkbox" name="exam[akt_path][lws][trieuchung][]" value="dau_nhoi" <?php echo checked_v('exam[akt_path][lws][trieuchung]', 'dau_nhoi'); ?>> đau nhói</label> <label><input type="checkbox" name="exam[akt_path][lws][trieuchung][]" value="dau_am_i" <?php echo checked_v('exam[akt_path][lws][trieuchung]', 'dau_am_i'); ?>> đau âm ỉ</label> <label><input type="checkbox" name="exam[akt_path][lws][trieuchung][]" value="te_bi" <?php echo checked_v('exam[akt_path][lws][trieuchung]', 'te_bi'); ?>> tê bì</label> <label><input type="checkbox" name="exam[akt_path][lws][trieuchung][]" value="liet" <?php echo checked_v('exam[akt_path][lws][trieuchung]', 'liet'); ?>> liệt</label></div>
                                        <div style="display: flex; gap: 1rem; align-items: center;"><i class="fas fa-arrows-alt-h" style="color: #94a3b8;"></i> <label><input type="checkbox" name="exam[akt_path][lws][trieuchung][]" value="yeu_co" <?php echo checked_v('exam[akt_path][lws][trieuchung]', 'yeu_co'); ?>> yếu cơ</label></div>
                                        <div style="margin-top: 0.5rem; display: flex; gap: 1rem; align-items: center; font-style: italic;">Hạn chế vận động về phía: <label><input type="checkbox" name="exam[akt_path][lws][trieuchung][]" value="hc_p" <?php echo checked_v('exam[akt_path][lws][trieuchung]', 'hc_p'); ?>> P</label> <label><input type="checkbox" name="exam[akt_path][lws][trieuchung][]" value="hc_t" <?php echo checked_v('exam[akt_path][lws][trieuchung]', 'hc_t'); ?>> T</label></div>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <div style="font-weight: 700;">Đau lan: <label><input type="checkbox" name="exam[akt_path][lws][lan][]" value="lan_p" <?php echo checked_v('exam[akt_path][lws][lan]', 'lan_p'); ?>> P</label> <label><input type="checkbox" name="exam[akt_path][lws][lan][]" value="lan_t" <?php echo checked_v('exam[akt_path][lws][lan]', 'lan_t'); ?>> T</label></div>
                                        <div style="display: flex; gap: 1rem; align-items: center;"><label><input type="checkbox" name="exam[akt_path][lws][lan][]" value="lan_ben" <?php echo checked_v('exam[akt_path][lws][lan]', 'lan_ben'); ?>> bên</label> / <label><input type="checkbox" name="exam[akt_path][lws][lan][]" value="lan_den_goi" <?php echo checked_v('exam[akt_path][lws][lan]', 'lan_den_goi'); ?>> đến gối</label></div>
                                        <div style="display: flex; gap: 1rem; align-items: center;"><label><input type="checkbox" name="exam[akt_path][lws][lan][]" value="lan_phia_sau" <?php echo checked_v('exam[akt_path][lws][lan]', 'lan_phia_sau'); ?>> phía sau</label> / <label><input type="checkbox" name="exam[akt_path][lws][lan][]" value="lan_den_ban_chan" <?php echo checked_v('exam[akt_path][lws][lan]', 'lan_den_ban_chan'); ?>> đến bàn chân</label></div>
                                        <div style="display: flex; gap: 1rem; align-items: center;"><label><input type="checkbox" name="exam[akt_path][lws][lan][]" value="lan_phia_trong" <?php echo checked_v('exam[akt_path][lws][lan]', 'lan_phia_trong'); ?>> phía trong</label> / <label><input type="checkbox" name="exam[akt_path][lws][lan][]" value="lan_phia_truoc" <?php echo checked_v('exam[akt_path][lws][lan]', 'lan_phia_truoc'); ?>> phía trước</label></div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div id="tab-schulter" class="tab-content">
                    <table class="path-table">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Vùng</th>
                                <th style="width: 40%;">Triệu chứng / Vị trí</th>
                                <th style="width: 40%;">Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Vai</strong></td>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 0.5rem;">
                                            <label><input type="checkbox" name="exam[akt_path][vai][vitri][]" value="p" <?php echo checked_v('exam[akt_path][vai][vitri]', 'p'); ?>> P</label> <label><input type="checkbox" name="exam[akt_path][vai][vitri][]" value="t" <?php echo checked_v('exam[akt_path][vai][vitri]', 't'); ?>> T</label> 
                                            <span style="margin-left: 1rem;">Thang đau: 1 <input type="number" min="1" max="10" name="exam[akt_path][vai][thang_dau]" value="<?php echo get_v('akt_path.vai.thang_dau'); ?>"> /10</span>
                                        </div>
                                        <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 0.5rem;">
                                            <label><input type="checkbox" name="exam[akt_path][vai][vitri][]" value="truoc" <?php echo checked_v('exam[akt_path][vai][vitri]', 'truoc'); ?>> phía trước (ventral)</label> <label><input type="checkbox" name="exam[akt_path][vai][vitri][]" value="sau" <?php echo checked_v('exam[akt_path][vai][vitri]', 'sau'); ?>> phía sau (dorsal)</label> <label><input type="checkbox" name="exam[akt_path][vai][vitri][]" value="ben_ngoai" <?php echo checked_v('exam[akt_path][vai][vitri]', 'ben_ngoai'); ?>> bên ngoài (lateral)</label>
                                        </div>
                                        <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 0.5rem;">
                                            khi: <label><input type="checkbox" name="exam[akt_path][vai][khi][]" value="vd" <?php echo checked_v('exam[akt_path][vai][khi]', 'vd'); ?>> vận động</label> <label><input type="checkbox" name="exam[akt_path][vai][khi][]" value="nghi" <?php echo checked_v('exam[akt_path][vai][khi]', 'nghi'); ?>> nghỉ</label> <label><input type="checkbox" name="exam[akt_path][vai][khi][]" value="lan" <?php echo checked_v('exam[akt_path][vai][khi]', 'lan'); ?>> lan theo hướng nhất định</label>
                                        </div>
                                        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                                            <label><input type="checkbox" name="exam[akt_path][vai][trieuchung][]" value="dau_nhoi" <?php echo checked_v('exam[akt_path][vai][trieuchung]', 'dau_nhoi'); ?>> đau nhói</label> <label><input type="checkbox" name="exam[akt_path][vai][trieuchung][]" value="dau_am_i" <?php echo checked_v('exam[akt_path][vai][trieuchung]', 'dau_am_i'); ?>> đau âm ỉ</label> <label><input type="checkbox" name="exam[akt_path][vai][trieuchung][]" value="te_bi" <?php echo checked_v('exam[akt_path][vai][trieuchung]', 'te_bi'); ?>> tê bì</label> <label><input type="checkbox" name="exam[akt_path][vai][trieuchung][]" value="yeu_co" <?php echo checked_v('exam[akt_path][vai][trieuchung]', 'yeu_co'); ?>> yếu cơ</label> <label><input type="checkbox" name="exam[akt_path][vai][trieuchung][]" value="liet" <?php echo checked_v('exam[akt_path][vai][trieuchung]', 'liet'); ?>> liệt</label>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][vai][trangthai][]" value="cap_tinh" <?php echo checked_v('exam[akt_path][vai][trangthai]', 'cap_tinh'); ?>> </label> cấp tính [từ <input type="text" name="exam[akt_path][vai][cap_tinh_tu]" placeholder="" value="<?php echo get_v('exam[akt_path][vai][cap_tinh_tu]'); ?>">]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][vai][trangthai][]" value="man_tinh" <?php echo checked_v('exam[akt_path][vai][trangthai]', 'man_tinh'); ?>> </label> mạn tính [từ <input type="text" name="exam[akt_path][vai][man_tinh_tu]" placeholder="" value="<?php echo get_v('exam[akt_path][vai][man_tinh_tu]'); ?>">]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][vai][trangthai][]" value="tai_phat" <?php echo checked_v('exam[akt_path][vai][trangthai]', 'tai_phat'); ?>> </label> tái phát [từ <input type="text" name="exam[akt_path][vai][tai_phat_tu]" placeholder="" value="<?php echo get_v('exam[akt_path][vai][tai_phat_tu]'); ?>">]</div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div id="tab-obere" class="tab-content">
                    <table class="path-table">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Khớp / Vùng</th>
                                <th style="width: 40%;">Triệu chứng / Định khu</th>
                                <th style="width: 40%;">Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <strong>Khuỷu tay</strong>
                                    <div style="font-size: 0.8rem; color: #64748b;">Ellenbogen</div>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 0.5rem;">
                                        <label><input type="checkbox" name="exam[akt_path][khuyu][vitri][]" value="p" <?php echo checked_v('exam[akt_path][khuyu][vitri]', 'p'); ?>> P</label> <label><input type="checkbox" name="exam[akt_path][khuyu][vitri][]" value="t" <?php echo checked_v('exam[akt_path][khuyu][vitri]', 't'); ?>> T</label> 
                                        <span style="margin-left: 1rem;">Thang đau: 1 <input type="number" min="1" max="10" name="exam[akt_path][khuyu][thang_dau]" value="<?php echo get_v('akt_path.khuyu.thang_dau'); ?>"> /10</span>
                                    </div>
                                    <div style="display: flex; flex-direction: column;">
                                        <label><input type="checkbox" name="exam[akt_path][khuyu][loai][]" value="tennis" <?php echo checked_v('exam[akt_path][khuyu][loai]', 'tennis'); ?>> viêm lồi cầu ngoài – khuỷu tay tennis (TA)</label>
                                        <label><input type="checkbox" name="exam[akt_path][khuyu][loai][]" value="golf" <?php echo checked_v('exam[akt_path][khuyu][loai]', 'golf'); ?>> viêm lồi cầu trong – khuỷu tay golf (GA)</label>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][khuyu][trangthai][]" value="cap_tinh" <?php echo checked_v('exam[akt_path][khuyu][trangthai]', 'cap_tinh'); ?>> </label> cấp tính [từ <input type="text" name="exam[akt_path][khuyu][cap_tinh_tu]" placeholder="" value="<?php echo get_v('exam[akt_path][khuyu][cap_tinh_tu]'); ?>">]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][khuyu][trangthai][]" value="man_tinh" <?php echo checked_v('exam[akt_path][khuyu][trangthai]', 'man_tinh'); ?>> </label> mạn tính [từ <input type="text" name="exam[akt_path][khuyu][man_tinh_tu]" placeholder="" value="<?php echo get_v('exam[akt_path][khuyu][man_tinh_tu]'); ?>">]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][khuyu][trangthai][]" value="tai_phat" <?php echo checked_v('exam[akt_path][khuyu][trangthai]', 'tai_phat'); ?>> </label> tái phát [từ <input type="text" name="exam[akt_path][khuyu][tai_phat_tu]" placeholder="" value="<?php echo get_v('exam[akt_path][khuyu][tai_phat_tu]'); ?>">]</div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Bàn tay & cổ tay</strong></td>
                                <td>
                                    <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 0.8rem;">
                                        <label><input type="checkbox" name="exam[akt_path][ban_tay][vitri][]" value="p" <?php echo checked_v('exam[akt_path][ban_tay][vitri]', 'p'); ?>> P</label> <label><input type="checkbox" name="exam[akt_path][ban_tay][vitri][]" value="t" <?php echo checked_v('exam[akt_path][ban_tay][vitri]', 't'); ?>> T</label> 
                                        <span style="margin-left: 1rem;">Thang đau: 1 <input type="number" min="1" max="10" name="exam[akt_path][ban_tay][thang_dau]" value="<?php echo get_v('akt_path.ban_tay.thang_dau'); ?>"> /10</span>
                                    </div>
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <div style="display: flex; align-items: center; flex-wrap: wrap;"><strong>Cổ tay:</strong> <label><input type="checkbox" name="exam[akt_path][ban_tay][cotay][]" value="mu" <?php echo checked_v('exam[akt_path][ban_tay][cotay]', 'mu'); ?>> mu</label> <label><input type="checkbox" name="exam[akt_path][ban_tay][cotay][]" value="gan" <?php echo checked_v('exam[akt_path][ban_tay][cotay]', 'gan'); ?>> gan</label> <label><input type="checkbox" name="exam[akt_path][ban_tay][cotay][]" value="ngoai" <?php echo checked_v('exam[akt_path][ban_tay][cotay]', 'ngoai'); ?>> bên ngoài</label> <label><input type="checkbox" name="exam[akt_path][ban_tay][cotay][]" value="trong" <?php echo checked_v('exam[akt_path][ban_tay][cotay]', 'trong'); ?>> bên trong</label> (phía quay – phía ngón cái)</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><strong>Xương bàn tay (MTC):</strong> <label><input type="checkbox" name="exam[akt_path][ban_tay][mtc][]" value="2" <?php echo checked_v('exam[akt_path][ban_tay][mtc]', '2'); ?>> 2</label> <label><input type="checkbox" name="exam[akt_path][ban_tay][mtc][]" value="3" <?php echo checked_v('exam[akt_path][ban_tay][mtc]', '3'); ?>> 3</label> <label><input type="checkbox" name="exam[akt_path][ban_tay][mtc][]" value="4" <?php echo checked_v('exam[akt_path][ban_tay][mtc]', '4'); ?>> 4</label> <label><input type="checkbox" name="exam[akt_path][ban_tay][mtc][]" value="5" <?php echo checked_v('exam[akt_path][ban_tay][mtc]', '5'); ?>> 5</label> (mặt gan)</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><strong>Ngón cái (Pollicis):</strong> <label><input type="checkbox" name="exam[akt_path][ban_tay][pollicis][]" value="sg" <?php echo checked_v('exam[akt_path][ban_tay][pollicis]', 'sg'); ?>> khớp yên (SG)</label> <label><input type="checkbox" name="exam[akt_path][ban_tay][pollicis][]" value="gg" <?php echo checked_v('exam[akt_path][ban_tay][pollicis]', 'gg'); ?>> khớp bàn-ngón (GG)</label></div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><strong>Ngón tay (Digiti):</strong> <label><input type="checkbox" name="exam[akt_path][ban_tay][digiti][]" value="2" <?php echo checked_v('exam[akt_path][ban_tay][digiti]', '2'); ?>> 2</label> <label><input type="checkbox" name="exam[akt_path][ban_tay][digiti][]" value="3" <?php echo checked_v('exam[akt_path][ban_tay][digiti]', '3'); ?>> 3</label> <label><input type="checkbox" name="exam[akt_path][ban_tay][digiti][]" value="4" <?php echo checked_v('exam[akt_path][ban_tay][digiti]', '4'); ?>> 4</label> <label><input type="checkbox" name="exam[akt_path][ban_tay][digiti][]" value="5" <?php echo checked_v('exam[akt_path][ban_tay][digiti]', '5'); ?>> 5</label></div>
                                        <div><strong>Hội chứng ống cổ tay (CTS):</strong> <label><input type="checkbox" name="exam[akt_path][ban_tay][cts][]" value="cts" <?php echo checked_v('exam[akt_path][ban_tay][cts]', 'cts'); ?>> </label></div>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][ban_tay][trangthai][]" value="cap_tinh" <?php echo checked_v('exam[akt_path][ban_tay][trangthai]', 'cap_tinh'); ?>> </label> cấp tính [từ <input type="text" name="exam[akt_path][ban_tay][cap_tinh_tu]" placeholder="" value="<?php echo get_v('exam[akt_path][ban_tay][cap_tinh_tu]'); ?>">]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][ban_tay][trangthai][]" value="man_tinh" <?php echo checked_v('exam[akt_path][ban_tay][trangthai]', 'man_tinh'); ?>> </label> mạn tính [từ <input type="text" name="exam[akt_path][ban_tay][man_tinh_tu]" placeholder="" value="<?php echo get_v('exam[akt_path][ban_tay][man_tinh_tu]'); ?>">]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][ban_tay][trangthai][]" value="tai_phat" <?php echo checked_v('exam[akt_path][ban_tay][trangthai]', 'tai_phat'); ?>> </label> tái phát [từ <input type="text" name="exam[akt_path][ban_tay][tai_phat_tu]" placeholder="" value="<?php echo get_v('exam[akt_path][ban_tay][tai_phat_tu]'); ?>">]</div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div id="tab-untere" class="tab-content">
                    <table class="path-table">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Vùng</th>
                                <th style="width: 40%;">Triệu chứng & phát hiện</th>
                                <th style="width: 40%;">Hạn chế</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <strong>Cẳng chân</strong>
                                    <div style="font-size: 0.8rem; color: #64748b;">Bein</div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 0.5rem;">
                                            <label><input type="checkbox" name="exam[akt_path][bein][vitri][]" value="p" <?php echo checked_v('exam[akt_path][bein][vitri]', 'p'); ?>> P</label> <label><input type="checkbox" name="exam[akt_path][bein][vitri][]" value="t" <?php echo checked_v('exam[akt_path][bein][vitri]', 't'); ?>> T</label> 
                                            <span style="margin-left: 1rem;">Thang đau: 1 <input type="number" min="1" max="10" name="exam[akt_path][bein][thang_dau]" value="<?php echo get_v('akt_path.bein.thang_dau'); ?>"> /10</span>
                                        </div>
                                        <div style="display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 0.5rem;">
                                            <label><input type="checkbox" name="exam[akt_path][bein][huong][]" value="sau" <?php echo checked_v('exam[akt_path][bein][huong]', 'sau'); ?>> phía sau</label> <label><input type="checkbox" name="exam[akt_path][bein][huong][]" value="truoc" <?php echo checked_v('exam[akt_path][bein][huong]', 'truoc'); ?>> phía trước</label> <label><input type="checkbox" name="exam[akt_path][bein][huong][]" value="ngoai" <?php echo checked_v('exam[akt_path][bein][huong]', 'ngoai'); ?>> bên ngoài</label> <label><input type="checkbox" name="exam[akt_path][bein][huong][]" value="trong" <?php echo checked_v('exam[akt_path][bein][huong]', 'trong'); ?>> bên trong</label>
                                        </div>
                                        <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 0.5rem;">
                                            <label><input type="checkbox" name="exam[akt_path][bein][khi][]" value="nghi" <?php echo checked_v('exam[akt_path][bein][khi]', 'nghi'); ?>> khi nghỉ</label> <label><input type="checkbox" name="exam[akt_path][bein][khi][]" value="vd" <?php echo checked_v('exam[akt_path][bein][khi]', 'vd'); ?>> khi vận động</label>
                                        </div>
                                        <div style="font-weight: 700; margin-top: 0.5rem;">Chênh lệch chiều dài chân đã biết:</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">chân ngắn: <label><input type="checkbox" name="exam[akt_path][bein][chenh_lech][]" value="p" <?php echo checked_v('exam[akt_path][bein][chenh_lech]', 'p'); ?>> P</label> / <label><input type="checkbox" name="exam[akt_path][bein][chenh_lech][]" value="t" <?php echo checked_v('exam[akt_path][bein][chenh_lech]', 't'); ?>> T</label></div>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][bein][trangthai][]" value="cap_tinh" <?php echo checked_v('exam[akt_path][bein][trangthai]', 'cap_tinh'); ?>> </label> cấp tính [từ <input type="text" name="exam[akt_path][bein][cap_tinh_tu]" placeholder="" value="<?php echo get_v('exam[akt_path][bein][cap_tinh_tu]'); ?>">]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][bein][trangthai][]" value="man_tinh" <?php echo checked_v('exam[akt_path][bein][trangthai]', 'man_tinh'); ?>> </label> mạn tính [từ <input type="text" name="exam[akt_path][bein][man_tinh_tu]" placeholder="" value="<?php echo get_v('exam[akt_path][bein][man_tinh_tu]'); ?>">]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][bein][trangthai][]" value="tai_phat" <?php echo checked_v('exam[akt_path][bein][trangthai]', 'tai_phat'); ?>> </label> tái phát [từ <input type="text" name="exam[akt_path][bein][tai_phat_tu]" placeholder="" value="<?php echo get_v('exam[akt_path][bein][tai_phat_tu]'); ?>">]</div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <strong>Khớp gối</strong>
                                    <div style="font-size: 0.8rem; color: #64748b;">Knie</div>
                                </td>
                                <td colspan="2">
                                    <div style="display: flex; flex-direction: column;">
                                        <div style="display: flex; gap: 1rem; margin-bottom: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][knie][vitri][]" value="p" <?php echo checked_v('exam[akt_path][knie][vitri]', 'p'); ?>> P</label> <label><input type="checkbox" name="exam[akt_path][knie][vitri][]" value="t" <?php echo checked_v('exam[akt_path][knie][vitri]', 't'); ?>> T</label></div>
                                        <div style="display: flex; gap: 1rem; margin-bottom: 0.5rem;">
                                            <label><input type="checkbox" name="exam[akt_path][knie][vung][]" value="trong" <?php echo checked_v('exam[akt_path][knie][vung]', 'trong'); ?>> trong (sụn chêm trong)</label> <label><input type="checkbox" name="exam[akt_path][knie][vung][]" value="ngoai" <?php echo checked_v('exam[akt_path][knie][vung]', 'ngoai'); ?>> ngoài (sụn chêm ngoài)</label>
                                        </div>
                                        <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 0.5rem;">
                                            <label><input type="checkbox" name="exam[akt_path][knie][vung][]" value="mac" <?php echo checked_v('exam[akt_path][knie][vung]', 'mac'); ?>> xương mác (Fibula)</label> <label><input type="checkbox" name="exam[akt_path][knie][vung][]" value="sun_chem" <?php echo checked_v('exam[akt_path][knie][vung]', 'sun_chem'); ?>> sụn chêm</label> <label><input type="checkbox" name="exam[akt_path][knie][vung][]" value="toan_bo" <?php echo checked_v('exam[akt_path][knie][vung]', 'toan_bo'); ?>> toàn bộ</label> <label><input type="checkbox" name="exam[akt_path][knie][vung][]" value="sau" <?php echo checked_v('exam[akt_path][knie][vung]', 'sau'); ?>> phía sau</label>
                                        </div>
                                        <div style="font-weight: 700; margin-top: 0.5rem;">Hạn chế vận động:</div>
                                        <div style="display: flex; gap: 1rem; margin-bottom: 0.5rem;">
                                            - <label><input type="checkbox" name="exam[akt_path][knie][han_che][]" value="dau" <?php echo checked_v('exam[akt_path][knie][han_che]', 'dau'); ?>> do đau</label>  - <label><input type="checkbox" name="exam[akt_path][knie][han_che][]" value="ket" <?php echo checked_v('exam[akt_path][knie][han_che]', 'ket'); ?>> do kẹt khớp</label>
                                        </div>
                                        <div style="font-weight: 700; margin-bottom: 0.5rem; display: flex; align-items: center;">Phù nề (Ödem) <label><input type="checkbox" name="exam[akt_path][knie][phu_ne][]" value="co" <?php echo checked_v('exam[akt_path][knie][phu_ne]', 'co'); ?>> </label></div>
                                        <div style="font-weight: 700; margin-top: 0.5rem;">Khó khăn khi:</div>
                                        <div style="display: flex; gap: 1rem; margin-bottom: 0.2rem;"><label><input type="checkbox" name="exam[akt_path][knie][kho_khan][]" value="len_cau" <?php echo checked_v('exam[akt_path][knie][kho_khan]', 'len_cau'); ?>> lên cầu thang</label> / <label><input type="checkbox" name="exam[akt_path][knie][kho_khan][]" value="xuong_cau" <?php echo checked_v('exam[akt_path][knie][kho_khan]', 'xuong_cau'); ?>> xuống cầu thang</label></div>
                                        <div style="display: flex; gap: 1rem; margin-bottom: 0.2rem;"><label><input type="checkbox" name="exam[akt_path][knie][kho_khan][]" value="di" <?php echo checked_v('exam[akt_path][knie][kho_khan]', 'di'); ?>> đi</label> / <label><input type="checkbox" name="exam[akt_path][knie][kho_khan][]" value="dung" <?php echo checked_v('exam[akt_path][knie][kho_khan]', 'dung'); ?>> đứng</label> / <label><input type="checkbox" name="exam[akt_path][knie][kho_khan][]" value="ngoi" <?php echo checked_v('exam[akt_path][knie][kho_khan]', 'ngoi'); ?>> ngồi</label></div>
                                        <div style="display: flex; gap: 1rem; margin-bottom: 0.2rem;"><label><input type="checkbox" name="exam[akt_path][knie][kho_khan][]" value="nghi" <?php echo checked_v('exam[akt_path][knie][kho_khan]', 'nghi'); ?>> khi nghỉ</label> / <label><input type="checkbox" name="exam[akt_path][knie][kho_khan][]" value="vd" <?php echo checked_v('exam[akt_path][knie][kho_khan]', 'vd'); ?>> khi vận động</label></div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div id="tab-fuss" class="tab-content">
                    <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 1rem;">
                        <strong>Bàn chân:</strong> <label><input type="checkbox" name="exam[akt_path][fuss][vitri][]" value="p" <?php echo checked_v('exam[akt_path][fuss][vitri]', 'p'); ?>> P</label> <label><input type="checkbox" name="exam[akt_path][fuss][vitri][]" value="t" <?php echo checked_v('exam[akt_path][fuss][vitri]', 't'); ?>> T</label> 
                        <span style="margin-left: 1rem;">Thang đau: 1 <input type="number" min="1" max="10" name="exam[akt_path][fuss][thang_dau]" value="<?php echo get_v('akt_path.fuss.thang_dau'); ?>"> /10</span>
                    </div>
                    <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px dashed #cbd5e1;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][fuss][trangthai][]" value="cap_tinh" <?php echo checked_v('exam[akt_path][fuss][trangthai]', 'cap_tinh'); ?>> </label> cấp tính [từ <input type="text" name="exam[akt_path][fuss][cap_tinh_tu]" placeholder="" value="<?php echo get_v('exam[akt_path][fuss][cap_tinh_tu]'); ?>">]</div>
                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][fuss][trangthai][]" value="man_tinh" <?php echo checked_v('exam[akt_path][fuss][trangthai]', 'man_tinh'); ?>> </label> mạn tính [từ <input type="text" name="exam[akt_path][fuss][man_tinh_tu]" placeholder="" value="<?php echo get_v('exam[akt_path][fuss][man_tinh_tu]'); ?>">]</div>
                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][fuss][trangthai][]" value="tai_phat" <?php echo checked_v('exam[akt_path][fuss][trangthai]', 'tai_phat'); ?>> </label> tái phát [từ <input type="text" name="exam[akt_path][fuss][tai_phat_tu]" placeholder="" value="<?php echo get_v('exam[akt_path][fuss][tai_phat_tu]'); ?>">]</div>
                    </div>
                    <table class="path-table">
                        <thead>
                            <tr>
                                <th style="width: 33%;">Hình ảnh lâm sàng</th>
                                <th style="width: 33%;">Định khu & giải phẫu</th>
                                <th style="width: 34%;">Biến dạng bàn chân</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        <strong>Gai gót chân (Fersensporn)</strong>
                                        <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;"><label><input type="checkbox" name="exam[akt_path][fuss][gai][]" value="gan" <?php echo checked_v('exam[akt_path][fuss][gai]', 'gan'); ?>> mặt gan (plantar)</label> / <label><input type="checkbox" name="exam[akt_path][fuss][gai][]" value="mu" <?php echo checked_v('exam[akt_path][fuss][gai]', 'mu'); ?>> mặt mu (dorsal)</label></div>
                                        
                                        <strong>Ngón cái vẹo ngoài (Hallux valgus):</strong>
                                        <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;"><label><input type="checkbox" name="exam[akt_path][fuss][hallux][]" value="p" <?php echo checked_v('exam[akt_path][fuss][hallux]', 'p'); ?>> P</label> <label><input type="checkbox" name="exam[akt_path][fuss][hallux][]" value="t" <?php echo checked_v('exam[akt_path][fuss][hallux]', 't'); ?>> T</label></div>
                                        
                                        <strong>U thần kinh Morton (Morton Neurom)</strong>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">Ngón: <label><input type="checkbox" name="exam[akt_path][fuss][morton][]" value="1" <?php echo checked_v('exam[akt_path][fuss][morton]', '1'); ?>> 1</label> <label><input type="checkbox" name="exam[akt_path][fuss][morton][]" value="2" <?php echo checked_v('exam[akt_path][fuss][morton]', '2'); ?>> 2</label> <label><input type="checkbox" name="exam[akt_path][fuss][morton][]" value="3" <?php echo checked_v('exam[akt_path][fuss][morton]', '3'); ?>> 3</label> <label><input type="checkbox" name="exam[akt_path][fuss][morton][]" value="4" <?php echo checked_v('exam[akt_path][fuss][morton]', '4'); ?>> 4</label> <label><input type="checkbox" name="exam[akt_path][fuss][morton][]" value="5" <?php echo checked_v('exam[akt_path][fuss][morton]', '5'); ?>> 5</label></div>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        <label><input type="checkbox" name="exam[akt_path][fuss][dinhkhu][]" value="mu" <?php echo checked_v('exam[akt_path][fuss][dinhkhu]', 'mu'); ?>> mặt mu (dorsal)</label>
                                        <label><input type="checkbox" name="exam[akt_path][fuss][dinhkhu][]" value="gan" <?php echo checked_v('exam[akt_path][fuss][dinhkhu]', 'gan'); ?>> mặt gan (plantar)</label>
                                        <label><input type="checkbox" name="exam[akt_path][fuss][dinhkhu][]" value="gan_proximal" <?php echo checked_v('exam[akt_path][fuss][dinhkhu]', 'gan_proximal'); ?>> gần (proximal)</label>
                                        <label><input type="checkbox" name="exam[akt_path][fuss][dinhkhu][]" value="xa" <?php echo checked_v('exam[akt_path][fuss][dinhkhu]', 'xa'); ?>> xa (distal)</label>
                                        <label><input type="checkbox" name="exam[akt_path][fuss][dinhkhu][]" value="trong" <?php echo checked_v('exam[akt_path][fuss][dinhkhu]', 'trong'); ?>> phía trong (medial)</label>
                                        <label><input type="checkbox" name="exam[akt_path][fuss][dinhkhu][]" value="ngoai" <?php echo checked_v('exam[akt_path][fuss][dinhkhu]', 'ngoai'); ?>> phía ngoài (lateral)</label>
                                        <div style="display: flex; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][fuss][dinhkhu][]" value="nghi" <?php echo checked_v('exam[akt_path][fuss][dinhkhu]', 'nghi'); ?>> khi nghỉ</label> / <label><input type="checkbox" name="exam[akt_path][fuss][dinhkhu][]" value="vd" <?php echo checked_v('exam[akt_path][fuss][dinhkhu]', 'vd'); ?>> khi vận động</label></div>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        <label><input type="checkbox" name="exam[akt_path][fuss][bien_dang][]" value="bet" <?php echo checked_v('exam[akt_path][fuss][bien_dang]', 'bet'); ?>> Bàn chân bẹt (Plattfuß)</label>
                                        <label><input type="checkbox" name="exam[akt_path][fuss][bien_dang][]" value="lom" <?php echo checked_v('exam[akt_path][fuss][bien_dang]', 'lom'); ?>> Bàn chân lõm/vòm cao (Hohlfuß)</label>
                                        <label><input type="checkbox" name="exam[akt_path][fuss][bien_dang][]" value="liem" <?php echo checked_v('exam[akt_path][fuss][bien_dang]', 'liem'); ?>> Bàn chân hình liềm (Sichelfuß)</label>
                                        <label><input type="checkbox" name="exam[akt_path][fuss][bien_dang][]" value="sup" <?php echo checked_v('exam[akt_path][fuss][bien_dang]', 'sup'); ?>> Sụp vòm (Senkfuß)</label>
                                        <label><input type="checkbox" name="exam[akt_path][fuss][bien_dang][]" value="xoe" <?php echo checked_v('exam[akt_path][fuss][bien_dang]', 'xoe'); ?>> Bàn chân xòe/bè (Spreizfuß)</label>
                                        <label><input type="checkbox" name="exam[akt_path][fuss][bien_dang][]" value="veo" <?php echo checked_v('exam[akt_path][fuss][bien_dang]', 'veo'); ?>> Bàn chân vẹo (Knickfuß)</label>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Khớp cổ chân (Sprunggelenk)</strong></td>
                                <td colspan="2">
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><strong>Khớp cổ chân trên (OSG)</strong> <label><input type="checkbox" name="exam[akt_path][fuss][khop][]" value="osg" <?php echo checked_v('exam[akt_path][fuss][khop]', 'osg'); ?>> </label></div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><strong>Khớp cổ chân dưới (USG):</strong> <label><input type="checkbox" name="exam[akt_path][fuss][khop][]" value="usg_ngua" <?php echo checked_v('exam[akt_path][fuss][khop]', 'usg_ngua'); ?>> xoay ngửa (sup)</label> / <label><input type="checkbox" name="exam[akt_path][fuss][khop][]" value="usg_sap" <?php echo checked_v('exam[akt_path][fuss][khop]', 'usg_sap'); ?>> xoay sấp (pron)</label></div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 1.5rem;">
                    <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 700; color: #1e293b;">Ô ghi chú chung Bệnh lý hiện tại:</label>
                    <textarea name="exam[akt_path][notes]" class="form-premium-input" rows="3" placeholder="Nhập ghi chú thêm cho phần Bệnh sử..."><?php echo get_v('akt_path.notes'); ?></textarea>
                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 0.4rem; font-style: italic;">Ghi chú trong phần Bệnh sử sẽ được gom hiển thị lại trên các bản Tái khám (Follow-Up) về sau.</div>
                </div>
            </div>
            
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const tabBtns = document.querySelectorAll('.tab-btn');
                const tabContents = document.querySelectorAll('.tab-content');
                
                tabBtns.forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        
                        // Xóa active
                        tabBtns.forEach(b => b.classList.remove('active'));
                        tabContents.forEach(c => c.classList.remove('active'));
                        
                        // Active tab được chọn
                        this.classList.add('active');
                        const targetId = this.getAttribute('data-target');
                        const targetContent = document.getElementById(targetId);
                        if(targetContent) {
                            targetContent.classList.add('active');
                        }
                    });
                });
            });
            </script>

        </div>

        <div style="margin-top: 3.5rem; display: flex; gap: 1.5rem; justify-content: flex-end; border-top: 2px solid #f1f5f9; padding-top: 2rem;">
            <a href="../patients/view.php?id=<?php echo $patient_id; ?>" class="btn" style="background: #f1f5f9; color: var(--text-main); padding: 1.25rem 3rem; font-weight: 700; border-radius: 16px;"><?php echo __('common.cancel'); ?></a>
            <button type="submit" class="btn btn-primary" style="padding: 1.25rem 5rem; font-weight: 800; font-size: 1.25rem; border-radius: 16px; box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.4);">
                <i class="fas fa-save" style="margin-right: 0.5rem;"></i> <?php echo __('medical.history.btn_save'); ?>
            </button>
        </div>
    </form>
</div>

<style>
/* Modern UI CSS Upgrades */
.form-input {
    width: 100%;
    padding: 0.65rem 1rem;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 500;
    line-height: 1.5;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    outline: none;
    background: #f8fafc;
    color: #1e293b;
    box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.02);
}
.form-input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15), inset 0 2px 4px 0 rgba(0, 0, 0, 0.02);
    background: #ffffff;
    transform: translateY(-1px);
}
.form-input:hover {
    border-color: #cbd5e1;
    background: #fff;
}
.form-input::placeholder {
    color: #94a3b8;
    font-size: 0.9rem;
    font-weight: 400;
}

.checkbox-tag { cursor: pointer; }
.checkbox-tag input { position: absolute; opacity: 0; }
.checkbox-tag span {
    display: inline-block;
    padding: 0.65rem 1.5rem;
    background: #f1f5f9;
    border: 2px solid transparent;
    border-radius: 50px;
    font-size: 0.9rem;
    font-weight: 700;
    color: #64748b;
    transition: all 0.25s ease;
}
.checkbox-tag:hover span { 
    background: #e2e8f0;
    transform: translateY(-1px);
}
.checkbox-tag input:checked + span {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
    box-shadow: 0 8px 20px -4px rgba(99, 102, 241, 0.4);
    transform: translateY(-2px);
}

.slider {
    -webkit-appearance: none;
    width: 100%;
    height: 12px;
    border-radius: 6px;
    background: #e2e8f0;
    outline: none;
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
}
.slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--primary);
    cursor: pointer;
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.4);
    border: 4px solid white;
    transition: transform 0.2s;
}
.slider::-webkit-slider-thumb:hover {
    transform: scale(1.15);
}

/* Marking Tool Styles */
.tool-btn {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    border: 2px solid #e2e8f0;
    background: white;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    font-size: 1.25rem;
}
.tool-btn:hover {
    border-color: #cbd5e1;
    background: #f8fafc;
    transform: translateY(-2px);
}
.tool-btn.active {
    background: #eff6ff;
    border-color: #3b82f6;
    color: #3b82f6;
    box-shadow: 0 8px 15px -3px rgba(59, 130, 246, 0.3);
    transform: translateY(-2px);
}
.tool-btn#marker-clear:hover {
    background: #fef2f2;
    border-color: #ef4444;
    color: #ef4444;
    box-shadow: 0 8px 15px -3px rgba(239, 68, 68, 0.3);
}

.intensity-btn {
    border: 2px solid transparent;
    border-radius: 14px;
    padding: 0.85rem 0.5rem;
    background: white;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    font-size: 0.8rem;
    font-weight: 800;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    width: 100%;
    border: 1px solid #e2e8f0;
}
.intensity-btn:hover {
    border-color: #cbd5e1;
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}
.intensity-btn span {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.intensity-btn.active {
    background: #f8fafc;
    box-shadow: 0 12px 20px -3px rgba(0, 0, 0, 0.15);
    transform: translateY(-4px);
    border-color: currentColor;
    border-width: 2px;
}

/* Animations */
@keyframes slideDownFadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.pain-detail-section {
    animation: slideDownFadeIn 0.3s ease-out forwards;
}
</style>

<script src="../../assets/js/medical_marking.js"></script>
<script>
function initPainDetailsLogic() {
    const locCheckboxes = document.querySelectorAll('.loc-checkbox');
    locCheckboxes.forEach(input => {
        // Run once on load
        toggleSpecificPainDetail(input);
        
        // Add event listener for change
        input.addEventListener('change', function() {
            toggleSpecificPainDetail(this);
        });
    });
}

function toggleSpecificPainDetail(inputEl) {
    const targetId = inputEl.getAttribute('data-target');
    const section = document.getElementById(targetId);
    if (section) {
        if (inputEl.checked) {
            section.style.display = 'block';
        } else {
            section.style.display = 'none';
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Existing logic
    new MedicalMarking(
        'anatomy-canvas', 
        'marking-data', 
        '../../assets/images/anatomy_4_views_clean.png'
    );

    // Initialize the dynamic sub-forms
    initPainDetailsLogic();
    
    // Sliders
    const sliders = document.querySelectorAll('.dynamic-slider');
    sliders.forEach(slider => {
        slider.addEventListener('input', function() {
            const targetId = this.getAttribute('data-val-id');
            const valEl = document.getElementById(targetId);
            if(valEl) valEl.textContent = this.value;
        });
    });
});

function resizeImageFile(file, maxWidth = 800, maxHeight = 800) {
    return new Promise((resolve, reject) => {
        if (!file.type.match(/image.*/)) {
            resolve(file); return;
        }
        const reader = new FileReader();
        reader.onload = function (readerEvent) {
            const image = new Image();
            image.onload = function () {
                let width = image.width;
                let height = image.height;
                if (width > maxWidth) {
                    height *= maxWidth / width;
                    width = maxWidth;
                }
                if (height > maxHeight) {
                    width *= maxHeight / height;
                    height = maxHeight;
                }
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                canvas.getContext('2d').drawImage(image, 0, 0, width, height);
                // Force JPEG to ensure maximum compression, ignore original file.type
                canvas.toBlob((blob) => {
                    if (blob) {
                        const resizedFile = new File([blob], file.name.replace(/\.[^/.]+$/, "") + ".jpg", { type: 'image/jpeg', lastModified: Date.now() });
                        resolve(resizedFile);
                    } else {
                        resolve(file); // fallback
                    }
                }, 'image/jpeg', 0.7); // 70% quality
            };
            image.onerror = () => resolve(file); // fallback on error
            image.src = readerEvent.target.result;
        };
        reader.onerror = () => resolve(file);
        reader.readAsDataURL(file);
    });
}

async function uploadExamImages() {
    const fileInput = document.getElementById('exam_images_input');
    if (!fileInput.files || fileInput.files.length === 0) {
        alert('Vui lòng chọn ít nhất 1 ảnh trước khi tải lên!');
        return;
    }
    
    const gallery = document.getElementById('uploaded-images-gallery');
    // Count existing valid images (ignoring loading states or errors if any)
    const existingCount = gallery.querySelectorAll('div > input[type="hidden"]').length;
    if (existingCount + fileInput.files.length > 4) {
        alert('Bạn chỉ có thể lưu tối đa 4 ảnh. Vui lòng xoá bớt ảnh cũ hoặc chọn ít ảnh hơn!');
        return;
    }
    
    const uploadBtn = document.getElementById('btn-upload-images');
    uploadBtn.disabled = true;
    uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang tải lên...';
    
    const filesToUpload = Array.from(fileInput.files);
    fileInput.value = ''; // clear input so user can pick more later
    
    for (let i = 0; i < filesToUpload.length; i++) {
        await uploadSingleImage(filesToUpload[i], <?php echo $patient_id; ?>, gallery);
    }
    
    uploadBtn.disabled = false;
    uploadBtn.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Tải ảnh lên';
}

function uploadSingleImage(originalFile, patientId, gallery) {
    return new Promise(async (resolve) => {
        const id = 'upload_' + Math.random().toString(36).substr(2, 9);
        const uiHtml = `
            <div id="${id}" style="position: relative; width: 80px; height: 80px; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; background: #f1f5f9; display: flex; flex-direction: column; justify-content: center; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05); animation: slideDownFadeIn 0.3s ease-out;">
                <div style="width: 80%; height: 6px; background: #cbd5e1; border-radius: 3px; overflow: hidden; margin-bottom: 5px;">
                    <div class="progress-bar" style="width: 0%; height: 100%; background: var(--primary); transition: width 0.2s;"></div>
                </div>
                <div class="status-text" style="font-size: 0.6rem; color: #64748b; font-weight: 700; text-align: center; padding: 0 4px; line-height: 1.2;">Đang chuẩn bị...</div>
            </div>
        `;
        gallery.insertAdjacentHTML('beforeend', uiHtml);
        
        const wrapper = document.getElementById(id);
        const progressBar = wrapper.querySelector('.progress-bar');
        const statusText = wrapper.querySelector('.status-text');
        
        statusText.textContent = 'Đang nén...';
        progressBar.style.width = '10%';
        
        let fileToUpload = originalFile;
        try {
            fileToUpload = await resizeImageFile(originalFile);
        } catch (e) {
            console.error("Lỗi nén ảnh", e);
        }
        
        statusText.textContent = 'Đang tải...';
        
        const formData = new FormData();
        formData.append('exam_images[]', fileToUpload);
        formData.append('patient_id', patientId);
        
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'ajax_upload_images.php', true);
        
        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                // start from 10% (compression done)
                const percentComplete = 10 + (e.loaded / e.total) * 90;
                progressBar.style.width = percentComplete + '%';
            }
        };
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    const res = JSON.parse(xhr.responseText);
                    if (res.success && res.files && res.files.length > 0) {
                        const imgUrl = res.files[0];
                        wrapper.innerHTML = `
                            <img src="../../${imgUrl}" style="width: 100%; height: 100%; object-fit: cover; cursor: pointer;" onclick="openLightbox(this.src)" title="Nhấn để xem lớn">
                            <input type="hidden" name="exam[images][]" value="${imgUrl}">
                            <label style="position: absolute; top: 2px; right: 2px; background: rgba(239,68,68,0.9); color: white; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer;" onclick="this.parentElement.remove();" title="Xoá ảnh này">
                                <i class="fas fa-times" style="font-size: 10px;"></i>
                            </label>
                            <div class="success-badge" style="position: absolute; bottom: 0; left: 0; right: 0; background: rgba(16, 185, 129, 0.9); color: white; font-size: 0.65rem; text-align: center; padding: 3px 0; font-weight: 800;"><i class="fas fa-check"></i> OK</div>
                        `;
                        setTimeout(() => {
                            const badge = wrapper.querySelector('.success-badge');
                            if (badge) badge.style.display = 'none';
                        }, 2500);
                        resolve(true);
                    } else {
                        wrapper.innerHTML = `<div style="padding: 5px; text-align: center; font-size: 0.65rem; color: #ef4444; font-weight: 800; line-height: 1.3;"><i class="fas fa-exclamation-triangle"></i><br>Lỗi File</div>`;
                        resolve(false);
                    }
                } catch (e) {
                    wrapper.innerHTML = `<div style="padding: 5px; text-align: center; font-size: 0.65rem; color: #ef4444; font-weight: 800; line-height: 1.3;"><i class="fas fa-exclamation-triangle"></i><br>Lỗi JSON</div>`;
                    resolve(false);
                }
            } else {
                let errText = xhr.status === 413 ? 'Ảnh<br>quá nặng' : 'Lỗi<br>' + xhr.status;
                wrapper.innerHTML = `<div style="padding: 5px; text-align: center; font-size: 0.65rem; color: #ef4444; font-weight: 800; line-height: 1.3;"><i class="fas fa-exclamation-triangle"></i><br>${errText}</div>`;
                resolve(false);
            }
        };
        
        xhr.onerror = function() {
            wrapper.innerHTML = `<div style="padding: 5px; text-align: center; font-size: 0.65rem; color: #ef4444; font-weight: 800; line-height: 1.3;"><i class="fas fa-wifi"></i><br>Mất mạng</div>`;
            resolve(false);
        };
        
        xhr.send(formData);
    });
}

// Lightbox Viewer
function openLightbox(src) {
    const overlay = document.createElement('div');
    overlay.style.position = 'fixed';
    overlay.style.top = '0';
    overlay.style.left = '0';
    overlay.style.width = '100vw';
    overlay.style.height = '100vh';
    overlay.style.backgroundColor = 'rgba(0,0,0,0.85)';
    overlay.style.zIndex = '999999';
    overlay.style.display = 'flex';
    overlay.style.alignItems = 'center';
    overlay.style.justifyContent = 'center';
    overlay.style.cursor = 'zoom-out';
    overlay.style.opacity = '0';
    overlay.style.transition = 'opacity 0.2s ease-out';
    
    const img = document.createElement('img');
    img.src = src;
    img.style.maxWidth = '90%';
    img.style.maxHeight = '90%';
    img.style.borderRadius = '8px';
    img.style.boxShadow = '0 10px 25px rgba(0,0,0,0.5)';
    img.style.objectFit = 'contain';
    img.style.transform = 'scale(0.95)';
    img.style.transition = 'transform 0.2s ease-out';
    
    overlay.appendChild(img);
    document.body.appendChild(overlay);
    
    // Trigger animations
    setTimeout(() => {
        overlay.style.opacity = '1';
        img.style.transform = 'scale(1)';
    }, 10);
    
    overlay.onclick = function() {
        overlay.style.opacity = '0';
        img.style.transform = 'scale(0.95)';
        setTimeout(() => {
            if (document.body.contains(overlay)) {
                document.body.removeChild(overlay);
            }
        }, 200);
    };
}
</script>

<!-- jQuery & Select2 JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const under1Check = document.getElementById('is_under_1_check');
    const relDisplay = document.getElementById('relationship_display');
    const relSelect = document.getElementById('relationship_select_area');
    
    function toggleGuardianSelect() {
        if (under1Check.checked) {
            relDisplay.style.display = 'none';
            relSelect.style.display = 'block';
            if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
                jQuery('#guardian_patient_select').select2({
                    width: '100%',
                    placeholder: '-- Chọn người giám hộ --'
                });
            }
        } else {
            relDisplay.style.display = 'block';
            relSelect.style.display = 'none';
        }
    }
    
    if (under1Check) {
        under1Check.addEventListener('change', toggleGuardianSelect);
        toggleGuardianSelect();
    }
});
</script>

<?php require_once '../../templates/footer.php'; ?>
