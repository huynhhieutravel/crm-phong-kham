<?php
require_once 'includes/db.php';
$db = getDB();
$stmt = $db->query("SELECT id FROM patient_packages LIMIT 1");
echo "Valid ID: " . $stmt->fetchColumn() . "\n";
