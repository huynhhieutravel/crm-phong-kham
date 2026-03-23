<?php
// modules/patients/add.php

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    die("ERROR: [$errno] $errstr in $errfile on line $errline");
});
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL) {
        die("FATAL ERROR: " . print_r($error, true));
    }
});

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';

$db = getDB();

// Fetch active consultants (CSKH/Admin)
$consultants_stmt = $db->query("
    SELECT u.id, u.full_name 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    WHERE r.name IN ('cskh', 'admin') AND u.status = 'active'
    ORDER BY u.full_name
");
$consultants = $consultants_stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $optionals = [
        'customer_id' => $_POST['customer_id'] ?: null,
        'full_name' => $_POST['full_name'] ?: '',
        'gender' => $_POST['gender'] ?: '',
        'birthday' => !empty($_POST['birthday']) ? $_POST['birthday'] : null,
        'phone' => $_POST['phone'] ?: '',
        'email' => $_POST['email'] ?: '',
        'address' => $_POST['address'] ?: '',
        'branch' => $_POST['branch'] ?: '',
        'branch_id' => 1, // Default fallback
        'occupation' => $_POST['occupation'] ?: '',
        'source' => $_POST['source'] ?: '',
        'consultant_id' => $_POST['consultant_id'] ?: null,
        'label' => $_POST['label'] ?: '',
        'zalo_number' => $_POST['zalo_number'] ?: '',
        'facebook_link' => $_POST['facebook_link'] ?: '',
        'instagram_link' => $_POST['instagram_link'] ?: '',
        'twitter_link' => $_POST['twitter_link'] ?: '',
        'guardian_name' => $_POST['guardian_name'] ?: '',
        'guardian_id_card' => $_POST['guardian_id_card'] ?: '',
        'guardian_phone' => $_POST['guardian_phone'] ?: '',
        'guardian_relationship' => $_POST['guardian_relationship'] ?: '',
        'notes' => $_POST['notes'] ?: '',
        'personal_notes' => $_POST['personal_notes'] ?: ''
    ];

    // Detect available columns
    $available_cols = $db->query("SHOW COLUMNS FROM patients")->fetchAll(PDO::FETCH_COLUMN);
    $data = [];
    foreach ($optionals as $col => $val) {
        if (in_array($col, $available_cols)) {
            $data[$col] = $val;
        }
    }

    if (!empty($data)) {
        $cols = implode(", ", array_keys($data));
        $placeholders = implode(", ", array_fill(0, count($data), "?"));
        $sql = "INSERT INTO patients ($cols) VALUES ($placeholders)";
        $stmt = $db->prepare($sql);
        $stmt->execute(array_values($data));
    }
    
    set_flash(__('patient.msg.add_success'));
    redirect('index.php');
}

$page_title = __('patient.add.title');
$current_page = 'patients';
require_once '../../templates/header.php';
?>

<div style="max-width: 1000px; margin: 0 auto;">
    <div style="margin-bottom: 2rem;">
        <h2 style="font-weight: 800; color: var(--text-main); margin: 0;"><i class="fas fa-user-plus" style="color: var(--primary);"></i> <?php echo __('patient.profile_title'); ?></h2>
        <p style="color: var(--text-muted); margin-top: 0.25rem;"><?php echo __('patient.add.subtitle'); ?></p>
    </div>

    <form method="POST">
        <!-- Section 1: General Info -->
        <div class="card" style="margin-bottom: 1.5rem; padding: 2rem;">
            <h3 style="font-size: 1rem; text-transform: uppercase; color: var(--primary); margin-bottom: 1.5rem; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.5rem;">
                <i class="fas fa-info-circle"></i> <?php echo __('patient.info.general'); ?>
            </h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label"><?php echo __('patient.info.fullname'); ?> <span style="color: red;">*</span></label>
                    <input type="text" name="full_name" class="form-input" required placeholder="<?php echo __('patient.placeholder.fullname'); ?>" style="font-size: 1.1rem; font-weight: 600;">
                </div>
                
                <div class="form-group">
                    <label class="form-label"><?php echo __('patient.info.phone'); ?> <span style="color: red;">*</span></label>
                    <input type="text" name="phone" class="form-input" required placeholder="<?php echo __('patient.placeholder.phone'); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('patient.info.customer_id_opt'); ?></label>
                    <input type="text" name="customer_id" class="form-input" placeholder="<?php echo __('patient.placeholder.customer_id'); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label"><?php echo __('patient.gender'); ?></label>
                    <div style="display: flex; gap: 1.5rem; padding: 0.5rem 0;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="radio" name="gender" value="male" checked> <?php echo __('patient.gender.male'); ?>
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="radio" name="gender" value="female"> <?php echo __('patient.gender.female'); ?>
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="radio" name="gender" value="other"> <?php echo __('patient.gender.other'); ?>
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('patient.info.dob'); ?></label>
                    <input type="date" name="birthday" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label"><?php echo __('patient.info.branch'); ?></label>
                    <select name="branch" class="form-input">
                        <option value="Trụ sở chính"><?php echo __('common.main_branch'); ?></option>
                        <option value="Chi nhánh 1">Chi nhánh 1</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('patient.info.source'); ?></label>
                    <select name="source" class="form-input">
                        <?php foreach (get_lead_sources() as $key => $label): ?>
                            <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                        <?php endforeach; ?>
                        <option value="Walk-in"><?php echo __('patient.source.walk_in'); ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('patient.info.consultant'); ?></label>
                    <select name="consultant_id" class="form-input">
                        <option value=""><?php echo __('patient.placeholder.select_consultant'); ?></option>
                        <?php foreach ($consultants as $con): ?>
                            <option value="<?php echo $con['id']; ?>"><?php echo e($con['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label"><?php echo __('patient.info.label_desc'); ?></label>
                    <input type="text" name="label" class="form-input" placeholder="<?php echo __('patient.placeholder.label'); ?>">
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label"><?php echo __('patient.info.occupation_desc'); ?></label>
                    <input type="text" name="occupation" class="form-input" placeholder="<?php echo __('patient.placeholder.occupation'); ?>">
                </div>
            </div>
            
            <div class="form-group" style="margin-top: 1.5rem;">
                <label class="form-label"><?php echo __('patient.info.address'); ?></label>
                <input type="text" name="address" class="form-input" placeholder="<?php echo __('patient.placeholder.address'); ?>">
            </div>
        </div>

        <!-- Section 2: Contact & Social -->
        <div class="card" style="margin-bottom: 1.5rem; padding: 2rem;">
            <h3 style="font-size: 1rem; text-transform: uppercase; color: #10b981; margin-bottom: 1.5rem; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.5rem;">
                <i class="fas fa-address-book"></i> <?php echo __('patient.info.contact_social'); ?>
            </h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div class="form-group">
                    <label class="form-label"><?php echo __('patient.info.zalo'); ?></label>
                    <input type="text" name="zalo_number" class="form-input" placeholder="<?php echo __('patient.placeholder.zalo'); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input" placeholder="<?php echo __('patient.placeholder.email'); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label"><i class="fab fa-facebook" style="color: #1877f2;"></i> Facebook Link</label>
                    <input type="url" name="facebook_link" class="form-input" placeholder="https://facebook.com/username">
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fab fa-instagram" style="color: #e4405f;"></i> Instagram Link</label>
                    <input type="url" name="instagram_link" class="form-input" placeholder="https://instagram.com/username">
                </div>
            </div>
            <input type="hidden" name="twitter_link" value="">
        </div>

        <!-- Section 3: Guardian Info (Conditional Layout) -->
        <div class="card" style="margin-bottom: 1.5rem; padding: 2rem; background: #fffbeb; border: 1px solid #fef3c7;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem;">
                <div>
                    <h3 style="font-size: 1rem; text-transform: uppercase; color: #d97706; margin: 0;">
                        <i class="fas fa-user-shield"></i> <?php echo __('patient.info.guardian'); ?>
                    </h3>
                    <p style="font-size: 0.8rem; color: #92400e; margin-top: 0.25rem;"><?php echo __('patient.guardian.desc'); ?></p>
                </div>
                <i class="fas fa-child fa-2x" style="color: #f59e0b; opacity: 0.5;"></i>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div class="form-group">
                    <label class="form-label"><?php echo __('patient.guardian.name'); ?></label>
                    <input type="text" name="guardian_name" class="form-input" placeholder="<?php echo __('patient.guardian.placeholder_name'); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('patient.guardian.id_card'); ?></label>
                    <input type="text" name="guardian_id_card" class="form-input" placeholder="<?php echo __('patient.guardian.placeholder_id_card'); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('patient.info.phone'); ?></label>
                    <input type="text" name="guardian_phone" class="form-input" placeholder="<?php echo __('patient.guardian.placeholder_phone'); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('patient.guardian.relationship'); ?></label>
                    <select name="guardian_relationship" class="form-input">
                        <option value=""><?php echo __('patient.guardian.placeholder_relation'); ?></option>
                        <option value="Cha"><?php echo __('patient.guardian.rel_father'); ?></option>
                        <option value="Mẹ"><?php echo __('patient.guardian.rel_mother'); ?></option>
                        <option value="Ông/Bà"><?php echo __('patient.guardian.rel_grandparent'); ?></option>
                        <option value="Anh/Chị"><?php echo __('patient.guardian.rel_sibling'); ?></option>
                        <option value="Người thân khác"><?php echo __('patient.guardian.rel_other_relative'); ?></option>
                    </select>
                </div>
            </div>
        </div>

            <h3 style="font-size: 1rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 1.5rem; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.5rem;">
                <i class="fas fa-sticky-note"></i> <?php echo __('patient.info.notes'); ?>
            </h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div class="form-group">
                    <label class="form-label" style="color: #6366f1; font-weight: 800;"><?php echo __('medical.record.medical'); ?> (Doctor)</label>
                    <textarea name="notes" class="form-input" rows="4" placeholder="<?php echo __('patient.placeholder.notes'); ?>"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label" style="color: #db2777; font-weight: 800;"><?php echo __('patient.info.personal_notes'); ?> (Consultant)</label>
                    <textarea name="personal_notes" class="form-input" rows="4" placeholder="<?php echo __('patient.placeholder.personal_notes'); ?>" style="border-color: #fce7f3;"></textarea>
                    <small style="color: var(--text-muted); font-style: italic;"><?php echo __('patient.info.personal_notes_desc'); ?></small>
                </div>
            </div>
        </div>
        
        <div style="margin-top: 2rem; display: flex; gap: 1rem; justify-content: flex-end; padding-bottom: 4rem;">
            <a href="index.php" class="btn" style="background: #f1f5f9; color: var(--text-main); padding: 1rem 2.5rem;"><?php echo __('common.cancel_action'); ?></a>
            <button type="submit" class="btn btn-primary" style="padding: 1rem 3rem; font-weight: 700; font-size: 1.1rem; box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.4);">
                <i class="fas fa-save" style="margin-right: 0.5rem;"></i> <?php echo __('patient.btn.save_profile'); ?>
            </button>
        </div>
    </form>
</div>

<?php require_once '../../templates/footer.php'; ?>
