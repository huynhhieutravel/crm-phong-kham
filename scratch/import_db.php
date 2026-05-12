<?php
$sql = file_get_contents('/tmp/clinic_sync_local.sql');
$db = new PDO("mysql:host=127.0.0.1;dbname=clinic_management;charset=utf8mb4", "root", "");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec($sql);
echo "Import successful!\n";
