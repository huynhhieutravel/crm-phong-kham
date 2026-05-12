<?php
require_once __DIR__ . '/includes/db.php';
$db = getDB();
$stmt = $db->prepare("DELETE FROM medical_sessions WHERE patient_id = 90 ORDER BY id DESC LIMIT 1");
$stmt->execute();
echo "Done";
?>
