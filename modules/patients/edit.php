<?php
// modules/patients/edit.php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';
require_permission('manage_patients');

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

// Fetch active consultants
$consultants_stmt = $db->query("
    SELECT u.id, u.full_name 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    WHERE r.name IN ('cskh', 'admin') AND u.status = 'active'
    ORDER BY u.full_name
");
$consultants = $consultants_stmt->fetchAll();

$stmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->execute([$id]);
$p = $stmt->fetch();

if (!$p) {
    set_flash(__('patient.msg.not_found'), 'error');
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // H1 FIX: Verify CSRF
    verify_csrf("edit.php?id=$id");
    $optionals = [
        'customer_id' => $_POST['customer_id'] ?: null,
        'full_name' => $_POST['full_name'] ?: '',
        'gender' => $_POST['gender'] ?: '',
        'birthday' => !empty($_POST['birthday']) ? $_POST['birthday'] : null,
        'phone' => $_POST['phone'] ?: '',
        'email' => $_POST['email'] ?: '',
        'address' => $_POST['address'] ?: '',
        'branch' => $_POST['branch'] ?: '',
        'branch_id' => 1,
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
        $set_parts = [];
        foreach (array_keys($data) as $col) {
            $set_parts[] = "$col = ?";
        }
        $sql = "UPDATE patients SET " . implode(", ", $set_parts) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge(array_values($data), [$id]));
    }
    
    set_flash(__('patient.msg.update_success'));
    redirect("view.php?id=$id");
}

$page_title = __('patient.edit.title');
$current_page = 'patients';
require_once '../../templates/header.php';
?>

<div style="max-width: 1000px; margin: 0 auto;">
    <div style="margin-bottom: 2rem;">
        <h2 style="font-weight: 800; color: var(--text-main); margin: 0;"><i class="fas fa-edit" style="color: var(--primary);"></i> <?php echo __('patient.edit.heading'); ?></h2>
        <p style="color: var(--text-muted); margin-top: 0.25rem;"><?php echo __('patient.edit.subtitle'); ?> <strong style="color: var(--text-main);"><?php echo e($p['full_name']); ?></strong></p>
    </div>

    <form method="POST">
        <?php echo csrf_field(); ?>
        <!-- Section 1: General Info -->
        <div class="card" style="margin-bottom: 1.5rem; padding: 2rem;">
            <h3 style="font-size: 1rem; text-transform: uppercase; color: var(--primary); margin-bottom: 1.5rem; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.5rem;">
                <i class="fas fa-info-circle"></i> <?php echo __('patient.info.general'); ?>
            </h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label"><?php echo __('patient.info.fullname'); ?> <span style="color: red;">*</span></label>
                    <input type="text" name="full_name" class="form-input" required value="<?php echo e($p['full_name']); ?>" style="font-size: 1.1rem; font-weight: 600;">
                </div>
                
                <div class="form-group">
                    <label class="form-label"><?php echo __('patient.info.phone'); ?> <span style="color: red;">*</span></label>
                    <input type="text" name="phone" class="form-input" required value="<?php echo e($p['phone']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('patient.info.customer_id_opt'); ?></label>
                    <input type="text" name="customer_id" class="form-input" value="<?php echo e($p['customer_id']); ?>" placeholder="<?php echo __('patient.placeholder.customer_id'); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label"><?php echo __('patient.gender'); ?></label>
                    <div style="display: flex; gap: 1.5rem; padding: 0.5rem 0;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="radio" name="gender" value="male" <?php echo $p['gender'] === 'male' ? 'checked' : ''; ?>> <?php echo __('patient.gender.male'); ?>
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="radio" name="gender" value="female" <?php echo $p['gender'] === 'female' ? 'checked' : ''; ?>> <?php echo __('patient.gender.female'); ?>
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="radio" name="gender" value="other" <?php echo $p['gender'] === 'other' ? 'checked' : ''; ?>> <?php echo __('patient.gender.other'); ?>
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('patient.info.dob'); ?></label>
                    <input type="date" name="birthday" min="1900-01-01" max="<?php echo date('Y-m-d'); ?>" class="form-input" value="<?php echo e($p['birthday']); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label"><?php echo __('patient.info.branch'); ?></label>
                    <select name="branch" class="form-input">
                        <option value="Trụ sở chính" <?php echo $p['branch'] === 'Trụ sở chính' ? 'selected' : ''; ?>><?php echo __('common.main_branch'); ?></option>
                        <option value="Chi nhánh 1" <?php echo $p['branch'] === 'Chi nhánh 1' ? 'selected' : ''; ?>><?php echo __('common.branch_1'); ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('patient.info.source'); ?></label>
                    <select name="source" class="form-input">
                        <?php foreach (get_lead_sources() as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo $p['source'] === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                        <option value="Walk-in" <?php echo $p['source'] === 'Walk-in' ? 'selected' : ''; ?>><?php echo __('patient.source.walk_in'); ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('patient.info.consultant'); ?></label>
                    <select name="consultant_id" class="form-input">
                        <option value=""><?php echo __('patient.placeholder.select_consultant'); ?></option>
                        <?php foreach ($consultants as $con): ?>
                            <option value="<?php echo $con['id']; ?>" <?php echo $p['consultant_id'] == $con['id'] ? 'selected' : ''; ?>><?php echo e($con['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label"><?php echo __('patient.info.label_desc'); ?></label>
                    <input type="text" name="label" class="form-input" value="<?php echo e($p['label']); ?>" placeholder="<?php echo __('patient.placeholder.label'); ?>">
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label"><?php echo __('patient.info.occupation_desc'); ?></label>
                    <input type="text" name="occupation" class="form-input" value="<?php echo e($p['occupation']); ?>" placeholder="<?php echo __('patient.placeholder.occupation'); ?>">
                </div>
            </div>
            
            <div class="form-group" style="margin-top: 1.5rem;">
                <label class="form-label"><?php echo __('patient.info.address'); ?></label>
                <input type="text" name="address" class="form-input" value="<?php echo e($p['address']); ?>">
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
                    <input type="text" name="zalo_number" class="form-input" value="<?php echo e($p['zalo_number']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input" value="<?php echo e($p['email']); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label"><i class="fab fa-facebook" style="color: #1877f2;"></i> Facebook Link</label>
                    <input type="url" name="facebook_link" class="form-input" value="<?php echo e($p['facebook_link']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fab fa-instagram" style="color: #e4405f;"></i> Instagram Link</label>
                    <input type="url" name="instagram_link" class="form-input" value="<?php echo e($p['instagram_link']); ?>">
                </div>
            </div>
            <input type="hidden" name="twitter_link" value="<?php echo e($p['twitter_link']); ?>">
        </div>

        <!-- Section 3: Guardian Info -->
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
                    <input type="text" name="guardian_name" class="form-input" value="<?php echo e($p['guardian_name']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('patient.guardian.id_card'); ?></label>
                    <input type="text" name="guardian_id_card" class="form-input" value="<?php echo e($p['guardian_id_card']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('patient.info.phone'); ?></label>
                    <input type="text" name="guardian_phone" class="form-input" value="<?php echo e($p['guardian_phone']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('patient.guardian.relationship'); ?></label>
                    <select name="guardian_relationship" class="form-input">
                        <option value=""><?php echo __('patient.guardian.placeholder_relation'); ?></option>
                        <option value="Cha" <?php echo $p['guardian_relationship'] === 'Cha' ? 'selected' : ''; ?>><?php echo __('patient.guardian.rel_father'); ?></option>
                        <option value="Mẹ" <?php echo $p['guardian_relationship'] === 'Mẹ' ? 'selected' : ''; ?>><?php echo __('patient.guardian.rel_mother'); ?></option>
                        <option value="Ông/Bà" <?php echo $p['guardian_relationship'] === 'Ông/Bà' ? 'selected' : ''; ?>><?php echo __('patient.guardian.rel_grandparent'); ?></option>
                        <option value="Anh/Chị" <?php echo $p['guardian_relationship'] === 'Anh/Chị' ? 'selected' : ''; ?>><?php echo __('patient.guardian.rel_sibling'); ?></option>
                        <option value="Người thân khác" <?php echo $p['guardian_relationship'] === 'Người thân khác' ? 'selected' : ''; ?>><?php echo __('patient.guardian.rel_other_relative'); ?></option>
                    </select>
                </div>
            </div>
        </div>

            <h3 style="font-size: 1rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 1.5rem; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.5rem;">
                <i class="fas fa-sticky-note"></i> <?php echo __('patient.info.notes'); ?>
            </h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div class="form-group">
                    <label class="form-label" style="color: #6366f1; font-weight: 800;"><?php echo __('medical.record.medical'); ?> <?php echo __('patient.info.notes_doctor'); ?></label>
                    <textarea name="notes" class="form-input" rows="4"><?php echo e($p['notes']); ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label" style="color: #db2777; font-weight: 800;"><?php echo __('patient.info.personal_notes'); ?> <?php echo __('patient.info.notes_consultant'); ?></label>
                    <textarea name="personal_notes" class="form-input" rows="4" style="border-color: #fce7f3;"><?php echo e($p['personal_notes']); ?></textarea>
                    <small style="color: var(--text-muted); font-style: italic;"><?php echo __('patient.info.personal_notes_desc'); ?></small>
                </div>
            </div>
        </div>
        
        <div style="margin-top: 2rem; display: flex; gap: 1rem; justify-content: flex-end; padding-bottom: 4rem;">
            <a href="view.php?id=<?php echo $id; ?>" class="btn" style="background: #f1f5f9; color: var(--text-main); padding: 1rem 2.5rem;"><?php echo __('common.cancel_action'); ?></a>
            <button type="submit" class="btn btn-primary" style="padding: 1rem 3rem; font-weight: 700; font-size: 1.1rem; box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.4);">
                <i class="fas fa-save" style="margin-right: 0.5rem;"></i> <?php echo __('patient.btn.update_profile'); ?>
            </button>
        </div>
    </form>
</div>

<?php require_once '../../templates/footer.php'; ?>
