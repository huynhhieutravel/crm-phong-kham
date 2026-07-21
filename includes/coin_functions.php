<?php
// includes/coin_functions.php
// Centralized, safe functions for managing Patient Coin Wallets and Ledger

if (!defined('COIN_FUNCTIONS_LOADED')) {
    define('COIN_FUNCTIONS_LOADED', true);

    /**
     * Get current coin balance for a patient
     */
    function get_patient_coin_balance($db, $patient_id) {
        $stmt = $db->prepare("SELECT coin_balance FROM patient_wallets WHERE patient_id = ?");
        $stmt->execute([$patient_id]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (float)$val : 0.00;
    }

    /**
     * Top-up coins for a patient (Cash to Coins)
     * Must be called inside or will start a PDO Transaction
     */
    function topup_patient_coins($db, $patient_id, $coins, $price_paid, $created_by, $note = '') {
        $in_transaction = $db->inTransaction();
        if (!$in_transaction) $db->beginTransaction();

        try {
            // Ensure wallet row exists
            $stmt = $db->prepare("INSERT INTO patient_wallets (patient_id, coin_balance) VALUES (?, ?) ON DUPLICATE KEY UPDATE coin_balance = coin_balance + VALUES(coin_balance)");
            $stmt->execute([$patient_id, $coins]);

            // Log transaction
            $stmt_log = $db->prepare("
                INSERT INTO coin_transactions (patient_id, amount, price_paid, transaction_type, reference_id, note, created_by, created_at)
                VALUES (?, ?, ?, 'topup', NULL, ?, ?, NOW())
            ");
            $stmt_log->execute([$patient_id, $coins, $price_paid, $note, $created_by]);

            if (!$in_transaction) $db->commit();
            return true;
        } catch (Exception $e) {
            if (!$in_transaction) $db->rollBack();
            throw $e;
        }
    }

    /**
     * Deduct coins for a treatment with ROW LOCKING (FOR UPDATE)
     */
    function deduct_patient_coins($db, $patient_id, $coins_needed, $treatment_id, $created_by, $note = '') {
        $in_transaction = $db->inTransaction();
        if (!$in_transaction) $db->beginTransaction();

        try {
            // ROW LOCK on wallet row
            $stmt = $db->prepare("SELECT coin_balance FROM patient_wallets WHERE patient_id = ? FOR UPDATE");
            $stmt->execute([$patient_id]);
            $current_balance = $stmt->fetchColumn();

            if ($current_balance === false || (float)$current_balance < $coins_needed) {
                if (!$in_transaction) $db->rollBack();
                return false; // Insufficient coins
            }

            // Deduct
            $stmt_up = $db->prepare("UPDATE patient_wallets SET coin_balance = coin_balance - ? WHERE patient_id = ? AND coin_balance >= ?");
            $stmt_up->execute([$coins_needed, $patient_id, $coins_needed]);

            // Log transaction (negative amount)
            $stmt_log = $db->prepare("
                INSERT INTO coin_transactions (patient_id, amount, price_paid, transaction_type, reference_id, note, created_by, created_at)
                VALUES (?, ?, 0.00, 'usage', ?, ?, ?, NOW())
            ");
            $stmt_log->execute([$patient_id, -$coins_needed, $treatment_id, $note, $created_by]);

            if (!$in_transaction) $db->commit();
            return true;
        } catch (Exception $e) {
            if (!$in_transaction) $db->rollBack();
            throw $e;
        }
    }

    /**
     * Convert an active patient package (remaining sessions) into Coins
     */
    function convert_package_to_coins($db, $patient_package_id, $created_by) {
        $in_transaction = $db->inTransaction();
        if (!$in_transaction) $db->beginTransaction();

        try {
            // Lock package row
            $stmt = $db->prepare("
                SELECT pp.*, pkg.name as package_name 
                FROM patient_packages pp
                JOIN packages pkg ON pp.package_id = pkg.id
                WHERE pp.id = ? FOR UPDATE
            ");
            $stmt->execute([$patient_package_id]);
            $pkg = $stmt->fetch();

            if (!$pkg || $pkg['sessions_remaining'] <= 0 || $pkg['status'] !== 'active') {
                if (!$in_transaction) $db->rollBack();
                return false;
            }

            $patient_id = $pkg['patient_id'];
            $sessions_left = (int)$pkg['sessions_remaining'];
            $pkg_name = mb_strtolower($pkg['package_name'], 'UTF-8');

            // Calculate coins per session based on rules
            $rate = 2; // Default DY60 = 2 coins
            if (mb_strpos($pkg_name, '90') !== false || mb_strpos($pkg_name, 'chiro') !== false || mb_strpos($pkg_name, 'nắn chỉnh') !== false) {
                $rate = 3;
            }

            $total_coins_converted = $sessions_left * $rate;

            // Close package
            $db->prepare("UPDATE patient_packages SET sessions_remaining = 0, status = 'exhausted' WHERE id = ?")
               ->execute([$patient_package_id]);

            // Add coins to wallet
            $db->prepare("INSERT INTO patient_wallets (patient_id, coin_balance) VALUES (?, ?) ON DUPLICATE KEY UPDATE coin_balance = coin_balance + VALUES(coin_balance)")
               ->execute([$patient_id, $total_coins_converted]);

            // Log ledger
            $note = "Quy đổi gói [{$pkg['package_name']}] còn $sessions_left buổi sang $total_coins_converted Coins (Hệ số $rate Coins/buổi)";
            $stmt_log = $db->prepare("
                INSERT INTO coin_transactions (patient_id, amount, price_paid, transaction_type, reference_id, note, created_by, created_at)
                VALUES (?, ?, 0.00, 'exchange_from_package', ?, ?, ?, NOW())
            ");
            $stmt_log->execute([$patient_id, $total_coins_converted, $patient_package_id, $note, $created_by]);

            if (!$in_transaction) $db->commit();
            return $total_coins_converted;
        } catch (Exception $e) {
            if (!$in_transaction) $db->rollBack();
            throw $e;
        }
    }
}
