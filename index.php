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
$allowed_tabs = ['overview', 'marketing', 'clinical'];
$tab = in_array($_GET['tab'] ?? '', $allowed_tabs) ? $_GET['tab'] : 'overview';

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
    <div style="display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem; border-bottom: 1px dashed var(--border-color); padding-bottom: 1rem; overflow-x: auto;">
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
        <form method="GET" id="period-filter-form" class="period-filter-form" style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
            <input type="hidden" name="tab" id="current-tab-input" value="<?php echo e($tab); ?>">
            <div class="btn-group" style="display: flex; background: #f1f5f9; padding: 0.25rem; border-radius: 10px; gap: 0.1rem;">
                <?php 
                $now = new DateTime();
                $sel_date = ($_GET['sel_date'] ?? '') ?: $now->format('Y-m-d');
                $sel_week_val = ($_GET['sel_week'] ?? '') ?: $now->format('Y') . '-W' . $now->format('W');
                $sel_month_val = (int)($_GET['sel_month'] ?? $now->format('n'));
                $sel_quarter_val = (int)($_GET['sel_quarter'] ?? ceil((int)$now->format('n') / 3));
                $sel_year_val = (int)($_GET['sel_year'] ?? $now->format('Y'));
                
                $periodLabels = [
                    'today' => __('filter.today'),
                    'week' => __('filter.week'),
                    'month' => __('filter.month'),
                    'quarter' => __('filter.quarter'),
                    'year' => __('filter.year')
                ];
                foreach($periodLabels as $val => $lbl): ?>
                    <button type="button" onclick="switchPeriod('<?php echo $val; ?>')" class="btn period-btn" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; border: none; background: <?php echo $period === $val ? 'var(--primary)' : 'transparent'; ?>; color: <?php echo $period === $val ? 'white' : 'var(--text-muted)'; ?>; border-radius: 8px; cursor: pointer;">
                        <?php echo $lbl; ?>
                    </button>
                <?php endforeach; ?>
                <button type="button" onclick="switchPeriod('custom')" class="btn period-btn" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; border: none; background: <?php echo $period === 'custom' ? 'var(--primary)' : 'transparent'; ?>; color: <?php echo $period === 'custom' ? 'white' : 'var(--text-muted)'; ?>; border-radius: 8px; cursor: pointer;">
                    <?php echo __('filter.custom'); ?>
                </button>
            </div>

            <!-- Period-specific selectors -->
            <div id="period-selector-container" style="display: flex; align-items: center; gap: 0.5rem; margin-left: 0.25rem; padding-left: 0.5rem; border-left: 1px solid var(--border-color);">
                
                <!-- Today: date picker + nav arrows -->
                <div id="sel-today" class="period-sel" style="display: <?php echo $period === 'today' ? 'flex' : 'none'; ?>; align-items: center; gap: 0.35rem;">
                    <button type="button" onclick="navDate(-1)" class="btn btn-sm" style="width: 28px; height: 28px; padding: 0; display: flex; align-items: center; justify-content: center; background: #f1f5f9; border: none; border-radius: 6px; cursor: pointer;"><i class="fas fa-chevron-left" style="font-size: 0.7rem;"></i></button>
                    <input type="date" name="sel_date" id="input-sel-date" value="<?php echo e($sel_date); ?>" class="form-control" style="width: 140px; padding: 0.35rem 0.5rem; font-size: 0.85rem; border-radius: 8px;" onchange="this.form.querySelector('#hidden-period').value='today'; this.form.submit();">
                    <button type="button" onclick="navDate(1)" class="btn btn-sm" style="width: 28px; height: 28px; padding: 0; display: flex; align-items: center; justify-content: center; background: #f1f5f9; border: none; border-radius: 6px; cursor: pointer;"><i class="fas fa-chevron-right" style="font-size: 0.7rem;"></i></button>
                </div>

                <!-- Week: week picker + nav arrows -->
                <div id="sel-week" class="period-sel" style="display: <?php echo $period === 'week' ? 'flex' : 'none'; ?>; align-items: center; gap: 0.35rem;">
                    <button type="button" onclick="navWeek(-1)" class="btn btn-sm" style="width: 28px; height: 28px; padding: 0; display: flex; align-items: center; justify-content: center; background: #f1f5f9; border: none; border-radius: 6px; cursor: pointer;"><i class="fas fa-chevron-left" style="font-size: 0.7rem;"></i></button>
                    <input type="week" name="sel_week" id="input-sel-week" value="<?php echo e($sel_week_val); ?>" class="form-control" style="width: 155px; padding: 0.35rem 0.5rem; font-size: 0.85rem; border-radius: 8px;" onchange="this.form.querySelector('#hidden-period').value='week'; this.form.submit();">
                    <button type="button" onclick="navWeek(1)" class="btn btn-sm" style="width: 28px; height: 28px; padding: 0; display: flex; align-items: center; justify-content: center; background: #f1f5f9; border: none; border-radius: 6px; cursor: pointer;"><i class="fas fa-chevron-right" style="font-size: 0.7rem;"></i></button>
                </div>

                <!-- Month: month + year dropdowns -->
                <div id="sel-month" class="period-sel" style="display: <?php echo $period === 'month' ? 'flex' : 'none'; ?>; align-items: center; gap: 0.35rem;">
                    <button type="button" onclick="navMonth(-1)" class="btn btn-sm" style="width: 28px; height: 28px; padding: 0; display: flex; align-items: center; justify-content: center; background: #f1f5f9; border: none; border-radius: 6px; cursor: pointer;"><i class="fas fa-chevron-left" style="font-size: 0.7rem;"></i></button>
                    <select name="sel_month" id="input-sel-month" class="form-control" style="width: 90px; padding: 0.35rem 0.3rem; font-size: 0.85rem; border-radius: 8px;" onchange="this.form.querySelector('#hidden-period').value='month'; this.form.submit();">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $sel_month_val === $m ? 'selected' : ''; ?>><?php echo __('common.month_prefix') . $m . __('common.month_suffix'); ?></option>
                        <?php endfor; ?>
                    </select>
                    <select name="sel_year" id="input-sel-year-month" class="form-control" style="width: 80px; padding: 0.35rem 0.3rem; font-size: 0.85rem; border-radius: 8px;" onchange="this.form.querySelector('#hidden-period').value='month'; this.form.submit();">
                        <?php for ($y = (int)date('Y') - 2; $y <= (int)date('Y') + 1; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $sel_year_val === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                    <button type="button" onclick="navMonth(1)" class="btn btn-sm" style="width: 28px; height: 28px; padding: 0; display: flex; align-items: center; justify-content: center; background: #f1f5f9; border: none; border-radius: 6px; cursor: pointer;"><i class="fas fa-chevron-right" style="font-size: 0.7rem;"></i></button>
                </div>

                <!-- Quarter: dropdown + year -->
                <div id="sel-quarter" class="period-sel" style="display: <?php echo $period === 'quarter' ? 'flex' : 'none'; ?>; align-items: center; gap: 0.35rem;">
                    <button type="button" onclick="navQuarter(-1)" class="btn btn-sm" style="width: 28px; height: 28px; padding: 0; display: flex; align-items: center; justify-content: center; background: #f1f5f9; border: none; border-radius: 6px; cursor: pointer;"><i class="fas fa-chevron-left" style="font-size: 0.7rem;"></i></button>
                    <select name="sel_quarter" id="input-sel-quarter" class="form-control" style="width: 80px; padding: 0.35rem 0.3rem; font-size: 0.85rem; border-radius: 8px;" onchange="this.form.querySelector('#hidden-period').value='quarter'; this.form.submit();">
                        <?php for ($q = 1; $q <= 4; $q++): ?>
                            <option value="<?php echo $q; ?>" <?php echo $sel_quarter_val === $q ? 'selected' : ''; ?>><?php echo __('common.quarter_prefix') . $q . __('common.quarter_suffix'); ?></option>
                        <?php endfor; ?>
                    </select>
                    <select name="sel_year" id="input-sel-year-quarter" class="form-control" style="width: 80px; padding: 0.35rem 0.3rem; font-size: 0.85rem; border-radius: 8px;" onchange="this.form.querySelector('#hidden-period').value='quarter'; this.form.submit();">
                        <?php for ($y = (int)date('Y') - 2; $y <= (int)date('Y') + 1; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $sel_year_val === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                    <button type="button" onclick="navQuarter(1)" class="btn btn-sm" style="width: 28px; height: 28px; padding: 0; display: flex; align-items: center; justify-content: center; background: #f1f5f9; border: none; border-radius: 6px; cursor: pointer;"><i class="fas fa-chevron-right" style="font-size: 0.7rem;"></i></button>
                </div>

                <!-- Year -->
                <div id="sel-year" class="period-sel" style="display: <?php echo $period === 'year' ? 'flex' : 'none'; ?>; align-items: center; gap: 0.35rem;">
                    <button type="button" onclick="navYear(-1)" class="btn btn-sm" style="width: 28px; height: 28px; padding: 0; display: flex; align-items: center; justify-content: center; background: #f1f5f9; border: none; border-radius: 6px; cursor: pointer;"><i class="fas fa-chevron-left" style="font-size: 0.7rem;"></i></button>
                    <select name="sel_year" id="input-sel-year" class="form-control" style="width: 80px; padding: 0.35rem 0.3rem; font-size: 0.85rem; border-radius: 8px;" onchange="this.form.querySelector('#hidden-period').value='year'; this.form.submit();">
                        <?php for ($y = (int)date('Y') - 3; $y <= (int)date('Y') + 1; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $sel_year_val === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                    <button type="button" onclick="navYear(1)" class="btn btn-sm" style="width: 28px; height: 28px; padding: 0; display: flex; align-items: center; justify-content: center; background: #f1f5f9; border: none; border-radius: 6px; cursor: pointer;"><i class="fas fa-chevron-right" style="font-size: 0.7rem;"></i></button>
                </div>

                <!-- Custom: date range -->
                <div id="sel-custom" class="period-sel" style="display: <?php echo $period === 'custom' ? 'flex' : 'none'; ?>; align-items: center; gap: 0.5rem;">
                    <input type="date" name="start" value="<?php echo e($start ?? date('Y-m-d')); ?>" class="form-control" style="width: 140px; padding: 0.35rem 0.5rem; font-size: 0.85rem; border-radius: 8px;">
                    <span style="color: var(--text-muted); font-size: 0.85rem;"><?php echo __('common.to'); ?></span>
                    <input type="date" name="end" value="<?php echo e($end ?? date('Y-m-d')); ?>" class="form-control" style="width: 140px; padding: 0.35rem 0.5rem; font-size: 0.85rem; border-radius: 8px;">
                    <button type="submit" class="btn btn-sm" style="background: var(--primary); color: white; height: 30px; width: 30px; padding: 0; display: flex; align-items: center; justify-content: center; border-radius: 8px; border: none;">
                        <i class="fas fa-search" style="font-size: 0.75rem;"></i>
                    </button>
                </div>
            </div>

            <input type="hidden" name="period" id="hidden-period" value="<?php echo e($period); ?>">
        </form>
    </div>
    <div style="margin-top: 1rem; font-size: 0.9rem; color: var(--text-muted); font-weight: 600;">
        <i class="fas fa-calendar-day"></i> <?php echo __('dashboard.viewing'); ?>: <span style="color: var(--primary);"><?php echo $period_label; ?></span>
    </div>
</div>

<script>
// === Period Switching ===
function switchPeriod(periodName) {
    const form = document.getElementById('period-filter-form');
    document.getElementById('hidden-period').value = periodName;

    // Show/hide period selectors
    document.querySelectorAll('.period-sel').forEach(el => el.style.display = 'none');
    const target = document.getElementById('sel-' + periodName);
    if (target) target.style.display = 'flex';

    // Update active button styles
    document.querySelectorAll('.period-btn').forEach(btn => {
        btn.style.background = 'transparent';
        btn.style.color = 'var(--text-muted)';
    });
    event.currentTarget.style.background = 'var(--primary)';
    event.currentTarget.style.color = 'white';

    // Submit form (except custom—user needs to pick dates first)
    if (periodName !== 'custom') {
        form.submit();
    }
}

// === Navigation helpers ===
function navDate(offset) {
    const input = document.getElementById('input-sel-date');
    const d = new Date(input.value);
    d.setDate(d.getDate() + offset);
    input.value = d.toISOString().slice(0, 10);
    document.getElementById('hidden-period').value = 'today';
    document.getElementById('period-filter-form').submit();
}

function navWeek(offset) {
    const input = document.getElementById('input-sel-week');
    const parts = input.value.split('-W');
    let year = parseInt(parts[0]), week = parseInt(parts[1]);
    week += offset;
    // ISO 8601: some years have 53 weeks
    function getISOWeeksInYear(y) {
        const d = new Date(y, 11, 28); // Dec 28 is always in the last ISO week
        const dayOfYear = Math.ceil((d - new Date(d.getFullYear(),0,1)) / 86400000) + 1;
        return Math.ceil((dayOfYear - d.getDay() + 10) / 7);
    }
    if (week < 1) { year--; week = getISOWeeksInYear(year); }
    const maxWeeks = getISOWeeksInYear(year);
    if (week > maxWeeks) { year++; week = 1; }
    input.value = year + '-W' + String(week).padStart(2, '0');
    document.getElementById('hidden-period').value = 'week';
    document.getElementById('period-filter-form').submit();
}

function navMonth(offset) {
    const monthSel = document.getElementById('input-sel-month');
    const yearSel = document.getElementById('input-sel-year-month');
    let month = parseInt(monthSel.value) + offset;
    let year = parseInt(yearSel.value);
    if (month < 1) { month = 12; year--; }
    if (month > 12) { month = 1; year++; }
    monthSel.value = month;
    yearSel.value = year;
    document.getElementById('hidden-period').value = 'month';
    document.getElementById('period-filter-form').submit();
}

function navQuarter(offset) {
    const qSel = document.getElementById('input-sel-quarter');
    const ySel = document.getElementById('input-sel-year-quarter');
    let q = parseInt(qSel.value) + offset;
    let y = parseInt(ySel.value);
    if (q < 1) { q = 4; y--; }
    if (q > 4) { q = 1; y++; }
    qSel.value = q;
    ySel.value = y;
    document.getElementById('hidden-period').value = 'quarter';
    document.getElementById('period-filter-form').submit();
}

function navYear(offset) {
    const ySel = document.getElementById('input-sel-year');
    ySel.value = parseInt(ySel.value) + offset;
    document.getElementById('hidden-period').value = 'year';
    document.getElementById('period-filter-form').submit();
}

// === Tab Switching ===
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
?>

<?php
    require_once 'templates/footer.php';
}
?>
