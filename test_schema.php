<?php
require_once 'config/database.php';
$db = getDB();
$stmt = $db->query("DESCRIBE medical_history");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
