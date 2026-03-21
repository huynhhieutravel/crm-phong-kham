<?php
// modules/leads/convert.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

$id = $_GET['id'] ?? 0;
$db = getDB();

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

    // 2. Insert into patients table
    // Carrying over medical_group into notes for the doctor to see
    $notes = "Chuyển đổi từ Lead Marketing.";
    if ($lead['medical_group']) {
        $notes .= "\nNhóm bệnh tư vấn: " . $lead['medical_group'];
    }
    if ($lead['notes']) {
        $notes .= "\nGhi chú gốc: " . $lead['notes'];
    }

    $stmt = $db->prepare("
        INSERT INTO patients (full_name, gender, birthday, phone, email, address, zalo_number, source, consultant_id, label, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $lead['full_name'],
        $lead['gender'],
        $lead['birthday'],
        $lead['phone'],
        $lead['email'],
        $lead['address'],
        $lead['zalo_number'],
        $lead['source'],
        $lead['consultant_id'],
        'Khách mới',
        $notes
    ]);
    $patient_id = $db->lastInsertId();

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
