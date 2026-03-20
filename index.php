<?php
// index.php
require_once 'includes/db.php';
$page_title = 'Dashboard';
$current_page = 'dashboard';
require_once 'templates/header.php';

$db = getDB();

// Handle Period Filters
$period = $_GET['period'] ?? 'month';
$start = $_GET['start'] ?? null;
$end = $_GET['end'] ?? null;
$tab = $_GET['tab'] ?? 'overview';

$range = get_date_range($period, $start, $end);
$start_date = $range['start'];
$end_date = $range['end'];
$period_label = $range['label'];
?>

<div class="dashboard-controls" style="background: white; padding: 1.25rem; border-radius: 16px; border: 1px solid var(--border-color); margin-bottom: 2rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1rem;">
        <!-- Tabs -->
        <div class="dashboard-tabs" style="display: flex; background: #f1f5f9; padding: 0.35rem; border-radius: 12px; gap: 0.25rem;">
            <a href="index.php?tab=overview&period=<?php echo $period; ?>" class="tab-item <?php echo $tab === 'overview' ? 'active' : ''; ?>" style="padding: 0.6rem 1.25rem; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem; color: <?php echo $tab === 'overview' ? 'var(--primary)' : 'var(--text-muted)'; ?>; background: <?php echo $tab === 'overview' ? 'white' : 'transparent'; ?>; box-shadow: <?php echo $tab === 'overview' ? '0 2px 4px rgba(0,0,0,0.05)' : 'none'; ?>;">
                <i class="fas fa-th-large"></i> Tổng quan
            </a>
            <a href="index.php?tab=marketing&period=<?php echo $period; ?>" class="tab-item <?php echo $tab === 'marketing' ? 'active' : ''; ?>" style="padding: 0.6rem 1.25rem; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem; color: <?php echo $tab === 'marketing' ? 'var(--primary)' : 'var(--text-muted)'; ?>; background: <?php echo $tab === 'marketing' ? 'white' : 'transparent'; ?>; box-shadow: <?php echo $tab === 'marketing' ? '0 2px 4px rgba(0,0,0,0.05)' : 'none'; ?>;">
                <i class="fas fa-bullhorn"></i> Marketing
            </a>
            <a href="index.php?tab=clinical&period=<?php echo $period; ?>" class="tab-item <?php echo $tab === 'clinical' ? 'active' : ''; ?>" style="padding: 0.6rem 1.25rem; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem; color: <?php echo $tab === 'clinical' ? 'var(--primary)' : 'var(--text-muted)'; ?>; background: <?php echo $tab === 'clinical' ? 'white' : 'transparent'; ?>; box-shadow: <?php echo $tab === 'clinical' ? '0 2px 4px rgba(0,0,0,0.05)' : 'none'; ?>;">
                <i class="fas fa-stethoscope"></i> Lâm sàng
            </a>
        </div>

        <!-- Period Filters -->
        <form method="GET" class="period-filter-form" style="display: flex; align-items: center; gap: 0.5rem;">
            <input type="hidden" name="tab" value="<?php echo e($tab); ?>">
            <div class="btn-group" style="display: flex; background: #f1f5f9; padding: 0.25rem; border-radius: 10px; gap: 0.1rem;">
                <?php foreach(['today' => 'Hôm nay', 'week' => 'Tuần', 'month' => 'Tháng', 'quarter' => 'Quý', 'year' => 'Năm'] as $val => $lbl): ?>
                    <a href="index.php?tab=<?php echo $tab; ?>&period=<?php echo $val; ?>" class="btn <?php echo $period === $val ? 'btn-primary' : ''; ?>" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; border: none; background: <?php echo $period === $val ? 'var(--primary)' : 'transparent'; ?>; color: <?php echo $period === $val ? 'white' : 'var(--text-muted)'; ?>; border-radius: 8px;">
                        <?php echo $lbl; ?>
                    </a>
                <?php endforeach; ?>
                <button type="button" onclick="toggleCustomRange()" class="btn <?php echo $period === 'custom' ? 'btn-primary' : ''; ?>" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; border: none; background: <?php echo $period === 'custom' ? 'var(--primary)' : 'transparent'; ?>; color: <?php echo $period === 'custom' ? 'white' : 'var(--text-muted)'; ?>; border-radius: 8px;">
                    Tùy chọn
                </button>
            </div>
            
            <div id="customRangeBlock" style="display: <?php echo $period === 'custom' ? 'flex' : 'none'; ?>; align-items: center; gap: 0.5rem; margin-left: 0.5rem; padding-left: 0.5rem; border-left: 1px solid var(--border-color);">
                <input type="date" name="start" value="<?php echo e($start ?: date('Y-m-d')); ?>" class="form-control" style="width: 140px; padding: 0.4rem;">
                <span>đến</span>
                <input type="date" name="end" value="<?php echo e($end ?: date('Y-m-d')); ?>" class="form-control" style="width: 140px; padding: 0.4rem;">
                <input type="hidden" name="period" value="custom">
                <button type="submit" class="btn btn-icon" style="background: var(--primary); color: white; height: 34px; width: 34px;">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>
    </div>
    <div style="margin-top: 1rem; font-size: 0.9rem; color: var(--text-muted); font-weight: 600;">
        <i class="fas fa-calendar-day"></i> Đang xem: <span style="color: var(--primary);"><?php echo $period_label; ?></span>
    </div>
</div>

<script>
function toggleCustomRange() {
    const block = document.getElementById('customRangeBlock');
    block.style.display = block.style.display === 'none' ? 'flex' : 'none';
}
</script>

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
        include 'modules/dashboard/overview_stats.php'; // We'll move old logic here
        break;
}

require_once 'templates/footer.php';
?>
