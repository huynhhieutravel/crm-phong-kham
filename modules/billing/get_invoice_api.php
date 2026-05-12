<?php
// modules/billing/get_invoice_api.php

require_once '../../includes/db.php';
require_once '../../includes/functions.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Missing ID']);
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT i.*, p.full_name as patient_name, p.phone as patient_phone,
               u.full_name as cashier_name
        FROM invoices i
        JOIN patients p ON i.patient_id = p.id
        JOIN users u ON i.created_by = u.id
        WHERE i.id = ?
    ");
    $stmt->execute([$id]);
    $invoice = $stmt->fetch();
    
    if (!$invoice) {
        echo json_encode(['success' => false, 'error' => 'Không tìm thấy phiếu']);
        exit;
    }
    
    $invoice['items'] = json_decode($invoice['items'], true);
    echo json_encode(['success' => true, 'invoice' => $invoice]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
