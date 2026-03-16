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
        <a href="/modules/patients/index.php" class="nav-item <?php echo $current_page === 'patients' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span><?php echo t('patients'); ?></span>
        </a>
        <a href="/modules/appointments/index.php" class="nav-item <?php echo $current_page === 'appointments' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-check"></i>
            <span><?php echo t('appointments'); ?></span>
        </a>
        <a href="/modules/medical/index.php" class="nav-item <?php echo $current_page === 'medical' ? 'active' : ''; ?>">
            <i class="fas fa-file-medical"></i>
            <span><?php echo t('medical_records'); ?></span>
        </a>
        <a href="/modules/leads/index.php" class="nav-item <?php echo $current_page === 'leads' ? 'active' : ''; ?>">
            <i class="fas fa-filter"></i>
            <span>Lead Marketing</span>
        </a>
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
        <a href="/modules/reports/index.php" class="nav-item <?php echo $current_page === 'reports' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <span><?php echo t('reports'); ?></span>
        </a>
    </nav>
    <div class="sidebar-footer" style="padding: 1rem; border-top: 1px solid rgba(255,255,255,0.05);">
        <a href="/logout.php" class="nav-item" style="color: #f87171;">
            <i class="fas fa-sign-out-alt"></i>
            <span><?php echo t('logout'); ?></span>
        </a>
    </div>
</aside>
