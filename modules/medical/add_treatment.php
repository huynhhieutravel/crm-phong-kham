<?php
// modules/medical/add_treatment.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_medical');

$db = getDB();
$patient_id = (int)($_GET['patient_id'] ?? 0);
$session_id = isset($_GET['session_id']) ? (int)$_GET['session_id'] : null;

$existing_treatment = null;
if ($session_id) {
    $stmt = $db->prepare("SELECT id, session_data, patient_id FROM treatments WHERE session_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$session_id]);
    $existing_treatment = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif (isset($_GET['id'])) {
    $treatment_id = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT id, session_data, session_id, patient_id FROM treatments WHERE id = ?");
    $stmt->execute([$treatment_id]);
    $existing_treatment = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($existing_treatment) {
        if (!$patient_id) $patient_id = (int)$existing_treatment['patient_id'];
        if (!$session_id) $session_id = (int)$existing_treatment['session_id'];
    }
}

$stmt = $db->prepare("SELECT full_name FROM patients WHERE id = ?");
$stmt->execute([$patient_id]);
$patient_name = $stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $db->beginTransaction();
    try {
        $session_data = $_POST['session_data'];

        // 1. Insert or Update treatment
        if ($existing_treatment) {
            $treatment_id = $existing_treatment['id'];
            $stmt = $db->prepare("
                UPDATE treatments 
                SET session_data = ?, technician_id = ? 
                WHERE id = ?
            ");
            $stmt->execute([
                $session_data,
                $_SESSION['user_id'],
                $treatment_id
            ]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO treatments (patient_id, session_id, technician_id, session_data)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $patient_id,
                $session_id,
                $_SESSION['user_id'],
                $session_data
            ]);
            $treatment_id = $db->lastInsertId();
        }

        $db->commit();
        set_flash(__('medical.treatment.msg_success'));
        
        if ($session_id) {
            redirect("session_view.php?id=$session_id");
        } else {
            redirect("../patients/view.php?id=$patient_id");
        }
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log('Treatment creation error: ' . $e->getMessage());
        $error = __('medical.treatment.msg_error');
    }
}

$page_title = __('medical.treatment.page_title');
$current_page = 'medical';
require_once '../../templates/header.php';
?>

<div class="card" style="max-width: 900px; margin: 0 auto;">
    <div style="margin-bottom: 2rem;">
        <h2 style="margin: 0;"><?php echo __('medical.treatment.title'); ?></h2>
        <p style="color: var(--text-muted);"><?php echo __('medical.treatment.patient_label'); ?><strong><?php echo e($patient_name); ?></strong></p>
    </div>

    <?php if (isset($error)): ?>
        <div style="background: #fee2e2; color: #ef4444; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" class="no-autosave">
        <?php echo csrf_field(); ?>
        <div class="form-group">
            <label class="form-label" style="font-weight: 700; font-size: 1rem; margin-bottom: 0.75rem;"><?php echo __('medical.treatment.details_label'); ?></label>
            <textarea name="session_data" class="form-input" rows="12" required placeholder="<?php echo __('medical.treatment.details_placeholder'); ?>" style="min-height: 260px; font-size: 1rem; line-height: 1.6; padding: 1rem; border-radius: 12px; resize: vertical;"><?php echo $existing_treatment ? e($existing_treatment['session_data']) : ''; ?></textarea>
        </div>

        <div style="margin-top: 1.5rem; padding: 1rem; background: #f8fafc; border-radius: 12px; border: 1px dashed var(--border-color);">
            <p style="font-size: 0.9rem; text-align: center; color: var(--text-muted);"><i class="fas fa-camera"></i> <?php echo __('medical.treatment.photo_label'); ?></p>
        </div>

        <div style="margin-top: 2rem; display: flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2.5rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem;"><i class="fas fa-save"></i> <?php echo __('common.save'); ?></button>
            <a href="<?php echo $session_id ? "session_view.php?id=$session_id" : "../patients/view.php?id=$patient_id"; ?>" class="btn" style="background: #f1f5f9; color: var(--text-main); padding: 0.75rem 1.5rem; font-weight: 600;"><?php echo __('common.cancel'); ?></a>
        </div>
    </form>
</div>

<?php require_once '../../templates/footer.php'; ?>
