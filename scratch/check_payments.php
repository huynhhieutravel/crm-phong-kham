<?php
require_once 'includes/db.php';
$db = getDB();
$stmt = $db->query("DESCRIBE payments");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
