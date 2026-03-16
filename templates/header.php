<?php
// templates/header.php
require_once __DIR__ . '/../includes/auth_middleware.php';

$current_page = $current_page ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="<?php echo get_current_lang(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Clinic Management'; ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        <main class="main-content">
            <header class="top-bar">
                <div class="header-left">
                    <button id="sidebarToggle" class="btn btn-icon d-md-none">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05rem; margin-bottom: 0.2rem;">
                            Clinic CRM / <?php echo e(ucfirst($current_page)); ?>
                        </div>
                        <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); line-height: 1.2;">
                            <?php echo $page_title ?? t('dashboard'); ?>
                        </h1>
                    </div>
                </div>

                <div class="header-right">
                    <div class="user-profile-dropdown" style="background: rgba(255,255,255,0.05); padding: 0.5rem 1rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1);">
                        <div class="user-info d-none d-lg-block" style="text-align: right; margin-right: 0.75rem;">
                            <span class="user-name" style="display: block; font-weight: 700; color: var(--text-main); font-size: 0.9rem;"><?php echo e($_SESSION['full_name']); ?></span>
                            <span class="user-role" style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">Quản trị viên</span>
                        </div>
                        <div class="avatar-wrapper" style="width: 36px; height: 36px; background: linear-gradient(135deg, var(--primary), #818cf8); color: white; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.9rem;">
                            <?php echo substr($_SESSION['full_name'] ?? 'U', 0, 1); ?>
                        </div>
                    </div>
                </div>
            </header>
            <div class="content-body">
                <?php if (isset($_SESSION['flash_message'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['flash_type'] ?? 'success'; ?>" id="flashMessage">
                        <i class="fas <?php echo ($_SESSION['flash_type'] ?? 'success') === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                        <?php 
                        echo $_SESSION['flash_message']; 
                        unset($_SESSION['flash_message']);
                        unset($_SESSION['flash_type']);
                        ?>
                    </div>
                    <script>
                        setTimeout(() => {
                            const flash = document.getElementById('flashMessage');
                            if (flash) {
                                flash.style.opacity = '0';
                                flash.style.transform = 'translateY(-10px)';
                                flash.style.transition = 'all 0.5s ease';
                                setTimeout(() => flash.remove(), 500);
                            }
                        }, 4000);
                    </script>
                <?php endif; ?>
