<?php
require __DIR__ . '/../includes/db.php';
$db = getDB();
$cols = $db->query("SHOW COLUMNS FROM transactions")->fetchAll(PDO::FETCH_ASSOC);
print_r($cols);
