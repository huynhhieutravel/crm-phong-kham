<?php
// modules/appointments/check_availability.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('view_appointments');

$db = getDB();
$date = $_GET['date'] ?? '';
$time = $_GET['time'] ?? '';
$doctor_id = (int)($_GET['doctor_id'] ?? 0);
$branch_id = $_SESSION['branch_id'] ?? 1;

if (empty($date)) {
    echo json_encode(['error' => 'Vui lòng chọn ngày']);
    exit;
}

$results = [
    'conflict' => false,
    'morning_count' => 0,
    'afternoon_count' => 0,
    'afternoon_count' => 0,
    'doctor_busy' => false,
    'doctor_on_leave' => false
];

// 1. Count morning/afternoon load
try {
    $available_cols = $db->query("SHOW COLUMNS FROM appointments")->fetchAll(PDO::FETCH_COLUMN);
    $has_branch_id = in_array('branch_id', $available_cols);
    
    // M1 FIX: Use range query instead of DATE() for index optimization
    $where_clause = "appointment_date >= ? AND appointment_date < ? + INTERVAL 1 DAY";
    $query_params = [$date, $date];
    
    if ($has_branch_id) {
        $where_clause .= " AND branch_id = ?";
        $query_params[] = $branch_id;
    }

    $stmt = $db->prepare("
        SELECT 
            SUM(CASE WHEN HOUR(appointment_date) < 12 THEN 1 ELSE 0 END) as morning,
            SUM(CASE WHEN HOUR(appointment_date) >= 12 THEN 1 ELSE 0 END) as afternoon
        FROM appointments 
        WHERE $where_clause
    ");
    $stmt->execute($query_params);
    $counts = $stmt->fetch();
    $results['morning_count'] = (int)($counts['morning'] ?? 0);
    $results['afternoon_count'] = (int)($counts['afternoon'] ?? 0);
} catch (Exception $e) {
    // Fail gracefully
}

// 2. Check doctor overlap if doctor and time are selected
if (!empty($time) && !empty($doctor_id)) {
    $full_datetime = $date . ' ' . $time;
    // Simple overlap check: check if there is any appointment within +/- 30 mins
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM appointments 
        WHERE doctor_id = ? 
        AND appointment_date BETWEEN (DATE_SUB(?, INTERVAL 29 MINUTE)) AND (DATE_ADD(?, INTERVAL 29 MINUTE))
        AND status NOT IN ('cancelled')
    ");
    $stmt->execute([$doctor_id, $full_datetime, $full_datetime]);
    if ($stmt->fetchColumn() > 0) {
        $results['doctor_busy'] = true;
    }
}

// 3. Fetch doctor's full schedule for the day (if doctor and date are present)
if (!empty($doctor_id) && !empty($date)) {
    // Check if doctor is on approved leave
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM leave_requests 
        WHERE user_id = ? 
        AND status = 'approved' 
        AND ? >= DATE(start_date) AND ? <= DATE(end_date)
    ");
    $stmt->execute([$doctor_id, $date, $date]);
    if ($stmt->fetchColumn() > 0) {
        $results['doctor_on_leave'] = true;
    }

    $stmt = $db->prepare("
        SELECT DATE_FORMAT(appointment_date, '%H:%i') as time, status
        FROM appointments 
        WHERE doctor_id = ? AND appointment_date >= ? AND appointment_date < ? + INTERVAL 1 DAY
        AND status NOT IN ('cancelled')
        ORDER BY appointment_date ASC
    ");
    $stmt->execute([$doctor_id, $date, $date]);
    $results['schedule'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

header('Content-Type: application/json');
echo json_encode($results);
