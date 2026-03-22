<?php
require_once '../../includes/db.php';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'contact_id' => 'lead:27',
    'doctor_id' => '1',
    'appointment_date' => '2026-03-21',
    'appointment_time' => '14:00',
    'appointment_end_time' => '14:30',
    'type' => 'consultation',
    'notes' => 'Test notes'
];

$_SESSION['branch_id'] = 1; 
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

ob_start();
try {
    include 'add.php';
} catch (Throwable $e) {
    ob_end_clean();
    echo "ERROR CAUGHT:\n";
    echo $e->getMessage() . "\n";
    exit(1);
}
ob_end_clean();
echo "SUCCESS\n";
