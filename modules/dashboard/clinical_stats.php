<?php
// modules/dashboard/clinical_stats.php
if (!isset($db)) die('Direct access not permitted');

// 1. Key Metrics
$new_patients = 0;
$total_sessions = 0;
$completed_appointments = 0;
$labels = [];
$apt_statuses = [];

if ($start_date && $end_date) {
    try {
        $stmt_np = $db->prepare("SELECT COUNT(*) FROM patients WHERE created_at >= ? AND created_at <= ?");
        $stmt_np->execute([$start_date, $end_date]);
        $new_patients = $stmt_np->fetchColumn();
    } catch (Exception $e) { error_log($e->getMessage()); }

    try {
        $stmt_ts = $db->prepare("SELECT COUNT(*) FROM medical_sessions WHERE session_date >= ? AND session_date <= ?");
        $stmt_ts->execute([$start_date, $end_date]);
        $total_sessions = $stmt_ts->fetchColumn();
    } catch (Exception $e) { error_log($e->getMessage()); }

    try {
        $stmt_ca = $db->prepare("SELECT COUNT(*) FROM appointments WHERE status = 'completed' AND appointment_date >= ? AND appointment_date <= ?");
        $stmt_ca->execute([$start_date, $end_date]);
        $completed_appointments = $stmt_ca->fetchColumn();
    } catch (Exception $e) { error_log($e->getMessage()); }

    try {
        // 2. Patient Labels Data
        $label_stmt = $db->prepare("SELECT label, COUNT(*) as count FROM patients WHERE created_at >= ? AND created_at <= ? GROUP BY label ORDER BY count DESC");
        $label_stmt->execute([$start_date, $end_date]);
        $labels = $label_stmt->fetchAll();
    } catch (Exception $e) {}

    try {
        // 3. Doctor Workload Data
        $doctor_stmt = $db->prepare("
            SELECT u.full_name, 
                   COUNT(DISTINCT a.id) as appointments,
                   COUNT(DISTINCT s.id) as sessions
            FROM users u
            LEFT JOIN appointments a ON u.id = a.doctor_id AND a.appointment_date BETWEEN ? AND ?
            LEFT JOIN medical_sessions s ON u.id = s.doctor_id AND s.session_date BETWEEN ? AND ?
            WHERE u.role_id = 2 -- Doctors
            GROUP BY u.id
            HAVING appointments > 0 OR sessions > 0
            ORDER BY sessions DESC, appointments DESC
        ");
        $doctor_stmt->execute([$start_date, $end_date, $start_date, $end_date]);
        $doctors = $doctor_stmt->fetchAll();
    } catch (Exception $e) {
        // Fallback: simple doctor list
        try {
            $doctors = $db->query("SELECT full_name, 0 as appointments, 0 as sessions FROM users WHERE role_id = 2")->fetchAll();
        } catch (Exception $e2) {
            $doctors = [];
        }
    }

    try {
        // 4. Appointment Status Distribution
        $apt_status_stmt = $db->prepare("SELECT status, COUNT(*) as count FROM appointments WHERE appointment_date >= ? AND appointment_date <= ? GROUP BY status");
        $apt_status_stmt->execute([$start_date, $end_date]);
        $apt_statuses = $apt_status_stmt->fetchAll();
    } catch (Exception $e) {}
} else {
    $doctors = [];
}
?>

<div class="grid-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <div class="card stat-card">
        <div class="stat-label" style="color: var(--text-muted); font-size: 0.875rem; font-weight: 500;"><?php echo __('dashboard.new_patients_count'); ?></div>
        <div class="stat-value" style="font-size: 1.5rem; font-weight: 700; color: var(--primary);"><?php echo number_format($new_patients); ?></div>
    </div>
    <div class="card stat-card">
        <div class="stat-label" style="color: var(--text-muted); font-size: 0.875rem; font-weight: 500;"><?php echo __('dashboard.total_sessions'); ?></div>
        <div class="stat-value" style="font-size: 1.5rem; font-weight: 700; color: #8b5cf6;"><?php echo number_format($total_sessions); ?></div>
    </div>
    <div class="card stat-card">
        <div class="stat-label" style="color: var(--text-muted); font-size: 0.875rem; font-weight: 500;"><?php echo __('dashboard.completed_appointments'); ?></div>
        <div class="stat-value" style="font-size: 1.5rem; font-weight: 700; color: #10b981;"><?php echo number_format($completed_appointments); ?></div>
    </div>
</div>

<div class="grid-charts" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Patient Labels Chart -->
    <div class="card">
        <h3 style="margin-bottom: 1.5rem; font-weight: 700;"><?php echo __('dashboard.patient_labels'); ?></h3>
        <div style="height: 300px;">
            <canvas id="labelChart"></canvas>
        </div>
    </div>

    <!-- Appointment Status Chart -->
    <div class="card">
        <h3 style="margin-bottom: 1.5rem; font-weight: 700;"><?php echo __('dashboard.apt_statuses'); ?></h3>
        <div style="height: 300px;">
            <canvas id="aptStatusChart"></canvas>
        </div>
    </div>
</div>

<div class="card">
    <h3 style="margin-bottom: 1.5rem; font-weight: 700;"><?php echo __('dashboard.doctor_load'); ?></h3>
    <div style="height: 400px; width: 100%;">
        <canvas id="doctorLoadChart"></canvas>
    </div>
</div>

<script>
(function() {
    // 1. Patient Labels Chart
    new Chart(document.getElementById('labelChart'), {
        type: 'pie',
        data: {
            labels: <?php echo json_encode(array_column($labels, 'label')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($labels, 'count')); ?>,
                backgroundColor: ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4']
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });

    // 2. Appointment Status Chart
    new Chart(document.getElementById('aptStatusChart'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_map(fn($s) => $s ? __('appointment.status.' . $s) : 'Unknown', array_column($apt_statuses, 'status'))); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($apt_statuses, 'count')); ?>,
                backgroundColor: ['#94a3b8', '#3b82f6', '#f59e0b', '#10b981', '#ef4444']
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });

    // 3. Doctor Load Chart
    new Chart(document.getElementById('doctorLoadChart'), {
        type: 'bar',
        indexAxis: 'y',
        data: {
            labels: <?php echo json_encode(array_column($doctors, 'full_name')); ?>,
            datasets: [
                {
                    label: '<?php echo __('dashboard.sessions_conducted'); ?>',
                    data: <?php echo json_encode(array_column($doctors, 'sessions')); ?>,
                    backgroundColor: 'rgba(139, 92, 246, 0.6)',
                    borderColor: '#8b5cf6',
                    borderWidth: 1
                },
                {
                    label: '<?php echo __('dashboard.appointments_assigned'); ?>',
                    data: <?php echo json_encode(array_column($doctors, 'appointments')); ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.6)',
                    borderColor: '#10b981',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { beginAtZero: true, ticks: { stepSize: 1 } }
            }
        }
    });
})();
</script>
