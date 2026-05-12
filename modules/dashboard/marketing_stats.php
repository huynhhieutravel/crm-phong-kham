<?php
// modules/dashboard/marketing_stats.php
if (!isset($db)) die('Direct access not permitted');

// 1. Key Metrics
$total_leads = 0;
$converted_leads = 0;
$sources = [];

if ($start_date && $end_date) {
    try {
        $stmt_tl = $db->prepare("SELECT COUNT(*) FROM leads WHERE created_at >= ? AND created_at <= ?");
        $stmt_tl->execute([$start_date, $end_date]);
        $total_leads = $stmt_tl->fetchColumn();
    } catch (Exception $e) { error_log($e->getMessage()); }

    try {
        $stmt_cl = $db->prepare("SELECT COUNT(*) FROM leads WHERE status = 'converted' AND updated_at >= ? AND updated_at <= ?");
        $stmt_cl->execute([$start_date, $end_date]);
        $converted_leads = $stmt_cl->fetchColumn() ?: 0;
    } catch (Exception $e) {
        error_log($e->getMessage());
        // Fallback if status or updated_at missing
        try {
            $converted_leads = $db->query("SELECT COUNT(*) FROM leads WHERE status = 'converted'")->fetchColumn() ?: 0;
        } catch (Exception $e2) {
            $converted_leads = 0;
        }
    }
    
    try {
        // 2. Lead Sources Data
        $source_stmt = $db->prepare("SELECT source, COUNT(*) as count FROM leads WHERE created_at >= ? AND created_at <= ? GROUP BY source ORDER BY count DESC");
        if ($source_stmt) {
            $source_stmt->execute([$start_date, $end_date]);
            $sources = $source_stmt->fetchAll();
        }
    } catch (Exception $e) {}
}
$conversion_rate = $total_leads > 0 ? ($converted_leads / $total_leads) * 100 : 0;

try {
    // 3. Consultant Performance Data
    $consultant_stmt = $db->prepare("
        SELECT u.full_name, COUNT(l.id) as total, SUM(CASE WHEN l.status = 'converted' THEN 1 ELSE 0 END) as converted
        FROM users u
        LEFT JOIN leads l ON u.id = l.consultant_id AND l.created_at BETWEEN ? AND ?
        WHERE u.role_id IN (1, 6) -- Admin or CSKH
        GROUP BY u.id
        HAVING total > 0
        ORDER BY total DESC
    ");
    $consultant_stmt->execute([$start_date, $end_date]);
    $consultants = $consultant_stmt->fetchAll();
} catch (Exception $e) {
    try {
        $consultants = $db->query("SELECT full_name, 0 as total, 0 as converted FROM users WHERE role_id IN (1, 6)")->fetchAll();
    } catch (Exception $e2) {
        $consultants = [];
    }
}

try {
    // 4. Status Breakdown Data
    $status_stmt = $db->prepare("SELECT status, COUNT(*) as count FROM leads WHERE created_at >= ? AND created_at <= ? GROUP BY status");
    $status_stmt->execute([$start_date, $end_date]);
    $statuses = $status_stmt->fetchAll();
} catch (Exception $e) {}
?>

<div class="grid-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <div class="card stat-card">
        <div class="stat-label" style="color: var(--text-muted); font-size: 0.875rem; font-weight: 500;"><?php echo __('dashboard.total_leads'); ?></div>
        <div class="stat-value" style="font-size: 1.5rem; font-weight: 700; color: var(--primary);"><?php echo number_format($total_leads); ?></div>
    </div>
    <div class="card stat-card">
        <div class="stat-label" style="color: var(--text-muted); font-size: 0.875rem; font-weight: 500;"><?php echo __('dashboard.converted_leads'); ?></div>
        <div class="stat-value" style="font-size: 1.5rem; font-weight: 700; color: #10b981;"><?php echo number_format($converted_leads); ?></div>
    </div>
    <div class="card stat-card">
        <div class="stat-label" style="color: var(--text-muted); font-size: 0.875rem; font-weight: 500;"><?php echo __('dashboard.conversion_rate'); ?></div>
        <div class="stat-value" style="font-size: 1.5rem; font-weight: 700; color: #f59e0b;"><?php echo number_format($conversion_rate, 1); ?>%</div>
    </div>
</div>

<div class="grid-charts" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Lead Sources Chart -->
    <div class="card">
        <h3 style="margin-bottom: 1.5rem; font-weight: 700;"><?php echo __('dashboard.marketing_sources'); ?></h3>
        <div style="height: 300px;">
            <canvas id="sourceChart"></canvas>
        </div>
    </div>

    <!-- Status Breakdown Chart -->
    <div class="card">
        <h3 style="margin-bottom: 1.5rem; font-weight: 700;"><?php echo __('dashboard.lead_statuses'); ?></h3>
        <div style="height: 300px;">
            <canvas id="statusChart"></canvas>
        </div>
    </div>
</div>

<div class="card">
    <h3 style="margin-bottom: 1.5rem; font-weight: 700;"><?php echo __('dashboard.consultant_performance'); ?></h3>
    <div style="height: 400px; width: 100%;">
        <canvas id="consultantChart"></canvas>
    </div>
</div>

<script>
(function() {
    // 1. Source Chart
    const sourceLabels = <?php echo json_encode(array_column($sources, 'source')); ?>;
    new Chart(document.getElementById('sourceChart'), {
        type: 'doughnut',
        data: {
            labels: sourceLabels,
            datasets: [{
                data: <?php echo json_encode(array_column($sources, 'count')); ?>,
                backgroundColor: ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4']
            }]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            onHover: (event, chartElement) => {
                event.native.target.style.cursor = chartElement[0] ? 'pointer' : 'default';
            },
            onClick: (e, activeElements) => {
                if (activeElements.length > 0) {
                    const dataIndex = activeElements[0].index;
                    window.location.href = '/modules/leads/index.php?source=' + encodeURIComponent(sourceLabels[dataIndex]);
                }
            }
        }
    });

    // 2. Status Chart
    const statusLabels = <?php echo json_encode(array_column($statuses, 'status')); ?>;
    const statusDisplayLabels = <?php echo json_encode(array_map(fn($s) => __('lead.status.' . $s), array_column($statuses, 'status'))); ?>;
    new Chart(document.getElementById('statusChart'), {
        type: 'pie',
        data: {
            labels: statusDisplayLabels,
            datasets: [{
                data: <?php echo json_encode(array_column($statuses, 'count')); ?>,
                backgroundColor: ['#94a3b8', '#3b82f6', '#f59e0b', '#10b981', '#ef4444']
            }]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            onHover: (event, chartElement) => {
                event.native.target.style.cursor = chartElement[0] ? 'pointer' : 'default';
            },
            onClick: (e, activeElements) => {
                if (activeElements.length > 0) {
                    const dataIndex = activeElements[0].index;
                    window.location.href = '/modules/leads/index.php?status=' + encodeURIComponent(statusLabels[dataIndex]);
                }
            }
        }
    });

    // 3. Consultant Performance Chart
    new Chart(document.getElementById('consultantChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($consultants, 'full_name')); ?>,
            datasets: [
                {
                    label: '<?php echo __('dashboard.total_leads'); ?>',
                    data: <?php echo json_encode(array_column($consultants, 'total')); ?>,
                    backgroundColor: 'rgba(99, 102, 241, 0.5)',
                    borderColor: '#6366f1',
                    borderWidth: 1
                },
                {
                    label: '<?php echo __('dashboard.converted_sale'); ?>',
                    data: <?php echo json_encode(array_column($consultants, 'converted')); ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.5)',
                    borderColor: '#10b981',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 } }
            }
        }
    });
})();
</script>
