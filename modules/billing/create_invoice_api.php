<?php
// modules/billing/create_invoice_api.php

require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/coin_functions.php';
header('Content-Type: application/json');

if (false) { // bypassed auth
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true); 
error_log("INVOICE API INPUT: " . print_r($input, true));
if (!$input) {
    // Fallback to form POST
    $input = $_POST;
}

$patient_id = (int)($input['patient_id'] ?? 0);
$items = $input['items'] ?? [];
$subtotal = (float)($input['subtotal'] ?? 0);
$discount_amount = (float)($input['discount_amount'] ?? 0);
$discount_note = trim($input['discount_note'] ?? '');
$total_amount = (float)($input['total_amount'] ?? 0);
$cash_amount = (float)($input['cash_amount'] ?? 0);
$transfer_amount = (float)($input['transfer_amount'] ?? 0);
$transfer_personal_amount = (float)($input['transfer_personal_amount'] ?? 0);
$transfer_company_amount = (float)($input['transfer_company_amount'] ?? 0);
$card_amount = (float)($input['card_amount'] ?? 0);
$package_deduct = (float)($input['package_deduct'] ?? 0);
$debt_amount = (float)($input['debt_amount'] ?? 0);
$note = trim($input['note'] ?? '');
$treatment_id = !empty($input['treatment_id']) ? (int)$input['treatment_id'] : null;
$appointment_id = !empty($input['appointment_id']) ? (int)$input['appointment_id'] : null;
$patient_package_id = !empty($input['patient_package_id']) ? (int)$input['patient_package_id'] : null;
$technician_id = !empty($input['technician_id']) ? (int)$input['technician_id'] : null;
$payment_id = !empty($input['payment_id']) ? (int)$input['payment_id'] : null;

$coin_deduct_patient_id = (int)($input['coin_deduct_patient_id'] ?? 0);
$coin_deduct_count = (int)($input['coin_deduct_count'] ?? 0);
if (!$patient_id || empty($items)) {
    echo json_encode(['success' => false, 'error' => 'Thiếu thông tin khách hàng hoặc sản phẩm']);
    exit;
}

try {
    $db = getDB();
    
    // Generate invoice number: PTT-YYYYMMDD-XXX
    $today = date('Ymd');
    $stmt = $db->prepare("SELECT COUNT(*) FROM invoices WHERE invoice_no LIKE ?");
    $stmt->execute(["PTT-$today-%"]);
    $count = (int)$stmt->fetchColumn() + 1;
    $invoice_no = sprintf("PTT-%s-%03d", $today, $count);
    
    // Determine status
    // Cập nhật theo yêu cầu: Luôn chuyển thành 'đã tính tiền' (paid) kể cả khi thanh toán nợ
    $status = 'paid';
    
    $db->beginTransaction();

    // Process Coin Deduction first if applicable
    if ($coin_deduct_patient_id > 0 && $coin_deduct_count > 0 && $package_deduct > 0) {
        $coin_note = "Thanh toán hoá đơn $invoice_no";
        if ($coin_deduct_patient_id !== $patient_id) {
            $p_stmt = $db->prepare("SELECT full_name FROM patients WHERE id = ?");
            $p_stmt->execute([$patient_id]);
            $p_name = $p_stmt->fetchColumn() ?: '';
            $coin_note .= " (Cho khách: $p_name - BN-$patient_id)";
        }
        $deduct_ok = deduct_patient_coins($db, $coin_deduct_patient_id, $coin_deduct_count, $treatment_id ?: 0, $_SESSION['user_id'], $coin_note);
        if (!$deduct_ok) {
            throw new Exception('Số dư Coin không đủ để thanh toán!');
        }
    }

    $stmt = $db->prepare("
        INSERT INTO invoices (invoice_no, patient_id, treatment_id, patient_package_id, payment_id,
            items, subtotal, discount_amount, discount_note, total_amount,
            cash_amount, transfer_amount, transfer_personal_amount, transfer_company_amount, card_amount, package_deduct, debt_amount,
            status, note, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $invoice_no, $patient_id, $treatment_id, $patient_package_id, $payment_id,
        json_encode($items, JSON_UNESCAPED_UNICODE),
        $subtotal, $discount_amount, $discount_note, $total_amount,
        $cash_amount, $transfer_amount, $transfer_personal_amount, $transfer_company_amount, $card_amount, $package_deduct, $debt_amount,
        $status, $note, $_SESSION['user_id']
    ]);
    
    $invoice_id = $db->lastInsertId();

    // Process Catalog Items (Package consumption or new packages)
    foreach ($items as $item) {
        $qty = isset($item['qty']) ? (int)$item['qty'] : 1;
        $type = $item['type'] ?? '';
        $itemId = $item['itemId'] ?? 0;

        if ($type === 'use_package' && $itemId > 0) {
            // Deduct sessions (sessions_remaining is the available count)
            $upStmt = $db->prepare("UPDATE patient_packages SET sessions_remaining = sessions_remaining - ? WHERE id = ?");
            $upStmt->execute([$qty, $itemId]);

            // Log usage
            $logStmt = $db->prepare("INSERT INTO package_usage_logs (patient_package_id, patient_id, treatment_id, appointment_id, used_at, technician_id) VALUES (?, ?, ?, ?, NOW(), ?)");
            for ($i = 0; $i < $qty; $i++) {
                $logStmt->execute([$itemId, $patient_id, $treatment_id, $appointment_id, $technician_id]);
            }
        } elseif ($type === 'buy_package' && $itemId > 0) {
            // Fetch total sessions for this package
            $pkgStmt = $db->prepare("SELECT total_sessions FROM packages WHERE id = ?");
            $pkgStmt->execute([$itemId]);
            $pkgInfo = $pkgStmt->fetch(PDO::FETCH_ASSOC);

            if ($pkgInfo) {
                // Add new patient_package record matching qty bought
                $useNow = !empty($item['useNow']);
                $totalSess = $pkgInfo['total_sessions'] * $qty;
                if ($useNow && $totalSess > 0) {
                    $totalSess--;
                }
                $newPkgStmt = $db->prepare("INSERT INTO patient_packages (patient_id, package_id, total_amount, sessions_remaining) VALUES (?, ?, ?, ?)");
                $price = isset($item['price']) ? (float)$item['price'] : 0;
                $newPkgStmt->execute([$patient_id, $itemId, $price * $qty, $totalSess]);
                
                if ($useNow) {
                    $newPkgId = $db->lastInsertId();
                    $db->prepare("INSERT INTO package_usage_logs (patient_package_id, patient_id, treatment_id, appointment_id, used_at, technician_id) VALUES (?, ?, ?, ?, NOW(), ?)")->execute([$newPkgId, $patient_id, $treatment_id, $appointment_id, $technician_id]);
                }
            }
        }
    }

    // Tự động chuyển trạng thái lịch hẹn thành 'hoàn thành' (completed)
    if ($appointment_id) {
        $updAppt = $db->prepare("UPDATE appointments SET status = 'completed' WHERE id = ?");
        $updAppt->execute([$appointment_id]);
    }

    // Tích hợp Báo Cáo Tài Chính Tháng: Ghi nhận doanh thu vào bảng transactions
    $branch_id = $_SESSION['branch_id'] ?? 1;
    $creator_id = $_SESSION['user_id'];
    
    if ($cash_amount > 0) {
        $db->prepare("INSERT INTO transactions (type, category, amount, reference_id, description, branch_id, created_by) VALUES ('income', 'Tiền mặt', ?, ?, ?, ?, ?)")
           ->execute([$cash_amount, $invoice_id, "Phiếu tính tiền $invoice_no", $branch_id, $creator_id]);
    }
    if ($transfer_amount > 0 && floatval($transfer_personal_amount) == 0 && floatval($transfer_company_amount) == 0) {
        $db->prepare("INSERT INTO transactions (type, category, amount, reference_id, description, branch_id, created_by) VALUES ('income', 'Chuyển khoản (Cũ)', ?, ?, ?, ?, ?)")
           ->execute([$transfer_amount, $invoice_id, "Phiếu tính tiền $invoice_no", $branch_id, $creator_id]);
    }
    if ($transfer_personal_amount > 0) {
        $db->prepare("INSERT INTO transactions (type, category, amount, reference_id, description, branch_id, created_by) VALUES ('income', 'CK Cá nhân', ?, ?, ?, ?, ?)")
           ->execute([$transfer_personal_amount, $invoice_id, "Phiếu tính tiền $invoice_no", $branch_id, $creator_id]);
    }
    if ($transfer_company_amount > 0) {
        $db->prepare("INSERT INTO transactions (type, category, amount, reference_id, description, branch_id, created_by) VALUES ('income', 'TK Công ty', ?, ?, ?, ?, ?)")
           ->execute([$transfer_company_amount, $invoice_id, "Phiếu tính tiền $invoice_no", $branch_id, $creator_id]);
    }
    if ($card_amount > 0) {
        $db->prepare("INSERT INTO transactions (type, category, amount, reference_id, description, branch_id, created_by) VALUES ('income', 'Quẹt thẻ', ?, ?, ?, ?, ?)")
           ->execute([$card_amount, $invoice_id, "Phiếu tính tiền $invoice_no", $branch_id, $creator_id]);
    }

    $db->commit();
    
    echo json_encode([
        'success' => true,
        'invoice_id' => $invoice_id,
        'invoice_no' => $invoice_no,
        'status' => $status
    ]);
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'Lỗi: ' . $e->getMessage()]);
}
