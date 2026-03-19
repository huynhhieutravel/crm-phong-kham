<?php
// modules/medical/add_treatment.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

$db = getDB();
$patient_id = $_GET['patient_id'] ?? 0;
$session_id = $_GET['session_id'] ?? null;

$stmt = $db->prepare("SELECT full_name FROM patients WHERE id = ?");
$stmt->execute([$patient_id]);
$patient_name = $stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db->beginTransaction();
    try {
        $session_data = $_POST['session_data'];
        $patient_package_id = !empty($_POST['patient_package_id']) ? $_POST['patient_package_id'] : null;

        // 1. Insert treatment
        $stmt = $db->prepare("
            INSERT INTO treatments (patient_id, session_id, technician_id, session_data, package_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $patient_id,
            $session_id,
            $_SESSION['user_id'],
            $session_data,
            $patient_package_id
        ]);
        $treatment_id = $db->lastInsertId();

        // 2. Handle package usage if selected
        if ($patient_package_id) {
            // Subtract session
            $db->prepare("UPDATE patient_packages SET sessions_remaining = sessions_remaining - 1 WHERE id = ?")
               ->execute([$patient_package_id]);
            
            // Log usage (Corporate support: log who actually used it)
            $db->prepare("INSERT INTO package_usage_logs (patient_package_id, patient_id, treatment_id) VALUES (?, ?, ?)")
               ->execute([$patient_package_id, $patient_id, $treatment_id]);
        }

        $db->commit();
        set_flash('Lưu nhật ký điều trị thành công!');
        
        if ($session_id) {
            redirect("session_view.php?id=$session_id");
        } else {
            redirect("../patients/view.php?id=$patient_id");
        }
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Lỗi: " . $e->getMessage();
    }
}

$page_title = 'Ghi nhật ký điều trị';
$current_page = 'medical';
require_once '../../templates/header.php';

// Fetch active packages for this patient (including corporate ones where this patient might be a user)
$stmt = $db->prepare("
    SELECT pp.*, p.name as package_name, p.is_corporate
    FROM patient_packages pp
    JOIN packages p ON pp.package_id = p.id
    WHERE pp.sessions_remaining > 0 AND (pp.patient_id = ? OR p.is_corporate = 1)
    AND pp.status = 'active'
");
$stmt->execute([$patient_id]);
$available_packages = $stmt->fetchAll();
?>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <div style="margin-bottom: 2rem;">
        <h2 style="margin: 0;">Nhật ký điều trị</h2>
        <p style="color: var(--text-muted);">Bệnh nhân: <strong><?php echo e($patient_name); ?></strong></p>
    </div>

    <?php if (isset($error)): ?>
        <div style="background: #fee2e2; color: #ef4444; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php if (!empty($available_packages)): ?>
            <div class="form-group" style="margin-bottom: 1.5rem; padding: 1rem; background: #f0fdf4; border-radius: 12px; border: 1px solid #bbf7d0;">
                <label class="form-label" style="color: #166534; font-weight: 700;">Áp dụng Gói dịch vụ</label>
                <select name="patient_package_id" class="form-input" style="border-color: #86efac;">
                    <option value="">-- Không dùng gói (Khách lẻ) --</option>
                    <?php foreach ($available_packages as $ap): ?>
                        <option value="<?php echo $ap['id']; ?>">
                            <?php echo e($ap['package_name']); ?> (Còn <?php echo $ap['sessions_remaining']; ?> buổi)
                            <?php echo $ap['is_corporate'] ? '[Corporate]' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p style="font-size: 0.8rem; color: #166534; margin-top: 0.5rem; opacity: 0.8;">Hệ thống sẽ tự động trừ 01 buổi trong gói khi hoàn tất.</p>
            </div>
        <?php endif; ?>

        <div class="form-group">
            <label class="form-label">Chi tiết buổi điều trị</label>
            <textarea name="session_data" class="form-input" rows="6" required placeholder="Ghi nhận tình trạng hiện tại, các kỹ thuật đã thực hiện..."></textarea>
        </div>

        <div style="margin-top: 1.5rem; padding: 1rem; background: #f8fafc; border-radius: 12px; border: 1px dashed var(--border-color);">
            <p style="font-size: 0.9rem; text-align: center; color: var(--text-muted);"><i class="fas fa-camera"></i> Chụp ảnh Trước/Sau (Sẽ triển khai sau)</p>
        </div>

        <div style="margin-top: 2rem; display: flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary">Hoàn tất buổi khám</button>
            <a href="../patients/view.php?id=<?php echo $patient_id; ?>" class="btn" style="background: #f1f5f9; color: var(--text-main);">Hủy</a>
        </div>
    </form>
</div>

<?php require_once '../../templates/footer.php'; ?>
