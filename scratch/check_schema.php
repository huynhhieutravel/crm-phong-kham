<?php
require_once 'includes/db.php';
$db = getDB();
$stmt = $db->query("DESCRIBE treatments");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
