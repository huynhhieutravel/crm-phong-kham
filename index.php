<?php
// index.php
require_once 'includes/db.php';
require_once 'includes/auth_middleware.php';

$db = getDB();

$is_ajax = (isset($_GET['ajax']) && $_GET['ajax'] == 1);

// Handle Period Filters
$period = $_GET['period'] ?? 'month';
$start = $_GET['start'] ?? null;
$end = $_GET['end'] ?? null;
$tab = $_GET['tab'] ?? 'overview';

$range = get_date_range($period, $start, $end);
$start_date = $range['start'];
$end_date = $range['end'];
$period_label = $range['label'];

if (!$is_ajax) {
    $page_title = 'Dashboard';
    $current_page = 'dashboard';
    require_once 'templates/header.php';
?>

<div class="dashboard-controls" style="background: white; padding: 1.25rem; border-radius: 16px; border: 1px solid var(--border-color); margin-bottom: 2rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
    
    <!-- Quick Actions Row -->
    <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; border-bottom: 1px dashed var(--border-color); padding-bottom: 1rem; overflow-x: auto;">
        <span style="font-size: 0.85rem; font-weight: 700; color: #94a3b8; align-self: center; white-space: nowrap;"><i class="fas fa-bolt"></i> <?php echo __('dashboard.quick_actions'); ?></span>
        <a href="/modules/appointments/add.php" class="btn btn-sm" style="background: #eef2ff; color: #4f46e5; border-radius: 8px; font-weight: 700; white-space: nowrap; transition: 0.2s;" onmouseover="this.style.background='#4f46e5'; this.style.color='white'" onmouseout="this.style.background='#eef2ff'; this.style.color='#4f46e5'"><i class="fas fa-plus"></i> <?php echo __('dashboard.qa_book_appt'); ?></a>
        <a href="/modules/leads/add.php" class="btn btn-sm" style="background: #ecfdf5; color: #10b981; border-radius: 8px; font-weight: 700; white-space: nowrap; transition: 0.2s;" onmouseover="this.style.background='#10b981'; this.style.color='white'" onmouseout="this.style.background='#ecfdf5'; this.style.color='#10b981'"><i class="fas fa-user-plus"></i> <?php echo __('dashboard.qa_new_lead'); ?></a>
        <a href="/modules/medical/daily.php" class="btn btn-sm" style="background: #fffbeb; color: #d97706; border-radius: 8px; font-weight: 700; white-space: nowrap; transition: 0.2s;" onmouseover="this.style.background='#d97706'; this.style.color='white'" onmouseout="this.style.background='#fffbeb'; this.style.color='#d97706'"><i class="fas fa-laptop-medical"></i> <?php echo __('dashboard.qa_daily_dispatch'); ?></a>
    </div>

    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1rem;">
        <!-- Tabs -->
        <div class="dashboard-tabs" style="display: flex; background: #f1f5f9; padding: 0.35rem; border-radius: 12px; gap: 0.25rem;">
            <a href="javascript:void(0)" onclick="loadTab('overview', this)" class="tab-item" style="padding: 0.6rem 1.25rem; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem; transition: all 0.25s ease; <?php echo $tab === 'overview' ? 'color: var(--primary); background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.05);' : 'color: var(--text-muted); background: transparent; box-shadow: none;'; ?>">
                <i class="fas fa-th-large"></i> <?php echo __('menu.dashboard'); ?>
            </a>
            <a href="javascript:void(0)" onclick="loadTab('marketing', this)" class="tab-item" style="padding: 0.6rem 1.25rem; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem; transition: all 0.25s ease; <?php echo $tab === 'marketing' ? 'color: var(--primary); background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.05);' : 'color: var(--text-muted); background: transparent; box-shadow: none;'; ?>">
                <i class="fas fa-bullhorn"></i> <?php echo __('menu.leads'); ?>
            </a>
            <a href="javascript:void(0)" onclick="loadTab('clinical', this)" class="tab-item" style="padding: 0.6rem 1.25rem; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem; transition: all 0.25s ease; <?php echo $tab === 'clinical' ? 'color: var(--primary); background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.05);' : 'color: var(--text-muted); background: transparent; box-shadow: none;'; ?>">
                <i class="fas fa-stethoscope"></i> <?php echo __('dashboard.clinical'); ?>
            </a>
        </div>

        <!-- Period Filters -->
        <form method="GET" class="period-filter-form" style="display: flex; align-items: center; gap: 0.5rem;">
            <input type="hidden" name="tab" id="current-tab-input" value="<?php echo e($tab); ?>">
            <div class="btn-group" style="display: flex; background: #f1f5f9; padding: 0.25rem; border-radius: 10px; gap: 0.1rem;">
                <?php 
                $periodLabels = [
                    'today' => __('filter.today'),
                    'week' => __('filter.week'),
                    'month' => __('filter.month'),
                    'quarter' => __('filter.quarter'),
                    'year' => __('filter.year')
                ];
                foreach($periodLabels as $val => $lbl): ?>
                    <a href="index.php?tab=<?php echo $tab; ?>&period=<?php echo $val; ?>" class="btn period-btn <?php echo $period === $val ? 'btn-primary' : ''; ?>" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; border: none; background: <?php echo $period === $val ? 'var(--primary)' : 'transparent'; ?>; color: <?php echo $period === $val ? 'white' : 'var(--text-muted)'; ?>; border-radius: 8px;">
                        <?php echo $lbl; ?>
                    </a>
                <?php endforeach; ?>
                <button type="button" onclick="toggleCustomRange()" class="btn <?php echo $period === 'custom' ? 'btn-primary' : ''; ?>" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; border: none; background: <?php echo $period === 'custom' ? 'var(--primary)' : 'transparent'; ?>; color: <?php echo $period === 'custom' ? 'white' : 'var(--text-muted)'; ?>; border-radius: 8px;">
                    <?php echo __('filter.custom'); ?>
                </button>
            </div>
            
            <div id="customRangeBlock" style="display: <?php echo $period === 'custom' ? 'flex' : 'none'; ?>; align-items: center; gap: 0.5rem; margin-left: 0.5rem; padding-left: 0.5rem; border-left: 1px solid var(--border-color);">
                <input type="date" name="start" value="<?php echo e($start ?? date('Y-m-d')); ?>" class="form-control" style="width: 140px; padding: 0.4rem;">
                <span><?php echo __('common.to'); ?></span>
                <input type="date" name="end" value="<?php echo e($end ?? date('Y-m-d')); ?>" class="form-control" style="width: 140px; padding: 0.4rem;">
                <input type="hidden" name="period" value="custom">
                <button type="submit" class="btn btn-icon" style="background: var(--primary); color: white; height: 34px; width: 34px;">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>
    </div>
    <div style="margin-top: 1rem; font-size: 0.9rem; color: var(--text-muted); font-weight: 600;">
        <i class="fas fa-calendar-day"></i> <?php echo __('dashboard.viewing'); ?>: <span style="color: var(--primary);"><?php echo $period_label; ?></span>
    </div>
</div>

<script>
function toggleCustomRange() {
    const block = document.getElementById('customRangeBlock');
    block.style.display = block.style.display === 'none' ? 'flex' : 'none';
}

function loadTab(tabName, btnEl) {
    document.getElementById('current-tab-input').value = tabName;
    
    // Update button styles smoothly
    document.querySelectorAll('.tab-item').forEach(el => {
        el.style.backgroundColor = 'transparent';
        el.style.color = 'var(--text-muted)';
        el.style.boxShadow = 'none';
    });
    btnEl.style.backgroundColor = 'white';
    btnEl.style.color = 'var(--primary)';
    btnEl.style.boxShadow = '0 2px 4px rgba(0,0,0,0.05)';

    // Update period links so changing date won't lose the tab!
    document.querySelectorAll('.period-btn').forEach(a => {
        a.href = a.href.replace(/tab=[^&]+/, "tab=" + tabName);
    });

    // Elegant spinner
    document.getElementById('dashboard-content-container').innerHTML = `
        <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; padding: 100px 0; animation: fadeIn 0.3s;">
            <i class="fas fa-circle-notch fa-spin fa-3x" style="color: #6366f1; margin-bottom: 20px;"></i>
            <h3 style="color: #475569; font-weight: 700;"><?php echo __('dashboard.processing_charts'); ?></h3>
            <p style="color: #94a3b8;"><?php echo __('dashboard.please_wait'); ?></p>
        </div>
    `;

    // Fetch new content
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tabName);
    url.searchParams.set('ajax', '1');
    
    fetch(url)
    .then(r => r.text())
    .then(html => {
        const container = document.getElementById('dashboard-content-container');
        container.innerHTML = html;
        
        // Browsers block executing innerHTML <script> tags for security. 
        // We clone and append them perfectly to run chart.js scripts!
        const scripts = container.querySelectorAll('script');
        scripts.forEach(oldScript => {
            const newScript = document.createElement('script');
            Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
            newScript.appendChild(document.createTextNode(oldScript.innerHTML));
            oldScript.parentNode.replaceChild(newScript, oldScript);
        });

        // Update URL bar for sharing
        const pushUrl = new URL(window.location.href);
        pushUrl.searchParams.set('tab', tabName);
        window.history.pushState({}, '', pushUrl);
    });
}
</script>

<?php 
   // Close if(!$is_ajax) wrapper
   echo '<div id="dashboard-content-container">';
} 
?>

<?php
// Load dynamic content based on tab
switch ($tab) {
    case 'marketing':
        include 'modules/dashboard/marketing_stats.php';
        break;
    case 'clinical':
        include 'modules/dashboard/clinical_stats.php';
        break;
    case 'overview':
    default:
        include 'modules/dashboard/overview_stats.php';
        break;
}
?>

<?php
if (!$is_ajax) {
    echo '</div>'; // Close container
    require_once 'templates/footer.php';
}
?>
