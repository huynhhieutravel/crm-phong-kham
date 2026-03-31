<?php
// templates/sidebar.php
?>
<aside class="sidebar">
    <div class="brand">
        <i class="fas fa-hand-holding-medical"></i>
        <span>SIMON CENTER</span>
    </div>
    <nav class="nav-menu">
        <div class="nav-item-wrapper">
            <a href="<?php echo $base_url; ?>index.php" class="nav-item <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                <i class="fas fa-th-large"></i>
                <span><?php echo __('menu.dashboard'); ?></span>
            </a>
            <div class="submenu">
                <a href="<?php echo $base_url; ?>index.php" class="submenu-item <?php echo ($current_page === 'dashboard' && strpos($_SERVER['PHP_SELF'], 'daily_operations.php') === false) ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i> <?php echo __('menu.dashboard.overview'); ?>
                </a>
                <a href="<?php echo $base_url; ?>modules/dashboard/daily_operations.php" class="submenu-item <?php echo ($current_page === 'dashboard' && strpos($_SERVER['PHP_SELF'], 'daily_operations.php') !== false) ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-day"></i> <?php echo __('menu.dashboard.daily'); ?>
                </a>
            </div>
        </div>

        <?php if (can('view_leads')): ?>
        <div class="nav-item-wrapper">
            <a href="<?php echo $base_url; ?>modules/leads/index.php" class="nav-item <?php echo $current_page === 'leads' ? 'active' : ''; ?>">
                <i class="fas fa-filter"></i>
                <span><?php echo __('menu.leads'); ?></span>
            </a>
            <div class="submenu">
                <a href="<?php echo $base_url; ?>modules/leads/index.php" class="submenu-item">
                    <i class="fas fa-list"></i> <?php echo __('common.list'); ?>
                </a>
                <a href="<?php echo $base_url; ?>modules/leads/dashboard.php" class="submenu-item">
                    <i class="fas fa-chart-line"></i> <?php echo __('menu.leads.stats'); ?>
                </a>
                <a href="<?php echo $base_url; ?>modules/leads/consultant_stats.php" class="submenu-item">
                    <i class="fas fa-user-tie"></i> <?php echo __('menu.leads.consultant'); ?>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (can('view_appointments')): ?>
        <div class="nav-item-wrapper">
            <a href="<?php echo $base_url; ?>modules/appointments/timeline.php" class="nav-item <?php echo $current_page === 'appointments' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-check"></i>
                <span><?php echo __('menu.appointments'); ?></span>
            </a>
            <div class="submenu">
                <a href="<?php echo $base_url; ?>modules/appointments/index.php?view=list" class="submenu-item <?php echo ($current_page == 'appointments' && (isset($_GET['view']) && $_GET['view'] == 'list' || !isset($_GET['view']))) ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> <?php echo __('common.list'); ?>
                </a>
                <a href="<?php echo $base_url; ?>modules/appointments/timeline.php" class="submenu-item <?php echo ($current_page == 'appointments' && strpos($_SERVER['PHP_SELF'], 'timeline.php') !== false) ? 'active' : ''; ?>">
                    <i class="fas fa-clock"></i> <?php echo __('menu.appointments.timeline'); ?>
                </a>
                <a href="<?php echo $base_url; ?>modules/appointments/staff_timeline.php" class="submenu-item <?php echo ($current_page == 'appointments' && strpos($_SERVER['PHP_SELF'], 'staff_timeline.php') !== false) ? 'active' : ''; ?>">
                    <i class="fas fa-user-clock"></i> <?php echo __('menu.appointments.staff_timeline'); ?>
                </a>
                <a href="<?php echo $base_url; ?>modules/appointments/dashboard.php" class="submenu-item <?php echo ($current_page == 'appointments' && strpos($_SERVER['PHP_SELF'], 'dashboard.php') !== false) ? 'active' : ''; ?>">
                    <i class="fas fa-chart-pie"></i> <?php echo __('menu.appointments.stats'); ?>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (can('view_patients')): ?>
        <div class="nav-item-wrapper">
            <a href="<?php echo $base_url; ?>modules/patients/dashboard.php" class="nav-item <?php echo $current_page === 'patients' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span><?php echo __('menu.patients'); ?></span>
            </a>
            <div class="submenu">
                <a href="<?php echo $base_url; ?>modules/patients/dashboard.php" class="submenu-item <?php echo ($current_page === 'patients' && strpos($_SERVER['PHP_SELF'], 'dashboard.php') !== false) ? 'active' : ''; ?>">
                    <i class="fas fa-chart-pie"></i> <?php echo __('menu.patients.dashboard'); ?>
                </a>
                <a href="<?php echo $base_url; ?>modules/patients/index.php" class="submenu-item <?php echo ($current_page === 'patients' && strpos($_SERVER['PHP_SELF'], 'index.php') !== false) ? 'active' : ''; ?>">
                    <i class="fas fa-list-ul"></i> <?php echo __('menu.patients.list'); ?>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (can('view_medical')): ?>
        <div class="nav-item-wrapper">
            <a href="<?php echo $base_url; ?>modules/medical/daily.php" class="nav-item <?php echo $current_page === 'medical' ? 'active' : ''; ?>">
                <i class="fas fa-file-medical"></i>
                <span><?php echo __('menu.medical'); ?></span>
            </a>
            <div class="submenu">
                <a href="<?php echo $base_url; ?>modules/medical/daily.php" class="submenu-item <?php echo ($current_page === 'medical' && strpos($_SERVER['PHP_SELF'], 'daily.php') !== false) ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-day"></i> <?php echo __('menu.medical.daily'); ?>
                </a>
                <a href="<?php echo $base_url; ?>modules/medical/index.php" class="submenu-item <?php echo ($current_page === 'medical' && strpos($_SERVER['PHP_SELF'], 'index.php') !== false) ? 'active' : ''; ?>">
                    <i class="fas fa-archive"></i> <?php echo __('menu.medical.records'); ?>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <div class="nav-section-label" style="padding: 1.5rem 1.5rem 0.5rem; font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 700;"><?php echo __('menu.management_label'); ?></div>
        
        <?php if (can('view_hr')): ?>
        <a href="<?php echo $base_url; ?>modules/hr/index.php" class="nav-item <?php echo $current_page === 'hr' ? 'active' : ''; ?>">
            <i class="fas fa-user-clock"></i>
            <span><?php echo __('menu.hr'); ?></span>
        </a>
        <?php endif; ?>

        <?php if (can('view_sales')): ?>
        <a href="<?php echo $base_url; ?>modules/sales/index.php" class="nav-item <?php echo $current_page === 'sales' ? 'active' : ''; ?>">
            <i class="fas fa-shopping-cart"></i>
            <span><?php echo __('menu.sales'); ?></span>
        </a>
        <?php endif; ?>

        <?php if (can('view_inventory')): ?>
        <a href="<?php echo $base_url; ?>modules/inventory/index.php" class="nav-item <?php echo $current_page === 'inventory' ? 'active' : ''; ?>">
            <i class="fas fa-boxes"></i>
            <span><?php echo __('menu.inventory'); ?></span>
        </a>
        <?php endif; ?>

        <?php if (can('view_reports')): ?>
        <div class="nav-item-wrapper">
            <a href="<?php echo $base_url; ?>modules/reports/index.php" class="nav-item <?php echo $current_page === 'reports' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                <span><?php echo __('menu.reports'); ?></span>
            </a>
            <div class="submenu">
                <a href="<?php echo $base_url; ?>modules/reports/index.php" class="submenu-item <?php echo ($current_page == 'reports' && strpos($_SERVER['PHP_SELF'], 'index.php') !== false) ? 'active' : ''; ?>">
                    <i class="fas fa-wallet"></i> <?php echo __('menu.reports.financial'); ?>
                </a>
                <a href="<?php echo $base_url; ?>modules/reports/kpi.php" class="submenu-item <?php echo ($current_page == 'reports' && strpos($_SERVER['PHP_SELF'], 'kpi.php') !== false) ? 'active' : ''; ?>">
                    <i class="fas fa-users-cog"></i> <?php echo __('menu.reports.kpi'); ?>
                </a>
            </div>
        </div>
        <?php endif; ?>
        <?php if (can('view_audit_logs')): ?>
        <a href="<?php echo $base_url; ?>modules/admin/audit_logs.php" class="nav-item <?php echo $current_page === 'admin_audit' ? 'active' : ''; ?>">
            <i class="fas fa-shield-alt"></i>
            <span><?php echo __('menu.audit_logs'); ?></span>
        </a>
        <?php endif; ?>

        <div class="nav-section-label" style="padding: 1.5rem 1.5rem 0.5rem; font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 700; border-top: 1px solid rgba(255,255,255,0.05); margin-top: 1rem;"><?php echo __('menu.guide'); ?></div>
        
        <?php 
        $guide_section = isset($_GET['section']) ? $_GET['section'] : 'overview';
        ?>
        <a href="<?php echo $base_url; ?>modules/guide/index.php?section=overview" class="nav-item <?php echo ($current_page === 'guide' && $guide_section === 'overview') ? 'active' : ''; ?>">
            <i class="fas fa-info-circle"></i>
            <span><?php echo __('guide.overview'); ?></span>
        </a>
        <a href="<?php echo $base_url; ?>modules/guide/index.php?section=roles" class="nav-item <?php echo ($current_page === 'guide' && $guide_section === 'roles') ? 'active' : ''; ?>">
            <i class="fas fa-user-shield"></i>
            <span><?php echo __('guide.roles'); ?></span>
        </a>
        <a href="<?php echo $base_url; ?>modules/guide/index.php?section=leads" class="nav-item <?php echo ($current_page === 'guide' && $guide_section === 'leads') ? 'active' : ''; ?>">
            <i class="fas fa-filter"></i>
            <span><?php echo __('guide.leads'); ?></span>
        </a>
        <a href="<?php echo $base_url; ?>modules/guide/index.php?section=appointments" class="nav-item <?php echo ($current_page === 'guide' && $guide_section === 'appointments') ? 'active' : ''; ?>">
            <i class="fas fa-calendar-check"></i>
            <span><?php echo __('guide.appointments'); ?></span>
        </a>
        <a href="<?php echo $base_url; ?>modules/guide/index.php?section=medical" class="nav-item <?php echo ($current_page === 'guide' && $guide_section === 'medical') ? 'active' : ''; ?>">
            <i class="fas fa-file-medical"></i>
            <span><?php echo __('guide.medical'); ?></span>
        </a>
        <a href="<?php echo $base_url; ?>modules/guide/index.php?section=sales" class="nav-item <?php echo ($current_page === 'guide' && $guide_section === 'sales') ? 'active' : ''; ?>">
            <i class="fas fa-shopping-cart"></i>
            <span><?php echo __('guide.sales'); ?></span>
        </a>
    </nav>
    <div class="sidebar-footer" style="padding: 1rem; border-top: 1px solid rgba(255,255,255,0.05);">
        <a href="<?php echo $base_url; ?>modules/hr/change_password.php" class="nav-item <?php echo $current_page === 'change_password' ? 'active' : ''; ?>" style="color: #94a3b8;">
            <i class="fas fa-key"></i>
            <span><?php echo __('menu.change_password'); ?></span>
        </a>
        <a href="<?php echo $base_url; ?>logout.php" class="nav-item" style="color: #f87171;">
            <i class="fas fa-sign-out-alt"></i>
            <span><?php echo __('menu.logout'); ?></span>
        </a>
    </div>
</aside>
