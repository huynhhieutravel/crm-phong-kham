<?php
require_once 'includes/db.php';
$db = getDB();
$stmt = $db->query("DESCRIBE packages");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
