<?php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_sales');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Phương thức không được phép']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$transaction_id = isset($input['transaction_id']) ? (int)$input['transaction_id'] : 0;
$reason = trim($input['reason'] ?? 'Điều chỉnh do nhập sai');

if ($transaction_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Thiếu ID giao dịch']);
    exit;
}

try {
    $db = getDB();
    $db->beginTransaction();

    // Lấy thông tin giao dịch gốc
    $stmt = $db->prepare("SELECT * FROM coin_transactions WHERE id = ? FOR UPDATE");
    $stmt->execute([$transaction_id]);
    $tx = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tx) {
        throw new Exception('Không tìm thấy giao dịch nạp Coin.');
    }
    if ($tx['transaction_type'] !== 'topup') {
        throw new Exception('Chỉ có thể huỷ giao dịch Nạp Coin.');
    }

    // Check if already refunded
    $stmt_check = $db->prepare("SELECT COUNT(*) FROM coin_transactions WHERE transaction_type = 'refund' AND reference_id = ?");
    $stmt_check->execute([$transaction_id]);
    if ($stmt_check->fetchColumn() > 0) {
        throw new Exception('Giao dịch này đã được huỷ trước đó.');
    }

    $patient_id = $tx['patient_id'];
    $amount_to_refund = (float)$tx['amount'];

    // Lock wallet to check balance
    $stmt_wallet = $db->prepare("SELECT coin_balance FROM patient_wallets WHERE patient_id = ? FOR UPDATE");
    $stmt_wallet->execute([$patient_id]);
    $current_balance = (float)$stmt_wallet->fetchColumn();

    if ($current_balance < $amount_to_refund) {
        throw new Exception("Bệnh nhân chỉ còn {$current_balance} Coins, không đủ để huỷ (cần trừ {$amount_to_refund} Coins). Khách đã sử dụng Coin này.");
    }

    // Trừ Coin trong ví
    $db->prepare("UPDATE patient_wallets SET coin_balance = coin_balance - ? WHERE patient_id = ?")
       ->execute([$amount_to_refund, $patient_id]);

    // Tạo record refund trong coin_transactions (Lưu ý: reference_id lưu ID giao dịch bị huỷ để trace)
    $db->prepare("
        INSERT INTO coin_transactions (patient_id, amount, price_paid, transaction_type, reference_id, note, created_by, created_at)
        VALUES (?, ?, ?, 'refund', ?, ?, ?, NOW())
    ")->execute([
        $patient_id, -$amount_to_refund, -$tx['price_paid'], $transaction_id, $reason, $_SESSION['user_id']
    ]);

    // Hủy Phiếu tính tiền (Invoice) và Dòng tiền (Transactions)
    if (!empty($tx['reference_id'])) {
        $invoice_id = $tx['reference_id'];
        
        $db->prepare("UPDATE invoices SET status = 'cancelled' WHERE id = ?")->execute([$invoice_id]);
        
        $db->prepare("DELETE FROM transactions WHERE reference_id = ? AND description LIKE 'Phiếu tính tiền %'")->execute([$invoice_id]);
    }

    $db->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
