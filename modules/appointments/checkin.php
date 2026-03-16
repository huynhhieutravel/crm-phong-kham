<?php
// modules/appointments/checkin.php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$id = $_GET['id'] ?? 0;
$db = getDB();

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
        throw new Exception("Không tìm thấy lịch hẹn hoặc Lead không hợp lệ.");
    }

    // 2. Create Patient from Lead
    $stmt = $db->prepare("
        INSERT INTO patients (full_name, phone, gender, birthday, email, address, source)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $appt['full_name'],
        $appt['phone'],
        $appt['gender'],
        $appt['birthday'],
        $appt['email'],
        $appt['address'],
        $appt['source']
    ]);
    $patient_id = $db->lastInsertId();

    // 3. Update Lead status
    $stmt = $db->prepare("UPDATE leads SET status = 'converted' WHERE id = ?");
    $stmt->execute([$appt['lead_id']]);

    // 4. Update Appointment
    $stmt = $db->prepare("
        UPDATE appointments 
        SET patient_id = ?, lead_id = NULL, status = 'arrived' 
        WHERE id = ?
    ");
    $stmt->execute([$patient_id, $id]);

    $db->commit();
    set_flash("Check-in thành công! Lead đã được chuyển thành Bệnh nhân.");
    
    // Redirect to patient view to start clinical documentation
    redirect("../patients/view.php?id=" . $patient_id);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    set_flash($e->getMessage(), 'error');
    redirect("index.php");
}
