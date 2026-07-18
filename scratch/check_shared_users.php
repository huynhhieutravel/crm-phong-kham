<?php
require_once 'includes/db.php';
$db = getDB();
$stmt = $db->query("DESCRIBE package_shared_users");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
