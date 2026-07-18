<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

session_id('test1234');
session_start();
$_SESSION['user_id'] = 1;

// Simulate POST body
$_SERVER['REQUEST_METHOD'] = 'POST';
$json = json_encode([
    'patient_id' => 1,
    'items' => [
        ['type' => 'buy_package', 'itemId' => 1, 'qty' => 2, 'price' => 9000000, 'useNow' => true],
        ['type' => 'buy_package', 'itemId' => 2, 'qty' => 1, 'price' => 75000000, 'useNow' => true]
    ],
    'subtotal' => 94000000,
    'discount_amount' => 0,
    'discount_note' => '',
    'total_amount' => 94000000,
    'cash_amount' => 250000,
    'transfer_amount' => 0,
    'transfer_personal_amount' => 0,
    'transfer_company_amount' => 0,
    'package_deduct' => 0,
    'debt_amount' => 93750000,
    'note' => '',
    'treatment_id' => null,
    'patient_package_id' => null,
    'payment_id' => null
]);
$_POST = json_decode($json, true);

// Override file_get_contents inside the target file
// Actually, it uses php://input. So we can't easily override it unless we use curl.
