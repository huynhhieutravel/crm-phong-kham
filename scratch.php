<?php
require_once 'includes/db.php';
$db = getDB();
$stmt = $db->query("SHOW COLUMNS FROM leave_requests");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
