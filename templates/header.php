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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

                <div class="header-right" style="display: flex; align-items: center; gap: 1rem;">
                    <!-- Language Switcher -->
                    <div class="lang-switcher" style="position: relative;">
                        <button onclick="this.parentElement.classList.toggle('open')" style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.1); padding: 0.4rem 0.8rem; border-radius: 10px; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; color: var(--text-main); font-size: 0.85rem; font-weight: 600; transition: all 0.2s;">
                            <?php 
                            $langs = get_available_langs();
                            $cur = get_current_lang();
                            echo $langs[$cur]['flag'] . ' ' . $langs[$cur]['name']; 
                            ?>
                            <i class="fas fa-chevron-down" style="font-size: 0.6rem; opacity: 0.5;"></i>
                        </button>
                        <div class="lang-dropdown" style="position: absolute; top: 110%; right: 0; background: white; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); overflow: hidden; min-width: 160px; z-index: 999; display: none;">
                            <?php 
                            $current_url = strtok($_SERVER['REQUEST_URI'], '?');
                            $params = $_GET;
                            foreach ($langs as $code => $info): 
                                $params['lang'] = $code;
                                $url = $current_url . '?' . http_build_query($params);
                                $is_active = ($code === $cur);
                            ?>
                            <a href="<?php echo $url; ?>" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.7rem 1rem; text-decoration: none; color: #1e293b; font-size: 0.85rem; font-weight: <?php echo $is_active ? '700' : '500'; ?>; transition: background 0.15s; <?php echo $is_active ? 'background: #eef2ff; color: #4f46e5;' : ''; ?>" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='<?php echo $is_active ? '#eef2ff' : ''; ?>'">
                                <span style="font-size: 1.2rem;"><?php echo $info['flag']; ?></span>
                                <?php echo $info['name']; ?>
                                <?php if ($is_active): ?><i class="fas fa-check" style="margin-left: auto; color: #4f46e5; font-size: 0.7rem;"></i><?php endif; ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

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

<script>
// Language Switcher Toggle
document.addEventListener('click', function(e) {
    const switcher = document.querySelector('.lang-switcher');
    if (!switcher) return;
    const dropdown = switcher.querySelector('.lang-dropdown');
    if (switcher.classList.contains('open') && !switcher.contains(e.target)) {
        switcher.classList.remove('open');
        dropdown.style.display = 'none';
    }
    if (switcher.contains(e.target) && e.target.closest('button')) {
        dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
    }
});
</script>
