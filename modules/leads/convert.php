<?php
// modules/leads/convert.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_leads');

// C1 FIX: Chỉ chấp nhận POST, không GET
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('Yêu cầu không hợp lệ.', 'error');
    redirect('index.php');
}

// C1 FIX: Verify CSRF token
verify_csrf('index.php');

$id = (int)($_POST['id'] ?? 0);
$force_merge = (int)($_POST['force_merge'] ?? 0);
$force_new = (int)($_POST['force_new'] ?? 0);
$db = getDB();

if (!$id) {
    set_flash('Lead không hợp lệ.', 'error');
    redirect('index.php');
}

$db->beginTransaction();
try {
    // 1. Fetch Lead data
    $stmt = $db->prepare("SELECT * FROM leads WHERE id = ?");
    $stmt->execute([$id]);
    $lead = $stmt->fetch();

    if (!$lead) {
        throw new Exception("Lead không tồn tại.");
    }

    if ($lead['status'] === 'converted') {
        throw new Exception("Lead này đã được chuyển đổi trước đó.");
    }

    // M4 FIX: Kiểm tra trùng lặp bệnh nhân bằng phone và tên trước khi tạo mới
    $existing_patient = null;
    
    if (!$force_merge && !$force_new) {
        if (!empty($lead['phone'])) {
            $stmt = $db->prepare("SELECT id, full_name, phone FROM patients WHERE phone = ? AND phone != '' LIMIT 1");
            $stmt->execute([$lead['phone']]);
            $existing_patient = $stmt->fetch();

            if ($existing_patient) {
                // Render confirmation UI
                $db->rollBack();
                $page_title = "Xác nhận chuyển đổi";
                require_once '../../templates/header.php';
                ?>
                <div class="card shadow-sm" style="max-width: 600px; margin: 3rem auto; padding: 2rem; border-radius: 16px;">
                    <h3 style="color: #ef4444; margin-bottom: 1rem;"><i class="fas fa-exclamation-triangle"></i> Phát hiện trùng số điện thoại</h3>
                    <p>Khách hàng <strong><?php echo e($lead['full_name']); ?></strong> có số điện thoại <strong><?php echo e($lead['phone']); ?></strong> đã tồn tại trong hệ thống dưới tên bệnh nhân <strong><?php echo e($existing_patient['full_name']); ?></strong>.</p>
                    <p>Bạn muốn tạo một hồ sơ bệnh nhân mới hoàn toàn, hay gộp thông tin vào hồ sơ đã có?</p>
                    
                    <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                        <form method="POST" action="convert.php" style="flex: 1;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                            <input type="hidden" name="force_new" value="1">
                            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.8rem; font-weight: 700; border-radius: 10px;">
                                <i class="fas fa-user-plus"></i> Tạo hồ sơ mới
                            </button>
                        </form>
                        <form method="POST" action="convert.php" style="flex: 1;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                            <input type="hidden" name="force_merge" value="1">
                            <button type="submit" class="btn" style="width: 100%; padding: 0.8rem; background: #f59e0b; color: white; font-weight: 700; border-radius: 10px;">
                                <i class="fas fa-link"></i> Gộp hồ sơ
                            </button>
                        </form>
                    </div>
                    <div style="margin-top: 1rem; text-align: center;">
                        <a href="index.php" class="btn btn-light" style="border-radius: 10px;">Hủy thao tác</a>
                    </div>
                </div>
                <?php
                require_once '../../templates/footer.php';
                exit;
            }
        }
    } else {
        if ($force_merge) {
            $stmt = $db->prepare("SELECT id, full_name, phone FROM patients WHERE phone = ? AND phone != '' LIMIT 1");
            $stmt->execute([$lead['phone']]);
            $existing_patient = $stmt->fetch();
        }
    }

    if ($existing_patient && !$force_new) {
        // Bệnh nhân đã tồn tại → chỉ cập nhật trường rỗng (tương tự checkin.php đã fix)
        $patient_id = $existing_patient['id'];
        $stmt = $db->prepare("
            UPDATE patients SET 
                full_name = COALESCE(NULLIF(full_name, ''), ?),
                gender = COALESCE(gender, ?),
                birthday = COALESCE(birthday, ?),
                email = COALESCE(NULLIF(email, ''), ?),
                address = COALESCE(NULLIF(address, ''), ?),
                source = COALESCE(NULLIF(source, ''), ?),
                lead_id = COALESCE(lead_id, ?)
            WHERE id = ?
        ");
        $stmt->execute([
            $lead['full_name'], $lead['gender'] ?? null, $lead['birthday'] ?? null,
            $lead['email'] ?? '', $lead['address'] ?? '', $lead['source'] ?? '',
            $lead['id'], $patient_id
        ]);
    } else {
        // 2. Insert into patients table
        $notes = "Chuyển đổi từ Lead Marketing.";
        if (isset($lead['medical_group']) && $lead['medical_group']) {
            $notes .= "\nNhóm bệnh tư vấn: " . $lead['medical_group'];
        }
        if (isset($lead['notes']) && $lead['notes']) {
            $notes .= "\nGhi chú gốc: " . $lead['notes'];
        }

        $patient_data = [
            'full_name' => $lead['full_name'],
            'phone' => $lead['phone'],
            'label' => 'Khách mới',
            'notes' => $notes
        ];

        $optionals = [
            'gender' => $lead['gender'] ?? 'Other',
            'birthday' => $lead['birthday'] ?? null,
            'email' => $lead['email'] ?? '',
            'address' => $lead['address'] ?? '',
            'zalo_number' => $lead['zalo_number'] ?? '',
            'source' => $lead['source'] ?? '',
            'consultant_id' => $lead['consultant_id'] ?? null,
            'lead_id' => $lead['id']
        ];

        // Detect available columns in patients table
        $available_cols = $db->query("SHOW COLUMNS FROM patients")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($optionals as $col => $val) {
            if (in_array($col, $available_cols)) {
                $patient_data[$col] = $val;
            }
        }

        $cols = implode(", ", array_keys($patient_data));
        $placeholders = implode(", ", array_fill(0, count($patient_data), "?"));
        
        $stmt = $db->prepare("INSERT INTO patients ($cols) VALUES ($placeholders)");
        $stmt->execute(array_values($patient_data));
        $patient_id = $db->lastInsertId();
    }

    // 3. Update Lead status
    $stmt = $db->prepare("UPDATE leads SET status = 'converted' WHERE id = ?");
    $stmt->execute([$id]);

    $db->commit();
    set_flash('Đã chuyển đổi Lead thành Bệnh nhân thành công!');
    redirect("../patients/view.php?id=$patient_id");

} catch (Exception $e) {
    $db->rollBack();
    set_flash($e->getMessage(), 'error');
    redirect('index.php');
}
