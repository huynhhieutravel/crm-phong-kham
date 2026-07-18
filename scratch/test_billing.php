<?php
session_start();
$_SESSION['user_id'] = 1;
$_SERVER['REQUEST_METHOD'] = 'POST';

$input = [
    'patient_id' => 1,
    'items' => [
        ['type' => 'buy_package', 'itemId' => 1, 'qty' => 2, 'price' => 9000000, 'useNow' => true],
        ['type' => 'buy_package', 'itemId' => 2, 'qty' => 1, 'price' => 75000000, 'useNow' => true]
    ],
    'subtotal' => 93000000,
    'discount_amount' => 0,
    'total_amount' => 93000000,
    'cash_amount' => 250000,
    'transfer_amount' => 0,
    'transfer_personal_amount' => 0,
    'transfer_company_amount' => 0,
    'debt_amount' => 92750000
];

// Mock php://input by defining $_POST but wait, create_invoice_api reads php://input !
// If it reads php://input, let's override the input inside create_invoice_api physically? No, just send a curl request but pass the cookie!
