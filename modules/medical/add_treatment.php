<?php
// modules/medical/add_treatment.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_medical');

$db = getDB();
$patient_id = (int)($_GET['patient_id'] ?? 0);
$session_id = isset($_GET['session_id']) ? (int)$_GET['session_id'] : null;

$stmt = $db->prepare("SELECT full_name FROM patients WHERE id = ?");
$stmt->execute([$patient_id]);
$patient_name = $stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $db->beginTransaction();
    try {
        $session_data = $_POST['session_data'];

        // 1. Insert treatment (payment_status defaults to 'unpaid')
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

        $db->commit();
        
        // Redirect to Checkout page for payment
        redirect("../sales/checkout.php?treatment_id=$treatment_id");
        
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

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <div style="margin-bottom: 2rem;">
        <h2 style="margin: 0;"><?php echo __('medical.treatment.title'); ?></h2>
        <p style="color: var(--text-muted);"><?php echo __('medical.treatment.patient_label'); ?><strong><?php echo e($patient_name); ?></strong></p>
    </div>

    <?php if (isset($error)): ?>
        <div style="background: #fee2e2; color: #ef4444; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Info: Payment step happens after this -->
    <div style="margin-bottom: 1.5rem; padding: 1rem; background: #eff6ff; border-radius: 12px; border: 1px solid #bfdbfe;">
        <p style="font-size: 0.85rem; color: #1e40af; margin: 0; font-weight: 600;">
            <i class="fas fa-info-circle"></i> <?php echo __('Sau khi lưu buổi điều trị, hệ thống sẽ chuyển đến trang Thanh Toán để chọn phương thức (Tiền mặt / CK / Gói / Nợ).'); ?>
        </p>
    </div>

    <form method="POST" class="no-autosave">
        <?php echo csrf_field(); ?>
        <div class="form-group">
            <label class="form-label"><?php echo __('medical.treatment.details_label'); ?></label>
            <textarea name="session_data" class="form-input" rows="6" required placeholder="<?php echo __('medical.treatment.details_placeholder'); ?>"></textarea>
        </div>

        <div style="margin-top: 1.5rem; padding: 1rem; background: #f8fafc; border-radius: 12px; border: 1px dashed var(--border-color);">
            <p style="font-size: 0.9rem; text-align: center; color: var(--text-muted);"><i class="fas fa-camera"></i> <?php echo __('medical.treatment.photo_label'); ?></p>
        </div>

        <div style="margin-top: 2rem; display: flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary"><?php echo __('medical.treatment.btn_complete'); ?></button>
            <a href="../patients/view.php?id=<?php echo $patient_id; ?>" class="btn" style="background: #f1f5f9; color: var(--text-main);"><?php echo __('common.cancel'); ?></a>
        </div>
    </form>
</div>

<?php require_once '../../templates/footer.php'; ?>
