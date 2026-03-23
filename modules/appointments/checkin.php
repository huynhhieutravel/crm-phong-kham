<?php
// modules/appointments/checkin.php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$id = isset($_GET['id']) ? $_GET['id'] : 0;
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
        throw new Exception(__('appointment.msg.checkin_invalid'));
    }

    // 2. Smart Check: Find existing patient by lead_id or phone
    $stmt = $db->prepare("SELECT id FROM patients WHERE lead_id = ? OR (phone = ? AND phone != '') LIMIT 1");
    $stmt->execute([$appt['lead_id'], $appt['phone']]);
    $existing_patient = $stmt->fetch();

    if ($existing_patient) {
        $patient_id = $existing_patient['id'];
        // Update patient info from lead to ensure it's up to date
        $stmt = $db->prepare("
            UPDATE patients 
            SET full_name = ?, gender = ?, birthday = ?, email = ?, address = ?, source = ?, lead_id = ?
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
    redirect("index.php");
}
