<?php
// modules/appointments/update_appointment.php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? $_POST['id'] : 0;
    $db = getDB();

    if (isset($_POST['doctor_id'])) {
        $stmt = $db->prepare("UPDATE appointments SET doctor_id = ? WHERE id = ?");
        $stmt->execute([$_POST['doctor_id'] ?: null, $id]);
    }

    if (isset($_POST['status'])) {
        $status = $_POST['status'];
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
                if (!$rule_id) {
                    $stmt = $db->prepare("SELECT id, frequency FROM reexam_rules WHERE patient_id = ? LIMIT 1");
                    $stmt->execute([$appt['patient_id']]);
                    $rule = $stmt->fetch();
                    if ($rule) {
                        $rule_id = $rule['id'];
                        $frequency = $rule['frequency'];
                    }
                } else {
                    $stmt = $db->prepare("SELECT frequency FROM reexam_rules WHERE id = ?");
                    $stmt->execute([$rule_id]);
                    $frequency = $stmt->fetchColumn();
                }

                if ($rule_id) {
                    if ($frequency > 0) {
                        $db->prepare("
                            UPDATE reexam_rules 
                            SET current_session = current_session + 1,
                                last_reexam_at = CURDATE(),
                                next_due_at = DATE_ADD(CURDATE(), INTERVAL ? MONTH)
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

    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    redirect('index.php');
}
