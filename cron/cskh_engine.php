<?php
// CSKH Auto-Sync Cron Engine
//
// Crontab: every 15 min -> php /var/www/crm_phong_kham/cron/cskh_engine.php >> /var/log/cskh_engine.log 2>&1
//
// Logic:
// 1. Quét appointments ngày mai -> sinh task T-1 (pre_exam)
// 2. Quét medical_sessions đã hoàn thành 3 ngày trước -> sinh task T+3 (post_exam)
// 3. Quét medical_sessions hoàn thành 7 ngày trước, vẫn chưa có lịch hẹn mới -> task T+7 (follow_up)
// 4. Quét patient_packages còn <= 2 buổi -> sinh task package_alert
// 5. Quét khách lâu chưa khám (30 ngày) -> sinh task reactivation
// 6. Mark overdue: task pending nhưng due_date < today -> overdue

// CLI only guard
if (php_sapi_name() !== 'cli' && !defined('CSKH_ENGINE_ALLOW_WEB')) {
    die('CLI only');
}

require_once __DIR__ . '/../includes/db.php';

$db = getDB();
$now = date('Y-m-d');
$log = function($msg) { echo "[" . date('Y-m-d H:i:s') . "] $msg\n"; };

$log("=== CSKH ENGINE START ===");

// Load active rules
$rules = $db->query("SELECT * FROM cskh_rules WHERE is_active = 1")->fetchAll();
$log("Loaded " . count($rules) . " active rules");

foreach ($rules as $rule) {
    switch ($rule['trigger_event']) {

        // ───────────────────────────────────────────────
        // RULE: appointment_upcoming (T-1: Nhắc trước lịch khám)
        // ───────────────────────────────────────────────
        case 'appointment_upcoming':
            $target_date = date('Y-m-d', strtotime("$now " . abs($rule['offset_days']) . " days"));
            // offset_days = -1 means the appointment is TOMORROW from today's view
            // So due_date = today (call today), reference = appointment on target_date
            
            $stmt = $db->prepare("
                SELECT a.id as appointment_id, a.patient_id
                FROM appointments a
                WHERE DATE(a.appointment_date) = ?
                  AND a.status IN ('scheduled','confirmed')
                  AND NOT EXISTS (
                      SELECT 1 FROM cskh_tasks t 
                      WHERE t.rule_id = ? AND t.reference_id = a.id AND t.reference_type = 'appointment'
                  )
            ");
            $stmt->execute([$target_date, $rule['id']]);
            $appointments = $stmt->fetchAll();

            $insert = $db->prepare("
                INSERT IGNORE INTO cskh_tasks (patient_id, rule_id, reference_id, reference_type, due_date, priority_color)
                VALUES (?, ?, ?, 'appointment', ?, ?)
            ");

            $count = 0;
            foreach ($appointments as $a) {
                $insert->execute([
                    $a['patient_id'],
                    $rule['id'],
                    $a['appointment_id'],
                    $now, // Due today — call them today
                    $rule['default_color']
                ]);
                $count++;
            }
            $log("[Rule #{$rule['id']}] {$rule['rule_name']}: Tạo $count task(s)");
            break;

        // ───────────────────────────────────────────────
        // RULE: session_completed (T+3 / T+7: Hỏi thăm sau điều trị)
        // ───────────────────────────────────────────────
        case 'session_completed':
            $target_date = date('Y-m-d', strtotime("$now -{$rule['offset_days']} days"));
            // offset_days = 3 means session completed 3 days ago
            
            $stmt = $db->prepare("
                SELECT ms.id as session_id, ms.patient_id
                FROM medical_sessions ms
                WHERE DATE(ms.session_date) = ?
                  AND ms.status = 'completed'
                  AND NOT EXISTS (
                      SELECT 1 FROM cskh_tasks t 
                      WHERE t.rule_id = ? AND t.reference_id = ms.id AND t.reference_type = 'session'
                  )
            ");
            $stmt->execute([$target_date, $rule['id']]);
            $sessions = $stmt->fetchAll();

            // For T+7 rule: additionally check if patient has NO future appointment
            $check_no_future_appt = ($rule['offset_days'] >= 7);

            $insert = $db->prepare("
                INSERT IGNORE INTO cskh_tasks (patient_id, rule_id, reference_id, reference_type, due_date, priority_color)
                VALUES (?, ?, ?, 'session', ?, ?)
            ");

            $count = 0;
            foreach ($sessions as $s) {
                if ($check_no_future_appt) {
                    $chk = $db->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND appointment_date > NOW() AND status IN ('scheduled','confirmed')");
                    $chk->execute([$s['patient_id']]);
                    if ($chk->fetchColumn() > 0) continue; // Has future appointment, skip
                }

                $insert->execute([
                    $s['patient_id'],
                    $rule['id'],
                    $s['session_id'],
                    $now,
                    $rule['default_color']
                ]);
                $count++;
            }
            $log("[Rule #{$rule['id']}] {$rule['rule_name']}: Tạo $count task(s)");
            break;

        // ───────────────────────────────────────────────
        // RULE: package_low_sessions (Gói sắp hết ≤ 2 buổi)
        // ───────────────────────────────────────────────
        case 'package_low_sessions':
            $stmt = $db->prepare("
                SELECT pp.id as pp_id, pp.patient_id
                FROM patient_packages pp
                WHERE pp.sessions_remaining <= 2
                  AND pp.sessions_remaining > 0
                  AND NOT EXISTS (
                      SELECT 1 FROM cskh_tasks t 
                      WHERE t.rule_id = ? AND t.reference_id = pp.id AND t.reference_type = 'package'
                  )
            ");
            $stmt->execute([$rule['id']]);
            $packages = $stmt->fetchAll();

            $insert = $db->prepare("
                INSERT IGNORE INTO cskh_tasks (patient_id, rule_id, reference_id, reference_type, due_date, priority_color)
                VALUES (?, ?, ?, 'package', ?, ?)
            ");

            $count = 0;
            foreach ($packages as $p) {
                $insert->execute([
                    $p['patient_id'],
                    $rule['id'],
                    $p['pp_id'],
                    $now,
                    $rule['default_color']
                ]);
                $count++;
            }
            $log("[Rule #{$rule['id']}] {$rule['rule_name']}: Tạo $count task(s)");
            break;

        // ───────────────────────────────────────────────
        // RULE: reactivation (Khách lâu chưa quay lại)
        // ───────────────────────────────────────────────
        case 'reactivation':
            $cutoff = date('Y-m-d', strtotime("$now -{$rule['offset_days']} days"));
            $stmt = $db->prepare("
                SELECT p.id as patient_id
                FROM patients p
                WHERE EXISTS (
                    SELECT 1 FROM medical_sessions ms WHERE ms.patient_id = p.id
                )
                AND NOT EXISTS (
                    SELECT 1 FROM medical_sessions ms2 WHERE ms2.patient_id = p.id AND ms2.session_date > ?
                )
                AND NOT EXISTS (
                    SELECT 1 FROM appointments a WHERE a.patient_id = p.id AND a.appointment_date > NOW() AND a.status IN ('scheduled','confirmed')
                )
                AND NOT EXISTS (
                    SELECT 1 FROM cskh_tasks t 
                    WHERE t.patient_id = p.id AND t.rule_id = ? AND t.status IN ('pending','in_progress')
                )
            ");
            $stmt->execute([$cutoff, $rule['id']]);
            $patients = $stmt->fetchAll();

            $insert = $db->prepare("
                INSERT IGNORE INTO cskh_tasks (patient_id, rule_id, reference_id, reference_type, due_date, priority_color)
                VALUES (?, ?, NULL, NULL, ?, ?)
            ");

            $count = 0;
            foreach ($patients as $p) {
                $insert->execute([
                    $p['patient_id'],
                    $rule['id'],
                    $now,
                    $rule['default_color']
                ]);
                $count++;
            }
            $log("[Rule #{$rule['id']}] {$rule['rule_name']}: Tạo $count task(s)");
            break;
    }
}

// ───────────────────────────────────────────────
// AUTO-MARK OVERDUE (pending + due_date < today)
// ───────────────────────────────────────────────
$overdue_stmt = $db->prepare("
    UPDATE cskh_tasks SET status = 'overdue' 
    WHERE status = 'pending' AND due_date < ?
");
$overdue_stmt->execute([$now]);
$overdue_count = $overdue_stmt->rowCount();
$log("[Overdue] Đánh dấu $overdue_count task(s) đã quá hạn");

$log("=== CSKH ENGINE COMPLETE ===\n");
