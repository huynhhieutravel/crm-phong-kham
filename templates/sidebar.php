<?php
// templates/sidebar.php
?>
<aside class="sidebar">
    <div class="brand">
        <i class="fas fa-hand-holding-medical"></i>
        <span>CLINIC CRM</span>
    </div>
    <nav class="nav-menu">
        <a href="/index.php" class="nav-item <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-th-large"></i>
            <span><?php echo t('dashboard'); ?></span>
        </a>
        <div class="nav-item-wrapper">
            <a href="/modules/leads/index.php" class="nav-item <?php echo $current_page === 'leads' ? 'active' : ''; ?>">
                <i class="fas fa-filter"></i>
                <span><?php echo t('leads'); ?></span>
            </a>
            <div class="submenu">
                <a href="/modules/leads/index.php" class="submenu-item">
                    <i class="fas fa-list"></i> <?php echo t('list'); ?>
                </a>
                <a href="/modules/leads/dashboard.php" class="submenu-item">
                    <i class="fas fa-chart-line"></i> <?php echo t('lead_stats'); ?>
                </a>
                <a href="/modules/leads/consultant_stats.php" class="submenu-item">
                    <i class="fas fa-user-tie"></i> <?php echo t('consultant_stats'); ?>
                </a>
            </div>
        </div>
        <div class="nav-item-wrapper">
            <a href="/modules/appointments/index.php" class="nav-item <?php echo $current_page === 'appointments' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-check"></i>
                <span><?php echo t('appointments'); ?></span>
            </a>
            <div class="submenu">
                <a href="/modules/appointments/index.php?view=list" class="submenu-item">
                    <i class="fas fa-list"></i> <?php echo t('list'); ?>
                </a>
                <a href="/modules/appointments/index.php?view=timeline_week" class="submenu-item">
                    <i class="fas fa-stream"></i> <?php echo t('timeline'); ?>
                </a>
                <a href="/modules/appointments/dashboard.php" class="submenu-item">
                    <i class="fas fa-chart-pie"></i> <?php echo t('appointment_stats'); ?>
                </a>
            </div>
        </div>
        <a href="/modules/patients/index.php" class="nav-item <?php echo $current_page === 'patients' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span><?php echo t('patients'); ?></span>
        </a>
        <div class="nav-item-wrapper">
            <a href="/modules/medical/index.php" class="nav-item <?php echo $current_page === 'medical' ? 'active' : ''; ?>">
                <i class="fas fa-file-medical"></i>
                <span><?php echo t('medical_records'); ?></span>
            </a>
            <div class="submenu">
                <a href="/modules/medical/index.php" class="submenu-item">
                    <i class="fas fa-list"></i> <?php echo t('medical_sessions'); ?>
                </a>
                <a href="/modules/medical/chiro_exam.php" class="submenu-item">
                    <i class="fas fa-stethoscope"></i> <?php echo t('chiro_first_exam'); ?>
                </a>
                <a href="/modules/medical/chiro_history.php" class="submenu-item">
                    <i class="fas fa-hospital-user"></i> <?php echo t('chiro_history'); ?>
                </a>
                <a href="/modules/medical/form.php?type=dong_y" class="submenu-item">
                    <i class="fas fa-leaf"></i> <?php echo t('dong_y_form'); ?>
                </a>
            </div>
        </div>
        <div class="nav-section-label" style="padding: 1.5rem 1.5rem 0.5rem; font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 700;"><?php echo t('management'); ?></div>
        <a href="/modules/hr/index.php" class="nav-item <?php echo $current_page === 'hr' ? 'active' : ''; ?>">
            <i class="fas fa-user-clock"></i>
            <span><?php echo t('hr'); ?></span>
        </a>
        <a href="/modules/sales/index.php" class="nav-item <?php echo $current_page === 'sales' ? 'active' : ''; ?>">
            <i class="fas fa-shopping-cart"></i>
            <span><?php echo t('sales'); ?></span>
        </a>
        <a href="/modules/inventory/index.php" class="nav-item <?php echo $current_page === 'inventory' ? 'active' : ''; ?>">
            <i class="fas fa-boxes"></i>
            <span><?php echo t('inventory'); ?></span>
        </a>
        <?php if (has_role('admin')): ?>
            <a href="/modules/admin/audit_logs.php" class="nav-item <?php echo $current_page === 'admin_audit' ? 'active' : ''; ?>">
                <i class="fas fa-shield-alt"></i>
                <span><?php echo t('audit_log'); ?></span>
            </a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer" style="padding: 1rem; border-top: 1px solid rgba(255,255,255,0.05);">
        <a href="/logout.php" class="nav-item" style="color: #f87171;">
            <i class="fas fa-sign-out-alt"></i>
            <span><?php echo t('logout'); ?></span>
        </a>
    </div>
</aside>
