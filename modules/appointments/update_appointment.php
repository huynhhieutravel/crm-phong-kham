<?php
// modules/appointments/update_appointment.php

require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_appointments');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // C2 FIX: Cast ID to int
    $id = (int)($_POST['id'] ?? 0);
    
    if (!$id) {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }
        $redirect_url = $_SESSION['appointment_list_url'] ?? 'index.php';
        redirect($redirect_url);
    }

    // C2 FIX: Verify CSRF
    $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    if (!$is_ajax) {
        verify_csrf('index.php');
    } else {
        $token = $_POST['_csrf_token'] ?? '';
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }
    }

    $db = getDB();

    if (isset($_POST['doctor_id'])) {
        $stmt = $db->prepare("UPDATE appointments SET doctor_id = ? WHERE id = ?");
        $stmt->execute([$_POST['doctor_id'] ?: null, $id]);
    }

    if (isset($_POST['status'])) {
        $status = $_POST['status'];

        // GUARD: "completed" can ONLY be set automatically by the invoice system (create_invoice_api.php)
        // Manual status change to "completed" is blocked here
        if ($status === 'completed') {
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Trạng thái "Hoàn thành" chỉ được tự động chuyển khi đã hoàn tất Phiếu Tính Tiền.']);
                exit;
            }
            set_flash('Trạng thái "Hoàn thành" chỉ được tự động chuyển khi đã hoàn tất Phiếu Tính Tiền.', 'error');
            $redirect_url = $_SESSION['appointment_list_url'] ?? 'index.php';
            redirect($redirect_url);
        }

        $stmt = $db->prepare("UPDATE appointments SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);

        // Logic for re-examination completion
        if ($status === 'completed') {
            $stmt = $db->prepare("SELECT patient_id, type, reexam_rule_id FROM appointments WHERE id = ?");
            $stmt->execute([$id]);
            $appt = $stmt->fetch();

            if ($appt && $appt['type'] === 're_exam') {
                // If there's a specific rule ID or just use an active one
                $rule_id = $appt['reexam_rule_id'];
                $frequency = 0;
                $frequency_type = 'months';
                
                if (!$rule_id) {
                    $stmt = $db->prepare("SELECT id, frequency, frequency_type FROM reexam_rules WHERE patient_id = ? LIMIT 1");
                    $stmt->execute([$appt['patient_id']]);
                    $rule = $stmt->fetch();
                    if ($rule) {
                        $rule_id = $rule['id'];
                        $frequency = $rule['frequency'];
                        $frequency_type = $rule['frequency_type'];
                    }
                } else {
                    $stmt = $db->prepare("SELECT frequency, frequency_type FROM reexam_rules WHERE id = ?");
                    $stmt->execute([$rule_id]);
                    $r_data = $stmt->fetch();
                    if ($r_data) {
                        $frequency = $r_data['frequency'];
                        $frequency_type = $r_data['frequency_type'];
                    }
                }

                if ($rule_id) {
                    if ($frequency > 0) {
                        $interval_map = ['days' => 'DAY', 'weeks' => 'WEEK', 'months' => 'MONTH'];
                        $interval_unit = isset($interval_map[$frequency_type]) ? $interval_map[$frequency_type] : 'MONTH';

                        $db->prepare("
                            UPDATE reexam_rules 
                            SET current_session = current_session + 1,
                                last_reexam_at = CURDATE(),
                                next_due_at = DATE_ADD(CURDATE(), INTERVAL ? {$interval_unit})
                            WHERE id = ?
                        ")->execute([$frequency, $rule_id]);
                    } else {
                        // One-off re-exam: mark as done by nulling next_due_at
                        $db->prepare("
                            UPDATE reexam_rules 
                            SET current_session = current_session + 1,
                                last_reexam_at = CURDATE(),
                                next_due_at = NULL
                            WHERE id = ?
                        ")->execute([$rule_id]);
                    }
                }
            }
        }
    }

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    $redirect_url = $_SESSION['appointment_list_url'] ?? 'index.php';
    redirect($redirect_url);
}
