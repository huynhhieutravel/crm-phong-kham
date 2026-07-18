<?php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_billing');

header('Content-Type: application/json');

try {
    $db = getDB();
    
    // Đọc dữ liệu JSON
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!$data || !isset($data['invoice_id']) || !isset($data['status'])) {
        throw new Exception('Thiếu thông tin invoice_id hoặc status');
    }
    
    $invoice_id = intval($data['invoice_id']);
    $new_status = $data['status'];
    $valid_statuses = ['paid', 'partial', 'debt', 'cancelled'];
    if (!in_array($new_status, $valid_statuses)) {
        throw new Exception('Trạng thái không hợp lệ');
    }
    
    // Check if invoice exists
    $stmt = $db->prepare("SELECT * FROM invoices WHERE id = ?");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch();
    
    if (!$invoice) {
        throw new Exception('Không tìm thấy phiếu tính tiền');
    }

    if ($invoice['status'] === $new_status) {
        echo json_encode(['success' => true]);
        exit;
    }

    $db->beginTransaction();

    // Cập nhật trạng thái phiếu
    $stmt = $db->prepare("UPDATE invoices SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $invoice_id]);

    if ($new_status === 'cancelled') {
        // Xóa các giao dịch liên quan để trừ doanh thu khỏi báo cáo
        $stmt = $db->prepare("DELETE FROM transactions WHERE reference_id = ? AND description LIKE 'Phiếu tính tiền %'");
        $stmt->execute([$invoice_id]);
    } else if ($invoice['status'] === 'cancelled') {
        // Nếu chuyển từ Hủy sang trạng thái khác (phục hồi), ta nên tạo lại giao dịch
        // Khôi phục bằng cách insert lại các dòng doanh thu
        $branch_id = $_SESSION['branch_id'] ?? 1;
        $creator_id = $_SESSION['user_id'];
        $inv_no = $invoice['invoice_no'];
        if ($invoice['cash_amount'] > 0) $db->prepare("INSERT INTO transactions (type, category, amount, reference_id, description, branch_id, created_by) VALUES ('income', 'Tiền mặt', ?, ?, ?, ?, ?)")->execute([$invoice['cash_amount'], $invoice_id, "Phiếu tính tiền $inv_no", $branch_id, $creator_id]);
        if ($invoice['transfer_personal_amount'] > 0) $db->prepare("INSERT INTO transactions (type, category, amount, reference_id, description, branch_id, created_by) VALUES ('income', 'CK Cá nhân', ?, ?, ?, ?, ?)")->execute([$invoice['transfer_personal_amount'], $invoice_id, "Phiếu tính tiền $inv_no", $branch_id, $creator_id]);
        if ($invoice['transfer_company_amount'] > 0) $db->prepare("INSERT INTO transactions (type, category, amount, reference_id, description, branch_id, created_by) VALUES ('income', 'TK Công ty', ?, ?, ?, ?, ?)")->execute([$invoice['transfer_company_amount'], $invoice_id, "Phiếu tính tiền $inv_no", $branch_id, $creator_id]);
        if (isset($invoice['card_amount']) && $invoice['card_amount'] > 0) $db->prepare("INSERT INTO transactions (type, category, amount, reference_id, description, branch_id, created_by) VALUES ('income', 'Quẹt thẻ', ?, ?, ?, ?, ?)")->execute([$invoice['card_amount'], $invoice_id, "Phiếu tính tiền $inv_no", $branch_id, $creator_id]);
        if ($invoice['transfer_amount'] > 0 && floatval($invoice['transfer_personal_amount']) == 0 && floatval($invoice['transfer_company_amount']) == 0) $db->prepare("INSERT INTO transactions (type, category, amount, reference_id, description, branch_id, created_by) VALUES ('income', 'Chuyển khoản (Cũ)', ?, ?, ?, ?, ?)")->execute([$invoice['transfer_amount'], $invoice_id, "Phiếu tính tiền $inv_no", $branch_id, $creator_id]);
    }

    // Ghi log hệ thống
    log_audit($_SESSION['user_id'], 'update', 'invoices', $invoice_id, json_encode($invoice, JSON_UNESCAPED_UNICODE), json_encode(['status' => $new_status, 'reason' => 'User changed status'], JSON_UNESCAPED_UNICODE));
    
    $db->commit();
    
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
