<?php
if (!function_exists('_t_v2')) {
    function _t_v2($vi, $de, $en) {
        $lang = 'vi';
        if (isset($_GET['lang']) && in_array($_GET['lang'], ['vi', 'en', 'de'])) {
            $lang = $_GET['lang'];
        } elseif (isset($_SESSION['lang']) && in_array($_SESSION['lang'], ['vi', 'en', 'de'])) {
            $lang = $_SESSION['lang'];
        }
        
        if ($lang === 'de') return $de;
        if ($lang === 'en') return $en;
        return $vi;
    }
}

// modules/medical/chiro_history.php

require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_medical');

$is_de = ($_GET['lang'] ?? $_SESSION['lang'] ?? 'vi') === 'de';

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
} elseif ($patient_id) {
    $stmt = $db->prepare("SELECT id, history_data FROM medical_history WHERE patient_id = ? AND type IN ('chiro_history_v2', 'chiro_history') ORDER BY id DESC LIMIT 1");
    $stmt->execute([$patient_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $record_id = (int)$row['id'];
        $existing_data = json_decode($row['history_data'], true) ?: [];
    }
}

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

    // Merging logic for Bệnh lý hiện tại notes
    if (!empty($exam['akt_path']['notes'])) {
        $path_notes = trim($exam['akt_path']['notes']);
        if (!empty($exam['notes'])) {
            $exam['notes'] = trim($exam['notes']) . "\n\n--- Ghi chú Bệnh lý hiện tại ---\n" . $path_notes;
        } else {
            $exam['notes'] = "--- Ghi chú Bệnh lý hiện tại ---\n" . $path_notes;
        }
        $exam['akt_path']['notes'] = ''; // Clear it from here
    }

    // Images are now handled via AJAX and submitted as hidden inputs in $_POST['exam']['images']
    $history_data = json_encode($exam, JSON_UNESCAPED_UNICODE);
    
    if ($record_id) {
        $stmt = $db->prepare("UPDATE medical_history SET history_data = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$history_data, $record_id]);
    } else {
        $stmt = $db->prepare("
            INSERT INTO medical_history (patient_id, session_id, type, history_data, created_by)
            VALUES (?, ?, 'chiro_history_v2', ?, ?)
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
$relationship = $patient['relationship'] ?: ($patient['guardian_relationship'] ?: (_t_v2('Bản thân', 'Selbst', 'Self')));
$birth_year = (!empty($patient['birthday']) && $patient['birthday'] != '0000-00-00') ? date('Y', strtotime($patient['birthday'])) : (_t_v2('Chưa rõ', 'Unbekannt', 'Unknown'));

$stmt = $db->prepare("SELECT treatment_date FROM treatments WHERE patient_id = ? ORDER BY treatment_date DESC LIMIT 1");
$stmt->execute([$patient_id]);
$last_treatment = $stmt->fetchColumn();
$last_visit = $last_treatment ? date('d/m/Y', strtotime($last_treatment)) : (_t_v2('Lần đầu', 'Erster Besuch', 'First Visit'));

$all_patients_stmt = $db->query("SELECT id, full_name, phone FROM patients ORDER BY full_name");
$all_patients = $all_patients_stmt->fetchAll();

if (!$patient_name) {
    set_flash(__('medical.exam.err_no_patient'), 'error');
    redirect('index.php');
}

    $page_title = 'Anamnese';
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
<style>
/* Print Styles */
@media print {
    .sidebar, .navbar, .top-header, #header, #sidebar, .no-print { display: none !important; }
    .main-content, #main-content, body, .content { margin: 0 !important; padding: 0 !important; width: 100% !important; background: white !important; }
    .card { box-shadow: none !important; border: none !important; margin: 0 !important; padding: 0 !important; }
    body { font-size: 11px !important; }
    textarea { border: 1px solid #eee !important; box-shadow: none !important; resize: none !important; overflow: hidden !important; background: transparent !important; }
    input[type="text"], input[type="date"], select { border: 1px solid #eee !important; box-shadow: none !important; background: transparent !important; }
    .btn, button, input[type="submit"] { display: none !important; }
    ::-webkit-scrollbar { display: none !important; }
}
</style>
<script>
function autoResizeTextarea(el) {
    el.style.height = 'auto';
    el.style.height = Math.max(el.scrollHeight + 10, parseInt(el.getAttribute('data-min-height') || 120)) + 'px';
}
window.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('textarea').forEach(t => {
        autoResizeTextarea(t);
        t.addEventListener('input', () => autoResizeTextarea(t));
    });
});
window.addEventListener('beforeprint', () => {
    document.querySelectorAll('textarea').forEach(t => {
        t.style.height = 'auto';
        t.style.height = (t.scrollHeight + 5) + 'px';
    });
});
</script>

<div class="card" style="background: var(--glass-bg); backdrop-filter: blur(20px); max-width: 1300px; margin: 0 auto; border-radius: 24px;">

    <!-- TOP NAVIGATION & HEADER -->
    <div class="no-print" style="display: flex; justify-content: flex-end; margin-bottom: 1.5rem;">
        <button type="button" onclick="window.print()" class="btn" style="background: #10b981; color: white; border-radius: 8px; font-weight: 700; border: none; padding: 10px 20px; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2); cursor: pointer;"><i class="fas fa-print" style="margin-right: 8px;"></i> <?php echo _t_v2('In PDF', 'Als PDF drucken', 'Print PDF'); ?></button>
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
                        <div style="color: #86868b; font-size: 0.65rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 0.2rem;"><?php echo _t_v2('Họ Tên', 'Name', 'Full Name'); ?></div>
                        <div style="font-size: 1.05rem; font-weight: 600; color: #1d1d1f; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo e($patient_name); ?>"><?php echo e($patient_name); ?></div>
                    </div>
                </div>

                <!-- 2. NĂM SINH -->
                <div style="display: flex; align-items: center; gap: 1rem; flex: 1 1 200px; min-width: 180px;">
                    <div style="width: 46px; height: 46px; border-radius: 50%; background: #f5f5f7; color: #1d1d1f; display: flex; justify-content: center; align-items: center; font-size: 1.15rem; flex-shrink: 0;">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div>
                        <div style="color: #86868b; font-size: 0.65rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 0.2rem;"><?php echo _t_v2('Năm Sinh', 'Geburtsjahr', 'Birth Year'); ?></div>
                        <div style="font-size: 1.05rem; font-weight: 600; color: #1d1d1f; line-height: 1.2; display: flex; align-items: center; gap: 0.5rem;">
                            <?php echo $birth_year; ?>
                            <label style="display: flex; align-items: center; gap: 0.35rem; cursor: pointer; background: #fff1f2; padding: 0.35rem 0.75rem; border-radius: 8px; color: #e11d48; font-weight: 700; font-size: 0.9rem; transition: all 0.2s; white-space: nowrap;">
                                <input type="checkbox" id="is_under_1_check" name="exam[is_under_1]" value="1" <?php echo checked_v('is_under_1', '1'); ?> style="transform: scale(1.1); margin: 0;">
                                <?php echo _t_v2('< 1T', '< 1J', '< 1Y'); ?>
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
                        <div style="color: #86868b; font-size: 0.65rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 0.2rem;"><?php echo _t_v2('Quan Hệ', 'Beziehung', 'Relationship'); ?></div>
                        <div style="font-size: 1.05rem; font-weight: 600; color: #1d1d1f; line-height: 1.2;" id="relationship_display">
                            <a href="../patients/edit.php?id=<?php echo $patient_id; ?>" target="_blank" style="color: inherit; text-decoration: underline; text-decoration-color: #cbd5e1; text-underline-offset: 4px;" title="Nhấn để xem/sửa hồ sơ">
                                <?php echo e($relationship); ?>
                            </a>
                        </div>
                        <div id="relationship_select_area" style="display: none; margin-top: 4px;">
                            <select name="exam[guardian_patient_id]" id="guardian_patient_select" style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 6px; font-size: 0.95rem; font-weight: 500; color: #1d1d1f; background: #f8fafc; outline: none;">
                                <option value=""><?php echo _t_v2('-- Chọn khách hàng --', '-- Patient wählen --', '-- Select Patient --'); ?></option>
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
                        <div style="color: #86868b; font-size: 0.65rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 0.2rem;"><?php echo _t_v2('Giới Tính', 'Geschlecht', 'Gender'); ?></div>
                        <div style="font-size: 1.05rem; font-weight: 600; color: #1d1d1f; line-height: 1.2;"><?php echo $patient['gender'] == 'male' ? (_t_v2('Nam', 'Männlich', 'Male')) : ($patient['gender'] == 'female' ? (_t_v2('Nữ', 'Weiblich', 'Female')) : (_t_v2('Chưa rõ', 'Unbekannt', 'Unknown'))); ?></div>
                    </div>
                </div>
                
                <!-- 5. LẦN KHÁM -->
                <div style="display: flex; align-items: center; gap: 1rem; flex: 1 1 200px; min-width: 150px;">
                    <div style="width: 46px; height: 46px; border-radius: 50%; background: #f5f5f7; color: #1d1d1f; display: flex; justify-content: center; align-items: center; font-size: 1.15rem; flex-shrink: 0;">
                        <i class="far fa-clock"></i>
                    </div>
                    <div>
                        <div style="color: #86868b; font-size: 0.65rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 0.2rem;"><?php echo _t_v2('Lần Khám', 'Besuch', 'Visit'); ?></div>
                        <div style="font-size: 1.05rem; font-weight: 600; color: #1d1d1f; line-height: 1.2;"><?php echo $last_visit; ?></div>
                    </div>
                </div>
                
                <!-- 6. PHÂN LOẠI -->
                <div style="display: flex; align-items: center; gap: 1rem; flex: 1 1 200px; min-width: 150px;">
                    <div style="width: 46px; height: 46px; border-radius: 50%; background: #f5f5f7; color: #1d1d1f; display: flex; justify-content: center; align-items: center; font-size: 1.15rem; flex-shrink: 0;">
                        <i class="fas fa-tags"></i>
                    </div>
                    <div>
                        <div style="color: #86868b; font-size: 0.65rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 0.2rem;"><?php echo _t_v2('Phân loại', 'Kategorie', 'Label'); ?></div>
                        <div style="font-size: 1.05rem; font-weight: 600; color: #1d1d1f; line-height: 1.2;">
                            <a href="../patients/edit.php?id=<?php echo $patient_id; ?>" target="_blank" style="color: inherit; text-decoration: underline; text-decoration-color: #cbd5e1; text-underline-offset: 4px;" title="Nhấn để xem/sửa hồ sơ">
                                <?php echo e($patient['label'] ?: _t_v2('Không', 'Keine', 'None')); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; margin-top: 2.5rem; margin-bottom: 2rem;">
                <div class="form-group">
                    <label class="form-label" style="display: block; margin-bottom: 0.6rem; font-weight: 600; font-size: 0.9rem; color: #1d1d1f;"><?php echo _t_v2('Trọng lượng phần chân (kg)', 'Beinbelastung (kg)', 'Leg Load (kg)'); ?></label>
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <input type="number" step="0.1" name="exam[biometrics][weight_left]" class="form-input" placeholder="<?php echo _t_v2('Trái...', 'li...', 'Left...'); ?>" value="<?php echo get_v('biometrics.weight_left'); ?>">
                        <input type="number" step="0.1" name="exam[biometrics][weight_right]" class="form-input" placeholder="<?php echo _t_v2('Phải...', 're...', 'Right...'); ?>" value="<?php echo get_v('biometrics.weight_right'); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" style="display: block; margin-bottom: 0.6rem; font-weight: 600; font-size: 0.9rem; color: #1d1d1f;"><?php echo _t_v2('Hình ảnh chụp bệnh nhân (Tối đa 4 ảnh)', 'Patientenbilder (max. 4 Bilder)', 'Patient Images (max 4 images)'); ?></label>
                    
                    <div style="display: flex; gap: 0.5rem; align-items: center; margin-bottom: 1rem;">
                        <div style="flex: 1; display: flex; align-items: center; border: 1px solid #cbd5e1; border-radius: 8px; overflow: hidden; background: #fff; position: relative;">
                            <label for="exam_images_input" style="background: #f1f5f9; padding: 0.5rem 1rem; border-right: 1px solid #cbd5e1; cursor: pointer; margin: 0; font-weight: 500; font-size: 0.9rem; color: #1e293b; height: 100%; display: flex; align-items: center;">
                                <?php echo _t_v2('Chọn tệp', 'Datei auswählen', 'Choose file'); ?>
                            </label>
                            <span id="file-chosen-text" style="padding: 0.5rem 1rem; color: #64748b; font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1;">
                                <?php echo _t_v2('Không có tệp nào được chọn', 'Keine Datei ausgewählt', 'No file chosen'); ?>
                            </span>
                            <input type="file" id="exam_images_input" multiple accept="image/*" style="position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); border: 0;" onchange="document.getElementById('file-chosen-text').textContent = this.files.length > 0 ? (this.files.length + ' <?php echo _t_v2('tệp được chọn', 'Dateien ausgewählt', 'files chosen'); ?>') : '<?php echo _t_v2('Không có tệp nào được chọn', 'Keine Datei ausgewählt', 'No file chosen'); ?>'">
                        </div>
                        <button type="button" id="btn-upload-images" onclick="uploadExamImages()" class="btn btn-primary" style="padding: 0.65rem 1rem; font-weight: 600; font-size: 0.85rem; border-radius: 8px; white-space: nowrap;">
                            <i class="fas fa-cloud-upload-alt"></i> <?php echo _t_v2('Tải ảnh lên', 'Hochladen', 'Upload'); ?>
                        </button>
                    </div>
                    
                    <!-- Individual progress will be shown in the gallery -->

                    <div id="uploaded-images-gallery" style="display: flex; gap: 1rem; flex-wrap: wrap; background: #f8fafc; padding: 1rem; border-radius: 12px; border: 1px dashed #cbd5e1; min-height: 80px;">
                        <?php 
                        $existing_images = get_v('images', []);
                        if (!empty($existing_images)): 
                            foreach ($existing_images as $img): ?>
                                <div style="position: relative; width: 80px; height: 80px; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                    <img src="../../<?php echo e($img); ?>" style="width: 100%; height: 100%; object-fit: cover; cursor: pointer;" onclick="openLightbox(this.src)" title="<?php echo _t_v2('Nhấn để xem lớn', 'Klicken zum Vergrößern', 'Click to Enlarge'); ?>">
                                    <input type="hidden" name="exam[images][]" value="<?php echo e($img); ?>">
                                    <label style="position: absolute; top: 2px; right: 2px; background: rgba(239,68,68,0.9); color: white; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer;" onclick="this.parentElement.remove();" title="<?php echo _t_v2('Xoá ảnh này', 'Dieses Bild löschen', 'Delete this Image'); ?>">
                                        <i class="fas fa-times" style="font-size: 10px;"></i>
                                    </label>
                                </div>
                            <?php endforeach; 
                        endif; ?>
                    </div>
                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.5rem;"><i class="fas fa-info-circle"></i> <?php echo _t_v2('Chọn ảnh, nhấn <strong>Tải ảnh lên</strong>, sau đó nhấn <strong>Lưu Bệnh Án</strong> ở cuối trang để hoàn tất.', 'Bild auswählen, auf <strong>Hochladen</strong> klicken, dann am Ende der Seite auf <strong>Speichern</strong> klicken, um abzuschließen.', 'Select image, click <strong>Upload</strong>, then click <strong>Save</strong> at the bottom of the page to complete.'); ?></div>
                </div>
            </div>

            <!-- GHI CHÚ CHUNG -->
            <div style="margin-bottom: 2.5rem;">
                <div class="form-group" style="margin-bottom: 2.5rem;">
                    <label class="form-label" style="font-weight: 700; color: #1d1d1f; font-size: 1rem; display: block; margin-bottom: 0.6rem;"><?php echo _t_v2('Ô ghi chú', 'Notizfeld', 'Note Field'); ?></label>
                    <textarea name="exam[notes]" class="form-input" rows="8" data-min-height="160" placeholder="<?php echo _t_v2('Nhập ghi chú hoặc thông tin bổ sung tại đây...', 'Hier Anmerkungen oder zusätzliche Informationen eingeben...', 'Enter notes or additional information here...'); ?>" style="min-height: 160px; font-size: 0.95rem; line-height: 1.6; resize: vertical;"><?php echo get_v('notes'); ?></textarea>
                    <div style="font-size: 0.8rem; color: #3b82f6; font-style: italic; background: #eff6ff; padding: 0.6rem 1rem; border-radius: 8px; border: 1px solid #bfdbfe; margin-top: 0.8rem; display: flex; gap: 0.5rem; align-items: flex-start;">
                        <i class="fas fa-info-circle" style="margin-top: 0.2rem;"></i>
                        <div><?php echo _t_v2('Sau khi bấm Lưu, ghi chú này sẽ được gom và hiển thị chung với phần ghi chú ở trên. Ghi chú trong phần Bệnh sử sẽ được hiển thị lại trên các bản Tái khám (Follow-Up) về sau.', 'Nach dem Speichern werden diese Notizen zusammengefasst und gemeinsam mit den obigen Notizen angezeigt. Notizen aus der Anamnese werden auf den späteren Follow-Up-Bögen erneut angezeigt.', 'After saving, these notes will be combined and displayed with the above notes. Notes from the Anamnesis will be displayed again on later Follow-Up forms.'); ?></div>
                    </div>
                </div>
            </div>

            

        </div> <!-- CLOSE PART 1 -->

        <!-- PART 2: TIỀN SỬ Y KHOA & CHẤN THƯƠNG -->
        <div style="margin-bottom: 4rem;">
            <h3 style="font-size: 1.15rem; font-weight: 700; color: #1d1d1f; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 1px solid rgba(0,0,0,0.06); padding-bottom: 1rem; letter-spacing: -0.2px;">
                <div style="width: 32px; height: 32px; background: #f5f5f7; border-radius: 8px; display: flex; justify-content: center; align-items: center; color: #1d1d1f; font-size: 0.9rem;">
                    <i class="fas fa-history"></i>
                </div>
                <?php echo _t_v2('PHẦN 2: TIỀN SỬ Y KHOA & CHẤN THƯƠNG', 'TEIL 2: MEDIZINISCHE VORGESCHICHTE & TRAUMA', 'PART 2: MEDICAL HISTORY & TRAUMA'); ?>
            </h3>
            <p style="font-style: italic; color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.9rem;"><?php echo __('medical.history.part3_desc'); ?></p>

            <!-- Intervention History -->
            <div class="form-group" style="margin-bottom: 2.5rem;">
                <label class="form-label" style="font-size: 1.1rem; color: #1e293b;"><?php echo _t_v2('Tiền sử phẫu thuật/chấn thương', 'Operations- und Trauma-Anamnese', 'Surgical and Trauma History'); ?></label>
                <div style="display: flex; flex-direction: column; gap: 1.25rem; margin-top: 1rem; padding-left: 1rem;">
                    
                    <!-- Surgery -->
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.95rem; font-weight: 700;">
                            <input type="checkbox" name="exam[medical_history][surgery_flag]" value="1" <?php echo checked_v('medical_history.surgery_flag', '1'); ?>> <?php echo _t_v2('Đã từng phẫu thuật', 'Hatte Operation', 'Had Surgery'); ?>
                        </label>
                        <div style="display: flex; align-items: flex-start; gap: 1rem; padding-left: 1.5rem;">
                            <div style="flex: 1;">
                                <div style="font-size: 0.85rem; margin-bottom: 0.25rem; font-weight: 600;"><?php echo _t_v2('Khu vực / Chi tiết phẫu thuật', 'Bereich', 'Area'); ?></div>
                                <textarea name="exam[medical_history][surgery_area]" class="form-input" rows="2" placeholder="<?php echo _t_v2('Ghi chú thoải mái...', 'Notizen...', 'Notes...'); ?>"><?php echo get_v('medical_history.surgery_area'); ?></textarea>
                            </div>
                            <div style="width: 200px;">
                                <div style="font-size: 0.85rem; margin-bottom: 0.25rem; font-weight: 600;"><?php echo _t_v2('Thời gian', 'Zeitpunkt', 'Time'); ?></div>
                                <input type="text" name="exam[medical_history][surgery_time]" class="form-input" placeholder="..." value="<?php echo get_v('medical_history.surgery_time'); ?>">
                            </div>
                        </div>
                        <div style="padding-left: 1.5rem; font-size: 0.85rem; color: #b45309; font-weight: 700;"><?php echo _t_v2('(Đánh dấu O vàng lên hình trên)', '(Gelbes O auf dem Bild oben markieren)', '(Mark Yellow O on the image above)'); ?></div>
                    </div>
                    
                    <!-- Fracture -->
                    <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 1rem;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.95rem; font-weight: 700;">
                            <input type="checkbox" name="exam[medical_history][fracture_flag]" value="1" <?php echo checked_v('medical_history.fracture_flag', '1'); ?>> <?php echo _t_v2('Đã từng gãy xương', 'Hatte Fraktur', 'Had Fracture'); ?>
                        </label>
                        <div style="display: flex; align-items: flex-start; gap: 1rem; padding-left: 1.5rem;">
                            <div style="flex: 1;">
                                <div style="font-size: 0.85rem; margin-bottom: 0.25rem; font-weight: 600;"><?php echo _t_v2('Khu vực / Chi tiết gãy xương', 'Bereich', 'Area'); ?></div>
                                <textarea name="exam[medical_history][fracture_area]" class="form-input" rows="2" placeholder="<?php echo _t_v2('Ghi chú thoải mái...', 'Notizen...', 'Notes...'); ?>"><?php echo get_v('medical_history.fracture_area'); ?></textarea>
                            </div>
                            <div style="width: 200px;">
                                <div style="font-size: 0.85rem; margin-bottom: 0.25rem; font-weight: 600;"><?php echo _t_v2('Thời gian', 'Zeitpunkt', 'Time'); ?></div>
                                <input type="text" name="exam[medical_history][fracture_time]" class="form-input" placeholder="..." value="<?php echo get_v('medical_history.fracture_time'); ?>">
                            </div>
                        </div>
                        <div style="padding-left: 1.5rem; font-size: 0.85rem; color: #b91c1c; font-weight: 700;"><?php echo _t_v2('(Đánh dấu X đỏ lên hình trên)', '(Rotes X auf dem Bild oben markieren)', '(Mark red X on the image above)'); ?></div>
                    </div>

                    <!-- Dental -->
                    <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; margin-top: 1rem;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.95rem; font-weight: 700;">
                            <input type="checkbox" name="exam[medical_history][dental]" value="1" <?php echo checked_v('medical_history.dental', '1'); ?>> <?php echo _t_v2('Niềng răng / Trồng răng', 'Trägt Zahnspange / Zahnimplantate', 'Wears Braces / Dental Implants'); ?>
                        </label>
                        <span style="font-size: 0.9rem;">(Seit wann: <input type="text" name="exam[medical_history][dental_time]" class="form-input" style="display: inline-block; width: 140px;" value="<?php echo get_v('medical_history.dental_time'); ?>">)</span>
                    </div>

                    <!-- Imaging -->
                    <div style="display: flex; align-items: center; gap: 1.5rem; margin-top: 1rem;">
                         <span style="font-size: 0.95rem; font-weight: 700;">Bildgebung vorhanden:</span>
                         <?php 
                         $imaging_options = [
                             'X-Ray' => 'X-Ray',
                             'MRI/CT' => 'MRI/CT'
                         ];
                         foreach ($imaging_options as $val => $label): ?>
                            <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.95rem; cursor: pointer; font-weight: 600;">
                                <input type="checkbox" name="exam[medical_history][imaging][]" value="<?php echo $val; ?>" <?php echo checked_v('medical_history.imaging', $val); ?>> <?php echo $label; ?>
                            </label>
                         <?php endforeach; ?>
                    </div>

                     <!-- Treatments -->
                    <div style="display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap; margin-top: 0.5rem;">
                         <span style="font-size: 0.95rem; font-weight: 700;">Zuvor behandelt bei:</span>
                         <?php 
                         $prev_treatment_options = [
                             'Andere Chiropraktik' => 'Andere Chiropraktik',
                             'Physiotherapie'  => 'Physiotherapie',
                             'Osteopath'        => 'Osteopath'
                         ];
                         foreach ($prev_treatment_options as $val => $label): ?>
                            <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.95rem; cursor: pointer; font-weight: 600;">
                                <input type="checkbox" name="exam[medical_history][prev_treatments][]" value="<?php echo $val; ?>" <?php echo checked_v('medical_history.prev_treatments', $val); ?>> <?php echo $label; ?>
                            </label>
                         <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div> <!-- CLOSE PART 2 -->


        <!-- NEW WRAPPER FOR OLD PART 2 -->
        <div style="margin-bottom: 4rem;">
            <!-- ============================================== -->
            <!-- <?php echo _t_v2('PHẦN 3 – BỆNH LÝ HIỆN TẠI', 'TEIL 3 – AKTUELLE BESCHWERDEN', 'PART 3 - CURRENT COMPLAINTS'); ?> -->
            <!-- ============================================== -->
            <div style="margin-top: 3.5rem; margin-bottom: 2rem; border-top: 1px solid #e2e8f0; padding-top: 2.5rem;">
                <h3 style="font-weight: 800; font-size: 1.15rem; color: #1d1d1f; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem;">
                    <i class="fas fa-stethoscope" style="color: #3b82f6;"></i> <?php echo _t_v2('PHẦN 3 – BỆNH LÝ HIỆN TẠI', 'TEIL 3 – AKTUELLE BESCHWERDEN', 'PART 3 - CURRENT COMPLAINTS'); ?>
                </h3>

            <!-- SƠ ĐỒ ĐIỂM ĐAU / CẢNH BÁO -->
            <div style="margin-top: 3rem; margin-bottom: 3rem;">
                <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--primary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-edit"></i> <?php echo __('medical.history.pain_map_title'); ?>
                </h3>
                
                <div style="display: flex; gap: 3rem;">
                    <div style="flex: 1; position: relative; background: white; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01); overflow: hidden; cursor: crosshair;">
                        <canvas id="anatomy-canvas" width="800" height="800" style="width: 100%; height: auto; display: block;"></canvas>
                        <input type="hidden" name="exam[markers]" id="marking-data" value="<?php echo e(json_encode(get_v('markers', []))); ?>">
                    </div>
                    
                    <div style="width: 350px;">
                        <div style="background: #fff9f0; padding: 1.25rem; border-radius: 12px; border: 1px solid #ffedd5; margin-bottom: 2rem;">
                            <h4 style="font-size: 0.8rem; color: #9a3412; text-transform: uppercase; margin-bottom: 0.75rem; font-weight: 800;"><?php echo __('medical.history.guide_title'); ?></h4>
                            <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.8rem; color: #9a3412; line-height: 1.6;">
                                <li><strong>O:</strong> <?php echo __('medical.history.guide_surgery'); ?></li>
                                <li><strong>X:</strong> <?php echo __('medical.history.guide_fracture'); ?></li>
                                <li><strong>M:</strong> <?php echo __('medical.history.guide_pain'); ?></li>
                            </ul>
                        </div>

                        <div style="display: flex; gap: 0.75rem; margin-bottom: 2rem;">
                            <button type="button" class="tool-btn active" id="tool-marker" title="<?php echo __('medical.history.tool_pain'); ?>"><i class="fas fa-pencil-alt"></i></button>
                            <button type="button" class="tool-btn" id="tool-surgery" style="color: #f59e0b; font-weight: 900;">O</button>
                            <button type="button" class="tool-btn" id="tool-fracture" style="color: #ef4444; font-weight: 900;">X</button>
                            <button type="button" class="tool-btn" id="marker-eraser" title="<?php echo __('medical.history.tool_eraser'); ?>"><i class="fas fa-eraser"></i></button>
                            <button type="button" class="tool-btn" id="marker-clear" style="margin-left: auto; color: #ef4444;"><i class="fas fa-trash"></i></button>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 0.75rem;">
                            <?php 
                            $colors = [
                                'M1' => '#38bdf8', 'M2' => '#4ade80', 'M3' => '#fbbf24', 'M4' => '#fb923c', 'M5' => '#ef4444'
                            ];
                            foreach($colors as $m => $color): ?>
                                <button type="button" class="intensity-btn <?php echo $m === 'M3' ? 'active' : ''; ?>" 
                                        data-intensity="<?php echo $m; ?>" 
                                        style="color: <?php echo $color; ?>"
                                        title="<?php echo $m; ?>">
                                    <span style="background: <?php echo $color; ?>;"></span> <?php echo $m; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top: 1.5rem; background: #e0f2fe; padding: 1rem; border-radius: 12px; border: 1px solid #bae6fd;">
                            <h4 style="font-size: 0.85rem; color: #0369a1; font-weight: 800; margin-bottom: 0.5rem;"><?php echo _t_v2('Quy ước 5 vòng tròn mức độ đau:', 'Legende zur Schmerzintensität (5 Kreise):', 'Pain Intensity Legend (5 Circles):'); ?></h4>
                            <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.8rem; color: #0c4a6e; line-height: 1.6;">
                                <li><strong><?php echo _t_v2('Vòng thứ 1 (xanh da trời)', 'Ring 1 (hellblau)', 'Ring 1 (Light Blue)'); ?>:</strong> <?php echo _t_v2('đã đỡ nhiều.', 'Deutlich verbessert.', 'Significantly improved.'); ?></li>
                                <li><strong><?php echo _t_v2('Vòng thứ 2 (xanh lá cây)', 'Ring 2 (grün)', 'Ring 2 (Green)'); ?>:</strong> <?php echo _t_v2('đỡ ít hơn.', 'Leicht verbessert.', 'Slightly improved.'); ?></li>
                                <li><strong><?php echo _t_v2('Vòng thứ 3 (vàng)', 'Ring 3 (gelb)', 'Ring 3 (Yellow)'); ?>:</strong> <?php echo _t_v2('vẫn đau như lần trước, không thay đổi.', 'Unverändert (Schmerzen wie zuvor).', 'Unchanged (Pain as before).'); ?></li>
                                <li><strong><?php echo _t_v2('Vòng thứ 4 (cam)', 'Ring 4 (orange)', 'Ring 4 (Orange)'); ?>:</strong> <?php echo _t_v2('đau hơn cũ một chút.', 'Leicht verschlechtert.', 'Slightly worsened.'); ?></li>
                                <li><strong><?php echo _t_v2('Vòng thứ 5 (đỏ)', 'Ring 5 (rot)', 'Ring 5 (Red)'); ?>:</strong> <?php echo _t_v2('đau hơn nhiều.', 'Deutlich verschlechtert.', 'Significantly worsened.'); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

                <!-- Tabs Navigation -->
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1.5rem; background: rgba(248,250,252,0.8); padding: 0.5rem; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <button type="button" class="tab-btn active" data-target="tab-kopf"><?php echo _t_v2('Đầu (Kopf)', 'Kopf', 'Head'); ?></button>
                    <button type="button" class="tab-btn" data-target="tab-hws"><?php echo _t_v2('Cột sống cổ (HWS)', 'HWS', 'Cervical Spine'); ?></button>
                    <button type="button" class="tab-btn" data-target="tab-bws"><?php echo _t_v2('CS ngực (BWS)', 'BWS', 'Thoracic Spine'); ?></button>
                    <button type="button" class="tab-btn" data-target="tab-lws"><?php echo _t_v2('CS thắt lưng (LWS)', 'LWS', 'Lumbar Spine'); ?></button>
                    <button type="button" class="tab-btn" data-target="tab-schulter"><?php echo _t_v2('Vai (Schulter)', 'Schulter', 'Shoulder'); ?></button>
                    <button type="button" class="tab-btn" data-target="tab-obere"><?php echo _t_v2('Chi trên (Obere Extr.)', 'Obere Extr.', 'Upper Extr.'); ?></button>
                    <button type="button" class="tab-btn" data-target="tab-untere"><?php echo _t_v2('Chi dưới (Untere Extr.)', 'Untere Extr.', 'Lower Extr.'); ?></button>
                    <button type="button" class="tab-btn" data-target="tab-fuss"><?php echo _t_v2('Bàn & cổ chân (Fuß & Sprunggelenk)', 'Fuß & Sprunggelenk', 'Foot & Ankle'); ?></button>
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
                    .path-table input[type="number"] { border: 1px solid #cbd5e1; border-radius: 8px; padding: 0.5rem 0.5rem; font-size: 0.95rem; outline: none; transition: all 0.2s; background: #f8fafc; width: 85px; text-align: center; box-shadow: inset 0 1px 2px rgba(0,0,0,0.02); color: #0f172a; }
                    .path-table input[type="number"]:focus { border-color: #007aff; background: white; box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.15); }
                    textarea.form-premium-input { width: 100%; border: 1px solid #cbd5e1; border-radius: 12px; padding: 1rem; font-size: 0.95rem; outline: none; transition: all 0.2s; background: #f8fafc; box-shadow: inset 0 1px 2px rgba(0,0,0,0.02); font-family: inherit; color: #1e293b; line-height: 1.5; resize: vertical; }
                    textarea.form-premium-input:focus { border-color: #007aff; background: white; box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.15); }
                </style>


                <div id="tab-kopf" class="tab-content active">
                    <table class="path-table">
                        <thead>
                            <tr>
                                <th style="width: 20%;"><?php echo _t_v2('Triệu chứng / Vùng (Symptom / Bereich)', 'Symptom / Bereich', 'Symptom / Area'); ?></th>
                                <th style="width: 40%;"><?php echo _t_v2('Tính chất / Vị trí (Eigenschaften / Ort)', 'Eigenschaften / Ort', 'Characteristics / Location'); ?></th>
                                <th style="width: 40%;"><?php echo _t_v2('Tần suất / Trạng thái (Häufigkeit / Status)', 'Häufigkeit / Status', 'Frequency / Status'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong><?php echo _t_v2('Đầu (Kopf)', 'Kopf', 'Head'); ?></strong></td>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        <label><input type="checkbox" name="exam[akt_path][kopf][vitri][]" value="sau_csc" <?php echo checked_v('exam[akt_path][kopf][vitri]', 'sau_csc'); ?>> <?php echo _t_v2('phía sau cột sống cổ (dorsal von HWS)', 'dorsal von HWS', 'Dorsal of Cervical Spine'); ?></label>
                                        <label><input type="checkbox" name="exam[akt_path][kopf][vitri][]" value="truoc" <?php echo checked_v('exam[akt_path][kopf][vitri]', 'truoc'); ?>> <?php echo _t_v2('phía trước (frontal)', 'frontal', 'Frontal'); ?></label>
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.6rem;">
                                            <label style="margin-bottom: 0; margin-right: 0.5rem;"><input type="checkbox" name="exam[akt_path][kopf][vitri][]" value="ben_hong" <?php echo checked_v('exam[akt_path][kopf][vitri]', 'ben_hong'); ?>> <?php echo _t_v2('bên hông (lateral)', 'lateral', 'Lateral'); ?></label>
                                            <label style="margin-bottom: 0; margin-right: 0.5rem;"><input type="checkbox" name="exam[akt_path][kopf][vitri][]" value="ben_hong_p" <?php echo checked_v('exam[akt_path][kopf][vitri]', 'ben_hong_p'); ?>> <?php echo _t_v2('Phải', 're', 'R'); ?></label>
                                            <label style="margin-bottom: 0;"><input type="checkbox" name="exam[akt_path][kopf][vitri][]" value="ben_hong_t" <?php echo checked_v('exam[akt_path][kopf][vitri]', 'ben_hong_t'); ?>> <?php echo _t_v2('Trái', 'li', 'L'); ?></label>
                                        </div>
                                        <label><input type="checkbox" name="exam[akt_path][kopf][vitri][]" value="mot_ben" <?php echo checked_v('exam[akt_path][kopf][vitri]', 'mot_ben'); ?>> <?php echo _t_v2('một bên', 'einseitig', 'Unilateral'); ?></label>
                                    </div>
                                </td>
                                <td>
                                    <div style="margin-bottom: 0.5rem; font-weight: 600;"><?php echo _t_v2('Tần suất:', 'wie oft:', 'How often:'); ?></div>
                                    <div style="display: grid; grid-template-columns: max-content auto; gap: 0.5rem 1rem;">
                                        <label><input type="checkbox" name="exam[akt_path][kopf][tansuat][]" value="hang_ngay" <?php echo checked_v('exam[akt_path][kopf][tansuat]', 'hang_ngay'); ?>> <?php echo _t_v2('hằng ngày', 'täglich', 'Daily'); ?></label>
                                        <label><input type="checkbox" name="exam[akt_path][kopf][tansuat][]" value="2_3_lan_tuan" <?php echo checked_v('exam[akt_path][kopf][tansuat]', '2_3_lan_tuan'); ?>> <?php echo _t_v2('2-3 lần/tuần', '2-3x/W', '2-3x/Week'); ?></label>
                                        
                                        <label><input type="checkbox" name="exam[akt_path][kopf][tansuat][]" value="1_lan_tuan" <?php echo checked_v('exam[akt_path][kopf][tansuat]', '1_lan_tuan'); ?>> <?php echo _t_v2('1 lần/tuần', '1x/W', '1x/Week'); ?></label>
                                        <label><input type="checkbox" name="exam[akt_path][kopf][tansuat][]" value="1_lan_thang" <?php echo checked_v('exam[akt_path][kopf][tansuat]', '1_lan_thang'); ?>> <?php echo _t_v2('1 lần/tháng', '1x/M', '1x/Month'); ?></label>
                                        
                                        <label><input type="checkbox" name="exam[akt_path][kopf][tansuat][]" value="thinh_thoang" <?php echo checked_v('exam[akt_path][kopf][tansuat]', 'thinh_thoang'); ?>> <?php echo _t_v2('thỉnh thoảng', 'sporadisch', 'Sporadic'); ?></label>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td><strong><label><input type="checkbox" name="exam[akt_path][chongmat][has]" value="1" <?php echo checked_v('exam[akt_path][chongmat][has]', '1'); ?>> <?php echo _t_v2('Chóng mặt', 'Schwindel', 'Dizziness'); ?></label></strong></td>
                                <td></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;"><?php echo _t_v2('Từ khi nào:', 'seit wann:', 'Since when:'); ?> <input type="text" name="exam[akt_path][chongmat][tu_khi_nao]" value="<?php echo get_v('exam[akt_path][chongmat][tu_khi_nao]'); ?>"></div>
                                    <div style="display: flex; gap: 1rem; margin-bottom: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][chongmat][donvi][]" value="ngay" <?php echo checked_v('exam[akt_path][chongmat][donvi]', 'ngay'); ?>> <?php echo _t_v2('ngày', 'Tage', 'Days'); ?></label> <label><input type="checkbox" name="exam[akt_path][chongmat][donvi][]" value="tuan" <?php echo checked_v('exam[akt_path][chongmat][donvi]', 'tuan'); ?>> <?php echo _t_v2('tuần', 'Wochen', 'Weeks'); ?></label> <label><input type="checkbox" name="exam[akt_path][chongmat][donvi][]" value="thang" <?php echo checked_v('exam[akt_path][chongmat][donvi]', 'thang'); ?>> <?php echo _t_v2('tháng', 'Monate', 'Months'); ?></label> <label><input type="checkbox" name="exam[akt_path][chongmat][donvi][]" value="nam" <?php echo checked_v('exam[akt_path][chongmat][donvi]', 'nam'); ?>> <?php echo _t_v2('năm', 'Jahre', 'Years'); ?></label></div>
                                    <div style="display: flex; flex-direction: column;">
                                        <label><input type="checkbox" name="exam[akt_path][chongmat][trangthai][]" value="lien_tuc" <?php echo checked_v('exam[akt_path][chongmat][trangthai]', 'lien_tuc'); ?>> <?php echo _t_v2('liên tục', 'permanent', 'Permanent'); ?></label>
                                        <label><input type="checkbox" name="exam[akt_path][chongmat][trangthai][]" value="luc_nhieu_luc_it" <?php echo checked_v('exam[akt_path][chongmat][trangthai]', 'luc_nhieu_luc_it'); ?>> <?php echo _t_v2('lúc nhiều lúc ít', 'intermittierend / wechselhaft', 'Intermittent / Variable'); ?></label>
                                        <label><input type="checkbox" name="exam[akt_path][chongmat][trangthai][]" value="co_luc_het_han" <?php echo checked_v('exam[akt_path][chongmat][trangthai]', 'co_luc_het_han'); ?>> <?php echo _t_v2('có lúc hết hẳn', 'phasenweise beschwerdefrei', 'Intermittently pain-free'); ?></label>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td><strong><label><input type="checkbox" name="exam[akt_path][utai][has]" value="1" <?php echo checked_v('exam[akt_path][utai][has]', '1'); ?>> <?php echo _t_v2('Ù tai', 'Ohrgeräusch', 'Tinnitus'); ?></label></strong></td>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        <label><input type="checkbox" name="exam[akt_path][utai][vitri][]" value="tieng_u" <?php echo checked_v('exam[akt_path][utai][vitri]', 'tieng_u'); ?>> <?php echo _t_v2('tiếng ù', 'rauschen', 'Rushing'); ?></label>
                                        <label><input type="checkbox" name="exam[akt_path][utai][vitri][]" value="tieng_rit" <?php echo checked_v('exam[akt_path][utai][vitri]', 'tieng_rit'); ?>> <?php echo _t_v2('tiếng rít', 'pfeifen', 'Tinnitus (whistling)'); ?></label>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;"><?php echo _t_v2('Từ khi nào:', 'seit wann:', 'Since when:'); ?> <input type="text" name="exam[akt_path][utai][tu_khi_nao]" value="<?php echo get_v('exam[akt_path][utai][tu_khi_nao]'); ?>"></div>
                                    <div style="display: flex; gap: 1rem; margin-bottom: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][utai][donvi][]" value="ngay" <?php echo checked_v('exam[akt_path][utai][donvi]', 'ngay'); ?>> <?php echo _t_v2('ngày', 'Tage', 'Days'); ?></label> <label><input type="checkbox" name="exam[akt_path][utai][donvi][]" value="tuan" <?php echo checked_v('exam[akt_path][utai][donvi]', 'tuan'); ?>> <?php echo _t_v2('tuần', 'Wochen', 'Weeks'); ?></label> <label><input type="checkbox" name="exam[akt_path][utai][donvi][]" value="thang" <?php echo checked_v('exam[akt_path][utai][donvi]', 'thang'); ?>> <?php echo _t_v2('tháng', 'Monate', 'Months'); ?></label> <label><input type="checkbox" name="exam[akt_path][utai][donvi][]" value="nam" <?php echo checked_v('exam[akt_path][utai][donvi]', 'nam'); ?>> <?php echo _t_v2('năm', 'Jahre', 'Years'); ?></label></div>
                                    <div style="display: flex; flex-direction: column;">
                                        <label><input type="checkbox" name="exam[akt_path][utai][trangthai][]" value="lien_tuc" <?php echo checked_v('exam[akt_path][utai][trangthai]', 'lien_tuc'); ?>> <?php echo _t_v2('liên tục', 'permanent', 'Permanent'); ?></label>
                                        <label><input type="checkbox" name="exam[akt_path][utai][trangthai][]" value="luc_nhieu_luc_it" <?php echo checked_v('exam[akt_path][utai][trangthai]', 'luc_nhieu_luc_it'); ?>> <?php echo _t_v2('lúc nhiều lúc ít', 'intermittierend / wechselhaft', 'Intermittent / Variable'); ?></label>
                                        <label><input type="checkbox" name="exam[akt_path][utai][trangthai][]" value="co_luc_het_han" <?php echo checked_v('exam[akt_path][utai][trangthai]', 'co_luc_het_han'); ?>> <?php echo _t_v2('có lúc hết hẳn', 'phasenweise beschwerdefrei', 'Intermittently pain-free'); ?></label>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td><strong><label><input type="checkbox" name="exam[akt_path][tmj][has]" value="1" <?php echo checked_v('exam[akt_path][tmj][has]', '1'); ?>> <?php echo _t_v2('Khớp thái dương hàm (TMJ)', 'Kiefergelenk', 'Temporomandibular Joint (TMJ)'); ?></label></strong></td>
                                <td>
                                    <div style="display: grid; grid-template-columns: max-content auto auto; gap: 0.6rem 1rem; align-items: center;">
                                        <label style="margin-bottom: 0;"><input type="checkbox" name="exam[akt_path][tmj][trieuchung][]" value="luc_cuc" <?php echo checked_v('exam[akt_path][tmj][trieuchung]', 'luc_cuc'); ?>> <?php echo _t_v2('lục cục', 'Knacken', 'Cracking'); ?></label>
                                        <label style="margin-bottom: 0;"><input type="checkbox" name="exam[akt_path][tmj][trieuchung][]" value="luc_cuc_p" <?php echo checked_v('exam[akt_path][tmj][trieuchung]', 'luc_cuc_p'); ?>> <?php echo _t_v2('Phải', 're', 'R'); ?></label>
                                        <label style="margin-bottom: 0;"><input type="checkbox" name="exam[akt_path][tmj][trieuchung][]" value="luc_cuc_t" <?php echo checked_v('exam[akt_path][tmj][trieuchung]', 'luc_cuc_t'); ?>> <?php echo _t_v2('Trái', 'li', 'L'); ?></label>

                                        <label style="margin-bottom: 0;"><input type="checkbox" name="exam[akt_path][tmj][trieuchung][]" value="dau" <?php echo checked_v('exam[akt_path][tmj][trieuchung]', 'dau'); ?>> <?php echo _t_v2('đau', 'Schmerzen', 'Pain'); ?></label>
                                        <label style="margin-bottom: 0;"><input type="checkbox" name="exam[akt_path][tmj][trieuchung][]" value="dau_p" <?php echo checked_v('exam[akt_path][tmj][trieuchung]', 'dau_p'); ?>> <?php echo _t_v2('Phải', 're', 'R'); ?></label>
                                        <label style="margin-bottom: 0;"><input type="checkbox" name="exam[akt_path][tmj][trieuchung][]" value="dau_t" <?php echo checked_v('exam[akt_path][tmj][trieuchung]', 'dau_t'); ?>> <?php echo _t_v2('Trái', 'li', 'L'); ?></label>

                                        <label style="margin-bottom: 0;"><input type="checkbox" name="exam[akt_path][tmj][trieuchung][]" value="hc" <?php echo checked_v('exam[akt_path][tmj][trieuchung]', 'hc'); ?>> <?php echo _t_v2('hạn chế vận động', 'eingeschränkt', 'Restricted Movement'); ?></label>
                                        <label style="margin-bottom: 0;"><input type="checkbox" name="exam[akt_path][tmj][trieuchung][]" value="hc_p" <?php echo checked_v('exam[akt_path][tmj][trieuchung]', 'hc_p'); ?>> <?php echo _t_v2('Phải', 're', 'R'); ?></label>
                                        <label style="margin-bottom: 0;"><input type="checkbox" name="exam[akt_path][tmj][trieuchung][]" value="hc_t" <?php echo checked_v('exam[akt_path][tmj][trieuchung]', 'hc_t'); ?>> <?php echo _t_v2('Trái', 'li', 'L'); ?></label>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;"><?php echo _t_v2('Từ khi nào:', 'seit wann:', 'Since when:'); ?> <input type="text" name="exam[akt_path][tmj][tu_khi_nao]" value="<?php echo get_v('exam[akt_path][tmj][tu_khi_nao]'); ?>"></div>
                                    <div style="display: flex; flex-direction: column;">
                                        <label><input type="checkbox" name="exam[akt_path][tmj][trangthai][]" value="dang_nieng" <?php echo checked_v('exam[akt_path][tmj][trangthai]', 'dang_nieng'); ?>> <?php echo _t_v2('đang niềng răng', 'hat Zahnspange', 'Has Braces'); ?></label>
                                        <label><input type="checkbox" name="exam[akt_path][tmj][trangthai][]" value="da_tung" <?php echo checked_v('exam[akt_path][tmj][trangthai]', 'da_tung'); ?>> <?php echo _t_v2('đã từng niềng răng', 'hatte Zahnspange', 'Had Braces'); ?></label>
                                        <div style="display: flex; gap: 1rem;"><label><input type="checkbox" name="exam[akt_path][tmj][trangthai][]" value="cau_rang" <?php echo checked_v('exam[akt_path][tmj][trangthai]', 'cau_rang'); ?>> <?php echo _t_v2('có cầu răng', 'hat Brücken', 'Has Bridges'); ?></label> <label><input type="checkbox" name="exam[akt_path][tmj][trangthai][]" value="mao_rang" <?php echo checked_v('exam[akt_path][tmj][trangthai]', 'mao_rang'); ?>> <?php echo _t_v2('có mão răng', 'hat Kronen', 'Has Crowns'); ?></label></div>
                                        <label><input type="checkbox" name="exam[akt_path][tmj][trangthai][]" value="cay_ghep" <?php echo checked_v('exam[akt_path][tmj][trangthai]', 'cay_ghep'); ?>> <?php echo _t_v2('có cấy ghép răng', 'hat Implantate', 'Has Implants'); ?></label>
                                        <label><input type="checkbox" name="exam[akt_path][tmj][trangthai][]" value="dieu_tri_tuy" <?php echo checked_v('exam[akt_path][tmj][trangthai]', 'dieu_tri_tuy'); ?>> <?php echo _t_v2('có răng đã điều trị tủy', 'hat wurzelbehandelte Zähne', 'Has Root Canal Treated Teeth'); ?></label>
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
                                <th style="width: 20%;"><?php echo _t_v2('Vùng (Bereich)', 'Bereich', 'Area'); ?></th>
                                <th style="width: 40%;"><?php echo _t_v2('Triệu chứng & chi tiết (Symptome & Details)', 'Symptome & Details', 'Symptoms & Details'); ?></th>
                                <th style="width: 40%;"><?php echo _t_v2('Trạng thái / Lan (Status / Ausstrahlung)', 'Status / Ausstrahlung', 'Status / Radiation'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <strong>HWS – Cột sống cổ</strong>
                                    <div style="font-size: 0.8rem; color: #64748b; font-style: italic;">Halswirbelsäule</div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;"><label><input type="checkbox" name="exam[akt_path][hws][trieuchung][]" value="thang_tren" <?php echo checked_v('exam[akt_path][hws][trieuchung]', 'thang_tren'); ?>> <?php echo _t_v2('phần trên', 'obere', 'Upper'); ?></label> <input type="number" min="1" max="10" name="exam[akt_path][hws][thang_tren_val]" placeholder="1-10" style="width: 85px;" value="<?php echo get_v('akt_path.hws.thang_tren_val'); ?>"></div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;"><label><input type="checkbox" name="exam[akt_path][hws][trieuchung][]" value="thang_giua" <?php echo checked_v('exam[akt_path][hws][trieuchung]', 'thang_giua'); ?>> <?php echo _t_v2('phần giữa', 'mittlere', 'Middle'); ?></label> <input type="number" min="1" max="10" name="exam[akt_path][hws][thang_giua_val]" placeholder="1-10" style="width: 85px;" value="<?php echo get_v('akt_path.hws.thang_giua_val'); ?>"></div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;"><label><input type="checkbox" name="exam[akt_path][hws][trieuchung][]" value="thang_duoi" <?php echo checked_v('exam[akt_path][hws][trieuchung]', 'thang_duoi'); ?>> <?php echo _t_v2('phần dưới', 'untere', 'Lower'); ?></label> <input type="number" min="1" max="10" name="exam[akt_path][hws][thang_duoi_val]" placeholder="1-10" style="width: 85px;" value="<?php echo get_v('akt_path.hws.thang_duoi_val'); ?>"></div>
                                        <div style="margin-top: 0.5rem; display: flex; gap: 1rem;"><label><input type="checkbox" name="exam[akt_path][hws][trieuchung][]" value="p" <?php echo checked_v('exam[akt_path][hws][trieuchung]', 'p'); ?>> <?php echo _t_v2('Phải', 're', 'R'); ?></label> <label><input type="checkbox" name="exam[akt_path][hws][trieuchung][]" value="t" <?php echo checked_v('exam[akt_path][hws][trieuchung]', 't'); ?>> <?php echo _t_v2('Trái', 'li', 'L'); ?></label></div>
                                        <div style="margin-top: 0.5rem; display: flex; gap: 1rem; align-items: center;"><?php echo _t_v2('khi:', 'bei:', 'During:'); ?> <label><input type="checkbox" name="exam[akt_path][hws][trieuchung][]" value="khi_vd" <?php echo checked_v('exam[akt_path][hws][trieuchung]', 'khi_vd'); ?>> <?php echo _t_v2('vận động', 'Bewegung', 'Movement'); ?></label> <label><input type="checkbox" name="exam[akt_path][hws][trieuchung][]" value="khi_nghi" <?php echo checked_v('exam[akt_path][hws][trieuchung]', 'khi_nghi'); ?>> <?php echo _t_v2('nghỉ', 'Ruhe', 'Rest'); ?></label></div>
                                        <div style="display: grid; grid-template-columns: max-content max-content max-content; gap: 0.5rem 1rem; margin-top: 0.5rem; margin-bottom: 0.5rem;">
    <label style="margin-bottom:0;"><input type="checkbox" name="exam[akt_path][hws][trieuchung][]" value="dau_nhoi" <?php echo checked_v('exam[akt_path][hws][trieuchung]', 'dau_nhoi'); ?>> <?php echo _t_v2('đau nhói', 'stechend', 'Sharp'); ?></label>
    <label style="margin-bottom:0;"><input type="checkbox" name="exam[akt_path][hws][trieuchung][]" value="dau_am_i" <?php echo checked_v('exam[akt_path][hws][trieuchung]', 'dau_am_i'); ?>> <?php echo _t_v2('đau âm ỉ', 'dumpf', 'Dull'); ?></label>
    <label style="margin-bottom:0;"><input type="checkbox" name="exam[akt_path][hws][trieuchung][]" value="te_bi" <?php echo checked_v('exam[akt_path][hws][trieuchung]', 'te_bi'); ?>> <?php echo _t_v2('tê bì', 'Taubheit', 'Numbness'); ?></label>
    <label style="margin-bottom:0;"><input type="checkbox" name="exam[akt_path][hws][trieuchung][]" value="yeu_co" <?php echo checked_v('exam[akt_path][hws][trieuchung]', 'yeu_co'); ?>> <?php echo _t_v2('yếu cơ', 'Schwäche', 'Weakness'); ?></label>
    <label style="margin-bottom:0;"><input type="checkbox" name="exam[akt_path][hws][trieuchung][]" value="liet" <?php echo checked_v('exam[akt_path][hws][trieuchung]', 'liet'); ?>> <?php echo _t_v2('liệt', 'Lähmung', 'Paralysis'); ?></label>
</div>
                                        <div style="margin-top: 0.5rem; display: flex; gap: 1rem; align-items: center; font-style: italic;"><?php echo _t_v2('Hạn chế vận động về phía:', 'Bewegung eingeschränkt nach:', 'Movement restricted to:'); ?></div>
                                        <div style="display: flex; gap: 1rem;"><label><input type="checkbox" name="exam[akt_path][hws][trieuchung][]" value="hc_p" <?php echo checked_v('exam[akt_path][hws][trieuchung]', 'hc_p'); ?>> <?php echo _t_v2('Phải', 're', 'R'); ?></label> <label><input type="checkbox" name="exam[akt_path][hws][trieuchung][]" value="hc_t" <?php echo checked_v('exam[akt_path][hws][trieuchung]', 'hc_t'); ?>> <?php echo _t_v2('Trái', 'li', 'L'); ?></label></div>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][hws][trangthai][]" value="cap_tinh" <?php echo checked_v('exam[akt_path][hws][trangthai]', 'cap_tinh'); ?>> <?php echo _t_v2('cấp tính [từ', 'akut seit', 'Acute since'); ?></label> <input type="text" name="exam[akt_path][hws][cap_tinh_tu]" style="width: 150px;" value="<?php echo get_v('exam[akt_path][hws][cap_tinh_tu]'); ?>">]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][hws][trangthai][]" value="man_tinh" <?php echo checked_v('exam[akt_path][hws][trangthai]', 'man_tinh'); ?>> <?php echo _t_v2('mạn tính [từ', 'chronisch seit', 'Chronic since'); ?></label> <input type="text" name="exam[akt_path][hws][man_tinh_tu]" style="width: 150px;" value="<?php echo get_v('exam[akt_path][hws][man_tinh_tu]'); ?>">]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][hws][trangthai][]" value="tai_phat" <?php echo checked_v('exam[akt_path][hws][trangthai]', 'tai_phat'); ?>> <?php echo _t_v2('tái phát [từ', 'wiederkehrend seit', 'Recurrent since'); ?></label> <input type="text" name="exam[akt_path][hws][tai_phat_tu]" style="width: 150px;" value="<?php echo get_v('exam[akt_path][hws][tai_phat_tu]'); ?>">]</div>
                                        <div style="margin-top: 0.5rem; font-weight: 700;"><?php echo _t_v2('Đau lan:', 'Ausstrahlung:', 'Radiation:'); ?> <label><input type="checkbox" name="exam[akt_path][hws][lan][]" value="lan_p" <?php echo checked_v('exam[akt_path][hws][lan]', 'lan_p'); ?>> <?php echo _t_v2('Phải', 're', 'R'); ?></label> <label><input type="checkbox" name="exam[akt_path][hws][lan][]" value="lan_t" <?php echo checked_v('exam[akt_path][hws][lan]', 'lan_t'); ?>> <?php echo _t_v2('Trái', 'li', 'L'); ?></label></div>
                                        <div style="margin-left: 0.5rem;">– <label><input type="checkbox" name="exam[akt_path][hws][lan][]" value="lan_canh_tay" <?php echo checked_v('exam[akt_path][hws][lan]', 'lan_canh_tay'); ?>> <?php echo _t_v2('đến cánh tay trên', 'bis Oberarm', 'To upper arm'); ?></label></div>
                                        <div style="margin-left: 0.5rem;">– <label><input type="checkbox" name="exam[akt_path][hws][lan][]" value="lan_ban_tay" <?php echo checked_v('exam[akt_path][hws][lan]', 'lan_ban_tay'); ?>> <?php echo _t_v2('đến bàn tay', 'bis Hand', 'To hand'); ?></label></div>
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
                                <th style="width: 20%;"><?php echo _t_v2('Vùng (Bereich)', 'Bereich', 'Area'); ?></th>
                                <th style="width: 40%;"><?php echo _t_v2('Triệu chứng & chi tiết (Symptome & Details)', 'Symptome & Details', 'Symptoms & Details'); ?></th>
                                <th style="width: 40%;"><?php echo _t_v2('Trạng thái / Lan (Status / Ausstrahlung)', 'Status / Ausstrahlung', 'Status / Radiation'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <strong>BWS – Cột sống ngực</strong>
                                    <div style="font-size: 0.8rem; color: #64748b; font-style: italic;">Brustwirbelsäule</div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;"><label><input type="checkbox" name="exam[akt_path][bws][trieuchung][]" value="thang_tren" <?php echo checked_v('exam[akt_path][bws][trieuchung]', 'thang_tren'); ?>> <?php echo _t_v2('phần trên', 'obere', 'Upper'); ?></label> <input type="number" min="1" max="10" name="exam[akt_path][bws][thang_tren_val]" placeholder="1-10" style="width: 85px;" value="<?php echo get_v('akt_path.bws.thang_tren_val'); ?>"></div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;"><label><input type="checkbox" name="exam[akt_path][bws][trieuchung][]" value="thang_giua" <?php echo checked_v('exam[akt_path][bws][trieuchung]', 'thang_giua'); ?>> <?php echo _t_v2('phần giữa', 'mittlere', 'Middle'); ?></label> <input type="number" min="1" max="10" name="exam[akt_path][bws][thang_giua_val]" placeholder="1-10" style="width: 85px;" value="<?php echo get_v('akt_path.bws.thang_giua_val'); ?>"></div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;"><label><input type="checkbox" name="exam[akt_path][bws][trieuchung][]" value="thang_duoi" <?php echo checked_v('exam[akt_path][bws][trieuchung]', 'thang_duoi'); ?>> <?php echo _t_v2('phần dưới', 'untere', 'Lower'); ?></label> <input type="number" min="1" max="10" name="exam[akt_path][bws][thang_duoi_val]" placeholder="1-10" style="width: 85px;" value="<?php echo get_v('akt_path.bws.thang_duoi_val'); ?>"></div>
                                        <div style="margin-top: 0.5rem; display: flex; gap: 1rem;"><label><input type="checkbox" name="exam[akt_path][bws][trieuchung][]" value="p" <?php echo checked_v('exam[akt_path][bws][trieuchung]', 'p'); ?>> <?php echo _t_v2('Phải', 're', 'R'); ?></label> <label><input type="checkbox" name="exam[akt_path][bws][trieuchung][]" value="t" <?php echo checked_v('exam[akt_path][bws][trieuchung]', 't'); ?>> <?php echo _t_v2('Trái', 'li', 'L'); ?></label></div>
                                        <div style="margin-top: 0.5rem; display: flex; gap: 1rem; align-items: center;"><?php echo _t_v2('khi:', 'bei:', 'During:'); ?> <label><input type="checkbox" name="exam[akt_path][bws][trieuchung][]" value="khi_vd" <?php echo checked_v('exam[akt_path][bws][trieuchung]', 'khi_vd'); ?>> <?php echo _t_v2('vận động', 'Bewegung', 'Movement'); ?></label> <label><input type="checkbox" name="exam[akt_path][bws][trieuchung][]" value="khi_nghi" <?php echo checked_v('exam[akt_path][bws][trieuchung]', 'khi_nghi'); ?>> <?php echo _t_v2('nghỉ', 'Ruhe', 'Rest'); ?></label></div>
                                        <div style="display: grid; grid-template-columns: max-content max-content max-content; gap: 0.5rem 1rem; margin-top: 0.5rem; margin-bottom: 0.5rem;">
    <label style="margin-bottom:0;"><input type="checkbox" name="exam[akt_path][bws][trieuchung][]" value="dau_nhoi" <?php echo checked_v('exam[akt_path][bws][trieuchung]', 'dau_nhoi'); ?>> <?php echo _t_v2('đau nhói', 'stechend', 'Sharp'); ?></label>
    <label style="margin-bottom:0;"><input type="checkbox" name="exam[akt_path][bws][trieuchung][]" value="dau_am_i" <?php echo checked_v('exam[akt_path][bws][trieuchung]', 'dau_am_i'); ?>> <?php echo _t_v2('đau âm ỉ', 'dumpf', 'Dull'); ?></label>
    <label style="margin-bottom:0;"><input type="checkbox" name="exam[akt_path][bws][trieuchung][]" value="te_bi" <?php echo checked_v('exam[akt_path][bws][trieuchung]', 'te_bi'); ?>> <?php echo _t_v2('tê bì', 'Taubheit', 'Numbness'); ?></label>
    <label style="margin-bottom:0;"><input type="checkbox" name="exam[akt_path][bws][trieuchung][]" value="yeu_co" <?php echo checked_v('exam[akt_path][bws][trieuchung]', 'yeu_co'); ?>> <?php echo _t_v2('yếu cơ', 'Schwäche', 'Weakness'); ?></label>
    <label style="margin-bottom:0;"><input type="checkbox" name="exam[akt_path][bws][trieuchung][]" value="liet" <?php echo checked_v('exam[akt_path][bws][trieuchung]', 'liet'); ?>> <?php echo _t_v2('liệt', 'Lähmung', 'Paralysis'); ?></label>
</div>
                                        <div style="margin-top: 0.5rem; display: flex; gap: 1rem; align-items: center; font-style: italic;"><?php echo _t_v2('Hạn chế vận động về phía:', 'Bewegung eingeschränkt nach:', 'Movement restricted to:'); ?></div>
                                        <div style="display: flex; gap: 1rem;"><label><input type="checkbox" name="exam[akt_path][bws][trieuchung][]" value="hc_p" <?php echo checked_v('exam[akt_path][bws][trieuchung]', 'hc_p'); ?>> <?php echo _t_v2('Phải', 're', 'R'); ?></label> <label><input type="checkbox" name="exam[akt_path][bws][trieuchung][]" value="hc_t" <?php echo checked_v('exam[akt_path][bws][trieuchung]', 'hc_t'); ?>> <?php echo _t_v2('Trái', 'li', 'L'); ?></label></div>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][bws][trangthai][]" value="cap_tinh" <?php echo checked_v('exam[akt_path][bws][trangthai]', 'cap_tinh'); ?>> <?php echo _t_v2('cấp tính [từ', 'akut seit', 'Acute since'); ?></label> <input type="text" name="exam[akt_path][bws][cap_tinh_tu]" style="width: 150px;" value="<?php echo get_v('exam[akt_path][bws][cap_tinh_tu]'); ?>">]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][bws][trangthai][]" value="man_tinh" <?php echo checked_v('exam[akt_path][bws][trangthai]', 'man_tinh'); ?>> <?php echo _t_v2('mạn tính [từ', 'chronisch seit', 'Chronic since'); ?></label> <input type="text" name="exam[akt_path][bws][man_tinh_tu]" style="width: 150px;" value="<?php echo get_v('exam[akt_path][bws][man_tinh_tu]'); ?>">]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][bws][trangthai][]" value="tai_phat" <?php echo checked_v('exam[akt_path][bws][trangthai]', 'tai_phat'); ?>> <?php echo _t_v2('tái phát [từ', 'wiederkehrend seit', 'Recurrent since'); ?></label> <input type="text" name="exam[akt_path][bws][tai_phat_tu]" style="width: 150px;" value="<?php echo get_v('exam[akt_path][bws][tai_phat_tu]'); ?>">]</div>
                                        <div style="margin-top: 0.5rem; font-weight: 700;"><?php echo _t_v2('Đau lan:', 'Ausstrahlung:', 'Radiation:'); ?> <label><input type="checkbox" name="exam[akt_path][bws][lan][]" value="lan_p" <?php echo checked_v('exam[akt_path][bws][lan]', 'lan_p'); ?>> <?php echo _t_v2('Phải', 're', 'R'); ?></label> <label><input type="checkbox" name="exam[akt_path][bws][lan][]" value="lan_t" <?php echo checked_v('exam[akt_path][bws][lan]', 'lan_t'); ?>> <?php echo _t_v2('Trái', 'li', 'L'); ?></label></div>
                                        <div style="margin-left: 0.5rem;">– <label><input type="checkbox" name="exam[akt_path][bws][lan][]" value="lan_canh_tay" <?php echo checked_v('exam[akt_path][bws][lan]', 'lan_canh_tay'); ?>> <?php echo _t_v2('đến cánh tay trên', 'bis Oberarm', 'To upper arm'); ?></label></div>
                                        <div style="margin-left: 0.5rem;">– <label><input type="checkbox" name="exam[akt_path][bws][lan][]" value="lan_ban_tay" <?php echo checked_v('exam[akt_path][bws][lan]', 'lan_ban_tay'); ?>> <?php echo _t_v2('đến bàn tay', 'bis Hand', 'To hand'); ?></label></div>
                                        <div style="margin-top: 0.5rem; font-weight: 700;"><?php echo _t_v2('Đau TK liên sườn:', 'ICN:', 'Intercostal Neuralgia (ICN):'); ?> <label><input type="checkbox" name="exam[akt_path][bws][lan][]" value="icn_p" <?php echo checked_v('exam[akt_path][bws][lan]', 'icn_p'); ?>> <?php echo _t_v2('Phải', 're', 'R'); ?></label> <label><input type="checkbox" name="exam[akt_path][bws][lan][]" value="icn_t" <?php echo checked_v('exam[akt_path][bws][lan]', 'icn_t'); ?>> <?php echo _t_v2('Trái', 'li', 'L'); ?></label></div>
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
                                <th style="width: 20%;"><?php echo _t_v2('Vùng (Bereich)', 'Bereich', 'Area'); ?></th>
                                <th style="width: 40%;"><?php echo _t_v2('Triệu chứng & chi tiết (Symptome & Details)', 'Symptome & Details', 'Symptoms & Details'); ?></th>
                                <th style="width: 40%;"><?php echo _t_v2('Trạng thái / Lan (Status / Ausstrahlung)', 'Status / Ausstrahlung', 'Status / Radiation'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <strong>LWS – Cột sống thắt lưng</strong>
                                    <div style="font-size: 0.8rem; color: #64748b; font-style: italic;">Lendenwirbelsäule</div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;"><label><input type="checkbox" name="exam[akt_path][lws][trieuchung][]" value="thang_tren" <?php echo checked_v('exam[akt_path][lws][trieuchung]', 'thang_tren'); ?>> <?php echo _t_v2('phần trên', 'obere', 'Upper'); ?></label> <input type="number" min="1" max="10" name="exam[akt_path][lws][thang_tren_val]" placeholder="1-10" style="width: 85px;" value="<?php echo get_v('akt_path.lws.thang_tren_val'); ?>"></div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;"><label><input type="checkbox" name="exam[akt_path][lws][trieuchung][]" value="thang_giua" <?php echo checked_v('exam[akt_path][lws][trieuchung]', 'thang_giua'); ?>> <?php echo _t_v2('phần giữa', 'mittlere', 'Middle'); ?></label> <input type="number" min="1" max="10" name="exam[akt_path][lws][thang_giua_val]" placeholder="1-10" style="width: 85px;" value="<?php echo get_v('akt_path.lws.thang_giua_val'); ?>"></div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;"><label><input type="checkbox" name="exam[akt_path][lws][trieuchung][]" value="thang_duoi" <?php echo checked_v('exam[akt_path][lws][trieuchung]', 'thang_duoi'); ?>> <?php echo _t_v2('phần dưới', 'untere', 'Lower'); ?></label> <input type="number" min="1" max="10" name="exam[akt_path][lws][thang_duoi_val]" placeholder="1-10" style="width: 85px;" value="<?php echo get_v('akt_path.lws.thang_duoi_val'); ?>"></div>
                                        <div style="margin-top: 0.5rem; display: flex; gap: 1rem;"><label><input type="checkbox" name="exam[akt_path][lws][trieuchung][]" value="p" <?php echo checked_v('exam[akt_path][lws][trieuchung]', 'p'); ?>> <?php echo _t_v2('Phải', 're', 'R'); ?></label> <label><input type="checkbox" name="exam[akt_path][lws][trieuchung][]" value="t" <?php echo checked_v('exam[akt_path][lws][trieuchung]', 't'); ?>> <?php echo _t_v2('Trái', 'li', 'L'); ?></label></div>
                                        <div style="margin-top: 0.5rem; display: flex; gap: 1rem; align-items: center;"><?php echo _t_v2('khi:', 'bei:', 'During:'); ?> <label><input type="checkbox" name="exam[akt_path][lws][trieuchung][]" value="khi_vd" <?php echo checked_v('exam[akt_path][lws][trieuchung]', 'khi_vd'); ?>> <?php echo _t_v2('vận động', 'Bewegung', 'Movement'); ?></label> <label><input type="checkbox" name="exam[akt_path][lws][trieuchung][]" value="khi_nghi" <?php echo checked_v('exam[akt_path][lws][trieuchung]', 'khi_nghi'); ?>> <?php echo _t_v2('nghỉ', 'Ruhe', 'Rest'); ?></label></div>
                                        <div style="display: grid; grid-template-columns: max-content max-content max-content; gap: 0.5rem 1rem; margin-top: 0.5rem; margin-bottom: 0.5rem;">
    <label style="margin-bottom:0;"><input type="checkbox" name="exam[akt_path][lws][trieuchung][]" value="dau_nhoi" <?php echo checked_v('exam[akt_path][lws][trieuchung]', 'dau_nhoi'); ?>> <?php echo _t_v2('đau nhói', 'stechend', 'Sharp'); ?></label>
    <label style="margin-bottom:0;"><input type="checkbox" name="exam[akt_path][lws][trieuchung][]" value="dau_am_i" <?php echo checked_v('exam[akt_path][lws][trieuchung]', 'dau_am_i'); ?>> <?php echo _t_v2('đau âm ỉ', 'dumpf', 'Dull'); ?></label>
    <label style="margin-bottom:0;"><input type="checkbox" name="exam[akt_path][lws][trieuchung][]" value="te_bi" <?php echo checked_v('exam[akt_path][lws][trieuchung]', 'te_bi'); ?>> <?php echo _t_v2('tê bì', 'Taubheit', 'Numbness'); ?></label>
    <label style="margin-bottom:0;"><input type="checkbox" name="exam[akt_path][lws][trieuchung][]" value="yeu_co" <?php echo checked_v('exam[akt_path][lws][trieuchung]', 'yeu_co'); ?>> <?php echo _t_v2('yếu cơ', 'Schwäche', 'Weakness'); ?></label>
    <label style="margin-bottom:0;"><input type="checkbox" name="exam[akt_path][lws][trieuchung][]" value="liet" <?php echo checked_v('exam[akt_path][lws][trieuchung]', 'liet'); ?>> <?php echo _t_v2('liệt', 'Lähmung', 'Paralysis'); ?></label>
</div>
                                        <div style="margin-top: 0.5rem; display: flex; gap: 1rem; align-items: center; font-style: italic;"><?php echo _t_v2('Hạn chế vận động về phía:', 'Bewegung eingeschränkt nach:', 'Movement restricted to:'); ?></div>
                                        <div style="display: flex; gap: 1rem;"><label><input type="checkbox" name="exam[akt_path][lws][trieuchung][]" value="hc_p" <?php echo checked_v('exam[akt_path][lws][trieuchung]', 'hc_p'); ?>> <?php echo _t_v2('P', 're', 'R'); ?></label> <label><input type="checkbox" name="exam[akt_path][lws][trieuchung][]" value="hc_t" <?php echo checked_v('exam[akt_path][lws][trieuchung]', 'hc_t'); ?>> <?php echo _t_v2('T', 'li', 'L'); ?></label></div>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <div style="font-weight: 700;"><?php echo _t_v2('Đau lan:', 'Ausstrahlung:', 'Radiation:'); ?> <label><input type="checkbox" name="exam[akt_path][lws][lan][]" value="lan_p" <?php echo checked_v('exam[akt_path][lws][lan]', 'lan_p'); ?>> <?php echo _t_v2('Phải', 're', 'R'); ?></label> <label><input type="checkbox" name="exam[akt_path][lws][lan][]" value="lan_t" <?php echo checked_v('exam[akt_path][lws][lan]', 'lan_t'); ?>> <?php echo _t_v2('Trái', 'li', 'L'); ?></label></div>
                                        <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-left: 0.5rem;">
                                            <label><input type="checkbox" name="exam[akt_path][lws][lan][]" value="lan_ben" <?php echo checked_v('exam[akt_path][lws][lan]', 'lan_ben'); ?>> <?php echo _t_v2('bên hông', 'Leiste/Flanke', 'Groin/Flank'); ?></label>
                                            <label><input type="checkbox" name="exam[akt_path][lws][lan][]" value="lan_den_goi" <?php echo checked_v('exam[akt_path][lws][lan]', 'lan_den_goi'); ?>> <?php echo _t_v2('đến gối', 'bis Knie', 'To knee'); ?></label>
                                            <label><input type="checkbox" name="exam[akt_path][lws][lan][]" value="lan_phia_sau" <?php echo checked_v('exam[akt_path][lws][lan]', 'lan_phia_sau'); ?>> <?php echo _t_v2('phía sau', 'dorsal/hinten', 'Dorsal/Back'); ?></label>
                                            <label><input type="checkbox" name="exam[akt_path][lws][lan][]" value="lan_den_ban_chan" <?php echo checked_v('exam[akt_path][lws][lan]', 'lan_den_ban_chan'); ?>> <?php echo _t_v2('đến bàn chân', 'bis Fuß', 'To foot'); ?></label>
                                            <label><input type="checkbox" name="exam[akt_path][lws][lan][]" value="lan_phia_trong" <?php echo checked_v('exam[akt_path][lws][lan]', 'lan_phia_trong'); ?>> <?php echo _t_v2('phía trong', 'medial/innen', 'Medial/Inside'); ?></label>
                                            <label><input type="checkbox" name="exam[akt_path][lws][lan][]" value="lan_phia_truoc" <?php echo checked_v('exam[akt_path][lws][lan]', 'lan_phia_truoc'); ?>> <?php echo _t_v2('phía trước', 'ventral/vorne', 'Ventral/Front'); ?></label>
                                        </div>
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
                                <th style="width: 20%;"><?php echo _t_v2('Vùng (Bereich)', 'Bereich', 'Area'); ?></th>
                                <th style="width: 40%;"><?php echo _t_v2('Triệu chứng / Vị trí (Symptome / Ort)', 'Symptome / Ort', 'Symptoms / Location'); ?></th>
                                <th style="width: 40%;"><?php echo _t_v2('Hạn chế (Einschränkungen)', 'Einschränkungen', 'Limitations'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong><?php echo _t_v2('Vai (Schulter)', 'Schulter', 'Shoulder'); ?></strong></td>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 0.5rem;">
                                            <label><input type="checkbox" name="exam[akt_path][vai][vitri][]" value="p" <?php echo checked_v('exam[akt_path][vai][vitri]', 'p'); ?>> <?php echo _t_v2('Phải', 're', 'R'); ?></label> <label><input type="checkbox" name="exam[akt_path][vai][vitri][]" value="t" <?php echo checked_v('exam[akt_path][vai][vitri]', 't'); ?>> <?php echo _t_v2('Trái', 'li', 'L'); ?></label> 
                                            <span style="margin-left: 1rem;"><?php echo _t_v2('Thang đau:', 'Schmerzskala:', 'Pain Scale:'); ?> 1 <input type="number" min="1" max="10" name="exam[akt_path][vai][thang_dau]" value="<?php echo get_v('akt_path.vai.thang_dau'); ?>"> 10</span>
                                        </div>
                                        <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 0.5rem;">
                                            <label><input type="checkbox" name="exam[akt_path][vai][vitri][]" value="truoc" <?php echo checked_v('exam[akt_path][vai][vitri]', 'truoc'); ?>> <?php echo _t_v2('phía trước (ventral)', 'ventral', 'Ventral'); ?></label>
                                            <label><input type="checkbox" name="exam[akt_path][vai][vitri][]" value="sau" <?php echo checked_v('exam[akt_path][vai][vitri]', 'sau'); ?>> <?php echo _t_v2('phía sau (dorsal)', 'dorsal', 'Dorsal'); ?></label>
                                            <label><input type="checkbox" name="exam[akt_path][vai][vitri][]" value="ben_ngoai" <?php echo checked_v('exam[akt_path][vai][vitri]', 'ben_ngoai'); ?>> <?php echo _t_v2('phía bên ngoài (lateral)', 'lateral', 'Lateral'); ?></label>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][vai][trangthai][]" value="cap_tinh" <?php echo checked_v('exam[akt_path][vai][trangthai]', 'cap_tinh'); ?>> <?php echo _t_v2('cấp tính [từ', 'akut seit', 'Acute since'); ?></label> <input type="text" name="exam[akt_path][vai][cap_tinh_tu]" style="width: 150px;" value="<?php echo get_v('exam[akt_path][vai][cap_tinh_tu]'); ?>">]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][vai][trangthai][]" value="man_tinh" <?php echo checked_v('exam[akt_path][vai][trangthai]', 'man_tinh'); ?>> <?php echo _t_v2('mạn tính [từ', 'chronisch seit', 'Chronic since'); ?></label> <input type="text" name="exam[akt_path][vai][man_tinh_tu]" style="width: 150px;" value="<?php echo get_v('exam[akt_path][vai][man_tinh_tu]'); ?>">]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][vai][trangthai][]" value="tai_phat" <?php echo checked_v('exam[akt_path][vai][trangthai]', 'tai_phat'); ?>> <?php echo _t_v2('tái phát [từ', 'wiederkehrend seit', 'Recurrent since'); ?></label> <input type="text" name="exam[akt_path][vai][tai_phat_tu]" style="width: 150px;" value="<?php echo get_v('exam[akt_path][vai][tai_phat_tu]'); ?>">]</div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td></td>
                                <td>
                                    <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 0.5rem;">
                                        <?php echo _t_v2('khi:', 'bei:', 'During:'); ?> <label><input type="checkbox" name="exam[akt_path][vai][khi][]" value="vd" <?php echo checked_v('exam[akt_path][vai][khi]', 'vd'); ?>> <?php echo _t_v2('vận động', 'Bewegung', 'Movement'); ?></label> <label><input type="checkbox" name="exam[akt_path][vai][khi][]" value="nghi" <?php echo checked_v('exam[akt_path][vai][khi]', 'nghi'); ?>> <?php echo _t_v2('nghỉ', 'Ruhe', 'Rest'); ?></label> <label><input type="checkbox" name="exam[akt_path][vai][khi][]" value="lan" <?php echo checked_v('exam[akt_path][vai][khi]', 'lan'); ?>> <?php echo _t_v2('lan theo hướng nhất định', 'Ausstrahlung in eine bestimmte Richtung', 'Radiation in specific direction'); ?></label>
                                    </div>
                                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                                        <label><input type="checkbox" name="exam[akt_path][vai][trieuchung][]" value="dau_nhoi" <?php echo checked_v('exam[akt_path][vai][trieuchung]', 'dau_nhoi'); ?>> <?php echo _t_v2('đau nhói', 'stechend', 'Sharp'); ?></label> <label><input type="checkbox" name="exam[akt_path][vai][trieuchung][]" value="dau_am_i" <?php echo checked_v('exam[akt_path][vai][trieuchung]', 'dau_am_i'); ?>> <?php echo _t_v2('đau âm ỉ', 'dumpf', 'Dull'); ?></label> <label><input type="checkbox" name="exam[akt_path][vai][trieuchung][]" value="te_bi" <?php echo checked_v('exam[akt_path][vai][trieuchung]', 'te_bi'); ?>> <?php echo _t_v2('tê bì', 'Taubheit', 'Numbness'); ?></label> <label><input type="checkbox" name="exam[akt_path][vai][trieuchung][]" value="yeu_co" <?php echo checked_v('exam[akt_path][vai][trieuchung]', 'yeu_co'); ?>> <?php echo _t_v2('yếu cơ', 'Schwäche', 'Weakness'); ?></label> <label><input type="checkbox" name="exam[akt_path][vai][trieuchung][]" value="liet" <?php echo checked_v('exam[akt_path][vai][trieuchung]', 'liet'); ?>> <?php echo _t_v2('liệt', 'Lähmung', 'Paralysis'); ?></label>
                                    </div>
                                </td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div id="tab-obere" class="tab-content">
                    <table class="path-table">
                        <thead>
                            <tr>
                                <th style="width: 20%;"><?php echo _t_v2('Khớp / Vùng (Gelenk / Bereich)', 'Gelenk / Bereich', 'Joint / Area'); ?></th>
                                <th style="width: 40%;"><?php echo _t_v2('Triệu chứng / Định khu (Symptome / Lokalisation)', 'Symptome / Lokalisation', 'Symptoms / Localization'); ?></th>
                                <th style="width: 40%;"><?php echo _t_v2('Trạng thái (Status)', 'Status', 'Status'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <strong>Khuỷu tay</strong>
                                    <div style="font-size: 0.8rem; color: #64748b; font-style: italic;">Ellenbogen</div>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 0.5rem;">
                                        <label><input type="checkbox" name="exam[akt_path][khuyu][vitri][]" value="p" <?php echo checked_v('exam[akt_path][khuyu][vitri]', 'p'); ?>> <?php echo _t_v2('Phải', 're', 'R'); ?></label> <label><input type="checkbox" name="exam[akt_path][khuyu][vitri][]" value="t" <?php echo checked_v('exam[akt_path][khuyu][vitri]', 't'); ?>> <?php echo _t_v2('Trái', 'li', 'L'); ?></label> 
                                        <span style="margin-left: 1rem;"><?php echo _t_v2('Thang đau:', 'Schmerzskala:', 'Pain Scale:'); ?> 1 <input type="number" min="1" max="10" name="exam[akt_path][khuyu][thang_dau]" value="<?php echo get_v('akt_path.khuyu.thang_dau'); ?>"> 10</span>
                                    </div>
                                    <div style="display: flex; flex-direction: column;">
                                        <label><input type="checkbox" name="exam[akt_path][khuyu][loai][]" value="tennis" <?php echo checked_v('exam[akt_path][khuyu][loai]', 'tennis'); ?>> <?php echo _t_v2('hội chứng khuỷu tay tennis', 'Tennisarm - TA', 'Tennis Elbow (TA)'); ?></label>
                                        <label><input type="checkbox" name="exam[akt_path][khuyu][loai][]" value="golf" <?php echo checked_v('exam[akt_path][khuyu][loai]', 'golf'); ?>> <?php echo _t_v2('hội chứng khuỷu tay golf', 'Golferarm - GA', 'Golfer\'s Elbow (GA)'); ?></label>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][khuyu][trangthai][]" value="cap_tinh" <?php echo checked_v('exam[akt_path][khuyu][trangthai]', 'cap_tinh'); ?>> <?php echo _t_v2('cấp tính [từ', 'akut seit', 'Acute since'); ?></label> <input type="text" name="exam[akt_path][khuyu][cap_tinh_tu]" style="width: 150px;" value="<?php echo get_v('exam[akt_path][khuyu][cap_tinh_tu]'); ?>">]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][khuyu][trangthai][]" value="man_tinh" <?php echo checked_v('exam[akt_path][khuyu][trangthai]', 'man_tinh'); ?>> <?php echo _t_v2('mạn tính [từ', 'chronisch seit', 'Chronic since'); ?></label> <input type="text" name="exam[akt_path][khuyu][man_tinh_tu]" style="width: 150px;" value="<?php echo get_v('exam[akt_path][khuyu][man_tinh_tu]'); ?>">]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][khuyu][trangthai][]" value="tai_phat" <?php echo checked_v('exam[akt_path][khuyu][trangthai]', 'tai_phat'); ?>> <?php echo _t_v2('tái phát [từ', 'wiederkehrend seit', 'Recurrent since'); ?></label> <input type="text" name="exam[akt_path][khuyu][tai_phat_tu]" style="width: 150px;" value="<?php echo get_v('exam[akt_path][khuyu][tai_phat_tu]'); ?>">]</div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td><strong><?php echo _t_v2('Bàn tay & cổ tay', 'Hand & Handgelenk', 'Hand & Wrist'); ?></strong></td>
                                <td>
                                    <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 0.8rem;">
                                        <label><input type="checkbox" name="exam[akt_path][ban_tay][vitri][]" value="p" <?php echo checked_v('exam[akt_path][ban_tay][vitri]', 'p'); ?>> <?php echo _t_v2('Phải', 're', 'R'); ?></label> <label><input type="checkbox" name="exam[akt_path][ban_tay][vitri][]" value="t" <?php echo checked_v('exam[akt_path][ban_tay][vitri]', 't'); ?>> <?php echo _t_v2('Trái', 'li', 'L'); ?></label> 
                                        <span style="margin-left: 1rem;"><?php echo _t_v2('Thang đau:', 'Schmerzskala:', 'Pain Scale:'); ?> 1 <input type="number" min="1" max="10" name="exam[akt_path][ban_tay][thang_dau]" value="<?php echo get_v('akt_path.ban_tay.thang_dau'); ?>"> 10</span>
                                    </div>
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <div style="display: flex; align-items: center; flex-wrap: wrap;"><strong><?php echo _t_v2('Cổ tay', 'Handgelenk', 'Wrist'); ?>:</strong> &nbsp; <label><input type="checkbox" name="exam[akt_path][ban_tay][cotay][]" value="mu" <?php echo checked_v('exam[akt_path][ban_tay][cotay]', 'mu'); ?>> <?php echo _t_v2('mu (dorsal)', 'dorsal', 'Dorsal'); ?></label> <label><input type="checkbox" name="exam[akt_path][ban_tay][cotay][]" value="gan" <?php echo checked_v('exam[akt_path][ban_tay][cotay]', 'gan'); ?>> <?php echo _t_v2('gan', 'palmar', 'Palmar'); ?></label> <label><input type="checkbox" name="exam[akt_path][ban_tay][cotay][]" value="ngoai" <?php echo checked_v('exam[akt_path][ban_tay][cotay]', 'ngoai'); ?>> <?php echo _t_v2('bên ngoài', 'außen - Ulnarseite', 'Outside - Ulnar side'); ?></label> <label><input type="checkbox" name="exam[akt_path][ban_tay][cotay][]" value="trong" <?php echo checked_v('exam[akt_path][ban_tay][cotay]', 'trong'); ?>> <?php echo _t_v2('bên trong', 'innen - Daumenseite', 'Inside - Thumb side'); ?></label></div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><strong><?php echo _t_v2('Xương bàn tay', 'Metacarpalia - MTC', 'Metacarpals (MTC)'); ?>:</strong> &nbsp; <label><input type="checkbox" name="exam[akt_path][ban_tay][mtc][]" value="2" <?php echo checked_v('exam[akt_path][ban_tay][mtc]', '2'); ?>> 2</label> <label><input type="checkbox" name="exam[akt_path][ban_tay][mtc][]" value="3" <?php echo checked_v('exam[akt_path][ban_tay][mtc]', '3'); ?>> 3</label> <label><input type="checkbox" name="exam[akt_path][ban_tay][mtc][]" value="4" <?php echo checked_v('exam[akt_path][ban_tay][mtc]', '4'); ?>> 4</label> <label><input type="checkbox" name="exam[akt_path][ban_tay][mtc][]" value="5" <?php echo checked_v('exam[akt_path][ban_tay][mtc]', '5'); ?>> 5 (mặt gan - palmar)</label></div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><strong><?php echo _t_v2('Ngón cái', 'Daumen/Pollicis', 'Thumb (Pollicis)'); ?>:</strong> &nbsp; <label><input type="checkbox" name="exam[akt_path][ban_tay][pollicis][]" value="sg" <?php echo checked_v('exam[akt_path][ban_tay][pollicis]', 'sg'); ?>> <?php echo _t_v2('khớp yên', 'Sattelgelenk - SG', 'Saddle Joint - SG'); ?></label> <label><input type="checkbox" name="exam[akt_path][ban_tay][pollicis][]" value="gg" <?php echo checked_v('exam[akt_path][ban_tay][pollicis]', 'gg'); ?>> <?php echo _t_v2('khớp bàn–ngón', 'Grundgelenk - GG', 'Metacarpophalangeal Joint - MCP'); ?></label></div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><strong><?php echo _t_v2('Ngón tay', 'Finger/Digiti', 'Fingers (Digiti)'); ?>:</strong> &nbsp; <label><input type="checkbox" name="exam[akt_path][ban_tay][digiti][]" value="2" <?php echo checked_v('exam[akt_path][ban_tay][digiti]', '2'); ?>> 2</label> <label><input type="checkbox" name="exam[akt_path][ban_tay][digiti][]" value="3" <?php echo checked_v('exam[akt_path][ban_tay][digiti]', '3'); ?>> 3</label> <label><input type="checkbox" name="exam[akt_path][ban_tay][digiti][]" value="4" <?php echo checked_v('exam[akt_path][ban_tay][digiti]', '4'); ?>> 4</label> <label><input type="checkbox" name="exam[akt_path][ban_tay][digiti][]" value="5" <?php echo checked_v('exam[akt_path][ban_tay][digiti]', '5'); ?>> 5</label></div>
                                        <div><strong><?php echo _t_v2('Hội chứng ống cổ tay (Carpaltunnelsyndrom - CTS)', 'Carpaltunnelsyndrom - CTS', 'Carpal Tunnel Syndrome (CTS)'); ?></strong> <label><input type="checkbox" name="exam[akt_path][ban_tay][cts][]" value="cts" <?php echo checked_v('exam[akt_path][ban_tay][cts]', 'cts'); ?>> </label></div>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][ban_tay][trangthai][]" value="cap_tinh" <?php echo checked_v('exam[akt_path][ban_tay][trangthai]', 'cap_tinh'); ?>> <?php echo _t_v2('cấp tính [từ', 'akut seit', 'Acute since'); ?></label> <input type="text" name="exam[akt_path][ban_tay][cap_tinh_tu]" style="width: 150px;" value="<?php echo get_v('exam[akt_path][ban_tay][cap_tinh_tu]'); ?>">]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][ban_tay][trangthai][]" value="man_tinh" <?php echo checked_v('exam[akt_path][ban_tay][trangthai]', 'man_tinh'); ?>> <?php echo _t_v2('mạn tính [từ', 'chronisch seit', 'Chronic since'); ?></label> <input type="text" name="exam[akt_path][ban_tay][man_tinh_tu]" style="width: 150px;" value="<?php echo get_v('exam[akt_path][ban_tay][man_tinh_tu]'); ?>">]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][ban_tay][trangthai][]" value="tai_phat" <?php echo checked_v('exam[akt_path][ban_tay][trangthai]', 'tai_phat'); ?>> <?php echo _t_v2('tái phát [từ', 'wiederkehrend seit', 'Recurrent since'); ?></label> <input type="text" name="exam[akt_path][ban_tay][tai_phat_tu]" style="width: 150px;" value="<?php echo get_v('exam[akt_path][ban_tay][tai_phat_tu]'); ?>">]</div>
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
                                <th style="width: 20%;"><?php echo _t_v2('Vùng (Bereich)', 'Bereich', 'Area'); ?></th>
                                <th style="width: 40%;"><?php echo _t_v2('Triệu chứng & phát hiện (Symptome & Befunde)', 'Symptome & Befunde', 'Symptoms & Findings'); ?></th>
                                <th style="width: 40%;"><?php echo _t_v2('Hạn chế (Einschränkungen)', 'Einschränkungen', 'Limitations'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <strong><?php echo _t_v2('Cẳng chân (Bein)', 'Bein', 'Leg'); ?></strong>
                                    <div style="font-size: 0.8rem; color: #64748b; font-style: italic;">Bein</div>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 0.5rem;">
                                        <label><input type="checkbox" name="exam[akt_path][cangchan][vitri][]" value="p" <?php echo checked_v('exam[akt_path][cangchan][vitri]', 'p'); ?>> <?php echo _t_v2('Phải', 're', 'R'); ?></label> <label><input type="checkbox" name="exam[akt_path][cangchan][vitri][]" value="t" <?php echo checked_v('exam[akt_path][cangchan][vitri]', 't'); ?>> <?php echo _t_v2('Trái', 'li', 'L'); ?></label> 
                                        <span style="margin-left: 1rem;"><?php echo _t_v2('Thang đau:', 'Schmerzskala:', 'Pain Scale:'); ?> 1 <input type="number" min="1" max="10" name="exam[akt_path][cangchan][thang_dau]" value="<?php echo get_v('akt_path.cangchan.thang_dau'); ?>"> 10</span>
                                    </div>
                                    <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 0.5rem;">
                                        <label><input type="checkbox" name="exam[akt_path][cangchan][vitri][]" value="sau" <?php echo checked_v('exam[akt_path][cangchan][vitri]', 'sau'); ?>> <?php echo _t_v2('phía sau', 'dorsal/hinten', 'Dorsal/Back'); ?></label> <label><input type="checkbox" name="exam[akt_path][cangchan][vitri][]" value="truoc" <?php echo checked_v('exam[akt_path][cangchan][vitri]', 'truoc'); ?>> <?php echo _t_v2('phía trước', 'ventral/vorne', 'Ventral/Front'); ?></label> <label><input type="checkbox" name="exam[akt_path][cangchan][vitri][]" value="ngoai" <?php echo checked_v('exam[akt_path][cangchan][vitri]', 'ngoai'); ?>> <?php echo _t_v2('bên ngoài', 'lateral/außen', 'Lateral/Outside'); ?></label> <label><input type="checkbox" name="exam[akt_path][cangchan][vitri][]" value="trong" <?php echo checked_v('exam[akt_path][cangchan][vitri]', 'trong'); ?>> <?php echo _t_v2('bên trong', 'medial/innen', 'Medial/Inside'); ?></label>
                                    </div>
                                    <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 0.5rem;">
                                        <label><input type="checkbox" name="exam[akt_path][cangchan][khi][]" value="nghi" <?php echo checked_v('exam[akt_path][cangchan][khi]', 'nghi'); ?>> <?php echo _t_v2('khi nghỉ', 'in Ruhe', 'At rest'); ?></label> <label><input type="checkbox" name="exam[akt_path][cangchan][khi][]" value="vd" <?php echo checked_v('exam[akt_path][cangchan][khi]', 'vd'); ?>> <?php echo _t_v2('khi vận động', 'bei Bewegung', 'On movement'); ?></label>
                                    </div>
                                    <div style="margin-top: 0.5rem; font-weight: 700;"><?php echo _t_v2('Chênh lệch chiều dài chân đã biết', 'Bekannte Beinlängendifferenz', 'Known Leg Length Discrepancy'); ?>:</div>
                                    <div style="display: flex; gap: 1rem; margin-top: 0.2rem;"><?php echo _t_v2('chân ngắn', 'kurzes Bein', 'Short Leg'); ?>: <label><input type="checkbox" name="exam[akt_path][cangchan][ngan][]" value="p" <?php echo checked_v('exam[akt_path][cangchan][ngan]', 'p'); ?>> <?php echo _t_v2('P', 're', 'R'); ?></label> / <label><input type="checkbox" name="exam[akt_path][cangchan][ngan][]" value="t" <?php echo checked_v('exam[akt_path][cangchan][ngan]', 't'); ?>> <?php echo _t_v2('T', 'li', 'L'); ?></label></div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][cangchan][trangthai][]" value="cap_tinh" <?php echo checked_v('exam[akt_path][cangchan][trangthai]', 'cap_tinh'); ?>> <?php echo _t_v2('cấp tính [từ', 'akut seit', 'Acute since'); ?></label> <input type="text" name="exam[akt_path][cangchan][cap_tinh_tu]" style="width: 150px;" value="<?php echo get_v('exam[akt_path][cangchan][cap_tinh_tu]'); ?>">]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][cangchan][trangthai][]" value="man_tinh" <?php echo checked_v('exam[akt_path][cangchan][trangthai]', 'man_tinh'); ?>> <?php echo _t_v2('mạn tính [từ', 'chronisch seit', 'Chronic since'); ?></label> <input type="text" name="exam[akt_path][cangchan][man_tinh_tu]" style="width: 150px;" value="<?php echo get_v('exam[akt_path][cangchan][man_tinh_tu]'); ?>">]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][cangchan][trangthai][]" value="tai_phat" <?php echo checked_v('exam[akt_path][cangchan][trangthai]', 'tai_phat'); ?>> <?php echo _t_v2('tái phát [từ', 'wiederkehrend seit', 'Recurrent since'); ?></label> <input type="text" name="exam[akt_path][cangchan][tai_phat_tu]" style="width: 150px;" value="<?php echo get_v('exam[akt_path][cangchan][tai_phat_tu]'); ?>">]</div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <strong><?php echo _t_v2('Khớp gối (Knie)', 'Knie', 'Knee'); ?></strong>
                                    <div style="font-size: 0.8rem; color: #64748b; font-style: italic;">Knie</div>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 0.8rem;">
                                        <label><input type="checkbox" name="exam[akt_path][goi][vitri][]" value="p" <?php echo checked_v('exam[akt_path][goi][vitri]', 'p'); ?>> <?php echo _t_v2('Phải', 're', 'R'); ?></label> <label><input type="checkbox" name="exam[akt_path][goi][vitri][]" value="t" <?php echo checked_v('exam[akt_path][goi][vitri]', 't'); ?>> <?php echo _t_v2('Trái', 'li', 'L'); ?></label> 
                                    </div>
                                </td>
                                <td></td>
                            </tr>
                            <tr>
                                <td></td>
                                <td>
                                    <div style="display: flex; gap: 1rem; margin-bottom: 0.5rem;">
                                        <label><input type="checkbox" name="exam[akt_path][goi][sun][]" value="trong" <?php echo checked_v('exam[akt_path][goi][sun]', 'trong'); ?>> <?php echo _t_v2('trong', 'sụn chêm trong - Innenmeniskus', 'Medial Meniscus'); ?></label>
                                        <label><input type="checkbox" name="exam[akt_path][goi][sun][]" value="ngoai" <?php echo checked_v('exam[akt_path][goi][sun]', 'ngoai'); ?>> <?php echo _t_v2('ngoài', 'sụn chêm ngoài - Außenmeniskus', 'Lateral Meniscus'); ?></label>
                                    </div>
                                    <div style="display: flex; gap: 1rem; margin-bottom: 0.5rem;">
                                        <label><input type="checkbox" name="exam[akt_path][goi][sun][]" value="fibula" <?php echo checked_v('exam[akt_path][goi][sun]', 'fibula'); ?>> <?php echo _t_v2('xương mác (Fibula)', 'Fibula', 'Fibula'); ?></label>
                                        <label><input type="checkbox" name="exam[akt_path][goi][sun][]" value="sun_chem" <?php echo checked_v('exam[akt_path][goi][sun]', 'sun_chem'); ?>> <?php echo _t_v2('sụn chêm', 'Meniskus', 'Meniscus'); ?></label>
                                        <label><input type="checkbox" name="exam[akt_path][goi][sun][]" value="toan_bo" <?php echo checked_v('exam[akt_path][goi][sun]', 'toan_bo'); ?>> <?php echo _t_v2('toàn bộ', 'gesamt', 'Total'); ?></label>
                                        <label><input type="checkbox" name="exam[akt_path][goi][sun][]" value="phia_sau" <?php echo checked_v('exam[akt_path][goi][sun]', 'phia_sau'); ?>> <?php echo _t_v2('phía sau', 'dorsal/hinten', 'Dorsal/Back'); ?></label>
                                    </div>
                                    <div style="margin-top: 0.5rem; font-weight: 700;">Hạn chế vận động:</div>
                                    <div style="display: flex; gap: 1rem; margin-bottom: 0.5rem;">
                                        – <label><input type="checkbox" name="exam[akt_path][goi][hanche][]" value="do_dau" <?php echo checked_v('exam[akt_path][goi][hanche]', 'do_dau'); ?>> <?php echo _t_v2('do đau', 'schmerzbedingt', 'Due to pain'); ?></label>
                                        – <label><input type="checkbox" name="exam[akt_path][goi][hanche][]" value="do_ket" <?php echo checked_v('exam[akt_path][goi][hanche]', 'do_ket'); ?>> <?php echo _t_v2('do kẹt khớp', 'Gelenkblockade', 'Joint Blockage'); ?></label>
                                    </div>
                                    <div style="margin-bottom: 0.5rem; font-weight: 700;"><?php echo _t_v2('Phù nề (Ödem)', 'Ödem', 'Edema'); ?> <label><input type="checkbox" name="exam[akt_path][goi][phune]" value="1" <?php echo checked_v('exam[akt_path][goi][phune]', '1'); ?>></label></div>
                                    <div style="margin-bottom: 0.2rem; font-weight: 700;"><?php echo _t_v2('Khó khăn khi', 'Schwierigkeiten beim', 'Difficulty with'); ?>:</div>
                                    <div style="display: flex; flex-direction: column; gap: 0.3rem;">
                                        <div style="display: flex; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][goi][khokhan][]" value="len_cau_thang" <?php echo checked_v('exam[akt_path][goi][khokhan]', 'len_cau_thang'); ?>> <?php echo _t_v2('lên cầu thang', 'Treppe aufwärts', 'Stairs Up'); ?></label> / <label><input type="checkbox" name="exam[akt_path][goi][khokhan][]" value="xuong_cau_thang" <?php echo checked_v('exam[akt_path][goi][khokhan]', 'xuong_cau_thang'); ?>> <?php echo _t_v2('xuống cầu thang', 'Treppe abwärts', 'Stairs Down'); ?></label></div>
                                        <div style="display: flex; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][goi][khokhan][]" value="di" <?php echo checked_v('exam[akt_path][goi][khokhan]', 'di'); ?>> <?php echo _t_v2('đi', 'Gehen', 'Walking'); ?></label> / <label><input type="checkbox" name="exam[akt_path][goi][khokhan][]" value="dung" <?php echo checked_v('exam[akt_path][goi][khokhan]', 'dung'); ?>> <?php echo _t_v2('đứng', 'Stehen', 'Standing'); ?></label> / <label><input type="checkbox" name="exam[akt_path][goi][khokhan][]" value="ngoi" <?php echo checked_v('exam[akt_path][goi][khokhan]', 'ngoi'); ?>> <?php echo _t_v2('ngồi', 'Sitzen', 'Sitting'); ?></label></div>
                                        <div style="display: flex; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][goi][khokhan][]" value="khi_nghi" <?php echo checked_v('exam[akt_path][goi][khokhan]', 'khi_nghi'); ?>> <?php echo _t_v2('khi nghỉ', 'in Ruhe', 'At rest'); ?></label> / <label><input type="checkbox" name="exam[akt_path][goi][khokhan][]" value="khi_vd" <?php echo checked_v('exam[akt_path][goi][khokhan]', 'khi_vd'); ?>> <?php echo _t_v2('khi vận động', 'bei Bewegung', 'On movement'); ?></label></div>
                                    </div>
                                </td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div id="tab-fuss" class="tab-content">

                    <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 1rem;">
                        <strong><?php echo _t_v2('Bàn chân', 'Fuß', 'Foot'); ?>:</strong> <label><input type="checkbox" name="exam[akt_path][fuss][vitri][]" value="p" <?php echo checked_v('exam[akt_path][fuss][vitri]', 'p'); ?>> <?php echo _t_v2('Phải', 're', 'R'); ?></label> <label><input type="checkbox" name="exam[akt_path][fuss][vitri][]" value="t" <?php echo checked_v('exam[akt_path][fuss][vitri]', 't'); ?>> <?php echo _t_v2('Trái', 'li', 'L'); ?></label> 
                        <span style="margin-left: 1rem;"><?php echo _t_v2('Thang đau:', 'Schmerzskala:', 'Pain Scale:'); ?> 1 <input type="number" min="1" max="10" name="exam[akt_path][fuss][thang_dau]" value="<?php echo get_v('akt_path.fuss.thang_dau'); ?>"> 10</span>
                    </div>
                    <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px dashed #cbd5e1;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][fuss][trangthai][]" value="cap_tinh" <?php echo checked_v('exam[akt_path][fuss][trangthai]', 'cap_tinh'); ?>> <?php echo _t_v2('cấp tính [từ', 'akut seit', 'Acute since'); ?></label> <input type="text" name="exam[akt_path][fuss][cap_tinh_tu]" placeholder="" style="width: 150px;" value="<?php echo get_v('exam[akt_path][fuss][cap_tinh_tu]'); ?>">]</div>
                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][fuss][trangthai][]" value="man_tinh" <?php echo checked_v('exam[akt_path][fuss][trangthai]', 'man_tinh'); ?>> <?php echo _t_v2('mạn tính [từ', 'chronisch seit', 'Chronic since'); ?></label> <input type="text" name="exam[akt_path][fuss][man_tinh_tu]" placeholder="" style="width: 150px;" value="<?php echo get_v('exam[akt_path][fuss][man_tinh_tu]'); ?>">]</div>
                        <div style="display: flex; align-items: center; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][fuss][trangthai][]" value="tai_phat" <?php echo checked_v('exam[akt_path][fuss][trangthai]', 'tai_phat'); ?>> <?php echo _t_v2('tái phát [từ', 'wiederkehrend seit', 'Recurrent since'); ?></label> <input type="text" name="exam[akt_path][fuss][tai_phat_tu]" placeholder="" style="width: 150px;" value="<?php echo get_v('exam[akt_path][fuss][tai_phat_tu]'); ?>">]</div>
                    </div>
                    <table class="path-table">
                        <thead>
                            <tr>
                                <th style="width: 33%;"><?php echo _t_v2('Hình ảnh lâm sàng (Klinisches Bild)', 'Klinisches Bild', 'Clinical Picture'); ?></th>
                                <th style="width: 33%;"><?php echo _t_v2('Định khu & giải phẫu (Lokalisation & Anatomie)', 'Lokalisation & Anatomie', 'Localization & Anatomy'); ?></th>
                                <th style="width: 34%;"><?php echo _t_v2('Biến dạng bàn chân (Fußdeformitäten)', 'Fußdeformitäten', 'Foot Deformities'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        <strong><?php echo _t_v2('Gai gót chân (Fersensporn)', 'Fersensporn', 'Heel Spur'); ?></strong>
                                        <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;"><label><input type="checkbox" name="exam[akt_path][fuss][gai][]" value="gan" <?php echo checked_v('exam[akt_path][fuss][gai]', 'gan'); ?>> <?php echo _t_v2('mặt gan (plantar)', 'plantar', 'Plantar'); ?></label> / <label><input type="checkbox" name="exam[akt_path][fuss][gai][]" value="mu" <?php echo checked_v('exam[akt_path][fuss][gai]', 'mu'); ?>> mặt <?php echo _t_v2('mu (dorsal)', 'dorsal', 'Dorsal'); ?></label></div>
                                        
                                        <strong><?php echo _t_v2('Ngón cái vẹo ngoài (Hallux valgus)', 'Hallux valgus', 'Hallux Valgus'); ?>:</strong> <label><input type="checkbox" name="exam[akt_path][fuss][hallux][]" value="p" <?php echo checked_v('exam[akt_path][fuss][hallux]', 'p'); ?>> <?php echo _t_v2('Phải', 're', 'R'); ?></label> <label><input type="checkbox" name="exam[akt_path][fuss][hallux][]" value="t" <?php echo checked_v('exam[akt_path][fuss][hallux]', 't'); ?>> <?php echo _t_v2('Trái', 'li', 'L'); ?></label><div style="margin-bottom: 1rem;"></div>
                                        
                                        <strong><?php echo _t_v2('U thần kinh Morton (Morton Neurom)', 'Morton Neurom', 'Morton\'s Neuroma'); ?></strong>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><?php echo _t_v2('Ngón', 'Zehe', 'Toe'); ?>: <label><input type="checkbox" name="exam[akt_path][fuss][morton][]" value="1" <?php echo checked_v('exam[akt_path][fuss][morton]', '1'); ?>> 1</label> <label><input type="checkbox" name="exam[akt_path][fuss][morton][]" value="2" <?php echo checked_v('exam[akt_path][fuss][morton]', '2'); ?>> 2</label> <label><input type="checkbox" name="exam[akt_path][fuss][morton][]" value="3" <?php echo checked_v('exam[akt_path][fuss][morton]', '3'); ?>> 3</label> <label><input type="checkbox" name="exam[akt_path][fuss][morton][]" value="4" <?php echo checked_v('exam[akt_path][fuss][morton]', '4'); ?>> 4</label> <label><input type="checkbox" name="exam[akt_path][fuss][morton][]" value="5" <?php echo checked_v('exam[akt_path][fuss][morton]', '5'); ?>> 5</label></div>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        <label><input type="checkbox" name="exam[akt_path][fuss][dinhkhu][]" value="mu" <?php echo checked_v('exam[akt_path][fuss][dinhkhu]', 'mu'); ?>> mặt <?php echo _t_v2('mu (dorsal)', 'dorsal', 'Dorsal'); ?></label>
                                        <label><input type="checkbox" name="exam[akt_path][fuss][dinhkhu][]" value="gan" <?php echo checked_v('exam[akt_path][fuss][dinhkhu]', 'gan'); ?>> <?php echo _t_v2('mặt gan (plantar)', 'plantar', 'Plantar'); ?></label>
                                        <label><input type="checkbox" name="exam[akt_path][fuss][dinhkhu][]" value="gan_proximal" <?php echo checked_v('exam[akt_path][fuss][dinhkhu]', 'gan_proximal'); ?>> <?php echo _t_v2('gần (proximal)', 'proximal', 'Proximal'); ?></label>
                                        <label><input type="checkbox" name="exam[akt_path][fuss][dinhkhu][]" value="xa" <?php echo checked_v('exam[akt_path][fuss][dinhkhu]', 'xa'); ?>> <?php echo _t_v2('xa (distal)', 'distal', 'Distal'); ?></label>
                                        <label><input type="checkbox" name="exam[akt_path][fuss][dinhkhu][]" value="trong" <?php echo checked_v('exam[akt_path][fuss][dinhkhu]', 'trong'); ?>> <?php echo _t_v2('phía trong (medial)', 'medial', 'Medial'); ?></label>
                                        <label><input type="checkbox" name="exam[akt_path][fuss][dinhkhu][]" value="ngoai" <?php echo checked_v('exam[akt_path][fuss][dinhkhu]', 'ngoai'); ?>> <?php echo _t_v2('phía ngoài (lateral)', 'lateral', 'Lateral'); ?></label>
                                        <div style="display: flex; gap: 0.5rem;"><label><input type="checkbox" name="exam[akt_path][fuss][dinhkhu][]" value="nghi" <?php echo checked_v('exam[akt_path][fuss][dinhkhu]', 'nghi'); ?>> <?php echo _t_v2('khi nghỉ', 'in Ruhe', 'At rest'); ?></label> / <label><input type="checkbox" name="exam[akt_path][fuss][dinhkhu][]" value="vd" <?php echo checked_v('exam[akt_path][fuss][dinhkhu]', 'vd'); ?>> <?php echo _t_v2('khi vận động', 'bei Bewegung', 'On movement'); ?></label></div>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        <label><input type="checkbox" name="exam[akt_path][fuss][bien_dang][]" value="bet" <?php echo checked_v('exam[akt_path][fuss][bien_dang]', 'bet'); ?>> <?php echo _t_v2('Bàn chân bẹt (Plattfuß)', 'Plattfuß', 'Flat Foot'); ?></label>
                                        <label><input type="checkbox" name="exam[akt_path][fuss][bien_dang][]" value="lom" <?php echo checked_v('exam[akt_path][fuss][bien_dang]', 'lom'); ?>> <?php echo _t_v2('Bàn chân lõm/vòm cao (Hohlfuß)', 'Hohlfuß', 'Cavus Foot'); ?></label>
                                        <label><input type="checkbox" name="exam[akt_path][fuss][bien_dang][]" value="liem" <?php echo checked_v('exam[akt_path][fuss][bien_dang]', 'liem'); ?>> <?php echo _t_v2('Bàn chân hình liềm (Sichelfuß)', 'Sichelfuß', 'Skew Foot'); ?></label>
                                        <label><input type="checkbox" name="exam[akt_path][fuss][bien_dang][]" value="sup" <?php echo checked_v('exam[akt_path][fuss][bien_dang]', 'sup'); ?>> <?php echo _t_v2('Sụp vòm (Senkfuß)', 'Senkfuß', 'Fallen Arches'); ?></label>
                                        <label><input type="checkbox" name="exam[akt_path][fuss][bien_dang][]" value="xoe" <?php echo checked_v('exam[akt_path][fuss][bien_dang]', 'xoe'); ?>> <?php echo _t_v2('Bàn chân xòe/bè (Spreizfuß)', 'Spreizfuß', 'Splay Foot'); ?></label>
                                        <label><input type="checkbox" name="exam[akt_path][fuss][bien_dang][]" value="veo" <?php echo checked_v('exam[akt_path][fuss][bien_dang]', 'veo'); ?>> <?php echo _t_v2('Bàn chân vẹo (Knickfuß)', 'Knickfuß', 'Valgus Foot'); ?></label>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td><strong><?php echo _t_v2('Khớp cổ chân', 'Sprunggelenk', 'Ankle'); ?></strong></td>
                                <td colspan="2">
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><strong><?php echo _t_v2('Khớp cổ chân trên (OSG)', 'OSG', 'Upper Ankle Joint (OSG)'); ?></strong> <label><input type="checkbox" name="exam[akt_path][fuss][khop][]" value="osg" <?php echo checked_v('exam[akt_path][fuss][khop]', 'osg'); ?>> </label></div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><strong><?php echo _t_v2('Khớp cổ chân dưới (USG)', 'USG', 'Lower Ankle Joint (USG)'); ?>:</strong> <label><input type="checkbox" name="exam[akt_path][fuss][khop][]" value="usg_ngua" <?php echo checked_v('exam[akt_path][fuss][khop]', 'usg_ngua'); ?>> <?php echo _t_v2('xoay ngửa (sup)', 'sup', 'Supination (sup)'); ?></label> / <label><input type="checkbox" name="exam[akt_path][fuss][khop][]" value="usg_sap" <?php echo checked_v('exam[akt_path][fuss][khop]', 'usg_sap'); ?>> <?php echo _t_v2('xoay sấp (pron)', 'pron', 'Pronation (pron)'); ?></label></div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 1.5rem;">
                    <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 700; color: #1e293b;"><?php echo _t_v2('Ô ghi chú:', 'Notizen:', 'Notes:'); ?></label>
                    <textarea name="exam[akt_path][notes]" class="form-premium-input" rows="8" data-min-height="160" placeholder="<?php echo _t_v2('Nhập ghi chú thêm cho phần Bệnh sử...', 'Weitere Notizen zur Anamnese eingeben...', 'Enter additional anamnesis notes...'); ?>" style="min-height: 160px; font-size: 0.95rem; line-height: 1.6; resize: vertical;"><?php echo get_v('akt_path.notes'); ?></textarea>
                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 0.4rem; font-style: italic;"><?php echo _t_v2('Sau khi bấm Lưu, ghi chú này sẽ được gom và hiển thị chung với phần ghi chú ở trên. Ghi chú trong phần Bệnh sử sẽ được hiển thị lại trên các bản Tái khám (Follow-Up) về sau.', 'Nach dem Speichern wird diese Notiz mit den obigen Notizen zusammengefasst. Notizen in der Anamnese werden in zukünftigen Follow-Ups wieder angezeigt.', 'After saving, this note will be merged with the above notes. Notes in the Anamnesis will be displayed in future Follow-ups.'); ?></div>
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

<script src="../../assets/js/medical_marking.js?v=20260728"></script>
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
