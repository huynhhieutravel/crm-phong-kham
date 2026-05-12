<?php
require 'config/database.php';
$db = new PDO("mysql:host=127.0.0.1;dbname=clinic_management;charset=utf8mb4", "crm_admin", "CrmAdmin2026@Pass");
$stmt = $db->query("SELECT count(*) FROM patient_packages");
echo "Count: " . $stmt->fetchColumn();
