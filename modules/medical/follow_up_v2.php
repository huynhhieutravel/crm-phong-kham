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

// modules/medical/follow_up_v2.php

require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_medical');

$is_de = ($_GET['lang'] ?? $_SESSION['lang'] ?? 'vi') === 'de';

$patient_id = (int)($_GET['patient_id'] ?? 0);
$session_id = isset($_GET['session_id']) ? (int)$_GET['session_id'] : null;
$record_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$db = getDB();

// 1. Fetch Patient Info
$stmt = $db->prepare("SELECT full_name, gender, birthday FROM patients WHERE id = ?");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$patient) {
    set_flash('Không tìm thấy bệnh nhân!', 'error');
    redirect('index.php');
}
$age = '?';
if (!empty($patient['birthday']) && $patient['birthday'] !== '0000-00-00') {
    $dob_date = date_create($patient['birthday']);
    if ($dob_date !== false) {
        $age = date_diff($dob_date, date_create('today'))->y;
    }
}
$age_str = $age !== '?' ? "$age tuổi" : "Chưa rõ tuổi";
$gender_text = $patient['gender'] == 'Male' ? (_t_v2('Nam', 'Männlich', 'Male')) : ($patient['gender'] == 'Female' ? (_t_v2('Nữ', 'Weiblich', 'Female')) : (_t_v2('Khác', 'Andere', 'Other')));

// Fetch Last Treatment
$stmt = $db->prepare("SELECT treatment_date FROM treatments WHERE patient_id = ? ORDER BY treatment_date DESC LIMIT 1");
$stmt->execute([$patient_id]);
$last_treatment = $stmt->fetchColumn();
$last_visit = $last_treatment ? date('d/m/Y', strtotime($last_treatment)) : (_t_v2('Lần đầu', 'Erster Besuch', 'First Visit'));

// 2. Fetch Navigation (Next/Prev Followups)
$stmt = $db->prepare("SELECT id FROM medical_history WHERE patient_id = ? AND type = 'soap_note_v2' ORDER BY id ASC");
$stmt->execute([$patient_id]);
$all_followups = $stmt->fetchAll(PDO::FETCH_COLUMN);

$prev_id = null;
$next_id = null;
if ($record_id && $all_followups) {
    $idx = array_search($record_id, $all_followups);
    if ($idx !== false) {
        if ($idx > 0) $prev_id = $all_followups[$idx - 1];
        if ($idx < count($all_followups) - 1) $next_id = $all_followups[$idx + 1];
    }
} else if ($all_followups) {
    $prev_id = end($all_followups); // If creating new, the last one is prev
}

// 3. Load Data for current Record (if edit)
$existing_data = [];
if ($record_id) {
    $stmt = $db->prepare("SELECT history_data FROM medical_history WHERE id = ?");
    $stmt->execute([$record_id]);
    $json = $stmt->fetchColumn();
    $existing_data = json_decode($json, true) ?: [];
}

// 4. Fetch the most recent Pathologie/History for "Ghi chú từ Bệnh sử"
$stmt = $db->prepare("
    SELECT history_data, created_at FROM medical_history 
    WHERE patient_id = ? AND type IN ('pathologie_v2', 'chiro_history_v2', 'chiro_history') " . ($record_id ? "AND id < $record_id" : "") . " 
    ORDER BY id DESC LIMIT 1
");
$stmt->execute([$patient_id]);
$history_record = $stmt->fetch(PDO::FETCH_ASSOC);
$history_note = '';
$history_date = '';
$history_markers = [];
if ($history_record) {
    $hd = json_decode($history_record['history_data'], true) ?: [];
    $history_note = $hd['notes'] ?? (is_array($hd['akt_path'] ?? null) ? ($hd['akt_path']['notes'] ?? '') : ($hd['akt_path'] ?? ''));
    $history_date = date('d/m/Y', strtotime($history_record['created_at']));
    $history_markers = $hd['markers'] ?? [];
    if (is_string($history_markers)) {
        $history_markers = json_decode($history_markers, true) ?: [];
    }
    if (!is_array($history_markers)) $history_markers = [];
}

// 5. Fetch previous Follow-Up for "Ghi chú lần điều trị gần nhất" and Previous Markers
$stmt = $db->prepare("
    SELECT history_data, created_at FROM medical_history 
    WHERE patient_id = ? AND type IN ('soap_note_v2', 'soap_note') " . ($record_id ? "AND id < $record_id" : "") . " 
    ORDER BY id DESC LIMIT 1
");
$stmt->execute([$patient_id]);
$prev_fu_record = $stmt->fetch(PDO::FETCH_ASSOC);
$prev_fu_note = '';
$prev_fu_date = '';
$prev_fu_data = [];
$prev_fu_markers = [];
if ($prev_fu_record) {
    $pfd = json_decode($prev_fu_record['history_data'], true) ?: [];
    $prev_fu_data = $pfd;
    $s_val = $pfd['s'] ?? '';
    $prev_fu_note = $pfd['today_notes'] ?? (is_array($s_val) ? ($s_val['notes'] ?? '') : $s_val);
    $prev_fu_date = date('d/m/Y', strtotime($prev_fu_record['created_at']));
    $prev_fu_markers = $pfd['markers'] ?? [];
    if (is_string($prev_fu_markers)) {
        $prev_fu_markers = json_decode($prev_fu_markers, true) ?: [];
    }
    if (!is_array($prev_fu_markers)) $prev_fu_markers = [];
}

// Inherit O/X markers for the NEW canvas
$inherited_markers = [];
$source_markers = !empty($prev_fu_markers) ? $prev_fu_markers : $history_markers;
if (is_array($source_markers)) {
    foreach ($source_markers as $m) {
        if (is_array($m) && isset($m['type'])) {
            if ($m['type'] === 'O' || $m['type'] === 'X' || $m['type'] === 'surgery' || $m['type'] === 'fracture') {
                $inherited_markers[] = $m; // copy over
            }
        }
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fu_data = $_POST['fu'] ?? [];
    if (isset($fu_data['markers']) && is_string($fu_data['markers'])) {
        $fu_data['markers'] = json_decode($fu_data['markers'], true) ?: [];
    }
    
    $json_data = json_encode($fu_data, JSON_UNESCAPED_UNICODE);
    
    if ($record_id) {
        $stmt = $db->prepare("UPDATE medical_history SET history_data = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$json_data, $record_id]);
    } else {
        $stmt = $db->prepare("
            INSERT INTO medical_history (patient_id, session_id, type, history_data, created_by)
            VALUES (?, ?, 'soap_note_v2', ?, ?)
        ");
        $stmt->execute([$patient_id, $session_id, $json_data, $_SESSION['user_id']]);
        $new_id = $db->lastInsertId();
    }
    
    set_flash('Lưu Follow-Up thành công!');
    
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

$page_title = _t_v2('Follow-up V2 (Mẫu Mới)', 'Follow-up V2 (Neu)', 'Follow-up V2 (New)');
$current_page = 'medical';
require_once '../../templates/header.php';

// Helper for checked state
function get_v($path, $default = '') {
    global $existing_data;
    $keys = explode('.', $path);
    $curr = $existing_data;
    foreach($keys as $k) {
        if(!is_array($curr) || !isset($curr[$k])) return $default;
        $curr = $curr[$k];
    }
    return $curr;
}
function checked_v($path, $value) {
    $curr = get_v($path);
    if (is_array($curr)) return in_array($value, $curr) ? 'checked' : '';
    return $curr === $value ? 'checked' : '';
}
// Helper to check if PREVIOUS follow up had this checked (for gray styling)
function checked_prev($path, $value) {
    global $prev_fu_data;
    $keys = explode('.', $path);
    $curr = $prev_fu_data;
    foreach($keys as $k) {
        if(!is_array($curr) || !isset($curr[$k])) return false;
        $curr = $curr[$k];
    }
    if (is_array($curr)) return in_array($value, $curr);
    return $curr === $value;
}
?>

<!-- Apple-style UI Overrides for this form -->
<style>
.fu-card { background: #ffffff; border-radius: 16px; padding: 2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; margin-bottom: 2rem; }
.fu-title { font-size: 1.15rem; font-weight: 800; color: #1d1d1f; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem; }
.fu-table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
.fu-table th { background: #f8fafc; padding: 0.75rem 1rem; text-align: center; font-size: 0.8rem; color: #475569; font-weight: 700; border: 1px solid #e2e8f0; }
.fu-table td { padding: 0.75rem 1rem; border: 1px solid #e2e8f0; vertical-align: middle; }
.fu-table td.label-col { background: #fefefe; font-weight: 600; color: #1e293b; font-size: 0.85rem; width: 35%; }

/* Custom Checkbox Checkmark inside Card */
.fu-card input[type="checkbox"] {
    appearance: none; -webkit-appearance: none; width: 22px; height: 22px; border: 2px solid #cbd5e1; border-radius: 6px; outline: none; cursor: pointer; transition: all 0.2s; position: relative; background: #fff; display: inline-block; vertical-align: middle;
}
.fu-table td > input[type="checkbox"] { margin: 0 auto; display: block; }
.fu-table td.label-col input[type="checkbox"] { display: inline-block; margin: 0; margin-right: 0.25rem; vertical-align: middle; }
.fu-card label { display: inline-flex; align-items: center; white-space: nowrap; margin-right: 0.5rem; gap: 0.5rem; }

.fu-card input[type="checkbox"]:checked {
    background: #3b82f6; border-color: #3b82f6;
}
.fu-card input[type="checkbox"]:checked::after {
    content: '\f00c'; font-family: 'Font Awesome 5 Free'; font-weight: 900; color: white; font-size: 12px; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
}

/* Grayed out 'Prev' state if it was checked last time but not checked now */
.fu-card input[type="checkbox"].was-checked-prev:not(:checked) {
    background: #f1f5f9; border-color: #94a3b8; opacity: 0.8;
}
.fu-card input[type="checkbox"].was-checked-prev:not(:checked)::after {
    content: '\f00c'; font-family: 'Font Awesome 5 Free'; font-weight: 900; color: #64748b; font-size: 12px; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
}

.fu-textarea {
    width: 100%; border: 1px solid #cbd5e1; border-radius: 12px; padding: 1rem; font-size: 0.9rem; resize: vertical; outline: none; transition: all 0.2s;
}
.fu-textarea:focus { border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); }
.fu-textarea.readonly { background: #f8fafc; color: #64748b; cursor: not-allowed; border-color: #e2e8f0; }

.pain-level-btn {
    width: 36px; height: 36px; border-radius: 50%; border: 2px solid transparent; cursor: pointer; transition: transform 0.2s;
}
.pain-level-btn:hover { transform: scale(1.1); }
.pain-level-btn.active { box-shadow: 0 0 0 3px #fff, 0 0 0 6px currentColor; }
</style>
<style>
/* Print Styles */
@media print {
    .sidebar, .navbar, .top-header, #header, #sidebar, .no-print { display: none !important; }
    .main-content, #main-content, body, .content { margin: 0 !important; padding: 0 !important; width: 100% !important; background: white !important; }
    .fu-card { box-shadow: none !important; border: 1px solid #e2e8f0 !important; margin: 0 0 1rem 0 !important; padding: 1rem !important; break-inside: avoid; }
    body { font-size: 11px !important; }
    textarea { border: 1px solid #eee !important; box-shadow: none !important; resize: none !important; overflow: hidden !important; background: transparent !important; }
    input[type="text"], input[type="date"], select { border: 1px solid #eee !important; box-shadow: none !important; background: transparent !important; }
    .btn, button, input[type="submit"] { display: none !important; }
    .fu-table th, .fu-table td { padding: 4px 6px !important; font-size: 11px !important; }
    ::-webkit-scrollbar { display: none !important; }
}
</style>
<script>
window.addEventListener('beforeprint', () => {
    document.querySelectorAll('textarea').forEach(t => {
        t.style.height = 'auto';
        t.style.height = (t.scrollHeight + 5) + 'px';
    });
});
</script>

<div style="max-width: 1100px; margin: 0 auto;">

    <!-- TOP NAVIGATION & HEADER (Removed original arrows to move them inside Notes section) -->
    <div class="no-print" style="display: flex; justify-content: flex-end; margin-bottom: 1.5rem;">
        <button type="button" onclick="window.print()" class="btn" style="background: #10b981; color: white; border-radius: 8px; font-weight: 700; border: none; padding: 10px 20px; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2); cursor: pointer;"><i class="fas fa-print" style="margin-right: 8px;"></i> <?php echo _t_v2('In PDF', 'Als PDF drucken', 'Print PDF'); ?></button>
    </div>

    <!-- PATIENT INFO CARD FROM ANAMNESE -->
    <div style="display: flex; flex-wrap: wrap; gap: 2rem 3rem; margin-bottom: 2.5rem; background: rgba(255,255,255,0.95); padding: 1.5rem 2rem; border-radius: 20px; box-shadow: 0 4px 24px rgba(0,0,0,0.04); border: 1px solid rgba(0,0,0,0.05); align-items: center; backdrop-filter: blur(10px);">
        
        <!-- 1. HỌ TÊN -->
        <div style="display: flex; align-items: center; gap: 1rem; flex: 1 1 280px; min-width: 250px;">
            <div style="width: 46px; height: 46px; border-radius: 50%; background: #f5f5f7; color: #1d1d1f; display: flex; justify-content: center; align-items: center; font-size: 1.15rem; flex-shrink: 0;">
                <i class="fas fa-user"></i>
            </div>
            <div style="flex: 1; min-width: 0;">
                <div style="color: #86868b; font-size: 0.65rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 0.2rem;"><?php echo _t_v2('Họ Tên', 'Name', 'Full Name'); ?></div>
                <div style="font-size: 1.05rem; font-weight: 600; color: #1d1d1f; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo e($patient['full_name']); ?>"><?php echo e($patient['full_name']); ?></div>
            </div>
        </div>

        <!-- 2. NĂM SINH -->
        <div style="display: flex; align-items: center; gap: 1rem; flex: 1 1 200px; min-width: 180px;">
            <div style="width: 46px; height: 46px; border-radius: 50%; background: #f5f5f7; color: #1d1d1f; display: flex; justify-content: center; align-items: center; font-size: 1.15rem; flex-shrink: 0;">
                <i class="fas fa-calendar"></i>
            </div>
            <div>
                <div style="color: #86868b; font-size: 0.65rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 0.2rem;"><?php echo _t_v2('Ngày Sinh', 'Geburtsdatum', 'Date of Birth'); ?></div>
                <div style="font-size: 1.05rem; font-weight: 600; color: #1d1d1f; line-height: 1.2;">
                    <?php echo !empty($patient['birthday']) && $patient['birthday'] !== '0000-00-00' ? date('d/m/Y', strtotime($patient['birthday'])) : (_t_v2('Chưa rõ', 'Unbekannt', 'Unknown')); ?>
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
                <div style="font-size: 1.05rem; font-weight: 600; color: #1d1d1f; line-height: 1.2;"><?php echo $gender_text; ?></div>
            </div>
        </div>
        
        <!-- 5. LẦN KHÁM -->
        <div style="display: flex; align-items: center; gap: 1rem; flex: 1 1 200px; min-width: 150px;">
            <div style="width: 46px; height: 46px; border-radius: 50%; background: #f5f5f7; color: #1d1d1f; display: flex; justify-content: center; align-items: center; font-size: 1.15rem; flex-shrink: 0;">
                <i class="far fa-clock"></i>
            </div>
            <div>
                <div style="color: #86868b; font-size: 0.65rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 0.2rem;"><?php echo _t_v2('Điều Trị Gần Nhất', 'Letzte Behandlung', 'Last Treatment'); ?></div>
                <div style="font-size: 1.05rem; font-weight: 600; color: #1d1d1f; line-height: 1.2;"><?php echo $last_visit; ?></div>
            </div>
        </div>

        <!-- QUAN HỆ -->
        <div style="display: flex; align-items: center; gap: 1rem; flex: 1 1 200px; min-width: 150px;">
            <div style="width: 46px; height: 46px; border-radius: 50%; background: #f5f5f7; color: #1d1d1f; display: flex; justify-content: center; align-items: center; font-size: 1.15rem; flex-shrink: 0;">
                <i class="fas fa-users"></i>
            </div>
            <div style="flex: 1; min-width: 0;">
                <div style="color: #86868b; font-size: 0.65rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 0.2rem;"><?php echo _t_v2('Quan Hệ', 'Beziehung', 'Relationship'); ?></div>
                <div style="font-size: 1.05rem; font-weight: 600; color: #1d1d1f; line-height: 1.2;">
                    <a href="../patients/edit.php?id=<?php echo $patient_id; ?>" target="_blank" style="color: inherit; text-decoration: underline; text-decoration-color: #cbd5e1; text-underline-offset: 4px;" title="Nhấn để xem/sửa hồ sơ">
                        <?php echo e($patient['relationship'] ?: ($patient['guardian_relationship'] ?: _t_v2('Bản thân', 'Selbst', 'Self'))); ?>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- PHÂN LOẠI -->
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

    <form method="POST">
        <?php echo csrf_field(); ?>
        
        <!-- SECTION 1: GHI CHÚ -->
        <div class="fu-card" style="padding: 1.5rem; background: #f8fafc; border: none; margin-bottom: 2rem;">
            <!-- Navigation arrows moved here to be prominent at the top of the form -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 1rem;">
                <div style="display: flex; gap: 0.75rem;">
                    <?php if ($prev_id): ?>
                        <a href="?patient_id=<?php echo $patient_id; ?>&id=<?php echo $prev_id; ?>" class="btn" style="background: #fff; border: 2px solid #cbd5e1; border-radius: 8px; font-weight: 700; color: #475569; padding: 0.5rem 1.5rem; transition: all 0.2s;"><i class="fas fa-chevron-left" style="margin-right: 0.5rem;"></i> <?php echo _t_v2('Bản FollowUp Trước', 'Vorheriges FollowUp', 'Previous FollowUp'); ?></a>
                    <?php else: ?>
                        <button type="button" class="btn" disabled style="background: #f1f5f9; border: 2px solid #e2e8f0; border-radius: 8px; font-weight: 700; color: #94a3b8; padding: 0.5rem 1.5rem; cursor: not-allowed;"><i class="fas fa-chevron-left" style="margin-right: 0.5rem;"></i> <?php echo _t_v2('Bản FollowUp Trước', 'Vorheriges FollowUp', 'Previous FollowUp'); ?></button>
                    <?php endif; ?>
                    
                    <?php if ($next_id): ?>
                        <a href="?patient_id=<?php echo $patient_id; ?>&id=<?php echo $next_id; ?>" class="btn" style="background: #fff; border: 2px solid #cbd5e1; border-radius: 8px; font-weight: 700; color: #475569; padding: 0.5rem 1.5rem; transition: all 0.2s;"><?php echo _t_v2('Bản FollowUp Sau', 'Nächstes FollowUp', 'Next FollowUp'); ?> <i class="fas fa-chevron-right" style="margin-left: 0.5rem;"></i></a>
                    <?php else: ?>
                        <button type="button" class="btn" disabled style="background: #f1f5f9; border: 2px solid #e2e8f0; border-radius: 8px; font-weight: 700; color: #94a3b8; padding: 0.5rem 1.5rem; cursor: not-allowed;"><?php echo _t_v2('Bản FollowUp Sau', 'Nächstes FollowUp', 'Next FollowUp'); ?> <i class="fas fa-chevron-right" style="margin-left: 0.5rem;"></i></button>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if ($record_id): ?>
                        <a href="?patient_id=<?php echo $patient_id; ?>" class="btn btn-primary" style="border-radius: 8px; font-weight: 700; padding: 0.5rem 1.5rem;"><i class="fas fa-plus"></i> <?php echo _t_v2('Tạo mới FollowUp', 'Neues FollowUp', 'New FollowUp'); ?></a>
                    <?php endif; ?>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                
                <!-- Box 1: Anamnese -->
                <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.02);">
                    <div style="background: #f1f5f9; padding: 0.75rem 1rem; border-bottom: 1px solid #e2e8f0; font-weight: 700; color: #475569; display: flex; justify-content: space-between; align-items: center;">
                        <span><i class="fas fa-file-medical-alt" style="color: #64748b; margin-right: 0.5rem;"></i> <?php echo _t_v2('Ghi chú Anamnese', 'Anamnese', 'Anamnesis'); ?></span>
                        <span style="font-size: 0.75rem; font-weight: 500; opacity: 0.8;"><?php echo $history_date ?: (_t_v2('Chưa rõ', 'Unbekannt', 'Unknown')); ?></span>
                    </div>
                    <div style="padding: 1rem;">
                        <textarea class="fu-textarea readonly" rows="5" readonly style="background: #fafafa; border: none; box-shadow: none; padding: 0; resize: none;"><?php echo e($history_note); ?></textarea>
                    </div>
                </div>

                <!-- Box 2: Previous FollowUp -->
                <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.02);">
                    <div style="background: #f1f5f9; padding: 0.75rem 1rem; border-bottom: 1px solid #e2e8f0; font-weight: 700; color: #475569; display: flex; justify-content: space-between; align-items: center;">
                        <span><i class="fas fa-history" style="color: #64748b; margin-right: 0.5rem;"></i> <?php echo _t_v2('Lần khám trước', 'Vorherige', 'Previous'); ?></span>
                        <span style="font-size: 0.75rem; font-weight: 500; opacity: 0.8;"><?php echo $prev_fu_date ?: (_t_v2('Chưa rõ', 'Unbekannt', 'Unknown')); ?></span>
                    </div>
                    <div style="padding: 1rem;">
                        <textarea class="fu-textarea readonly" rows="5" readonly style="background: #fafafa; border: none; box-shadow: none; padding: 0; resize: none;"><?php echo e($prev_fu_note); ?></textarea>
                    </div>
                </div>

                <!-- Box 3: Current FollowUp -->
                <div style="background: #fff; border: 2px solid #3b82f6; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.1);">
                    <div style="background: #eff6ff; padding: 0.75rem 1rem; border-bottom: 1px solid #bfdbfe; font-weight: 800; color: #1d4ed8; display: flex; justify-content: space-between; align-items: center;">
                        <span><i class="fas fa-edit" style="margin-right: 0.5rem;"></i> <?php echo _t_v2('Ghi chú LẦN NÀY', 'HEUTE', 'TODAY'); ?></span>
                    </div>
                    <div style="padding: 1rem;">
                        <textarea name="fu[today_notes]" class="fu-textarea" rows="5" placeholder="<?php echo _t_v2('Nhập ghi chú cho buổi điều trị này...', 'Notizen für diese Behandlung eingeben...', 'Enter notes for this treatment...'); ?>" style="border: none; background: transparent; padding: 0; box-shadow: none; resize: none; font-size: 1rem; color: #0f172a;"><?php echo e(get_v('today_notes')); ?></textarea>
                    </div>
                </div>

            </div>
        </div>

        <!-- SECTION 2: HÌNH VẼ SO SÁNH -->
        <div class="fu-card">
            <h3 class="fu-title" style="background: linear-gradient(to right, #ecfdf5, #d1fae5); display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.2rem; margin-bottom: 0.5rem; border-radius: 12px; color: #065f46; border: 1px solid #10b981; font-weight: 800;"><i class="fas fa-draw-polygon" style="color: #10b981; font-size: 1.1rem;"></i> <?php echo _t_v2('Hình vẽ so sánh: Hình lần trước ↔ Hình lần này', 'Vergleichsskizzen: Vorheriges Bild ↔ Aktuelles Bild', 'Comparison sketches: Previous Image ↔ Current Image'); ?></h3>
            <p style="font-size: 0.95rem; font-weight: 600; color: #1e293b; margin-bottom: 1.5rem; line-height: 1.6; font-style: italic;"><?php echo _t_v2('Nếu chưa có hình lần trước thì lấy từ Bệnh sử. Hình lần trước chỉ được xem, không được sửa. Hình lần này vẫn kế thừa các đánh dấu phẫu thuật và gãy xương của lần trước. Ở đây chỉ dùng 5 mức độ đau.', 'Wenn kein vorheriges Bild vorhanden ist, wird das aus der Anamnese übernommen. Das vorherige Bild kann nur angesehen, nicht bearbeitet werden. Das aktuelle Bild übernimmt die Operations- und Frakturmarkierungen des vorherigen. Hier werden nur die 5 Schmerzstufen verwendet.', 'If no previous image exists, it\'s taken from the anamnesis. The previous image can only be viewed, not edited. The current image inherits the surgery and fracture marks of the previous one. Only the 5 pain levels are used here.'); ?></p>
            
            <div style="background: #e0f2fe; padding: 1rem 1.5rem; border-radius: 12px; border: 1px solid #bae6fd; margin-bottom: 2rem;">
                <h4 style="font-size: 0.85rem; color: #0369a1; font-weight: 800; margin-bottom: 0.5rem; text-transform: uppercase;"><?php echo _t_v2('Quy ước 5 vòng tròn mức độ đau:', 'LEGENDE ZUR SCHMERZINTENSITÄT (5 KREISE):', 'PAIN INTENSITY LEGEND (5 CIRCLES):'); ?></h4>
                <div style="display: flex; flex-wrap: wrap; gap: 1.5rem; font-size: 0.8rem; color: #0c4a6e;">
                    <div style="display: flex; align-items: center; gap: 0.3rem;"><div style="width:12px; height:12px; border-radius:50%; background:#38bdf8;"></div> <strong><?php echo _t_v2('Vòng 1:', 'Ring 1 (hellblau):', 'Ring 1 (Light Blue):'); ?></strong> <?php echo _t_v2('Đã đỡ nhiều', 'Deutlich verbessert', 'Significantly improved'); ?></div>
                    <div style="display: flex; align-items: center; gap: 0.3rem;"><div style="width:12px; height:12px; border-radius:50%; background:#4ade80;"></div> <strong><?php echo _t_v2('Vòng 2:', 'Ring 2 (grün):', 'Ring 2 (Green):'); ?></strong> <?php echo _t_v2('Đỡ ít hơn', 'Leicht verbessert', 'Slightly improved'); ?></div>
                    <div style="display: flex; align-items: center; gap: 0.3rem;"><div style="width:12px; height:12px; border-radius:50%; background:#fbbf24;"></div> <strong><?php echo _t_v2('Vòng 3:', 'Ring 3 (gelb):', 'Ring 3 (Yellow):'); ?></strong> <?php echo _t_v2('Không thay đổi', 'Unverändert', 'Unchanged'); ?></div>
                    <div style="display: flex; align-items: center; gap: 0.3rem;"><div style="width:12px; height:12px; border-radius:50%; background:#fb923c;"></div> <strong><?php echo _t_v2('Vòng 4:', 'Ring 4 (orange):', 'Ring 4 (Orange):'); ?></strong> <?php echo _t_v2('Đau hơn cũ', 'Leicht verschlechtert', 'Slightly worsened'); ?></div>
                    <div style="display: flex; align-items: center; gap: 0.3rem;"><div style="width:12px; height:12px; border-radius:50%; background:#ef4444;"></div> <strong><?php echo _t_v2('Vòng 5:', 'Ring 5 (rot):', 'Ring 5 (Red):'); ?></strong> <?php echo _t_v2('Đau hơn nhiều', 'Deutlich verschlechtert', 'Significantly worsened'); ?></div>
                </div>
            </div>

            <!-- Color Palette 5 Levels (Centered at top) -->
            <div style="display: flex; justify-content: center; margin-bottom: 1.5rem;">
                <div style="display: flex; gap: 0.75rem; background: #f8fafc; padding: 0.75rem 1.5rem; border-radius: 50px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                    <div class="pain-level-btn active" style="background: #38bdf8; color: #38bdf8;" onclick="setPainColor('M1', this)" title="Đã đỡ nhiều"></div>
                    <div class="pain-level-btn" style="background: #4ade80; color: #4ade80;" onclick="setPainColor('M2', this)" title="Đỡ ít hơn"></div>
                    <div class="pain-level-btn" style="background: #fbbf24; color: #fbbf24;" onclick="setPainColor('M3', this)" title="Vẫn đau như lần trước"></div>
                    <div class="pain-level-btn" style="background: #fb923c; color: #fb923c;" onclick="setPainColor('M4', this)" title="Đau hơn cũ 1 chút"></div>
                    <div class="pain-level-btn" style="background: #ef4444; color: #ef4444;" onclick="setPainColor('M5', this)" title="Đau hơn nhiều"></div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <!-- OLD CANVAS -->
                <div>
                    <div style="text-align: center; font-weight: 700; margin-bottom: 0.5rem; color: #64748b;"><?php echo _t_v2('Lần trước', 'Vorheriges Bild', 'Previous Image'); ?> (<?php echo $prev_fu_date ?: ($history_date ?: (_t_v2('Chưa có', 'Nicht vorhanden', 'Not available'))); ?>)</div>
                    <div style="border: 2px solid #e2e8f0; border-radius: 12px; background: white; padding: 10px; opacity: 0.7; pointer-events: none;">
                        <canvas id="canvas-old" width="800" height="800" style="width: 100%; height: auto; display: block;"></canvas>
                    </div>
                </div>
                
                <!-- NEW CANVAS -->
                <div>
                    <div style="text-align: center; font-weight: 800; margin-bottom: 0.5rem; color: #1e293b;"><?php echo _t_v2('Lần này', 'Aktuelles Bild', 'Current Image'); ?></div>
                    
                    <div style="border: 2px solid #3b82f6; border-radius: 12px; background: white; padding: 10px; box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.1);">
                        <canvas id="canvas-new" width="800" height="800" style="width: 100%; height: auto; display: block; cursor: crosshair;"></canvas>
                        <input type="hidden" name="fu[markers]" id="new-markers-data">
                        <div style="display: flex; justify-content: flex-end; margin-top: 0.5rem; gap: 0.5rem;">
                            <button type="button" class="btn btn-sm" onclick="undoNewMarking()" style="background: #f1f5f9; color: #475569; font-size: 0.75rem;"><i class="fas fa-undo"></i> Undo</button>
                            <button type="button" class="btn btn-sm" onclick="clearNewMarking()" style="background: #fee2e2; color: #ef4444; font-size: 0.75rem;"><i class="fas fa-trash"></i> Xóa hết</button>
                        </div>
                    </div>
                </div>
            </div>


            
            <style>
            .progress-state-group {
                display: flex;
                flex-wrap: wrap;
                gap: 0.75rem;
                margin-top: 0.75rem;
            }
            .progress-state-option {
                display: inline-block;
                cursor: pointer;
            }
            .progress-state-option input[type="radio"] {
                display: none;
            }
            .progress-state-card {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                border: 2px solid #e2e8f0;
                border-radius: 10px;
                padding: 0.5rem;
                width: 85px;
                height: 90px;
                background: #fff;
                transition: all 0.2s ease;
            }
            .progress-state-card img {
                width: 40px;
                height: 40px;
                object-fit: contain;
                margin-bottom: 0.4rem;
                transition: all 0.2s ease;
            }
            .progress-state-text {
                font-size: 0.65rem;
                font-weight: 700;
                color: #64748b;
                text-align: center;
                line-height: 1.2;
            }
            .progress-state-option input[type="radio"]:checked + .progress-state-card {
                border-color: #3b82f6;
                background: #eff6ff;
                box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.1);
            }
            .progress-state-option input[type="radio"]:checked + .progress-state-card img {
                filter: grayscale(0%);
                opacity: 1;
                transform: scale(1.15);
            }
            .progress-state-option input[type="radio"]:checked + .progress-state-card .progress-state-text {
                color: #1d4ed8;
            }
            .progress-state-option:hover .progress-state-card {
                border-color: #cbd5e1;
            }
            </style>
            <div style="margin-top: 1.5rem; background: #f8fafc; padding: 1.25rem; border-radius: 12px; border: 1px solid #e2e8f0;">
                <label style="font-weight: 800; color: #1e293b; margin: 0; display: block; font-size: 0.95rem;">
                    <?php echo _t_v2('Diễn tiến so với lần trước:', 'Verlauf im Vergleich zur letzten Behandlung:', 'Progress compared to last treatment:'); ?>
                </label>
                <div class="progress-state-group">
                    <?php
                    $progress_options = [
                        'Tốt dần lên' => ['img' => 'tot-dan-len.png', 'de' => 'Besser werden', 'en' => 'Getting better'],
                        'Ổn định tốt' => ['img' => 'on-dinh-tot.png', 'de' => 'Sehr stabil', 'en' => 'Very stable'],
                        'Ổn định' => ['img' => 'on-dinh.png', 'de' => 'Stabil', 'en' => 'Stable'],
                        'Phục hồi' => ['img' => 'phuc-hoi.png', 'de' => 'Erholend', 'en' => 'Recovering'],
                        'Dao động' => ['img' => 'dao-dong.png', 'de' => 'Schwankend', 'en' => 'Fluctuating'],
                        'Suy giảm' => ['img' => 'suy-giam.png', 'de' => 'Nachlassend', 'en' => 'Declining'],
                        'Mệt mỏi' => ['img' => 'met-moi.png', 'de' => 'Müde', 'en' => 'Tired']
                    ];
                    
                    foreach ($progress_options as $val => $data) {
                        $checked = checked_v('progress', $val) ? 'checked' : '';
                        $text = _t_v2($val, $data['de'], $data['en']);
                        echo '
                        <label class="progress-state-option">
                            <input type="radio" name="fu[progress]" value="'.$val.'" '.$checked.'>
                            <div class="progress-state-card">
                                <img src="../../assets/images/progress/'.$data['img'].'" alt="'.$val.'">
                                <span class="progress-state-text">'.$text.'</span>
                            </div>
                        </label>
                        ';
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- SECTION 3: BẢNG NẮN CHỈNH XƯƠNG KHỚP -->
        <div class="fu-card">
            <h3 class="fu-title"><i class="fas fa-bone" style="color: #10b981;"></i> <?php echo _t_v2('Danh sách Điều trị & Nắn chỉnh', 'Behandlungs- & Justierungsliste', 'Treatment & Adjustment List'); ?></h3>
            <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 1.5rem;"><?php echo _t_v2('Lưu ý: Các ô <strong style="color: #94a3b8;">màu xám</strong> là các vị trí đã được nắn chỉnh trong lần điều trị gần nhất. Bạn có thể check đè lên để cập nhật cho lần này.', 'Hinweis: Grau markierte Felder wurden in der letzten Sitzung behandelt. Sie können diese überschreiben, um sie für dieses Mal zu aktualisieren.', 'Note: Gray highlighted fields were treated in the last session. You can overwrite them to update for this time.'); ?></p>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <!-- Cột Sống Cổ (CSC) -->
                <div>
                    <h4 style="color: #0284c7; font-size: 0.95rem; margin-bottom: 0.75rem;"><?php echo _t_v2('Vùng nắn chỉnh - Cột sống cổ', 'Justierungsbereich - HWS', 'Adjustment Area - Cervical Spine'); ?></h4>
                    <table class="fu-table">
                        <thead><tr><th width="15%"><?php echo _t_v2('T', 'li', 'L'); ?></th><th width="15%"><?php echo _t_v2('P', 're', 'R'); ?></th><th><?php echo _t_v2('Vị trí', 'Position', 'Position'); ?></th></tr></thead>
                        <tbody>
                            <?php 
                            $csc_list = [
                                'occiput' => _t_v2('Xương chẩm (Occiput)', 'Occiput', 'Occiput'), 'tmj' => _t_v2('Khớp thái dương hàm (TMJ)', 'Kiefergelenk (TMJ)', 'TMJ (Temporomandibular Joint)'), 
                                'c1' => _t_v2('Đốt đội (Atlas C1)', 'Atlas (C1)', 'Atlas (C1)'), 'c2' => _t_v2('Đốt trục (Axis C2)', 'Axis (C2)', 'Axis (C2)'), 
                                'c3' => 'C3', 'c4' => 'C4', 'c5' => 'C5', 'c6' => 'C6', 'c7' => 'C7'
                            ];
                            foreach($csc_list as $key => $label): ?>
                            <tr>
                                <td><input type="checkbox" name="fu[csc][<?php echo $key; ?>][L]" value="1" class="<?php echo checked_prev("csc.$key.L", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("csc.$key.L", "1"); ?>></td>
                                <td><input type="checkbox" name="fu[csc][<?php echo $key; ?>][R]" value="1" class="<?php echo checked_prev("csc.$key.R", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("csc.$key.R", "1"); ?>></td>
                                <td class="label-col"><?php echo $label; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Cột Sống Thắt Lưng (CSTL) -->
                <div>
                    <h4 style="color: #0284c7; font-size: 0.95rem; margin-bottom: 0.75rem;"><?php echo _t_v2('Vùng nắn chỉnh - Cột sống thắt lưng (LWS)', 'Justierungsbereich - LWS', 'Adjustment Area - Lumbar Spine'); ?></h4>
                    <table class="fu-table">
                        <thead><tr><th width="15%"><?php echo _t_v2('T', 'li', 'L'); ?></th><th width="15%"><?php echo _t_v2('P', 're', 'R'); ?></th><th><?php echo _t_v2('Vị trí', 'Position', 'Position'); ?></th></tr></thead>
                        <tbody>
                            <?php for($i=1; $i<=5; $i++): $k="l$i"; ?>
                            <tr>
                                <td><input type="checkbox" name="fu[cstl][<?php echo $k; ?>][L]" value="1" class="<?php echo checked_prev("cstl.$k.L", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("cstl.$k.L", "1"); ?>></td>
                                <td><input type="checkbox" name="fu[cstl][<?php echo $k; ?>][R]" value="1" class="<?php echo checked_prev("cstl.$k.R", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("cstl.$k.R", "1"); ?>></td>
                                <td class="label-col">L<?php echo $i; ?></td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Cột sống ngực & Xương sườn -->
            <div style="display: grid; grid-template-columns: 45% 55%; gap: 1rem; margin-top: 2rem;">
                <div>
                    <h4 style="color: #0284c7; font-size: 0.95rem; margin-bottom: 0.75rem;"><?php echo _t_v2('Vùng nắn chỉnh - Cột sống ngực (BWS)', 'Justierungsbereich - BWS', 'Adjustment Area - Thoracic Spine'); ?></h4>
                    <table class="fu-table">
                        <thead><tr><th width="15%"><?php echo _t_v2('T', 'li', 'L'); ?></th><th width="15%"><?php echo _t_v2('P', 're', 'R'); ?></th><th width="30%"><?php echo _t_v2('Đốt sống', 'Wirbel', 'Vertebra'); ?></th><th width="20%"><?php echo _t_v2('Trước', 'Ventral', 'Ventral'); ?></th><th width="20%"><?php echo _t_v2('Sau', 'Dorsal', 'Dorsal'); ?></th></tr></thead>
                        <tbody>
                            <?php for($i=1; $i<=12; $i++): $k="t$i"; ?>
                            <tr>
                                <td><input type="checkbox" name="fu[csn][<?php echo $k; ?>][L]" value="1" class="<?php echo checked_prev("csn.$k.L", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("csn.$k.L", "1"); ?>></td>
                                <td><input type="checkbox" name="fu[csn][<?php echo $k; ?>][R]" value="1" class="<?php echo checked_prev("csn.$k.R", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("csn.$k.R", "1"); ?>></td>
                                <td class="label-col" style="text-align: center;">T<?php echo $i; ?></td>
                                <td><input type="checkbox" name="fu[csn][<?php echo $k; ?>][front]" value="1" class="<?php echo checked_prev("csn.$k.front", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("csn.$k.front", "1"); ?>></td>
                                <td><input type="checkbox" name="fu[csn][<?php echo $k; ?>][back]" value="1" class="<?php echo checked_prev("csn.$k.back", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("csn.$k.back", "1"); ?>></td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
                <div>
                    <h4 style="color: #0284c7; font-size: 0.95rem; margin-bottom: 0.75rem;"><?php echo _t_v2('Xương sườn (Rippen)', 'Rippen', 'Ribs'); ?></h4>
                    <table class="fu-table">
                        <thead><tr><th width="18%"><?php echo _t_v2('Sau - T', 'Dorsal - li', 'Dorsal - L'); ?></th><th width="18%"><?php echo _t_v2('Sau - P', 'Dorsal - re', 'Dorsal - R'); ?></th><th width="28%"><?php echo _t_v2('Sườn', 'Rippe', 'Rib'); ?></th><th width="18%"><?php echo _t_v2('Trước - P', 'Ventral - re', 'Ventral - R'); ?></th><th width="18%"><?php echo _t_v2('Trước - T', 'Ventral - li', 'Ventral - L'); ?></th></tr></thead>
                        <tbody>
                            <?php for($i=1; $i<=12; $i++): $k="r$i"; ?>
                            <tr>
                                <td><input type="checkbox" name="fu[ribs][<?php echo $k; ?>][back_L]" value="1" class="<?php echo checked_prev("ribs.$k.back_L", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("ribs.$k.back_L", "1"); ?>></td>
                                <td><input type="checkbox" name="fu[ribs][<?php echo $k; ?>][back_R]" value="1" class="<?php echo checked_prev("ribs.$k.back_R", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("ribs.$k.back_R", "1"); ?>></td>
                                <td class="label-col" style="text-align: center;">T<?php echo $i; ?></td>
                                <td><input type="checkbox" name="fu[ribs][<?php echo $k; ?>][front_R]" value="1" class="<?php echo checked_prev("ribs.$k.front_R", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("ribs.$k.front_R", "1"); ?>></td>
                                <td><input type="checkbox" name="fu[ribs][<?php echo $k; ?>][front_L]" value="1" class="<?php echo checked_prev("ribs.$k.front_L", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("ribs.$k.front_L", "1"); ?>></td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Khung chậu & Xương cùng -->
            <div style="margin-top: 2rem;">
                <h4 style="color: #0284c7; font-size: 0.95rem; margin-bottom: 0.75rem;"><?php echo _t_v2('Khớp cùng-chậu (ISG) & Khung chậu (Becken)', 'ISG & Becken', 'Sacroiliac Joint (SIJ) & Pelvis'); ?></h4>
                <table class="fu-table">
                    <thead>
                        <tr><th width="15%"><?php echo _t_v2('Khung chậu (Becken)', 'Becken', 'Pelvis'); ?></th><th>AS</th><th>PI</th><th>IN-Ilium</th><th>EX-Ilium</th><th>Up-Slip</th><th>Down-Slip</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach(['L'=>'Trái (li)', 'R'=>'Phải (re)'] as $k => $label): ?>
                        <tr>
                            <td class="label-col"><?php echo $label; ?></td>
                            <?php foreach(['as','pi','in','ex','up','down'] as $col): ?>
                            <td><input type="checkbox" name="fu[becken][<?php echo $k; ?>][<?php echo $col; ?>]" value="1" class="<?php echo checked_prev("becken.$k.$col", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("becken.$k.$col", "1"); ?>></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 2rem; max-width: 600px;">
                <h4 style="color: #0284c7; font-size: 0.95rem; margin-bottom: 0.75rem;"><?php echo _t_v2('Xương cùng (Sacrum)', 'Sacrum', 'Sacrum'); ?></h4>
                <div style="display: inline-block; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 1rem;">
                    <table style="border-collapse: collapse; margin: 0;">
                        <tbody>
                            <?php for($r=0; $r<3; $r++): ?>
                            <tr>
                                <?php for($c=0; $c<3; $c++): $key="s_{$r}_{$c}"; ?>
                                <td style="padding: 0.85rem; border: 1px solid #e2e8f0; text-align: center; width: 3.5rem; height: 3.5rem;"><input type="checkbox" name="fu[sacrum][<?php echo $key; ?>]" value="1" class="<?php echo checked_prev("sacrum.$key", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("sacrum.$key", "1"); ?>></td>
                                <?php endfor; ?>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
                
                <div style="margin-top: 1rem; font-size: 0.85rem; font-weight: 600; color: #1e293b; line-height: 2.4;">
                    <div><?php echo _t_v2('Khớp mu (Symphyse):', 'Symphyse:', 'Symphysis:'); ?> 
                        <span style="margin-left: 0.5rem;">
                            <label><input type="checkbox" name="fu[misc][symph_L]" value="1" class="<?php echo checked_prev("misc.symph_L", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("misc.symph_L", "1"); ?>> <?php echo _t_v2('T', 'li', 'L'); ?></label>
                            <label><input type="checkbox" name="fu[misc][symph_R]" value="1" class="<?php echo checked_prev("misc.symph_R", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("misc.symph_R", "1"); ?>> <?php echo _t_v2('P', 're', 'R'); ?></label>
                        </span>
                        <span style="color:#cbd5e1; margin:0 0.75rem;">|</span>
                        <label><input type="checkbox" name="fu[misc][symph_ventral]" value="1" <?php echo checked_v("misc.symph_ventral", "1"); ?>> <?php echo _t_v2('ra trước (ventral)', 'ventral', 'Ventral'); ?></label> <span style="color:#cbd5e1; margin:0 0.25rem;">/</span> 
                        <label><input type="checkbox" name="fu[misc][symph_cranial]" value="1" <?php echo checked_v("misc.symph_cranial", "1"); ?>> <?php echo _t_v2('lên trên (cranial)', 'cranial', 'Cranial'); ?></label> <span style="color:#cbd5e1; margin:0 0.25rem;">/</span> 
                        <label><input type="checkbox" name="fu[misc][symph_caudal]" value="1" <?php echo checked_v("misc.symph_caudal", "1"); ?>> <?php echo _t_v2('xuống dưới (caudal)', 'caudal', 'Caudal'); ?></label>
                    </div>
                </div>
                <div style="margin-top: 0.5rem; font-size: 0.85rem; font-weight: 600; color: #1e293b; line-height: 2.4;">
                    <div><?php echo _t_v2('Xương cụt (Os coccygis):', 'Os coccygis:', 'Coccyx (Os coccygis):'); ?> 
                        <span style="margin-left: 0.5rem;">
                            <label><input type="checkbox" name="fu[misc][coccyx_L]" value="1" <?php echo checked_v("misc.coccyx_L", "1"); ?>> <?php echo _t_v2('T', 'li', 'L'); ?></label>
                            <label><input type="checkbox" name="fu[misc][coccyx_R]" value="1" <?php echo checked_v("misc.coccyx_R", "1"); ?>> <?php echo _t_v2('P', 're', 'R'); ?></label>
                        </span>
                        <span style="color:#cbd5e1; margin:0 0.75rem;">|</span>
                        <label><input type="checkbox" name="fu[misc][coccyx_ventral]" value="1" <?php echo checked_v("misc.coccyx_ventral", "1"); ?>> <?php echo _t_v2('ra trước (ventral)', 'ventral', 'Ventral'); ?></label> <span style="color:#cbd5e1; margin:0 0.25rem;">/</span> 
                        <label><input type="checkbox" name="fu[misc][coccyx_cranial]" value="1" <?php echo checked_v("misc.coccyx_cranial", "1"); ?>> <?php echo _t_v2('lên trên (cranial)', 'cranial', 'Cranial'); ?></label> <span style="color:#cbd5e1; margin:0 0.25rem;">/</span> 
                        <label><input type="checkbox" name="fu[misc][coccyx_caudal]" value="1" <?php echo checked_v("misc.coccyx_caudal", "1"); ?>> <?php echo _t_v2('xuống dưới (caudal)', 'caudal', 'Caudal'); ?></label>
                    </div>
                </div>
            </div>

            <!-- Chi trên & Chi dưới -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 2.5rem;">
                <!-- CHI TRÊN -->
                <div>
                    <h4 style="color: #0284c7; font-size: 0.95rem; margin-bottom: 0.75rem;"><?php echo _t_v2('Danh sách điều trị – Chi trên (Behandlungsliste – Obere Extremitäten)', 'Behandlungsliste – Obere Extremitäten', 'Treatment List - Upper Extremities'); ?></h4>
                    <table class="fu-table">
                        <thead><tr><th width="15%"><?php echo _t_v2('T', 'li', 'L'); ?></th><th width="15%"><?php echo _t_v2('P', 're', 'R'); ?></th><th><?php echo _t_v2('Cấu trúc / Khớp (Struktur / Gelenk)', 'Struktur / Gelenk', 'Structure / Joint'); ?></th></tr></thead>
                        <tbody>
                            <?php 
                            $upper = [
                                'rib1' => _t_v2('Xương sườn 1 (Rippe 1)', 'Rippe 1', 'Rib 1'), 'biceps' => _t_v2('Cơ nhị đầu (Bizeps)', 'Bizeps', 'Biceps'), 'rotator' => _t_v2('Cơ chóp xoay (Rotatorenmanschette)', 'Rotatorenmanschette', 'Rotator Cuff'),
                                'acg' => _t_v2('Khớp cùng đòn (ACG)', 'ACG', 'AC Joint (ACG)'), 'scg' => _t_v2('Khớp ức đòn (SCG)', 'SCG', 'SC Joint (SCG)'), 'coracoid' => _t_v2('Mỏm quạ (Proc. coracoideus)', 'Proc. coracoideus', 'Coracoid Process'),
                                'supras' => _t_v2('Cơ trên gai (Supraspinatus)', 'Supraspinatus', 'Supraspinatus'),
                                'hwk' => _t_v2('Xương cổ tay / Hội chứng ống cổ tay (Handwurzelknochen / CTS)', 'Handwurzelknochen / CTS', 'Carpal Bones / CTS'), 'cts' => _t_v2('Hội chứng ống cổ tay (CTS)', 'CTS', 'CTS'),
                            ];
                            foreach($upper as $k => $lbl): ?>
                            <tr>
                                <td><input type="checkbox" name="fu[upper][<?php echo $k; ?>][L]" value="1" class="<?php echo checked_prev("upper.$k.L", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("upper.$k.L", "1"); ?>></td>
                                <td><input type="checkbox" name="fu[upper][<?php echo $k; ?>][R]" value="1" class="<?php echo checked_prev("upper.$k.R", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("upper.$k.R", "1"); ?>></td>
                                <td class="label-col"><?php echo $lbl; ?></td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <!-- Viêm lồi cầu -->
                            <tr>
                                <td><input type="checkbox" name="fu[upper][ta][L]" value="1" <?php echo checked_v("upper.ta.L", "1"); ?>></td>
                                <td><input type="checkbox" name="fu[upper][ta][R]" value="1" <?php echo checked_v("upper.ta.R", "1"); ?>></td>
                                <td class="label-col">
                                    <div style="margin-bottom: 0.5rem;"><?php echo _t_v2('Viêm lồi cầu ngoài (Tennisarm - TA):', 'Tennisarm (TA):', 'Tennis Elbow (TA):'); ?></div>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; font-weight: 400; font-size: 0.75rem;">
                                        <label><input type="checkbox" name="fu[upper][ta][opt][]" value="sau" <?php echo checked_v("upper.ta.opt", "sau"); ?>> <?php echo _t_v2('sau (post)', 'post', 'Posterior'); ?></label>
                                        <label><input type="checkbox" name="fu[upper][ta][opt][]" value="sauben" <?php echo checked_v("upper.ta.opt", "sauben"); ?>> sau-bên (post-lat)</label>
                                        <label><input type="checkbox" name="fu[upper][ta][opt][]" value="ben" <?php echo checked_v("upper.ta.opt", "ben"); ?>> bên (lat)</label>
                                        <label><input type="checkbox" name="fu[upper][ta][opt][]" value="truoc" <?php echo checked_v("upper.ta.opt", "truoc"); ?>> <?php echo _t_v2('trước (ant)', 'ant', 'Anterior'); ?></label>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td><input type="checkbox" name="fu[upper][ga][L]" value="1" <?php echo checked_v("upper.ga.L", "1"); ?>></td>
                                <td><input type="checkbox" name="fu[upper][ga][R]" value="1" <?php echo checked_v("upper.ga.R", "1"); ?>></td>
                                <td class="label-col">
                                    <div style="margin-bottom: 0.5rem;"><?php echo _t_v2('Viêm lồi cầu trong (Golferarm - GA):', 'Golferarm (GA):', 'Golfer\'s Elbow (GA):'); ?></div>
                                    <div style="display: flex; gap: 1rem; font-weight: 400; font-size: 0.75rem;">
                                        <label><input type="checkbox" name="fu[upper][ga][opt][]" value="trong" <?php echo checked_v("upper.ga.opt", "trong"); ?>> <?php echo _t_v2('trong (med)', 'med', 'Medial'); ?></label>
                                        <label><input type="checkbox" name="fu[upper][ga][opt][]" value="ngoai" <?php echo checked_v("upper.ga.opt", "ngoai"); ?>> <?php echo _t_v2('ngoài (lat)', 'lat', 'Lateral'); ?></label>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- Xương bàn tay & ngón -->
                            <tr>
                                <td><input type="checkbox" name="fu[upper][mtc][L]" value="1" <?php echo checked_v("upper.mtc.L", "1"); ?>></td>
                                <td><input type="checkbox" name="fu[upper][mtc][R]" value="1" <?php echo checked_v("upper.mtc.R", "1"); ?>></td>
                                <td class="label-col">
                                    <div style="margin-bottom: 0.5rem;"><?php echo _t_v2('Xương bàn tay (Metacarpalia):', 'Metacarpalia:', 'Metacarpals:'); ?></div>
                                    <div style="display: flex; gap: 1rem; font-weight: 400; font-size: 0.75rem;">
                                        <?php foreach([2,3,4,5] as $v): ?><label><input type="checkbox" name="fu[upper][mtc][opt][]" value="<?php echo $v; ?>" <?php echo checked_v("upper.mtc.opt", $v); ?>> <?php echo $v; ?></label><?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td><input type="checkbox" name="fu[upper][thumb][L]" value="1" <?php echo checked_v("upper.thumb.L", "1"); ?>></td>
                                <td><input type="checkbox" name="fu[upper][thumb][R]" value="1" <?php echo checked_v("upper.thumb.R", "1"); ?>></td>
                                <td class="label-col">
                                    <div style="margin-bottom: 0.5rem;"><?php echo _t_v2('Ngón cái (Daumen):', 'Daumen:', 'Thumb:'); ?></div>
                                    <div style="display: flex; flex-direction: column; gap: 0.75rem; font-weight: 400; font-size: 0.75rem;">
                                        <label><input type="checkbox" name="fu[upper][thumb][opt][]" value="sg" <?php echo checked_v("upper.thumb.opt", "sg"); ?>> <?php echo _t_v2('khớp yên (Sattelgelenk)', 'Sattelgelenk', 'Saddle Joint'); ?></label>
                                        <label><input type="checkbox" name="fu[upper][thumb][opt][]" value="gg" <?php echo checked_v("upper.thumb.opt", "gg"); ?>> <?php echo _t_v2('khớp bàn ngón (Grundgelenk)', 'Grundgelenk', 'Metacarpophalangeal Joint'); ?></label>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- CHI DƯỚI -->
                <div>
                    <h4 style="color: #0284c7; font-size: 0.95rem; margin-bottom: 0.75rem;"><?php echo _t_v2('Danh sách điều trị – Chi dưới (Behandlungsliste – Untere Extremitäten)', 'Behandlungsliste – Untere Extremitäten', 'Treatment List - Lower Extremities'); ?></h4>
                    <table class="fu-table">
                        <thead><tr><th width="15%"><?php echo _t_v2('T', 'li', 'L'); ?></th><th width="15%"><?php echo _t_v2('P', 're', 'R'); ?></th><th><?php echo _t_v2('Cấu trúc / Khớp (Struktur / Gelenk)', 'Struktur / Gelenk', 'Structure / Joint'); ?></th></tr></thead>
                        <tbody>
                            <tr>
                                <td><input type="checkbox" name="fu[lower][usg][L]" value="1" <?php echo checked_v("lower.usg.L", "1"); ?>></td>
                                <td><input type="checkbox" name="fu[lower][usg][R]" value="1" <?php echo checked_v("lower.usg.R", "1"); ?>></td>
                                <td class="label-col">
                                    <div style="margin-bottom: 0.5rem;"><?php echo _t_v2('Khớp cổ chân dưới (USG):', 'USG:', 'Lower Ankle Joint (USG):'); ?></div>
                                    <div style="display: flex; gap: 1.5rem; font-weight: 400; font-size: 0.75rem;">
                                        <label><input type="checkbox" name="fu[lower][usg][opt][]" value="sup" <?php echo checked_v("lower.usg.opt", "sup"); ?>> <?php echo _t_v2('ngửa (sup)', 'sup', 'Supination (sup)'); ?></label>
                                        <label><input type="checkbox" name="fu[lower][usg][opt][]" value="pron" <?php echo checked_v("lower.usg.opt", "pron"); ?>> <?php echo _t_v2('sấp (pron)', 'pron', 'Pronation (pron)'); ?></label>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td><input type="checkbox" name="fu[lower][osg][L]" value="1" <?php echo checked_v("lower.osg.L", "1"); ?>></td>
                                <td><input type="checkbox" name="fu[lower][osg][R]" value="1" <?php echo checked_v("lower.osg.R", "1"); ?>></td>
                                <td class="label-col">
                                    <div style="margin-bottom: 0.5rem;"><?php echo _t_v2('Khớp cổ chân trên (OSG):', 'OSG:', 'Upper Ankle Joint (OSG):'); ?></div>
                                    <div style="display: flex; gap: 1.5rem; font-weight: 400; font-size: 0.75rem;">
                                        <label><input type="checkbox" name="fu[lower][osg][opt][]" value="post" <?php echo checked_v("lower.osg.opt", "post"); ?>> <?php echo _t_v2('sau (post)', 'post', 'Posterior'); ?></label>
                                        <label><input type="checkbox" name="fu[lower][osg][opt][]" value="ant" <?php echo checked_v("lower.osg.opt", "ant"); ?>> <?php echo _t_v2('trước (ant)', 'ant', 'Anterior'); ?></label>
                                    </div>
                                </td>
                            </tr>
                            <?php 
                            $lower_simple = [
                                'cuboid' => _t_v2('Xương hộp (Os cuboideum)', 'Os cuboideum', 'Cuboid Bone'), 'naviculare' => _t_v2('Xương ghe (Os naviculare)', 'Os naviculare', 'Navicular Bone'), 'hallux' => _t_v2('Ngón cái vẹo ngoài (Hallux valgus)', 'Hallux valgus', 'Hallux Valgus'),
                                'knee' => _t_v2('Khớp gối (Kniegelenk)', 'Kniegelenk', 'Knee Joint'), 'patella' => _t_v2('Di động xương bánh chè (Patellamobilität)', 'Patellamobilität', 'Patellar Mobility'), 'hip' => _t_v2('Khớp háng (Hüftgelenk)', 'Hüftgelenk', 'Hip Joint'), 'iliopsoas' => _t_v2('Cơ thắt lưng chậu', 'Iliopsoas', 'Iliopsoas')
                            ];
                            foreach($lower_simple as $k => $lbl): ?>
                            <tr>
                                <td><input type="checkbox" name="fu[lower][<?php echo $k; ?>][L]" value="1" class="<?php echo checked_prev("lower.$k.L", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("lower.$k.L", "1"); ?>></td>
                                <td><input type="checkbox" name="fu[lower][<?php echo $k; ?>][R]" value="1" class="<?php echo checked_prev("lower.$k.R", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("lower.$k.R", "1"); ?>></td>
                                <td class="label-col"><?php echo $lbl; ?></td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <tr>
                                <td><input type="checkbox" name="fu[lower][cuneiforme][L]" value="1" <?php echo checked_v("lower.cuneiforme.L", "1"); ?>></td>
                                <td><input type="checkbox" name="fu[lower][cuneiforme][R]" value="1" <?php echo checked_v("lower.cuneiforme.R", "1"); ?>></td>
                                <td class="label-col">
                                    <div style="margin-bottom: 0.5rem;"><?php echo _t_v2('Xương chêm (Ossa cuneiformia):', 'Ossa cuneiformia:', 'Cuneiform Bones:'); ?></div>
                                    <div style="display: flex; flex-wrap: wrap; gap: 1.5rem; font-weight: 400; font-size: 0.75rem;">
                                        <label><input type="checkbox" name="fu[lower][cuneiforme][opt][]" value="trong" <?php echo checked_v("lower.cuneiforme.opt", "trong"); ?>> <?php echo _t_v2('trong (mediale)', 'mediale', 'Medial'); ?></label>
                                        <label><input type="checkbox" name="fu[lower][cuneiforme][opt][]" value="giua" <?php echo checked_v("lower.cuneiforme.opt", "giua"); ?>> <?php echo _t_v2('giữa (intermedium)', 'intermedium', 'Intermediate'); ?></label>
                                        <label><input type="checkbox" name="fu[lower][cuneiforme][opt][]" value="ngoai" <?php echo checked_v("lower.cuneiforme.opt", "ngoai"); ?>> <?php echo _t_v2('ngoài (laterale)', 'laterale', 'Lateral'); ?></label>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td><input type="checkbox" name="fu[lower][mtt][L]" value="1" <?php echo checked_v("lower.mtt.L", "1"); ?>></td>
                                <td><input type="checkbox" name="fu[lower][mtt][R]" value="1" <?php echo checked_v("lower.mtt.R", "1"); ?>></td>
                                <td class="label-col">
                                    <div style="margin-bottom: 0.5rem;"><?php echo _t_v2('Xương bàn chân (Metatarsalia - MTT):', 'Metatarsalia (MTT):', 'Metatarsals (MTT):'); ?></div>
                                    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 1.5rem; font-weight: 400; font-size: 0.75rem;">
                                        <div style="display: flex; gap: 1rem;">
                                            <?php foreach([1,2,3,4,5] as $v): ?><label><input type="checkbox" name="fu[lower][mtt][num][]" value="<?php echo $v; ?>" <?php echo checked_v("lower.mtt.num", $v); ?>><?php echo $v; ?></label><?php endforeach; ?>
                                        </div>
                                        <span style="color:#cbd5e1;">|</span>
                                        <div style="display: flex; gap: 1rem;">
                                            <label><input type="checkbox" name="fu[lower][mtt][opt][]" value="mu" <?php echo checked_v("lower.mtt.opt", "mu"); ?>> <?php echo _t_v2('mu (dorsal)', 'dorsal', 'Dorsal'); ?></label>
                                            <label><input type="checkbox" name="fu[lower][mtt][opt][]" value="gan" <?php echo checked_v("lower.mtt.opt", "gan"); ?>> <?php echo _t_v2('gan (plantar)', 'plantar', 'Plantar'); ?></label>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- BOTTOM NAVIGATION -->
        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 2px solid #e2e8f0; padding-top: 2rem; margin-top: 3rem;">
            <div style="display: flex; gap: 0.5rem;">
                <?php if ($prev_id): ?>
                    <a href="?patient_id=<?php echo $patient_id; ?>&id=<?php echo $prev_id; ?>" class="btn" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; font-weight: 600;"><i class="fas fa-chevron-left"></i> <?php echo _t_v2('Trước', 'Zurück', 'Back'); ?></a>
                <?php endif; ?>
                <?php if ($next_id): ?>
                    <a href="?patient_id=<?php echo $patient_id; ?>&id=<?php echo $next_id; ?>" class="btn" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; font-weight: 600;"><?php echo _t_v2('Sau', 'Weiter', 'Next'); ?> <i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
            <div style="display: flex; gap: 1.5rem;">
                <a href="../patients/view.php?id=<?php echo $patient_id; ?>" class="btn" style="background: #f1f5f9; color: var(--text-main); padding: 1.25rem 3rem; font-weight: 700; border-radius: 12px;"><?php echo _t_v2('Hủy', 'Abbrechen', 'Cancel'); ?></a>
                <button type="submit" class="btn btn-primary" style="padding: 1.25rem 5rem; font-weight: 800; font-size: 1.15rem; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.4);">
                    <i class="fas fa-save" style="margin-right: 0.5rem;"></i> <?php echo _t_v2('Lưu Follow-Up', 'Speichern', 'Save'); ?>
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Scripts for Marking -->
<script src="../../assets/js/medical_marking.js?v=20260728"></script>
<script>
let newMarking;
let currentIntensity = 'M1';

function setPainColor(intensity, btnEl) {
    currentIntensity = intensity;
    document.querySelectorAll('.pain-level-btn').forEach(b => b.classList.remove('active'));
    btnEl.classList.add('active');
    
    // Update the marking JS current intensity
    if (newMarking) {
        newMarking.currentIntensity = intensity;
        newMarking.currentTool = 'marker';
        newMarking.isEraser = false;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // 1. Setup Old Canvas (Read-only)
    const oldMarkersData = <?php echo json_encode(!empty($prev_fu_markers) ? $prev_fu_markers : $history_markers); ?>;
    const oldMarking = new MedicalMarking('canvas-old', null, '../../assets/images/anatomy_4_views_clean.png');
    // Override colors for the old canvas too so they show correctly if they were using standard M1-M5
    // But since they might have old colors, we leave them as is. If we want we can override:
    oldMarking.colors = {
        'M1': '#38bdf8', // Xanh biển (Đã đỡ nhiều)
        'M2': '#4ade80', // Xanh lá (Đỡ ít hơn)
        'M3': '#fbbf24', // Vàng (Vẫn đau)
        'M4': '#fb923c', // Cam (Đau hơn cũ)
        'M5': '#ef4444'  // Đỏ (Đau hơn nhiều)
    };
    setTimeout(() => {
        oldMarking.markers = oldMarkersData;
        oldMarking.redraw();
        // Prevent drawing on old canvas
        oldMarking.canvas.style.pointerEvents = 'none'; 
    }, 500);
    
    // 2. Setup New Canvas (Interactive)
    const newInherited = <?php echo json_encode($inherited_markers); ?>;
    const newExisting = <?php echo json_encode(get_v('markers', [])); ?>;
    
    newMarking = new MedicalMarking('canvas-new', 'new-markers-data', '../../assets/images/anatomy_4_views_clean.png');
    // Override colors
    newMarking.colors = {
        'M1': '#38bdf8', // Xanh biển
        'M2': '#4ade80', // Xanh lá
        'M3': '#fbbf24', // Vàng
        'M4': '#fb923c', // Cam
        'M5': '#ef4444'  // Đỏ
    };
    newMarking.currentIntensity = currentIntensity;
    newMarking.currentTool = 'marker';
    
    setTimeout(() => {
        if (newExisting.length > 0) {
            newMarking.markers = newExisting;
        } else {
            newMarking.markers = newInherited;
        }
        newMarking.redraw();
        newMarking.updateInput();
    }, 500);
});

// Custom undo & clear logic
function undoNewMarking() {
    if (newMarking && newMarking.markers.length > 0) {
        newMarking.markers.pop();
        newMarking.updateInput();
        newMarking.redraw();
    }
}
function clearNewMarking() {
    if (newMarking && confirm('Xóa toàn bộ đánh dấu hiện tại?')) {
        // Keep only inherited (O, X)
        const newInherited = <?php echo json_encode($inherited_markers); ?>;
        newMarking.markers = newInherited;
        newMarking.updateInput();
        newMarking.redraw();
    }
}
</script>

<?php require_once '../../templates/footer.php'; ?>
