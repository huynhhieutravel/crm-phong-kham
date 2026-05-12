<?php
require_once 'includes/db.php';
$db = getDB();
$count = $db->query("SELECT count(*) FROM patients")->fetchColumn();
echo "Total patients: $count\n";
