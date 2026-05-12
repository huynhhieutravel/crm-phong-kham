<?php
// modules/patients/import.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_patients');

$page_title = __('patient.import.page_title');
require_once '../../templates/header.php';

$db = getDB();
$import_results = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    // C3 FIX: Verify CSRF
    verify_csrf('import.php');

    $file = $_FILES['csv_file'];
    
    // C3 FIX: Validate file ext/size
    $allowed_ext = ['csv', 'txt'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($ext, $allowed_ext)) {
        $import_results = ['success' => 0, 'error' => 1, 'details' => ['Chỉ chấp nhận file CSV (.csv, .txt)']];
    } elseif ($file['size'] > $max_size) {
        $import_results = ['success' => 0, 'error' => 1, 'details' => ['File quá lớn (tối đa 5MB)']];
    } elseif (($handle = fopen($file['tmp_name'], "r")) !== FALSE) {
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);
        
        $headers = fgetcsv($handle, 1000, ",");
        $success_count = 0;
        $error_count = 0;
        $errors = [];

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (count($data) < 3 || empty($data[1]) || empty($data[2])) continue;

            try {
                $customer_id = trim($data[0] ?? '');
                $name = trim($data[1]);
                $phone = trim($data[2]);
                $gender_raw = trim($data[3] ?? '');
                $birthday = trim($data[4] ?? '');
                $address = trim($data[5] ?? '');
                $label = trim($data[6] ?? '');
                $notes = trim($data[7] ?? '');
                
                $gender = ($gender_raw === __('patient.gender.male') || $gender_raw === 'male') ? 'male' : (($gender_raw === __('patient.gender.female') || $gender_raw === 'female') ? 'female' : 'other');

                $stmt = $db->prepare("INSERT INTO patients (customer_id, full_name, phone, gender, birthday, address, label, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$customer_id, $name, $phone, $gender, $birthday, $address, $label, $notes]);
                $success_count++;
            } catch (Exception $e) {
                $error_message = str_replace(':phone', e($phone), __('patient.import.msg_error_line'));
                $error_count++;
                $errors[] = $error_message . " " . e($e->getMessage());
            }
        }
        fclose($handle);
        $import_results = ['success' => $success_count, 'error' => $error_count, 'details' => $errors];
    }
}
?>

<div class="card shadow-sm" style="max-width: 600px; margin: 2rem auto; border-radius: 16px;">
    <div class="card-header bg-white" style="border-bottom: 1px solid #f1f5f9; padding: 1.5rem;">
        <h5 class="mb-0" style="font-weight: 800; color: var(--text-main);">
            <i class="fas fa-file-import text-primary mr-2"></i> <?php echo __('patient.import.title'); ?>
        </h5>
    </div>
    <div class="card-body" style="padding: 2rem;">
        <?php if ($import_results): ?>
            <div class="alert alert-<?php echo $import_results['error'] > 0 ? 'warning' : 'success'; ?> mb-4">
                <strong><?php echo __('patient.import.msg_success_header'); ?></strong> <?php echo __('patient.import.msg_success_count'); ?> <?php echo $import_results['success']; ?>, <?php echo __('patient.import.msg_error_count'); ?> <?php echo $import_results['error']; ?>.
            </div>
            <?php if (!empty($import_results['details'])): ?>
                <div style="max-height: 200px; overflow-y: auto; font-size: 0.8rem; background: #fff1f2; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                    <?php foreach ($import_results['details'] as $err) echo "<div>" . e($err) . "</div>"; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <div class="mb-4">
                <label class="form-label" style="font-weight: 700; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase;"><?php echo __('patient.import.label_select_file'); ?></label>
                <input type="file" name="csv_file" class="form-control" accept=".csv" required style="border-radius: 12px; padding: 0.6rem;">
                <p class="text-muted mt-2" style="font-size: 0.8rem;">
                    <i class="fas fa-info-circle"></i> <?php echo __('patient.import.hint_utf8'); ?> 
                    <a href="template_patients.csv" class="text-primary font-weight-bold"><?php echo __('patient.import.link_template'); ?></a>
                </p>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100" style="height: 48px; border-radius: 12px; font-weight: 700;"><?php echo __('patient.import.btn_start'); ?></button>
                <a href="index.php" class="btn btn-light" style="height: 48px; border-radius: 12px; display: flex; align-items: center; padding: 0 1.5rem;"><?php echo __('common.back'); ?></a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../templates/footer.php'; ?>
