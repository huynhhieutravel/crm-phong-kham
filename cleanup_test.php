<?php
require_once 'includes/db.php';
$db = getDB();

// Find all patients with "Khách hàng Test V2"
$stmt = $db->query("SELECT id FROM patients WHERE full_name LIKE 'Khách hàng Test V2%'");
$patients = $stmt->fetchAll();

$deleted_count = 0;
foreach ($patients as $p) {
    $id = $p['id'];
    
    // Delete medical history
    $db->prepare("DELETE FROM medical_history WHERE patient_id = ?")->execute([$id]);
    
    // Delete patient
    $db->prepare("DELETE FROM patients WHERE id = ?")->execute([$id]);
    
    $deleted_count++;
}

echo "Successfully deleted $deleted_count test patients and their medical records.\n";
