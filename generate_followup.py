# coding=utf-8
import os

php_content = """<?php
// modules/medical/follow_up_v2.php

require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_medical');

$patient_id = (int)($_GET['patient_id'] ?? 0);
$session_id = isset($_GET['session_id']) ? (int)$_GET['session_id'] : null;
$record_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$db = getDB();

// 1. Fetch Patient Info
$stmt = $db->prepare("SELECT full_name, gender, dob FROM patients WHERE id = ?");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$patient) {
    set_flash('Không tìm thấy bệnh nhân!', 'error');
    redirect('index.php');
}
$age = $patient['dob'] ? date_diff(date_create($patient['dob']), date_create('today'))->y : '?';
$gender_text = $patient['gender'] == 'Male' ? 'Nam' : ($patient['gender'] == 'Female' ? 'Nữ' : 'Khác');

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
    WHERE patient_id = ? AND type IN ('pathologie_v2', 'chiro_history_v2') " . ($record_id ? "AND id < $record_id" : "") . " 
    ORDER BY id DESC LIMIT 1
");
$stmt->execute([$patient_id]);
$history_record = $stmt->fetch(PDO::FETCH_ASSOC);
$history_note = '';
$history_date = '';
$history_markers = [];
if ($history_record) {
    $hd = json_decode($history_record['history_data'], true) ?: [];
    $history_note = $hd['akt_path']['notes'] ?? '';
    $history_date = date('d/m/Y', strtotime($history_record['created_at']));
    $history_markers = $hd['markers'] ?? [];
}

// 5. Fetch previous Follow-Up for "Ghi chú lần điều trị gần nhất" and Previous Markers
$stmt = $db->prepare("
    SELECT history_data, created_at FROM medical_history 
    WHERE patient_id = ? AND type = 'soap_note_v2' " . ($record_id ? "AND id < $record_id" : "") . " 
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
    $prev_fu_note = $pfd['today_notes'] ?? '';
    $prev_fu_date = date('d/m/Y', strtotime($prev_fu_record['created_at']));
    $prev_fu_markers = $pfd['markers'] ?? [];
}

// Inherit O/X markers for the NEW canvas
$inherited_markers = [];
$source_markers = !empty($prev_fu_markers) ? $prev_fu_markers : $history_markers;
foreach ($source_markers as $m) {
    if ($m['type'] === 'O' || $m['type'] === 'X') {
        $inherited_markers[] = $m; // copy over
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

$page_title = 'Follow-up V2 (Mẫu Mới)';
$current_page = 'medical';
require_once '../../templates/header.php';

// Helper for checked state
function get_v($path, $default = '') {
    global $existing_data;
    $keys = explode('.', $path);
    $curr = $existing_data;
    foreach($keys as $k) {
        if(!isset($curr[$k])) return $default;
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
        if(!isset($curr[$k])) return false;
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

/* Custom Checkbox Checkmark inside Table */
.fu-table input[type="checkbox"] {
    appearance: none; -webkit-appearance: none; width: 22px; height: 22px; border: 2px solid #cbd5e1; border-radius: 6px; outline: none; cursor: pointer; transition: all 0.2s; position: relative; background: #fff; margin: 0 auto; display: block;
}
.fu-table input[type="checkbox"]:checked {
    background: #3b82f6; border-color: #3b82f6;
}
.fu-table input[type="checkbox"]:checked::after {
    content: '\\f00c'; font-family: 'Font Awesome 5 Free'; font-weight: 900; color: white; font-size: 12px; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
}

/* Grayed out 'Prev' state if it was checked last time but not checked now */
.fu-table input[type="checkbox"].was-checked-prev:not(:checked) {
    background: #f1f5f9; border-color: #94a3b8; opacity: 0.7;
}
.fu-table input[type="checkbox"].was-checked-prev:not(:checked)::after {
    content: '\\f00c'; font-family: 'Font Awesome 5 Free'; font-weight: 900; color: #94a3b8; font-size: 12px; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
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

<div style="max-width: 1100px; margin: 0 auto;">

    <!-- TOP NAVIGATION & HEADER -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1 style="margin: 0; font-size: 1.5rem; font-weight: 900; color: #1e293b;">Follow-up V2 (Mẫu Mới)</h1>
            <div style="margin-top: 0.5rem; display: flex; gap: 1.5rem; font-size: 0.9rem; color: #475569;">
                <div><i class="fas fa-user-circle"></i> <strong><?php echo e($patient['full_name']); ?></strong> (<?php echo $gender_text; ?>, <?php echo $age; ?> tuổi)</div>
                <div><i class="fas fa-calendar-check"></i> Điều trị gần nhất: <strong><?php echo $prev_fu_date ?: '-'; ?></strong></div>
            </div>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <a href="?patient_id=<?php echo $patient_id; ?><?php echo $prev_id ? '&id='.$prev_id : ''; ?>" class="btn" style="background: <?php echo $prev_id ? '#fff' : '#f1f5f9'; ?>; border: 1px solid #e2e8f0; border-radius: 8px; font-weight: 600; <?php echo $prev_id ? '' : 'pointer-events: none; opacity: 0.5;'; ?>"><i class="fas fa-chevron-left"></i> Trước</a>
            <a href="?patient_id=<?php echo $patient_id; ?><?php echo $next_id ? '&id='.$next_id : ''; ?>" class="btn" style="background: <?php echo $next_id ? '#fff' : '#f1f5f9'; ?>; border: 1px solid #e2e8f0; border-radius: 8px; font-weight: 600; <?php echo $next_id ? '' : 'pointer-events: none; opacity: 0.5;'; ?>">Sau <i class="fas fa-chevron-right"></i></a>
            <?php if ($record_id): ?>
                <a href="?patient_id=<?php echo $patient_id; ?>" class="btn btn-primary" style="border-radius: 8px; font-weight: 600;"><i class="fas fa-plus"></i> Tạo mới</a>
            <?php endif; ?>
        </div>
    </div>

    <form method="POST">
        <?php echo csrf_field(); ?>
        
        <!-- SECTION 1: GHI CHÚ CŨ -->
        <div class="fu-card" style="background: #f8fafc; border: 1px dashed #cbd5e1;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div>
                    <label style="font-weight: 700; font-size: 0.85rem; color: #64748b; margin-bottom: 0.5rem; display: block;">[ Ghi chú từ Bệnh sử ] <?php echo $history_date ? "(Kèm ngày: $history_date)" : ''; ?></label>
                    <textarea class="fu-textarea readonly" rows="4" readonly><?php echo e($history_note); ?></textarea>
                </div>
                <div>
                    <label style="font-weight: 700; font-size: 0.85rem; color: #64748b; margin-bottom: 0.5rem; display: block;">[ Ghi chú lần điều trị gần nhất ] <?php echo $prev_fu_date ? "(Kèm ngày: $prev_fu_date)" : ''; ?></label>
                    <textarea class="fu-textarea readonly" rows="4" readonly><?php echo e($prev_fu_note); ?></textarea>
                </div>
            </div>
        </div>

        <!-- SECTION 2: HÌNH VẼ SO SÁNH -->
        <div class="fu-card">
            <h3 class="fu-title"><i class="fas fa-draw-polygon" style="color: #8b5cf6;"></i> Hình vẽ so sánh (Vergleichsskizzen)</h3>
            <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 1.5rem; line-height: 1.5;">Hình bên trái là lần trước (chỉ xem), hình bên phải là lần này. Hình lần này tự động kế thừa các đánh dấu phẫu thuật (O) và gãy xương (X) của lần trước.</p>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <!-- OLD CANVAS -->
                <div>
                    <div style="text-align: center; font-weight: 700; margin-bottom: 0.5rem; color: #64748b;">Lần trước (<?php echo $prev_fu_date ?: $history_date; ?>)</div>
                    <div style="border: 2px solid #e2e8f0; border-radius: 12px; background: white; padding: 10px; opacity: 0.7; pointer-events: none;">
                        <canvas id="canvas-old" width="800" height="800" style="width: 100%; height: auto; display: block;"></canvas>
                    </div>
                </div>
                
                <!-- NEW CANVAS -->
                <div>
                    <div style="text-align: center; font-weight: 800; margin-bottom: 0.5rem; color: #1e293b;">Lần này</div>
                    
                    <!-- Color Palette 5 Levels -->
                    <div style="display: flex; gap: 0.75rem; justify-content: center; margin-bottom: 1rem; background: #f8fafc; padding: 0.75rem; border-radius: 50px;">
                        <!-- Custom colors for this canvas. Note: The marking JS needs these colors -->
                        <div class="pain-level-btn active" style="background: #fbbf24; color: #fbbf24;" onclick="setPainColor('#fbbf24', this)" title="Vẫn đau như lần trước"></div>
                        <div class="pain-level-btn" style="background: #38bdf8; color: #38bdf8;" onclick="setPainColor('#38bdf8', this)" title="Đã đỡ nhiều"></div>
                        <div class="pain-level-btn" style="background: #4ade80; color: #4ade80;" onclick="setPainColor('#4ade80', this)" title="Đỡ ít hơn"></div>
                        <div class="pain-level-btn" style="background: #fb923c; color: #fb923c;" onclick="setPainColor('#fb923c', this)" title="Đau hơn cũ 1 chút"></div>
                        <div class="pain-level-btn" style="background: #ef4444; color: #ef4444;" onclick="setPainColor('#ef4444', this)" title="Đau hơn nhiều"></div>
                    </div>
                    
                    <div style="border: 2px solid #3b82f6; border-radius: 12px; background: white; padding: 10px; box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.1);">
                        <canvas id="canvas-new" width="800" height="800" style="width: 100%; height: auto; display: block; cursor: crosshair;"></canvas>
                        <input type="hidden" name="fu[markers]" id="new-markers-data">
                        <div style="display: flex; justify-content: flex-end; margin-top: 0.5rem; gap: 0.5rem;">
                            <button type="button" class="btn btn-sm" onclick="newMarking.undo()" style="background: #f1f5f9; color: #475569; font-size: 0.75rem;"><i class="fas fa-undo"></i> Undo</button>
                            <button type="button" class="btn btn-sm" onclick="newMarking.clear()" style="background: #fee2e2; color: #ef4444; font-size: 0.75rem;"><i class="fas fa-trash"></i> Xóa hết</button>
                        </div>
                    </div>
                </div>
            </div>

            <div style="margin-top: 2rem;">
                <label style="font-weight: 800; font-size: 0.95rem; color: #1e293b; margin-bottom: 0.5rem; display: block;">[ Ghi chú cho buổi điều trị này ]</label>
                <textarea name="fu[today_notes]" class="fu-textarea" rows="4" placeholder="Nội dung sẽ hiển thị ở buổi điều trị kế tiếp..."><?php echo e(get_v('today_notes')); ?></textarea>
            </div>
            
            <div style="margin-top: 1.5rem; display: flex; align-items: center; gap: 1rem; background: #f8fafc; padding: 1rem; border-radius: 12px; border: 1px solid #e2e8f0;">
                <label style="font-weight: 800; color: #1e293b; margin: 0;">Diễn tiến so với lần trước:</label>
                <select name="fu[progress]" class="fu-textarea" style="width: auto; padding: 0.5rem 1rem; margin: 0;">
                    <option value="">-- Chọn --</option>
                    <option value="Tốt lên" <?php echo checked_v('progress', 'Tốt lên') ? 'selected' : ''; ?>>📈 Tốt lên</option>
                    <option value="Giữ nguyên" <?php echo checked_v('progress', 'Giữ nguyên') ? 'selected' : ''; ?>>➖ Giữ nguyên</option>
                    <option value="Tệ đi" <?php echo checked_v('progress', 'Tệ đi') ? 'selected' : ''; ?>>📉 Tệ đi</option>
                </select>
            </div>
        </div>
"""
php_content += """
        <!-- SECTION 3: BẢNG NẮN CHỈNH XƯƠNG KHỚP -->
        <div class="fu-card">
            <h3 class="fu-title"><i class="fas fa-bone" style="color: #10b981;"></i> Danh sách Điều trị & Nắn chỉnh</h3>
            <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 1.5rem;">Lưu ý: Các ô <strong style="color: #94a3b8;">màu xám</strong> là các vị trí đã được nắn chỉnh trong lần điều trị gần nhất. Bạn có thể check đè lên để cập nhật cho lần này.</p>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <!-- Cột Sống Cổ (CSC) -->
                <div>
                    <h4 style="color: #0284c7; font-size: 0.95rem; margin-bottom: 0.75rem;">Vùng nắn chỉnh - Cột sống cổ (CSC)</h4>
                    <table class="fu-table">
                        <thead><tr><th width="15%">T</th><th width="15%">P</th><th>Vị trí</th></tr></thead>
                        <tbody>
                            <?php 
                            $csc_list = [
                                'occiput' => 'Xương chẩm (Occiput)', 'tmj' => 'Khớp thái dương hàm (TMJ)', 
                                'c1' => 'Đốt đội - C1 (Atlas)', 'c2' => 'Đốt trục - C2 (Axis)', 
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
                    <h4 style="color: #0284c7; font-size: 0.95rem; margin-bottom: 0.75rem;">Vùng nắn chỉnh - Cột sống thắt lưng (CSTL)</h4>
                    <table class="fu-table">
                        <thead><tr><th width="15%">T</th><th width="15%">P</th><th>Vị trí</th></tr></thead>
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
                    <h4 style="color: #0284c7; font-size: 0.95rem; margin-bottom: 0.75rem;">Vùng nắn chỉnh - Cột sống ngực (CSN)</h4>
                    <table class="fu-table">
                        <thead><tr><th width="15%">T</th><th width="15%">P</th><th width="30%">Đốt sống</th><th width="20%">Trước</th><th width="20%">Sau</th></tr></thead>
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
                    <h4 style="color: #0284c7; font-size: 0.95rem; margin-bottom: 0.75rem;">Xương sườn (Rippen)</h4>
                    <table class="fu-table">
                        <thead><tr><th width="18%">Sau - T</th><th width="18%">Sau - P</th><th width="28%">Sườn</th><th width="18%">Trước - T</th><th width="18%">Trước - P</th></tr></thead>
                        <tbody>
                            <?php for($i=1; $i<=12; $i++): $k="r$i"; ?>
                            <tr>
                                <td><input type="checkbox" name="fu[ribs][<?php echo $k; ?>][back_L]" value="1" class="<?php echo checked_prev("ribs.$k.back_L", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("ribs.$k.back_L", "1"); ?>></td>
                                <td><input type="checkbox" name="fu[ribs][<?php echo $k; ?>][back_R]" value="1" class="<?php echo checked_prev("ribs.$k.back_R", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("ribs.$k.back_R", "1"); ?>></td>
                                <td class="label-col" style="text-align: center;">T<?php echo $i; ?></td>
                                <?php if($i<=10): ?>
                                <td><input type="checkbox" name="fu[ribs][<?php echo $k; ?>][front_L]" value="1" class="<?php echo checked_prev("ribs.$k.front_L", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("ribs.$k.front_L", "1"); ?>></td>
                                <td><input type="checkbox" name="fu[ribs][<?php echo $k; ?>][front_R]" value="1" class="<?php echo checked_prev("ribs.$k.front_R", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("ribs.$k.front_R", "1"); ?>></td>
                                <?php else: ?>
                                <td style="background: #f8fafc;"></td><td style="background: #f8fafc;"></td>
                                <?php endif; ?>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Khung chậu & Xương cùng -->
            <div style="margin-top: 2rem;">
                <h4 style="color: #0284c7; font-size: 0.95rem; margin-bottom: 0.75rem;">Khớp cùng-chậu (ISG) & Khung chậu (Becken)</h4>
                <table class="fu-table">
                    <thead>
                        <tr><th width="15%">Khung chậu</th><th>AS</th><th>PI</th><th>IN-Ilium</th><th>EX-Ilium</th><th>Up-Slip</th><th>Down-Slip</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach(['L'=>'Trái', 'R'=>'Phải'] as $k => $label): ?>
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
                <h4 style="color: #0284c7; font-size: 0.95rem; margin-bottom: 0.75rem;">Xương cùng (Sacrum)</h4>
                <table class="fu-table" style="text-align: center;">
                    <tbody>
                        <?php for($r=0; $r<3; $r++): ?>
                        <tr>
                            <?php for($c=0; $c<3; $c++): $key="s_{$r}_{$c}"; ?>
                            <td><input type="checkbox" name="fu[sacrum][<?php echo $key; ?>]" value="1" class="<?php echo checked_prev("sacrum.$key", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("sacrum.$key", "1"); ?>></td>
                            <?php endfor; ?>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
                
                <div style="display: flex; gap: 2rem; margin-top: 1rem; font-size: 0.85rem; font-weight: 600; color: #1e293b;">
                    <div>Khớp mu: 
                        <label><input type="checkbox" name="fu[misc][symph_L]" value="1" class="<?php echo checked_prev("misc.symph_L", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("misc.symph_L", "1"); ?>> T</label>
                        <label><input type="checkbox" name="fu[misc][symph_R]" value="1" class="<?php echo checked_prev("misc.symph_R", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("misc.symph_R", "1"); ?>> P</label>
                        <span style="color:#cbd5e1; margin:0 0.5rem;">|</span>
                        <label><input type="checkbox" name="fu[misc][symph_ventral]" value="1" <?php echo checked_v("misc.symph_ventral", "1"); ?>> ra trước</label> / 
                        <label><input type="checkbox" name="fu[misc][symph_cranial]" value="1" <?php echo checked_v("misc.symph_cranial", "1"); ?>> lên trên</label> / 
                        <label><input type="checkbox" name="fu[misc][symph_caudal]" value="1" <?php echo checked_v("misc.symph_caudal", "1"); ?>> xuống dưới</label>
                    </div>
                </div>
                <div style="display: flex; gap: 2rem; margin-top: 0.5rem; font-size: 0.85rem; font-weight: 600; color: #1e293b;">
                    <div>Xương cụt: 
                        <label><input type="checkbox" name="fu[misc][coccyx_L]" value="1" <?php echo checked_v("misc.coccyx_L", "1"); ?>> T</label>
                        <label><input type="checkbox" name="fu[misc][coccyx_R]" value="1" <?php echo checked_v("misc.coccyx_R", "1"); ?>> P</label>
                        <span style="color:#cbd5e1; margin:0 0.5rem;">|</span>
                        <label><input type="checkbox" name="fu[misc][coccyx_ventral]" value="1" <?php echo checked_v("misc.coccyx_ventral", "1"); ?>> ra trước</label> / 
                        <label><input type="checkbox" name="fu[misc][coccyx_cranial]" value="1" <?php echo checked_v("misc.coccyx_cranial", "1"); ?>> lên trên</label> / 
                        <label><input type="checkbox" name="fu[misc][coccyx_caudal]" value="1" <?php echo checked_v("misc.coccyx_caudal", "1"); ?>> xuống dưới</label>
                    </div>
                </div>
            </div>

            <!-- Chi trên & Chi dưới -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 2.5rem;">
                <!-- CHI TRÊN -->
                <div>
                    <h4 style="color: #0284c7; font-size: 0.95rem; margin-bottom: 0.75rem;">Danh sách điều trị – Chi trên</h4>
                    <table class="fu-table">
                        <thead><tr><th width="20%">T | P</th><th>Cấu trúc / Khớp</th></tr></thead>
                        <tbody>
                            <?php 
                            $upper = [
                                'rib1' => 'Xương sườn 1', 'biceps' => 'Cơ nhị đầu', 'rotator' => 'Cơ chóp xoay',
                                'acg' => 'Khớp cùng–đòn (ACG)', 'scg' => 'Khớp ức–đòn (SCG)', 'coracoid' => 'Mỏm quạ',
                                'supras' => 'Cơ trên gai',
                                'hwk' => 'Xương cổ tay / Hội chứng ống cổ tay', 'cts' => 'Hội chứng ống cổ tay',
                            ];
                            foreach($upper as $k => $lbl): ?>
                            <tr>
                                <td>
                                    <div style="display:flex; justify-content:center; gap:0.5rem;">
                                        <input type="checkbox" name="fu[upper][<?php echo $k; ?>][L]" value="1" class="<?php echo checked_prev("upper.$k.L", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("upper.$k.L", "1"); ?>>
                                        <span style="color:#94a3b8;">|</span>
                                        <input type="checkbox" name="fu[upper][<?php echo $k; ?>][R]" value="1" class="<?php echo checked_prev("upper.$k.R", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("upper.$k.R", "1"); ?>>
                                    </div>
                                </td>
                                <td class="label-col"><?php echo $lbl; ?></td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <!-- Viêm lồi cầu -->
                            <tr>
                                <td>
                                    <div style="display:flex; justify-content:center; gap:0.5rem;">
                                        <input type="checkbox" name="fu[upper][ta][L]" value="1" <?php echo checked_v("upper.ta.L", "1"); ?>>
                                        <span style="color:#94a3b8;">|</span>
                                        <input type="checkbox" name="fu[upper][ta][R]" value="1" <?php echo checked_v("upper.ta.R", "1"); ?>>
                                    </div>
                                </td>
                                <td class="label-col">
                                    Viêm lồi cầu ngoài (TA): 
                                    <span style="font-weight: 400; font-size: 0.75rem; margin-left: 0.5rem;">
                                        <label><input type="checkbox" name="fu[upper][ta][opt][]" value="sau" <?php echo checked_v("upper.ta.opt", "sau"); ?>> sau</label>
                                        <label><input type="checkbox" name="fu[upper][ta][opt][]" value="sauben" <?php echo checked_v("upper.ta.opt", "sauben"); ?>> sau-bên</label>
                                        <label><input type="checkbox" name="fu[upper][ta][opt][]" value="ben" <?php echo checked_v("upper.ta.opt", "ben"); ?>> bên</label>
                                        <label><input type="checkbox" name="fu[upper][ta][opt][]" value="truoc" <?php echo checked_v("upper.ta.opt", "truoc"); ?>> trước</label>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div style="display:flex; justify-content:center; gap:0.5rem;">
                                        <input type="checkbox" name="fu[upper][ga][L]" value="1" <?php echo checked_v("upper.ga.L", "1"); ?>>
                                        <span style="color:#94a3b8;">|</span>
                                        <input type="checkbox" name="fu[upper][ga][R]" value="1" <?php echo checked_v("upper.ga.R", "1"); ?>>
                                    </div>
                                </td>
                                <td class="label-col">
                                    Viêm lồi cầu trong (GA): 
                                    <span style="font-weight: 400; font-size: 0.75rem; margin-left: 0.5rem;">
                                        <label><input type="checkbox" name="fu[upper][ga][opt][]" value="trong" <?php echo checked_v("upper.ga.opt", "trong"); ?>> trong</label>
                                        <label><input type="checkbox" name="fu[upper][ga][opt][]" value="ngoai" <?php echo checked_v("upper.ga.opt", "ngoai"); ?>> ngoài</label>
                                    </span>
                                </td>
                            </tr>
                            
                            <!-- Xương bàn tay & ngón -->
                            <tr>
                                <td>
                                    <div style="display:flex; justify-content:center; gap:0.5rem;">
                                        <input type="checkbox" name="fu[upper][mtc][L]" value="1" <?php echo checked_v("upper.mtc.L", "1"); ?>> <span style="color:#94a3b8;">|</span> <input type="checkbox" name="fu[upper][mtc][R]" value="1" <?php echo checked_v("upper.mtc.R", "1"); ?>>
                                    </div>
                                </td>
                                <td class="label-col">Xương bàn tay: <span style="font-weight:400; font-size:0.75rem;">
                                    <?php foreach([2,3,4,5] as $v): ?><label><input type="checkbox" name="fu[upper][mtc][opt][]" value="<?php echo $v; ?>" <?php echo checked_v("upper.mtc.opt", $v); ?>> <?php echo $v; ?></label> <?php endforeach; ?>
                                </span></td>
                            </tr>
                            <tr>
                                <td>
                                    <div style="display:flex; justify-content:center; gap:0.5rem;">
                                        <input type="checkbox" name="fu[upper][thumb][L]" value="1" <?php echo checked_v("upper.thumb.L", "1"); ?>> <span style="color:#94a3b8;">|</span> <input type="checkbox" name="fu[upper][thumb][R]" value="1" <?php echo checked_v("upper.thumb.R", "1"); ?>>
                                    </div>
                                </td>
                                <td class="label-col">Ngón cái: <span style="font-weight:400; font-size:0.75rem;">
                                    <label><input type="checkbox" name="fu[upper][thumb][opt][]" value="sg" <?php echo checked_v("upper.thumb.opt", "sg"); ?>> khớp yên</label>
                                    <label><input type="checkbox" name="fu[upper][thumb][opt][]" value="gg" <?php echo checked_v("upper.thumb.opt", "gg"); ?>> khớp bàn ngón</label>
                                </span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- CHI DƯỚI -->
                <div>
                    <h4 style="color: #0284c7; font-size: 0.95rem; margin-bottom: 0.75rem;">Danh sách điều trị – Chi dưới</h4>
                    <table class="fu-table">
                        <thead><tr><th width="20%">T | P</th><th>Cấu trúc / Khớp</th></tr></thead>
                        <tbody>
                            <tr>
                                <td><div style="display:flex; justify-content:center; gap:0.5rem;"><input type="checkbox" name="fu[lower][usg][L]" value="1" <?php echo checked_v("lower.usg.L", "1"); ?>> <span style="color:#94a3b8;">|</span> <input type="checkbox" name="fu[lower][usg][R]" value="1" <?php echo checked_v("lower.usg.R", "1"); ?>></div></td>
                                <td class="label-col">Khớp cổ chân dưới: <span style="font-weight:400; font-size:0.75rem;"><label><input type="checkbox" name="fu[lower][usg][opt][]" value="sup" <?php echo checked_v("lower.usg.opt", "sup"); ?>> ngửa</label> <label><input type="checkbox" name="fu[lower][usg][opt][]" value="pron" <?php echo checked_v("lower.usg.opt", "pron"); ?>> sấp</label></span></td>
                            </tr>
                            <tr>
                                <td><div style="display:flex; justify-content:center; gap:0.5rem;"><input type="checkbox" name="fu[lower][osg][L]" value="1" <?php echo checked_v("lower.osg.L", "1"); ?>> <span style="color:#94a3b8;">|</span> <input type="checkbox" name="fu[lower][osg][R]" value="1" <?php echo checked_v("lower.osg.R", "1"); ?>></div></td>
                                <td class="label-col">Khớp cổ chân trên: <span style="font-weight:400; font-size:0.75rem;"><label><input type="checkbox" name="fu[lower][osg][opt][]" value="post" <?php echo checked_v("lower.osg.opt", "post"); ?>> sau</label> <label><input type="checkbox" name="fu[lower][osg][opt][]" value="ant" <?php echo checked_v("lower.osg.opt", "ant"); ?>> trước</label></span></td>
                            </tr>
                            <?php 
                            $lower_simple = [
                                'cuboid' => 'Xương hộp', 'naviculare' => 'Xương ghe', 'hallux' => 'Ngón cái vẹo ngoài',
                                'knee' => 'Khớp gối', 'patella' => 'Di động xương bánh chè', 'hip' => 'Khớp háng', 'iliopsoas' => 'Cơ thắt lưng chậu'
                            ];
                            foreach($lower_simple as $k => $lbl): ?>
                            <tr>
                                <td><div style="display:flex; justify-content:center; gap:0.5rem;"><input type="checkbox" name="fu[lower][<?php echo $k; ?>][L]" value="1" class="<?php echo checked_prev("lower.$k.L", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("lower.$k.L", "1"); ?>> <span style="color:#94a3b8;">|</span> <input type="checkbox" name="fu[lower][<?php echo $k; ?>][R]" value="1" class="<?php echo checked_prev("lower.$k.R", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("lower.$k.R", "1"); ?>></div></td>
                                <td class="label-col"><?php echo $lbl; ?></td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <tr>
                                <td><div style="display:flex; justify-content:center; gap:0.5rem;"><input type="checkbox" name="fu[lower][cuneiforme][L]" value="1" <?php echo checked_v("lower.cuneiforme.L", "1"); ?>> <span style="color:#94a3b8;">|</span> <input type="checkbox" name="fu[lower][cuneiforme][R]" value="1" <?php echo checked_v("lower.cuneiforme.R", "1"); ?>></div></td>
                                <td class="label-col">Xương chêm: <span style="font-weight:400; font-size:0.75rem;"><label><input type="checkbox" name="fu[lower][cuneiforme][opt][]" value="trong" <?php echo checked_v("lower.cuneiforme.opt", "trong"); ?>> trong</label> <label><input type="checkbox" name="fu[lower][cuneiforme][opt][]" value="giua" <?php echo checked_v("lower.cuneiforme.opt", "giua"); ?>> giữa</label> <label><input type="checkbox" name="fu[lower][cuneiforme][opt][]" value="ngoai" <?php echo checked_v("lower.cuneiforme.opt", "ngoai"); ?>> ngoài</label></span></td>
                            </tr>
                            <tr>
                                <td><div style="display:flex; justify-content:center; gap:0.5rem;"><input type="checkbox" name="fu[lower][mtt][L]" value="1" <?php echo checked_v("lower.mtt.L", "1"); ?>> <span style="color:#94a3b8;">|</span> <input type="checkbox" name="fu[lower][mtt][R]" value="1" <?php echo checked_v("lower.mtt.R", "1"); ?>></div></td>
                                <td class="label-col">Xương bàn chân: <span style="font-weight:400; font-size:0.75rem;">
                                    <?php foreach([1,2,3,4,5] as $v): ?><label><input type="checkbox" name="fu[lower][mtt][num][]" value="<?php echo $v; ?>" <?php echo checked_v("lower.mtt.num", $v); ?>><?php echo $v; ?></label> <?php endforeach; ?> - 
                                    <label><input type="checkbox" name="fu[lower][mtt][opt][]" value="mu" <?php echo checked_v("lower.mtt.opt", "mu"); ?>> mu</label> <label><input type="checkbox" name="fu[lower][mtt][opt][]" value="gan" <?php echo checked_v("lower.mtt.opt", "gan"); ?>> gan</label>
                                </span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div style="margin-top: 3rem; display: flex; gap: 1.5rem; justify-content: flex-end; border-top: 2px solid #e2e8f0; padding-top: 2rem;">
            <a href="../patients/view.php?id=<?php echo $patient_id; ?>" class="btn" style="background: #f1f5f9; color: var(--text-main); padding: 1.25rem 3rem; font-weight: 700; border-radius: 12px;">Hủy</a>
            <button type="submit" class="btn btn-primary" style="padding: 1.25rem 5rem; font-weight: 800; font-size: 1.15rem; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.4);">
                <i class="fas fa-save" style="margin-right: 0.5rem;"></i> Lưu Follow-Up
            </button>
        </div>
    </form>
</div>

<!-- Scripts for Marking -->
<script src="../../assets/js/medical_marking.js"></script>
<script>
let newMarking;
let currentColor = '#fbbf24'; // default yellow

function setPainColor(color, btnEl) {
    currentColor = color;
    document.querySelectorAll('.pain-level-btn').forEach(b => b.classList.remove('active'));
    btnEl.classList.add('active');
    
    // Update the marking JS current color
    if (newMarking) {
        newMarking.currentPaintColor = color;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // 1. Setup Old Canvas (Read-only)
    const oldMarkersData = <?php echo json_encode(!empty($prev_fu_markers) ? $prev_fu_markers : $history_markers); ?>;
    const oldMarking = new MedicalMarking('canvas-old', null, '../../assets/images/anatomy_4_views_clean.png');
    // Override render logic to not allow clicks, just draw
    setTimeout(() => {
        oldMarking.markers = oldMarkersData;
        oldMarking.redraw();
    }, 500); // give time for image to load
    
    // 2. Setup New Canvas (Interactive)
    const newInherited = <?php echo json_encode($inherited_markers); ?>;
    const newExisting = <?php echo json_encode(get_v('markers', [])); ?>;
    
    newMarking = new MedicalMarking('canvas-new', 'new-markers-data', '../../assets/images/anatomy_4_views_clean.png');
    newMarking.currentPaintColor = currentColor;
    
    setTimeout(() => {
        if (newExisting.length > 0) {
            newMarking.markers = newExisting;
        } else {
            newMarking.markers = newInherited;
        }
        newMarking.redraw();
        
        // Custom add marker to use specific color
        const canvasNew = document.getElementById('canvas-new');
        canvasNew.addEventListener('mousedown', function(e) {
            // Because MedicalMarking class might internally override addMarker, we just let it do its thing,
            // but we ensure it uses 'M' type with currentColor.
            // Note: Make sure medical_marking.js supports `marker.color`.
            // We will hook into it. If the user selects a tool other than O/X, it draws 'M'.
            newMarking.setCurrentTool('M'); 
        });
    }, 500);
});
</script>

<?php require_once '../../templates/footer.php'; ?>
"""

with open("modules/medical/follow_up_v2.php", "w", encoding="utf-8") as f:
    f.write(php_content)

print("Generated follow_up_v2.php successfully.")
