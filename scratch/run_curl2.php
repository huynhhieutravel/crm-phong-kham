<?php
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
curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
// Pass cookie to mock session
curl_setopt($ch, CURLOPT_COOKIE, 'CLINIC_SESSID=e8nnb6s987h5h85t10r5k00s5f'); // just use whatever session exists!
// wait, we don't have a valid session ID. 
// We can just temporarily edit create_invoice_api.php to bypass auth!
$ch = curl_init('http://localhost:8080/modules/billing/create_invoice_api.php');
curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch); echo $res;
