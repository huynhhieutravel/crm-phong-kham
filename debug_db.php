<?php
// debug_vps.php
// Place this in the root of your project or in modules/appointments/
require_once 'includes/db.php';
$db = getDB();

echo "<h2>Diagnostic Report</h2>";

function test_query($db, $name, $sql) {
    echo "Testing <strong>$name</strong>: ";
    try {
        $db->query($sql);
        echo "<span style='color: green;'>PASS</span><br>";
    } catch (PDOException $e) {
        echo "<span style='color: red;'>FAIL</span> - " . $e->getMessage() . "<br>";
    }
}

test_query($db, "Patients Table", "SELECT id, full_name, phone FROM patients LIMIT 1");
test_query($db, "Leads Table (Basics)", "SELECT id, full_name, phone FROM leads LIMIT 1");
test_query($db, "Leads Table (Status Column)", "SELECT status FROM leads LIMIT 1");
test_query($db, "Leads Table (Booking Time Column)", "SELECT appointment_booking_time FROM leads LIMIT 1");
test_query($db, "Roles Table (Display Name Column)", "SELECT display_name FROM roles LIMIT 1");
test_query($db, "Appointments Table (Branch ID Column)", "SELECT branch_id FROM appointments LIMIT 1");
test_query($db, "Appointments Table (End Time Column)", "SELECT appointment_end_time FROM appointments LIMIT 1");
test_query($db, "Appointments Table (Re-exam Rule Column)", "SELECT reexam_rule_id FROM appointments LIMIT 1");

$doctor_query = "
    SELECT u.id, u.full_name, r.display_name as role_name 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    WHERE r.name IN ('doctor', 'cskh', 'admin') AND u.status = 'active'
    LIMIT 1
";
test_query($db, "Doctors Join Query", $doctor_query);
