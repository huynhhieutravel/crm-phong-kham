<?php
require 'includes/db.php';
$stmt = $db->query("SHOW TABLES LIKE 'settings'");
echo json_encode($stmt->fetchAll());
