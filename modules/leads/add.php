<?php
// modules/leads/add.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

$db = getDB();

$medical_groups = get_medical_groups();
$lead_sources = get_lead_sources();

// Handle form submission before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $optionals = [
        'full_name' => $_POST['full_name'],
        'phone' => $_POST['phone'],
        'gender' => $_POST['gender'],
        'birthday' => !empty($_POST['birthday']) ? $_POST['birthday'] : null,
        'email' => $_POST['email'],
        'address' => $_POST['address'],
        'source' => $_POST['source'],
        'medical_group' => $_POST['medical_group'],
        'consultant_id' => $_POST['consultant_id'] ?: null,
        'status' => $_POST['status'],
        'notes' => $_POST['notes']
    ];

    // Detect available columns
    $available_cols = $db->query("SHOW COLUMNS FROM leads")->fetchAll(PDO::FETCH_COLUMN);
    $data = [];
    foreach ($optionals as $col => $val) {
        if (in_array($col, $available_cols)) {
            $data[$col] = $val;
        }
    }

    if (!empty($data)) {
        $cols = implode(", ", array_keys($data));
        $placeholders = implode(", ", array_fill(0, count($data), "?"));
        $sql = "INSERT INTO leads ($cols) VALUES ($placeholders)";
        $stmt = $db->prepare($sql);
        $stmt->execute(array_values($data));
    }
    
    set_flash(__('lead.msg_add_success'));
    redirect('index.php');
}

// Fetch consultants
$consultants_stmt = $db->query("
    SELECT u.id, u.full_name 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    WHERE r.name IN ('cskh', 'admin') AND u.status = 'active'
    ORDER BY u.full_name
");
$consultants = $consultants_stmt->fetchAll();

$page_title = __('leads.form.add_title');
$current_page = 'leads';
require_once '../../templates/header.php';
?>

<div class="card" style="max-width: 900px; margin: 0 auto; padding: 2.5rem;">
    <div class="page-header" style="margin-bottom: 2rem;">
        <div>
            <h1 class="page-title"><?php echo __('leads.form.add_title'); ?></h1>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;">
                <?php echo __('leads.form.add_subtitle'); ?>
            </p>
        </div>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> <?php echo __('common.back'); ?>
        </a>
    </div>

    <form method="POST">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group">
                <label class="form-label"><?php echo __('leads.form.label_full_name'); ?> <span style="color: red;">*</span></label>
                <input type="text" name="full_name" class="form-input" required placeholder="<?php echo __('leads.form.placeholder_name', 'Nguyễn Văn A'); ?>">
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo __('leads.form.label_phone'); ?> <span style="color: red;">*</span></label>
                <input type="text" name="phone" class="form-input" required placeholder="<?php echo __('leads.form.placeholder_phone', '0912345678'); ?>">
            </div>
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label"><?php echo __('leads.form.label_gender'); ?></label>
                    <select name="gender" class="form-input">
                        <option value="male"><?php echo __('patient.gender.male'); ?></option>
                        <option value="female"><?php echo __('patient.gender.female'); ?></option>
                        <option value="other"><?php echo __('patient.gender.other'); ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('leads.form.label_birthday'); ?></label>
                    <input type="date" name="birthday" class="form-input">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo __('leads.form.label_email'); ?></label>
                <input type="email" name="email" class="form-input" placeholder="<?php echo __('leads.form.placeholder_email', 'example@gmail.com'); ?>">
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo __('leads.form.label_source'); ?></label>
                <select name="source" class="form-input">
                    <?php foreach ($lead_sources as $key => $label): ?>
                        <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo __('leads.form.label_consultant'); ?></label>
                <select name="consultant_id" class="form-input">
                    <option value=""><?php echo __('leads.form.label_consultant_placeholder', '-- Chọn tư vấn viên --'); ?></option>
                    <?php foreach ($consultants as $con): ?>
                        <option value="<?php echo $con['id']; ?>"><?php echo e($con['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo __('leads.form.label_medical_group'); ?></label>
                <select name="medical_group" class="form-input">
                    <option value=""><?php echo __('leads.form.label_medical_group_placeholder', '-- Chọn nhóm bệnh --'); ?></option>
                    <?php foreach ($medical_groups as $val => $key): ?>
                        <option value="<?php echo $val; ?>"><?php echo __($key); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo __('leads.form.label_status'); ?></label>
                <select name="status" class="form-input">
                    <option value="new">Mới</option>
                    <option value="contacted">Đã liên hệ</option>
                    <option value="scheduled">Đã đặt lịch</option>
                    <option value="converted">Đã chuyển đổi</option>
                    <option value="cancelled">Đã hủy</option>
                </select>
            </div>
        </div>

        <div class="form-group" style="margin-top: 1.5rem;">
            <label class="form-label">Ghi chú</label>
            <textarea name="notes" class="form-input" rows="3" placeholder="Ghi chú về tình trạng, nhu cầu của khách..."></textarea>
        </div>
        
        <div style="margin-top: 2.5rem; display: flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary" style="padding: 0.8rem 2rem; font-weight: 700;">
                <i class="fas fa-save"></i> Lưu hồ sơ Lead
            </button>
            <a href="index.php" class="btn" style="background: #f1f5f9; color: var(--text-main); padding: 0.8rem 2rem;">Hủy bỏ</a>
        </div>
    </form>
</div>

<?php require_once '../../templates/footer.php'; ?>
