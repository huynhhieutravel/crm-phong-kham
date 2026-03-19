<?php
// modules/medical/form.php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
$type = $_GET['type'] ?? 'chiropractic';
$patient_id = $_GET['patient_id'] ?? 0;
$session_id = $_GET['session_id'] ?? null;

$page_title = ($type === 'chiropractic' ? 'Phiếu Chiropractic' : 'Phiếu Đông Y');
$current_page = 'medical';
$db = getDB();
$history_id = $_GET['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $history_data = json_encode($_POST['history'] ?? []);
    
    if ($history_id) {
        $stmt = $db->prepare("UPDATE medical_history SET history_data = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$history_data, $history_id]);
    } else {
        $stmt = $db->prepare("
            INSERT INTO medical_history (patient_id, session_id, type, history_data, created_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$patient_id, $session_id, $type, $history_data, $_SESSION['user_id']]);
    }
    
    set_flash('Lưu hồ sơ thành công!');
    
    if ($session_id) {
        redirect("session_view.php?id=$session_id");
    } else {
        redirect("../patients/view.php?id=$patient_id");
    }
}

require_once '../../templates/header.php';

// Initialize $data for pre-filling
$data = [];
if ($history_id) {
    $stmt = $db->prepare("SELECT history_data FROM medical_history WHERE id = ?");
    $stmt->execute([$history_id]);
    $record = $stmt->fetch();
    if ($record) {
        $data = json_decode($record['history_data'], true) ?? [];
    }
}
?>

<style>
    :root {
        --premium-shadow: 0 4px 20px -5px rgba(0, 0, 0, 0.05), 0 8px 32px -12px rgba(0, 0, 0, 0.08);
        --premium-border: 1px solid #eef2f6;
        --accent-heat: #ef4444;
        --accent-cold: #3b82f6;
    }

    .premium-card {
        background: white;
        padding: 1.5rem;
        border-radius: 20px;
        border: var(--premium-border);
        box-shadow: var(--premium-shadow);
        transition: all 0.3s ease;
    }
    .premium-card:hover {
        box-shadow: 0 12px 40px -10px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }

    .checkbox-tag {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        user-select: none;
    }
    .checkbox-tag:hover {
        border-color: var(--primary);
        background: white;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.08);
    }
    .checkbox-tag input {
        width: 1.1rem;
        height: 1.1rem;
        accent-color: var(--primary);
    }
    .checkbox-tag span {
        font-weight: 600;
        color: #475569;
        font-size: 0.9rem;
    }
    .checkbox-tag:has(input:checked) {
        background: white;
        border-color: var(--primary);
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.15);
    }
    .checkbox-tag:has(input:checked) span {
        color: var(--primary);
    }

    /* Circular Toggle Group */
    .toggle-group-premium {
        display: flex;
        gap: 0.5rem;
        background: #f1f5f9;
        padding: 0.25rem;
        border-radius: 50px;
        width: fit-content;
    }
    .toggle-item-premium {
        position: relative;
        cursor: pointer;
    }
    .toggle-item-premium input {
        position: absolute;
        opacity: 0;
        cursor: pointer;
    }
    .toggle-item-premium span {
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 32px;
        height: 32px;
        padding: 0 10px;
        border-radius: 50px;
        font-size: 0.8rem;
        font-weight: 800;
        color: #64748b;
        transition: all 0.2s ease;
        white-space: nowrap;
    }
    .toggle-item-premium input:checked + span {
        background: var(--primary);
        color: white;
        box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
    }

    .section-label-premium {
        font-size: 0.85rem;
        font-weight: 800;
        color: var(--primary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }

    .pulse-pair {
        background: #f8fafc;
        padding: 1.25rem;
        border-radius: 18px;
        border: 1px solid #eef2f6;
    }
    .medical-form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 0.75rem;
    }
</style>

<?php
$stmt = $db->prepare("SELECT full_name, birthday, occupation FROM patients WHERE id = ?");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch();
$patient_name = $patient['full_name'] ?? 'Bệnh nhân';
$patient_birthday = $patient['birthday'] ?? '';
$patient_occupation = $patient['occupation'] ?? '';
$patient_birth_year = $patient_birthday ? date('Y', strtotime($patient_birthday)) : '';

// Define questions for the forms with icons
$questions = [];
if ($type === 'chiropractic') {
    $questions = [
        'Lối sống & Thói quen' => [
            ['label' => 'Ngồi nhiều', 'icon' => 'fa-chair'],
            ['label' => 'Đứng nhiều', 'icon' => 'fa-user-tie'],
            ['label' => 'Lao động nặng', 'icon' => 'fa-weight-hanging'],
            ['label' => 'Ngủ nghiêng', 'icon' => 'fa-bed'],
            ['label' => 'Tư thế sai', 'icon' => 'fa-user-slash'],
            ['label' => 'Căng thẳng', 'icon' => 'fa-brain'],
            ['label' => 'Ít vận động', 'icon' => 'fa-walking']
        ],
        'Tình trạng hiện tại' => [
            ['label' => 'Đau nhói', 'icon' => 'fa-bolt'],
            ['label' => 'Đau âm ỉ', 'icon' => 'fa-wave-square'],
            ['label' => 'Tê bì', 'icon' => 'fa-hands'],
            ['label' => 'Yếu cơ', 'icon' => 'fa-fist-raised'],
            ['label' => 'Hạn chế vận động', 'icon' => 'fa-lock']
        ],
        'Tiền sử bệnh lý (Cơ xương khớp)' => [
            ['label' => 'Thoát vị đĩa đệm', 'icon' => 'fa-spine'],
            ['label' => 'Thoái hóa cột sống', 'icon' => 'fa-bone'],
            ['label' => 'Vẹo cột sống (Skoliose)', 'icon' => 'fa-bezier-curve'],
            ['label' => 'Viêm khớp dạng thấp', 'icon' => 'fa-hand-dots'],
            ['label' => 'Loãng xương', 'icon' => 'fa-skeleton'],
            ['label' => 'Viêm cột sống dính khớp', 'icon' => 'fa-link']
        ],
        'Tiền sử y khoa bàn bản' => [
            ['label' => 'Đã từng phẫu thuật', 'icon' => 'fa-procedures'],
            ['label' => 'Từng bị gãy xương', 'icon' => 'fa-crutch'],
            ['label' => 'Đã có phim chụp X-Ray', 'icon' => 'fa-x-ray'],
            ['label' => 'Đã có phim MRI/CT', 'icon' => 'fa-microscope'],
            ['label' => 'Rối loạn đông máu', 'icon' => 'fa-droplet-slash'],
            ['label' => 'Huyết áp cao', 'icon' => 'fa-heart-pulse']
        ],
        'Rà soát hệ thống' => [
            ['label' => 'Đau đầu / Chóng mặt', 'icon' => 'fa-head-side-virus'],
            ['label' => 'Ù tai', 'icon' => 'fa-ear-listen'],
            ['label' => 'Tê lan xuống tay', 'icon' => 'fa-hand-sparkles'],
            ['label' => 'Tê lan xuống chân', 'icon' => 'fa-shoe-prints'],
            ['label' => 'Mất kiểm soát ruột/bàng quang', 'icon' => 'fa-person-circle-exclamation']
        ],
        'Mục tiêu điều trị' => [
            ['label' => 'Giảm đau nhanh', 'icon' => 'fa-fire-extinguisher'],
            ['label' => 'Phục hồi vận động', 'icon' => 'fa-running'],
            ['label' => 'Phòng ngừa lâu dài', 'icon' => 'fa-shield-heart']
        ]
    ];
} else {
    $questions = [
        'Tổng quát' => [
            ['label' => 'Ăn uống kém', 'icon' => 'fa-utensils'],
            ['label' => 'Mệt mỏi', 'icon' => 'fa-tired'],
            ['label' => 'Hay ra mồ hôi', 'icon' => 'fa-tint'],
            ['label' => 'Sợ lạnh', 'icon' => 'fa-snowflake'],
            ['label' => 'Sợ nóng', 'icon' => 'fa-fire'],
            ['label' => 'Cơ thể suy nhược', 'icon' => 'fa-battery-empty']
        ],
        'Tiêu hóa' => [
            ['label' => 'Đầy hơi', 'icon' => 'fa-wind'],
            ['label' => 'Táo bón', 'icon' => 'fa-poop'],
            ['label' => 'Tiêu chảy', 'icon' => 'fa-water'],
            ['label' => 'Đau dạ dày', 'icon' => 'fa-band-aid']
        ]
    ];
}

?>

<div class="card" style="background: var(--glass-bg); backdrop-filter: blur(20px);">
    <div style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 style="margin: 0; font-weight: 800; color: var(--primary);"><?php echo $page_title; ?></h2>
            <p style="color: var(--text-muted); margin-top: 0.25rem;">Bệnh nhân: <strong style="color: var(--text-main);"><?php echo e($patient_name); ?></strong></p>
        </div>
        <div style="background: rgba(99, 102, 241, 0.1); padding: 0.5rem 1.25rem; border-radius: 50px; color: var(--primary); font-weight: 700; font-size: 0.85rem;">
            <?php echo strtoupper($type); ?>
        </div>
    </div>

    <form method="POST">
        <?php if ($type === 'dong_y'): ?>
            <!-- I. THÔNG TIN CƠ BẢN & HUYẾT ÁP -->
            <div style="margin-bottom: 3rem; padding-bottom: 2rem; border-bottom: 2px solid #f1f5f9;">
                <h3 style="font-size: 1.1rem; text-transform: uppercase; color: var(--primary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-id-card"></i> I. THÔNG TIN CƠ BẢN & HUYẾT ÁP
                </h3>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 1.5rem;">
                    <div class="form-group">
                        <label class="form-label">Họ tên</label>
                        <input type="text" class="form-input" value="<?php echo e($patient_name); ?>" readonly style="background: #f8fafc;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Năm sinh</label>
                        <input type="text" class="form-input" value="<?php echo e($patient_birth_year); ?>" readonly style="background: #f8fafc;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nghề nghiệp</label>
                        <input type="text" class="form-input" value="<?php echo e($patient_occupation); ?>" readonly style="background: #f8fafc;">
                    </div>
                </div>
                <!-- Hết phần thông tin cơ bản -->

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 1.5rem;">
                    <div class="pulse-pair">
                        <span style="font-size: 0.85rem; font-weight: 800; color: var(--primary); display: block; margin-bottom: 1rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.5rem;">HUYẾT ÁP TAY TRÁI</span>
                        <div style="display: flex; gap: 1rem;">
                            <div style="flex: 1;">
                                <label style="font-size: 0.7rem; color: var(--text-muted); display: block; margin-bottom: 0.25rem;">Chỉ số (mmHg)</label>
                                <input type="text" name="history[bp_left]" class="form-input" value="<?php echo e($data['bp_left'] ?? ''); ?>" placeholder="Ví dụ: 120/80">
                            </div>
                            <div style="flex: 1;">
                                <label style="font-size: 0.7rem; color: var(--text-muted); display: block; margin-bottom: 0.25rem;">Nhịp tim</label>
                                <input type="text" name="history[hr_left]" class="form-input" value="<?php echo e($data['hr_left'] ?? ''); ?>" placeholder="Lần/phút">
                            </div>
                        </div>
                    </div>
                    <div class="pulse-pair">
                        <span style="font-size: 0.85rem; font-weight: 800; color: var(--primary); display: block; margin-bottom: 1rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.5rem;">HUYẾT ÁP TAY PHẢI</span>
                        <div style="display: flex; gap: 1rem;">
                            <div style="flex: 1;">
                                <label style="font-size: 0.7rem; color: var(--text-muted); display: block; margin-bottom: 0.25rem;">Chỉ số (mmHg)</label>
                                <input type="text" name="history[bp_right]" class="form-input" value="<?php echo e($data['bp_right'] ?? ''); ?>" placeholder="Ví dụ: 120/80">
                            </div>
                            <div style="flex: 1;">
                                <label style="font-size: 0.7rem; color: var(--text-muted); display: block; margin-bottom: 0.25rem;">Nhịp tim</label>
                                <input type="text" name="history[hr_right]" class="form-input" value="<?php echo e($data['hr_right'] ?? ''); ?>" placeholder="Lần/phút">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Lý do đến khám</label>
                    <textarea name="history[reason]" class="form-input" rows="2" placeholder="Nhập lý do khách hàng đến khám..."><?php echo e($data['reason'] ?? ''); ?></textarea>
                </div>
            </div>

            <!-- II. VỌNG CHẨN (Nhìn) -->
            <div style="margin-bottom: 3rem; padding-bottom: 2rem; border-bottom: 2px solid #f1f5f9;">
                <h3 style="font-size: 1.1rem; text-transform: uppercase; color: var(--primary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-eye"></i> II. VỌNG CHẨN (Nhìn)
                </h3>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                    <div class="form-group">
                        <label class="form-label">1. Thần sắc</label>
                        <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
                            <?php foreach (['Còn thần (Tươi nhuận)', 'Thất thần (Mệt mỏi, lờ đờ)'] as $opt): ?>
                                <label class="checkbox-tag">
                                    <input type="radio" name="history[spirit]" value="<?php echo $opt; ?>" <?php echo ($data['spirit'] ?? '') == $opt ? 'checked' : ''; ?>>
                                    <span><?php echo $opt; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <span style="font-size: 0.85rem; opacity: 0.7; display: block; margin-bottom: 0.5rem;">Sắc mặt:</span>
                        <div class="medical-form-grid" style="grid-template-columns: repeat(4, 1fr);">
                            <?php foreach (['Trắng bệch', 'Vàng vọt', 'Đỏ gay', 'Sạm đen'] as $opt): ?>
                                <label class="checkbox-tag">
                                    <input type="radio" name="history[face_color]" value="<?php echo $opt; ?>" <?php echo ($data['face_color'] ?? '') == $opt ? 'checked' : ''; ?>>
                                    <span><?php echo $opt; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div style="background: #fff; padding: 1.5rem; border-radius: 16px; border: 1px solid #e2e8f0;">
                        <span class="form-label" style="color: #6366f1; font-size: 0.9rem; margin-bottom: 1rem; display: block;">
                            <i class="fas fa-tongue"></i> VỌNG LƯỠI (Cực kỳ quan trọng)
                        </span>
                        
                        <!-- 1. Chất lưỡi -->
                        <div style="margin-bottom: 1.25rem;">
                            <div style="font-size: 0.85rem; font-weight: 700; color: #475569; margin-bottom: 0.75rem;">Chất lưỡi:</div>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 0.75rem;">
                                <?php foreach (['Hồng đều', 'Đỏ sẫm', 'Tím tái', 'Có điểm ứ huyết'] as $opt): ?>
                                    <label class="checkbox-tag">
                                        <input type="radio" name="history[tongue_body]" value="<?php echo $opt; ?>" <?php echo ($data['tongue_body'] ?? '') == $opt ? 'checked' : ''; ?>>
                                        <span><?php echo $opt; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- 2. Hình dáng -->
                        <div style="margin-bottom: 1.25rem;">
                            <div style="font-size: 0.85rem; font-weight: 700; color: #475569; margin-bottom: 0.75rem;">Hình dáng:</div>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem;">
                                <?php foreach (['Thon gọn', 'Bệu béo (có vết răng)', 'Nứt ngang/dọc'] as $opt): ?>
                                    <label class="checkbox-tag">
                                        <input type="radio" name="history[tongue_shape]" value="<?php echo $opt; ?>" <?php echo ($data['tongue_shape'] ?? '') == $opt ? 'checked' : ''; ?>>
                                        <span><?php echo $opt; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- 3. Rêu lưỡi -->
                        <div style="margin-bottom: 1.25rem;">
                            <div style="font-size: 0.85rem; font-weight: 700; color: #475569; margin-bottom: 0.75rem;">Rêu lưỡi:</div>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 0.75rem;">
                                <?php foreach (['Trắng mỏng', 'Trắng dày', 'Vàng mỏng', 'Vàng dày', 'Nhớt/Dính'] as $opt): ?>
                                    <label class="checkbox-tag">
                                        <input type="checkbox" name="history[tongue_coating][]" value="<?php echo $opt; ?>" <?php echo in_array($opt, $data['tongue_coating'] ?? []) ? 'checked' : ''; ?>>
                                        <span><?php echo $opt; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- 4. Đầu lưỡi -->
                        <div>
                            <div style="font-size: 0.85rem; font-weight: 700; color: #475569; margin-bottom: 0.75rem;">Đầu lưỡi:</div>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 0.75rem;">
                                <?php foreach (['Hồng', 'Đỏ', 'Nhạt'] as $opt): ?>
                                    <label class="checkbox-tag">
                                        <input type="radio" name="history[tongue_tip]" value="<?php echo $opt; ?>" <?php echo ($data['tongue_tip'] ?? '') == $opt ? 'checked' : ''; ?>>
                                        <span><?php echo $opt; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                    <div class="premium-card">
                        <label class="section-label-premium"><i class="fas fa-eye"></i> 3. Mắt</label>
                        <div class="medical-form-grid" style="grid-template-columns: 1fr; gap: 0.75rem;">
                            <?php foreach (['Lòng trắng đỏ (Can hỏa)', 'Quầng thâm mắt (Thận hư)', 'Mắt sưng nề (Tỳ thấp)'] as $opt): ?>
                                <label class="checkbox-tag" style="width: 100%;">
                                    <input type="checkbox" name="history[eyes][]" value="<?php echo $opt; ?>" <?php echo in_array($opt, $data['eyes'] ?? []) ? 'checked' : ''; ?>>
                                    <span><?php echo $opt; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px dashed #e2e8f0;">
                            <span style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.5rem; display: block;">Mí mắt:</span>
                            <div class="medical-form-grid" style="grid-template-columns: 1fr; gap: 0.5rem;">
                                <?php foreach (['Hồng đều', 'Trong nhạt ngoài hồng', 'Trong nhạt ngoài đỏ', 'Đỏ toàn bộ'] as $opt): ?>
                                    <label class="checkbox-tag" style="width: 100%;">
                                        <input type="radio" name="history[eyelids]" value="<?php echo $opt; ?>" <?php echo ($data['eyelids'] ?? '') == $opt ? 'checked' : ''; ?>>
                                        <span><?php echo $opt; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="premium-card">
                        <label class="section-label-premium"><i class="fas fa-lips"></i> 4. Niêm mạc môi</label>
                        <div class="medical-form-grid" style="grid-template-columns: 1fr; gap: 0.5rem;">
                            <?php foreach (['Hồng tươi', 'Ẩn vàng', 'Ẩn nâu', 'Có tia máu', 'Ẩn xanh tím tái', 'Nhạt'] as $opt): ?>
                                <label class="checkbox-tag" style="width: 100%;">
                                    <input type="radio" name="history[lips]" value="<?php echo $opt; ?>" <?php echo ($data['lips'] ?? '') == $opt ? 'checked' : ''; ?>>
                                    <span><?php echo $opt; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- III. VĂN CHẨN (Nghe) -->
            <div style="margin-bottom: 3rem;">
                <h3 style="font-size: 1.1rem; text-transform: uppercase; color: var(--primary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-volume-up"></i> III. VĂN CHẨN (Nghe)
                </h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div class="premium-card">
                        <label class="section-label-premium"><i class="fas fa-comment-medical"></i> 1. Tiếng nói / Hơi thở</label>
                        <div class="medical-form-grid" style="grid-template-columns: 1fr;">
                            <?php foreach (['Tiếng nói to, vang (Thực)', 'Tiếng nói nhỏ, thào thào (Hư)', 'Hơi thở ngắn (Đoản hơi)', 'Nhanh', 'Chậm', 'Khò khè / Có đờm'] as $opt): ?>
                                <label class="checkbox-tag">
                                    <input type="checkbox" name="history[voice_breath][]" value="<?php echo $opt; ?>" <?php echo in_array($opt, $data['voice_breath'] ?? []) ? 'checked' : ''; ?>>
                                    <span><?php echo $opt; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="premium-card">
                        <label class="section-label-premium"><i class="fas fa-wind"></i> 2. Mùi cơ thể</label>
                        <div class="medical-form-grid" style="grid-template-columns: 1fr;">
                            <?php foreach (['Hơi thở hôi (Vị nhiệt)', 'Cơ thể có mùi hăng/chua'] as $opt): ?>
                                <label class="checkbox-tag">
                                    <input type="checkbox" name="history[body_odor][]" value="<?php echo $opt; ?>" <?php echo in_array($opt, $data['body_odor'] ?? []) ? 'checked' : ''; ?>>
                                    <span><?php echo $opt; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- IV. VẤN CHẨN (Hỏi) -->
            <div style="margin-bottom: 3rem;">
                <h3 style="font-size: 1.1rem; text-transform: uppercase; color: var(--primary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-comments"></i> IV. VẤN CHẨN (Hỏi)
                </h3>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                    <div class="premium-card">
                        <label class="section-label-premium"><i class="fas fa-history"></i> Tiền sử / Phụ khoa</label>
                        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                            <?php foreach (['Sinh thường', 'Sinh mổ', 'Phẫu thuật khác'] as $opt): ?>
                                <label class="checkbox-tag">
                                    <input type="radio" name="history[lifestyle_history]" value="<?php echo $opt; ?>" <?php echo ($data['lifestyle_history'] ?? '') == $opt ? 'checked' : ''; ?>>
                                    <span><?php echo $opt; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="premium-card">
                        <label class="section-label-premium"><i class="fas fa-bed"></i> Giấc ngủ</label>
                        <div class="medical-form-grid" style="grid-template-columns: 1fr 1fr;">
                            <?php foreach (['Dễ', 'Khó', 'Thẳng giấc', 'Trở giấc', 'Hay mơ (Mộng mị)', 'Đạo hãn (Mồ hôi trộm)', 'Đủ giờ', 'Thiếu giờ'] as $opt): ?>
                                <label class="checkbox-tag">
                                    <input type="checkbox" name="history[sleep_quality][]" value="<?php echo $opt; ?>" <?php echo in_array($opt, $data['sleep_quality'] ?? []) ? 'checked' : ''; ?>>
                                    <span><?php echo $opt; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="premium-card">
                        <label class="section-label-premium"><i class="fas fa-sun"></i> Thức dậy</label>
                        <div style="display: flex; gap: 1rem;">
                            <?php foreach(['Tỉnh táo', 'Lờ đờ'] as $opt): ?>
                                <label class="checkbox-tag">
                                    <input type="radio" value="<?php echo $opt; ?>" name="history[wake_up_state]" <?php echo ($data['wake_up_state'] ?? '') == $opt ? 'checked' : ''; ?>>
                                    <span><?php echo $opt; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="premium-card">
                        <label class="section-label-premium"><i class="fas fa-clock"></i> Khung giờ tỉnh giấc</label>
                        <div class="medical-form-grid" style="grid-template-columns: 1fr; gap: 0.5rem;">
                            <?php foreach ([
                                '21h-23h: Khó vào giấc (Tam Tiêu)', 
                                '23h-01h: Hay giật mình, lo sợ (Đởm)', 
                                '01h-03h: Tỉnh giấc bứt rứt, nóng nảy (Can)', 
                                '03h-05h: Tỉnh giấc kèm ho, buồn rầu (Phế)', 
                                '05h-07h: Tỉnh giấc đi ngoài ngay (Đại Trường)'
                            ] as $opt): ?>
                                <label class="checkbox-tag" style="width: 100%;">
                                    <input type="checkbox" name="history[night_wake_times][]" value="<?php echo $opt; ?>" <?php echo in_array($opt, $data['night_wake_times'] ?? []) ? 'checked' : ''; ?>>
                                    <span><?php echo $opt; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- PHẦN IV: VẤN CHẨN - Thói Quen (Layout Image 4) -->
                <div class="premium-card" style="margin-bottom: 2.5rem;">
                    <label class="section-label-premium"><i class="fas fa-user-clock"></i> Thói quen, Môi trường & Tư thế</label>
                    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                        <!-- Row 1 -->
                        <div>
                            <span style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.75rem; display: block;">Lối sống:</span>
                            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                                <?php foreach (['Ăn đêm sau 20h', 'Tắm sau 19h', 'Uống nước đá lạnh', 'Dùng điều hòa nhiệt độ dưới 25 độ', 'Stress'] as $opt): ?>
                                    <label class="checkbox-tag">
                                        <input type="checkbox" name="history[habits][]" value="<?php echo $opt; ?>" <?php echo in_array($opt, $data['habits'] ?? []) ? 'checked' : ''; ?>>
                                        <span><?php echo $opt; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Row 2 -->
                        <div style="padding-top: 1.25rem; border-top: 1px dashed #e2e8f0;">
                            <span style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.75rem; display: block;">Trước khi ngủ:</span>
                            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                                <?php foreach (['Sử dụng thiết bị điện tử sát giờ ngủ', 'Ngủ sau 23h'] as $opt): ?>
                                    <label class="checkbox-tag">
                                        <input type="checkbox" name="history[habits][]" value="<?php echo $opt; ?>" <?php echo in_array($opt, $data['habits'] ?? []) ? 'checked' : ''; ?>>
                                        <span><?php echo $opt; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Row 3 & 4 Grid -->
                        <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 2rem; padding-top: 1.25rem; border-top: 1px dashed #e2e8f0;">
                            <div>
                                <span style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.75rem; display: block;">Đặc thù tư thế (Làm việc):</span>
                                <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                                    <?php foreach (['Đứng nhiều', 'Ngồi nhiều', 'Đi nhiều'] as $opt): ?>
                                        <label class="checkbox-tag">
                                            <input type="radio" value="<?php echo $opt; ?>" name="history[work_posture]" <?php echo ($data['work_posture'] ?? '') == $opt ? 'checked' : ''; ?>>
                                            <span><?php echo $opt; ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div>
                                <span style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.75rem; display: block;">Môi trường sống:</span>
                                <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                                    <?php foreach (['Bình thường', 'Ẩm ướt'] as $opt): ?>
                                        <label class="checkbox-tag">
                                            <input type="radio" value="<?php echo $opt; ?>" name="history[living_env]" <?php echo ($data['living_env'] ?? '') == $opt ? 'checked' : ''; ?>>
                                            <span><?php echo $opt; ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PHẦN V: TIÊU HÓA & BÀI TIẾT (Premium Redesign) -->
                <div class="premium-card" style="margin-bottom: 2.5rem;">
                    <label class="section-label-premium"><i class="fas fa-utensils"></i> V. Tiêu hóa & Bài tiết</label>
                    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                            <!-- Ăn uống -->
                            <div style="background: #f8fafc; padding: 1.25rem; border-radius: 16px; border: 1px solid #eef2f6;">
                                <span style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 1rem; display: block;">Hệ tiêu hóa (Ăn uống):</span>
                                <div class="medical-form-grid" style="grid-template-columns: 1fr 1fr;">
                                    <?php foreach (['Ngon miệng', 'Thích đồ mát', 'Thích đồ nóng', 'Sợ ăn/Chán ăn'] as $opt): ?>
                                        <label class="checkbox-tag">
                                            <input type="checkbox" name="history[digestion_eating][]" value="<?php echo $opt; ?>" <?php echo in_array($opt, $data['digestion_eating'] ?? []) ? 'checked' : ''; ?>>
                                            <span><?php echo $opt; ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Đại tiện -->
                            <div style="background: #f8fafc; padding: 1.25rem; border-radius: 16px; border: 1px solid #eef2f6;">
                                <span style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 1rem; display: block;">Đại tiện (Tính chất):</span>
                                <div class="medical-form-grid" style="grid-template-columns: 1fr 1fr;">
                                    <?php foreach (['Táo bón', 'Sống phân/Nát', 'Tiêu chảy', 'Bình thường'] as $opt): ?>
                                        <label class="checkbox-tag">
                                            <input type="checkbox" name="history[digestion_excretion][]" value="<?php echo $opt; ?>" <?php echo in_array($opt, $data['digestion_excretion'] ?? []) ? 'checked' : ''; ?>>
                                            <span><?php echo $opt; ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Frequency Rows -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; padding-top: 1.25rem; border-top: 1px dashed #e2e8f0;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <span style="font-size: 0.85rem; font-weight: 700; color: #475569;">Số lần đại tiện trong ngày:</span>
                                <div class="toggle-group-premium">
                                    <?php foreach (['1', '2', '3', 'Nhiều hơn'] as $opt): ?>
                                        <label class="toggle-item-premium">
                                            <input type="radio" value="<?php echo $opt; ?>" name="history[excretion_frequency]" <?php echo ($data['excretion_frequency'] ?? '') == $opt ? 'checked' : ''; ?>>
                                            <span><?php echo $opt; ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <span style="font-size: 0.85rem; font-weight: 700; color: #475569;">Số lần tiểu đêm:</span>
                                <div class="toggle-group-premium">
                                    <?php foreach (['1', '2', '3', 'Nhiều hơn'] as $opt): ?>
                                        <label class="toggle-item-premium">
                                            <input type="radio" value="<?php echo $opt; ?>" name="history[night_urine_count]" <?php echo ($data['night_urine_count'] ?? '') == $opt ? 'checked' : ''; ?>>
                                            <span><?php echo $opt; ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Urine Color -->
                        <div style="padding-top: 1.25rem; border-top: 1px dashed #e2e8f0;">
                            <span style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.75rem; display: block;">Màu sắc tiểu tiện:</span>
                            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                                <?php foreach (['Hơi vàng', 'Trắng, trong', 'Vàng sẫm', 'Đục', 'Đau, xót'] as $opt): ?>
                                    <label class="checkbox-tag">
                                        <input type="checkbox" name="history[urine_color][]" value="<?php echo $opt; ?>" <?php echo in_array($opt, $data['urine_color'] ?? []) ? 'checked' : ''; ?>>
                                        <span><?php echo $opt; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                </div>

                <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 1.5rem; margin-bottom: 2.5rem;">
                    <div class="premium-card">
                        <label class="section-label-premium"><i class="fas fa-venus text-pink-500"></i> Kinh nguyệt (Nữ giới)</label>
                        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 1.25rem;">
                            <?php foreach (['Đều', 'Không đều'] as $opt): ?>
                                <label class="checkbox-tag">
                                    <input type="radio" name="history[menses_regularity]" value="<?php echo $opt; ?>" <?php echo ($data['menses_regularity'] ?? '') == $opt ? 'checked' : ''; ?>>
                                    <span><?php echo $opt; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-bottom: 1.25rem;">
                            <input type="text" name="history[menses_days]" class="form-input" value="<?php echo e($data['menses_days'] ?? ''); ?>" placeholder="Số ngày hành kinh..." style="width: 100%; height: 42px; border-radius: 12px; border: 1px solid #e2e8f0; padding: 0 1rem; font-weight: 600;">
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                            <div>
                                <span style="font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 0.5rem;">Đau bụng kinh:</span>
                                <div style="display: flex; gap: 0.5rem;">
                                    <?php foreach (['Có', 'Không'] as $opt): ?>
                                        <label class="checkbox-tag" style="padding: 0.4rem 0.75rem;">
                                            <input type="radio" name="history[menses_pain]" value="<?php echo $opt; ?>" <?php echo ($data['menses_pain'] ?? '') == $opt ? 'checked' : ''; ?>>
                                            <span><?php echo $opt; ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div>
                                <span style="font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 0.5rem;">Huyết trắng:</span>
                                <div style="display: flex; gap: 0.5rem;">
                                    <?php foreach (['Có', 'Không'] as $opt): ?>
                                        <label class="checkbox-tag" style="padding: 0.4rem 0.75rem;">
                                            <input type="radio" name="history[menses_leucorrhoea]" value="<?php echo $opt; ?>" <?php echo ($data['menses_leucorrhoea'] ?? '') == $opt ? 'checked' : ''; ?>>
                                            <span><?php echo $opt; ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div>
                            <span style="font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 0.5rem;">Màu sắc kinh nguyệt:</span>
                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                <?php foreach (['Đỏ tươi', 'Có cục / Thẫm màu'] as $opt): ?>
                                    <label class="checkbox-tag" style="padding: 0.4rem 0.75rem;">
                                        <input type="radio" name="history[menses_color]" value="<?php echo $opt; ?>" <?php echo ($data['menses_color'] ?? '') == $opt ? 'checked' : ''; ?>>
                                        <span><?php echo $opt; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="premium-card">
                        <label class="section-label-premium"><i class="fas fa-thermometer-half"></i> Cảm giác đối với bệnh lý</label>
                        <div style="margin-bottom: 1.25rem; padding: 1.25rem; background: #fff1f2; border-radius: 16px; border: 1px solid #fecaca;">
                            <span style="font-size: 0.75rem; font-weight: 800; color: #dc2626; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.75rem;">
                                <i class="fas fa-fire"></i> NHIỆT (NÓNG):
                            </span>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                                <?php foreach (['Đau', 'Ngứa', 'Mỏi', 'Nóng'] as $opt): ?>
                                    <label class="checkbox-tag" style="padding: 0.5rem; background: white; border-color: #fecaca;">
                                        <input type="checkbox" name="history[sensation_heat][]" value="<?php echo $opt; ?>" <?php echo in_array($opt, $data['sensation_heat'] ?? []) ? 'checked' : ''; ?>>
                                        <span><?php echo $opt; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div style="padding: 1.25rem; background: #eff6ff; border-radius: 16px; border: 1px solid #bfdbfe;">
                            <span style="font-size: 0.75rem; font-weight: 800; color: #2563eb; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.75rem;">
                                <i class="fas fa-snowflake"></i> HÀN (LẠNH):
                            </span>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                                <?php foreach (['Nhức', 'Tê', 'Nặng nề', 'Lạnh'] as $opt): ?>
                                    <label class="checkbox-tag" style="padding: 0.5rem; background: white; border-color: #bfdbfe;">
                                        <input type="checkbox" name="history[sensation_cold][]" value="<?php echo $opt; ?>" <?php echo in_array($opt, $data['sensation_cold'] ?? []) ? 'checked' : ''; ?>>
                                        <span><?php echo $opt; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

                <!-- V. THIẾT CHẨN (Bắt mạch & Sờ nắn) -->
                <div style="margin-bottom: 3.5rem;">
                    <h3 style="font-size: 1.1rem; text-transform: uppercase; color: var(--primary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-hand-holding-heart"></i> V. THIẾT CHẨN (Thiết)
                    </h3>
                    
                    <div class="premium-card" style="margin-bottom: 2rem;">
                        <label class="section-label-premium"><i class="fas fa-wave-square"></i> 1. Mạch tượng (Hệ mạch chính)</label>
                        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
                            <div class="pulse-pair">
                                <span style="font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 0.75rem;">Độ sâu (Vị):</span>
                                <label class="checkbox-tag" style="width: 100%; margin-bottom: 0.5rem;"><input type="radio" name="history[pulse_depth]" value="Phù (Nổi)" <?php echo ($data['pulse_depth'] ?? '') == 'Phù (Nổi)' ? 'checked' : ''; ?>><span>Phù</span></label>
                                <label class="checkbox-tag" style="width: 100%;"><input type="radio" name="history[pulse_depth]" value="Trầm (Chìm)" <?php echo ($data['pulse_depth'] ?? '') == 'Trầm (Chìm)' ? 'checked' : ''; ?>><span>Trầm</span></label>
                            </div>
                            <div class="pulse-pair">
                                <span style="font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 0.75rem;">Tốc độ (Sác):</span>
                                <label class="checkbox-tag" style="width: 100%; margin-bottom: 0.5rem;"><input type="radio" name="history[pulse_speed]" value="Trì (Chậm)" <?php echo ($data['pulse_speed'] ?? '') == 'Trì (Chậm)' ? 'checked' : ''; ?>><span>Trì</span></label>
                                <label class="checkbox-tag" style="width: 100%;"><input type="radio" name="history[pulse_speed]" value="Sác (Nhanh)" <?php echo ($data['pulse_speed'] ?? '') == 'Sác (Nhanh)' ? 'checked' : ''; ?>><span>Sác</span></label>
                            </div>
                            <div class="pulse-pair">
                                <span style="font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 0.75rem;">Hình dạng (Thể):</span>
                                <label class="checkbox-tag" style="width: 100%; margin-bottom: 0.5rem;"><input type="radio" name="history[pulse_texture]" value="Hoạt (Trơn)" <?php echo ($data['pulse_texture'] ?? '') == 'Hoạt (Trơn)' ? 'checked' : ''; ?>><span>Hoạt</span></label>
                                <label class="checkbox-tag" style="width: 100%;"><input type="radio" name="history[pulse_texture]" value="Sáp (Rít)" <?php echo ($data['pulse_texture'] ?? '') == 'Sáp (Rít)' ? 'checked' : ''; ?>><span>Sáp</span></label>
                            </div>
                            <div class="pulse-pair">
                                <span style="font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 0.75rem;">Lực (Lực):</span>
                                <label class="checkbox-tag" style="width: 100%; margin-bottom: 0.5rem;"><input type="radio" name="history[pulse_strength]" value="Có lực (Thực)" <?php echo ($data['pulse_strength'] ?? '') == 'Có lực (Thực)' ? 'checked' : ''; ?>><span>Có lực</span></label>
                                <label class="checkbox-tag" style="width: 100%;"><input type="radio" name="history[pulse_strength]" value="Không lực (Hư)" <?php echo ($data['pulse_strength'] ?? '') == 'Không lực (Hư)' ? 'checked' : ''; ?>><span>Không lực</span></label>
                            </div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                        <div class="premium-card">
                            <label class="section-label-premium"><i class="fas fa-hand-paper"></i> 2. Xúc chẩn (Sờ nắn)</label>
                            <div class="medical-form-grid" style="grid-template-columns: 1fr; gap: 0.5rem;">
                                <?php foreach (['Chân tay lạnh (Dương hư)', 'Lòng bàn tay chân nóng (Âm hư)', 'Ấn bụng đau tăng (Cự án)', 'Ấn bụng thấy dễ chịu (Thiện án)', 'Đầu ấm chân lạnh'] as $opt): ?>
                                    <label class="checkbox-tag">
                                        <input type="checkbox" name="history[palpation][]" value="<?php echo $opt; ?>" <?php echo in_array($opt, $data['palpation'] ?? []) ? 'checked' : ''; ?>>
                                        <span><?php echo $opt; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="premium-card">
                            <label class="section-label-premium"><i class="fas fa-child"></i> Cơ bắp & Nhiệt độ</label>
                            <div style="margin-bottom: 1.5rem;">
                                <span style="font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 0.75rem;">Trạng thái cơ bắp:</span>
                                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                    <?php foreach (['Săn chắc', 'Co cứng', 'Lỏng lẽo'] as $opt): ?>
                                        <label class="checkbox-tag">
                                            <input type="radio" name="history[palpation_muscle]" value="<?php echo $opt; ?>" <?php echo ($data['palpation_muscle'] ?? '') == $opt ? 'checked' : ''; ?>>
                                            <span><?php echo $opt; ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div>
                                <span style="font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 0.75rem;">Nhiệt độ cơ thể:</span>
                                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                    <?php foreach (['Bình thường', 'Nóng', 'Lạnh'] as $opt): ?>
                                        <label class="checkbox-tag">
                                            <input type="radio" name="history[body_temp]" value="<?php echo $opt; ?>" <?php echo ($data['body_temp'] ?? '') == $opt ? 'checked' : ''; ?>>
                                            <span><?php echo $opt; ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <!-- VI. TỔNG KẾT NHANH -->
            <div style="margin-bottom: 3.5rem;">
                <h3 style="font-size: 1.1rem; text-transform: uppercase; color: var(--primary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-clipboard-check"></i> VI. TỔNG KẾT NHANH
                </h3>
                <div class="premium-card">
                    <label class="section-label-premium"><i class="fas fa-tags"></i> Bát cương</label>
                    <div class="medical-form-grid" style="grid-template-columns: repeat(4, 1fr); gap: 1rem;">
                        <?php foreach (['Biểu', 'Lý', 'Hàn', 'Nhiệt', 'Hư', 'Thực', 'Âm', 'Dương'] as $opt): ?>
                            <label class="checkbox-tag" style="justify-content: center; padding: 1rem;">
                                <input type="checkbox" name="history[bat_cuong][]" value="<?php echo $opt; ?>" <?php echo in_array($opt, $data['bat_cuong'] ?? []) ? 'checked' : ''; ?>>
                                <span><?php echo $opt; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Chiropractic / Generic Sections -->
            <?php foreach ($questions as $section => $options): ?>
                <div style="margin-bottom: 2.5rem;">
                    <h3 style="font-size: 1rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                        <span style="width: 4px; height: 16px; background: var(--primary); border-radius: 2px;"></span>
                        <?php echo $section; ?>
                    </h3>
                    <div class="medical-form-grid">
                        <?php foreach ($options as $opt): ?>
                            <label class="checkbox-card">
                                <input type="checkbox" name="history[<?php echo $section; ?>][]" value="<?php echo $opt['label']; ?>" <?php echo in_array($opt['label'], $data[$section] ?? []) ? 'checked' : ''; ?>>
                                <i class="fas <?php echo $opt['icon']; ?>"></i>
                                <span class="label-text"><?php echo $opt['label']; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="form-group" style="margin-top: 2rem;">
            <label class="form-label">Ghi chú lâm sàng / Tình trạng khác</label>
            <textarea name="history[additional_notes]" class="form-input" rows="4" placeholder="Nhập thêm chi tiết nếu có..."><?php echo e($data['additional_notes'] ?? ''); ?></textarea>
        </div>

        <div style="margin-top: 2rem; display: flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary" style="padding: 1rem 2.5rem; border-radius: 12px; font-weight: 800;">
                <i class="fas fa-save"></i> <?php echo $history_id ? 'CẬP NHẬT HỒ SƠ' : 'LƯU HỒ SƠ MỚI'; ?>
            </button>
            <a href="../patients/view.php?id=<?php echo $patient_id; ?>" class="btn" style="background: #f1f5f9; color: var(--text-main); padding: 1rem 2rem; border-radius: 12px; font-weight: 800;">HỦY</a>
        </div>
    </form>
</div>

<?php require_once '../../templates/footer.php'; ?>
