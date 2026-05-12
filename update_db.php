<?php
$config = require '/var/www/crm_phong_kham/config/database.php';
$dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
$db = new PDO($dsn, $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('ALTER TABLE patient_packages MODIFY total_amount DECIMAL(15,2) NOT NULL;');
$db->exec('ALTER TABLE patient_packages MODIFY paid_amount DECIMAL(15,2) DEFAULT 0.00;');
$db->exec('ALTER TABLE patient_packages ADD COLUMN discount_amount DECIMAL(15,2) DEFAULT 0.00 AFTER total_amount;');
$db->exec('ALTER TABLE patient_packages ADD COLUMN discount_note VARCHAR(255) NULL AFTER discount_amount;');
echo 'Remote schema updated.';
