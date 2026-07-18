<?php
// modules/appointments/checkin.php

require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_appointments');

// C2 FIX: Chỉ xử lý POST request, không chấp nhận GET
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('Yêu cầu không hợp lệ.', 'error');
    $redirect_url = $_SESSION['appointment_list_url'] ?? 'index.php';
    redirect($redirect_url);
}

// C1 FIX: Verify CSRF token
verify_csrf('index.php');

// C2 FIX: Đọc từ POST thay vì GET
$id = (int)($_POST['appointment_id'] ?? 0);
$force_merge = (int)($_POST['force_merge'] ?? 0);
$force_new = (int)($_POST['force_new'] ?? 0);
$db = getDB();

if (!$id) {
    set_flash(__('appointment.msg.checkin_invalid'), 'error');
    $redirect_url = $_SESSION['appointment_list_url'] ?? 'index.php';
    redirect($redirect_url);
}

try {
    $db->beginTransaction();

    // 1. Get appointment and lead data
    $stmt = $db->prepare("
        SELECT a.*, l.full_name, l.phone, l.gender, l.birthday, l.email, l.address, l.source 
        FROM appointments a
        JOIN leads l ON a.lead_id = l.id
        WHERE a.id = ?
    ");
    $stmt->execute([$id]);
    $appt = $stmt->fetch();

    if (!$appt) {
        throw new Exception(__('appointment.msg.checkin_invalid'));
    }

    $existing_patient = null;

    if (!$force_merge && !$force_new) {
        // First check exact lead match
        $stmt = $db->prepare("SELECT id, full_name, phone FROM patients WHERE lead_id = ? AND lead_id IS NOT NULL AND lead_id != 0 LIMIT 1");
        $stmt->execute([$appt['lead_id']]);
        $existing_patient_by_lead = $stmt->fetch();

        if ($existing_patient_by_lead) {
            $force_merge = 1;
            $existing_patient = $existing_patient_by_lead;
        } else {
            // Smart Check: Find existing patient by phone
            if (!empty($appt['phone'])) {
                $stmt = $db->prepare("SELECT id, full_name, phone FROM patients WHERE phone = ? AND phone != '' LIMIT 1");
                $stmt->execute([$appt['phone']]);
                $existing_patient = $stmt->fetch();

                if ($existing_patient) {
                    // Render confirmation UI
                    $db->rollBack();
                    $page_title = "Xác nhận gộp hồ sơ";
                    require_once '../../templates/header.php';
                    ?>
                    <div class="card shadow-sm" style="max-width: 600px; margin: 3rem auto; padding: 2rem; border-radius: 16px;">
                        <h3 style="color: #ef4444; margin-bottom: 1rem;"><i class="fas fa-exclamation-triangle"></i> Phát hiện trùng số điện thoại</h3>
                        <p>Khách hàng <strong><?php echo e($appt['full_name']); ?></strong> có số điện thoại <strong><?php echo e($appt['phone']); ?></strong> đã tồn tại trong hệ thống dưới tên bệnh nhân <strong><?php echo e($existing_patient['full_name']); ?></strong>.</p>
                        <p>Bạn muốn tạo một hồ sơ bệnh nhân mới hoàn toàn, hay gộp thông tin vào hồ sơ đã có?</p>
                        
                        <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                            <form method="POST" action="checkin.php" style="flex: 1;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="appointment_id" value="<?php echo $id; ?>">
                                <input type="hidden" name="force_new" value="1">
                                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.8rem; font-weight: 700; border-radius: 10px;">
                                    <i class="fas fa-user-plus"></i> Tạo hồ sơ mới
                                </button>
                            </form>
                            <form method="POST" action="checkin.php" style="flex: 1;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="appointment_id" value="<?php echo $id; ?>">
                                <input type="hidden" name="force_merge" value="1">
                                <button type="submit" class="btn" style="width: 100%; padding: 0.8rem; background: #f59e0b; color: white; font-weight: 700; border-radius: 10px;">
                                    <i class="fas fa-link"></i> Gộp hồ sơ
                                </button>
                            </form>
                        </div>
                        <div style="margin-top: 1rem; text-align: center;">
                            <?php $back_url = $_SESSION['appointment_list_url'] ?? 'index.php'; ?>
                            <a href="<?php echo htmlspecialchars($back_url); ?>" class="btn btn-light" style="border-radius: 10px;">Hủy thao tác</a>
                        </div>
                    </div>
                    <?php
                    require_once '../../templates/footer.php';
                    exit;
                }
            }
        }
    } else {
        if ($force_merge) {
            $stmt = $db->prepare("SELECT id, full_name, phone FROM patients WHERE phone = ? AND phone != '' LIMIT 1");
            $stmt->execute([$appt['phone']]);
            $existing_patient = $stmt->fetch();
        }
    }

    if ($existing_patient && !$force_new) {
        $patient_id = $existing_patient['id'];
        // C3 FIX: Chỉ cập nhật các trường rỗng/NULL, không ghi đè dữ liệu đã có
        $stmt = $db->prepare("
            UPDATE patients 
            SET full_name = COALESCE(NULLIF(full_name, ''), ?),
                gender = COALESCE(gender, ?),
                birthday = COALESCE(birthday, ?),
                email = COALESCE(NULLIF(email, ''), ?),
                address = COALESCE(NULLIF(address, ''), ?),
                source = COALESCE(NULLIF(source, ''), ?),
                lead_id = COALESCE(lead_id, ?)
            WHERE id = ?
        ");
        $stmt->execute([
            $appt['full_name'], $appt['gender'], $appt['birthday'], 
            $appt['email'], $appt['address'], $appt['source'], 
            $appt['lead_id'], $patient_id
        ]);
    } else {
        // Create New Patient
        $stmt = $db->prepare("
            INSERT INTO patients (full_name, phone, gender, birthday, email, address, source, lead_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $appt['full_name'], $appt['phone'], $appt['gender'], 
            $appt['birthday'], $appt['email'], $appt['address'], 
            $appt['source'], $appt['lead_id']
        ]);
        $patient_id = $db->lastInsertId();
    }

    // 3. Update Lead status
    $stmt = $db->prepare("UPDATE leads SET status = 'converted' WHERE id = ?");
    $stmt->execute([$appt['lead_id']]);

    // 4. Update Appointment - Keep lead_id for historical link and revert support
    $stmt = $db->prepare("
        UPDATE appointments 
        SET patient_id = ?, status = 'arrived' 
        WHERE id = ?
    ");
    $stmt->execute([$patient_id, $id]);

    $db->commit();
    set_flash(__('appointment.msg.checkin_success'));
    
    // Redirect to patient view to start clinical documentation
    redirect("../patients/view.php?id=" . $patient_id);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    set_flash($e->getMessage(), 'error');
    $redirect_url = $_SESSION['appointment_list_url'] ?? 'index.php';
    redirect($redirect_url);
}
