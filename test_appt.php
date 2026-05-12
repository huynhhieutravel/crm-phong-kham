<?php
require_once __DIR__ . '/includes/db.php';
$db = getDB();
$stmt = $db->query("SELECT id, status FROM appointments ORDER BY id DESC LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
