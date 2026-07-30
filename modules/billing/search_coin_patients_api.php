<?php
// modules/billing/search_coin_patients_api.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

header('Content-Type: application/json');

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

try {
    $db = getDB();
    $safe_q = addcslashes($q, '%_');
    
    $current_patient_id = isset($_GET['current_patient_id']) ? (int)$_GET['current_patient_id'] : 0;
    
    // We only want patients who actually have a wallet (or at least we show their wallet if it exists)
    // Left join with patient_wallets to easily display coin balance
    if ($q === '') {
        if ($current_patient_id > 0) {
            $stmt = $db->prepare("
                SELECT p.id, p.full_name, p.phone, p.customer_id, COALESCE(w.coin_balance, 0) as coin_balance
                FROM patients p
                LEFT JOIN patient_wallets w ON p.id = w.patient_id
                WHERE p.id = ?
            ");
            $stmt->execute([$current_patient_id]);
        } else {
            echo json_encode(['results' => []]);
            exit;
        }
    } else {
        $safe_q = addcslashes($q, '%_');
        $stmt = $db->prepare("
            SELECT p.id, p.full_name, p.phone, p.customer_id, COALESCE(w.coin_balance, 0) as coin_balance
            FROM patients p
            LEFT JOIN patient_wallets w ON p.id = w.patient_id
            WHERE p.full_name LIKE ? OR p.phone LIKE ? OR p.customer_id LIKE ?
            ORDER BY p.full_name ASC
            LIMIT 20
        ");
        $stmt->execute(["%$safe_q%", "%$safe_q%", "%$safe_q%"]);
    }
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $items = [];
    foreach ($results as $row) {
        $cid = $row['customer_id'] ?: 'BN-'.$row['id'];
        $balance = (float)$row['coin_balance'];
        $items[] = [
            'id' => $row['id'],
            'text' => "{$row['full_name']} - {$row['phone']} (Dư: {$balance} Coins)",
            'coin_balance' => $balance,
            'full_name' => $row['full_name']
        ];
    }
    
    // Select2 expects format { results: items }
    echo json_encode(['results' => $items]);
} catch (Exception $e) {
    echo json_encode(['results' => []]);
}
