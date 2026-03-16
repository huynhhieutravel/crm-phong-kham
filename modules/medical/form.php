<?php
// modules/medical/form.php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
$type = $_GET['type'] ?? 'chiropractic';
$patient_id = $_GET['patient_id'] ?? 0;

$page_title = ($type === 'chiropractic' ? 'Phiếu Chiropractic' : 'Phiếu Đông Y');
$current_page = 'medical';
$db = getDB();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $history_data = json_encode($_POST['history'] ?? []);
    
    $stmt = $db->prepare("
        INSERT INTO medical_history (patient_id, type, history_data, created_by)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$patient_id, $type, $history_data, $_SESSION['user_id']]);
    
    set_flash('Lưu hồ sơ tiền sử bệnh thành công!');
    redirect("../patients/view.php?id=$patient_id");
}

require_once '../../templates/header.php';

$stmt = $db->prepare("SELECT full_name FROM patients WHERE id = ?");
$stmt->execute([$patient_id]);
$patient_name = $stmt->fetchColumn();

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
        <?php foreach ($questions as $section => $options): ?>
            <div style="margin-bottom: 2.5rem;">
                <h3 style="font-size: 1rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                    <span style="width: 4px; height: 16px; background: var(--primary); border-radius: 2px;"></span>
                    <?php echo $section; ?>
                </h3>
                <div class="medical-form-grid">
                    <?php foreach ($options as $opt): ?>
                        <label class="checkbox-card">
                            <input type="checkbox" name="history[<?php echo $section; ?>][]" value="<?php echo $opt['label']; ?>">
                            <i class="fas <?php echo $opt['icon']; ?>"></i>
                            <span class="label-text"><?php echo $opt['label']; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="form-group" style="margin-top: 2rem;">
            <label class="form-label">Ghi chú thêm</label>
            <textarea name="history[additional_notes]" class="form-input" rows="4" placeholder="Nhập thêm chi tiết nếu có..."></textarea>
        </div>

        <div style="margin-top: 2rem; display: flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary" style="padding: 1rem 2rem;">Lưu hồ sơ</button>
            <a href="../patients/view.php?id=<?php echo $patient_id; ?>" class="btn" style="background: #f1f5f9; color: var(--text-main); padding: 1rem 2rem;">Hủy</a>
        </div>
    </form>
</div>

<?php require_once '../../templates/footer.php'; ?>
