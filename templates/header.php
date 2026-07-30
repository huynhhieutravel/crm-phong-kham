<?php
// templates/header.php
require_once __DIR__ . '/../includes/auth_middleware.php';

$current_page = isset($current_page) ? $current_page : 'dashboard';

// Dynamic base_url calculation if not set
if (!isset($base_url)) {
    $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME']));
    $project_root = str_replace('\\', '/', realpath(__DIR__ . '/../'));
    $rel_path = trim(str_replace($project_root, '', $script_dir), '/');
    $base_url = ($rel_path === '') ? './' : str_repeat('../', substr_count($rel_path, '/') + 1);
}
?>
<!DOCTYPE html>
<html lang="<?php echo get_current_lang(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?php echo csrf_token(); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : __('common.crm_title'); ?></title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="<?php echo $base_url; ?>assets/js/confirm-modal.js?v=1.1"></script>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        <main class="main-content">
            <header class="top-bar">
                <style>
                    .mobile-toggle-btn { display: none; background: none; border: none; font-size: 1.5rem; color: var(--text-main); cursor: pointer; padding: 0.5rem; }
                    @media (max-width: 1024px) {
                        .mobile-toggle-btn { display: block; }
                    }
                </style>
                <div class="header-left" style="display: flex; align-items: center; gap: 1rem;">
                    <button class="mobile-toggle-btn" onclick="toggleMobileSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05rem; margin-bottom: 0.2rem;">
                            <?php echo __('common.crm_title'); ?> / <?php echo e(ucfirst($current_page)); ?>
                        </div>
                        <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); line-height: 1.2;">
                            <?php echo isset($page_title) ? $page_title : __('menu.dashboard'); ?>
                        </h1>
                    </div>
                </div>

                <div class="header-right" style="display: flex; align-items: center; gap: 1rem;">
                    <!-- Tasks Notification -->
                    <div class="tasks-dropdown-wrapper" style="position: relative;">
                        <button onclick="toggleTasksDrawer()" style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.1); padding: 0.5rem; border-radius: 10px; cursor: pointer; color: var(--text-main); position: relative; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; transition: all 0.2s;">
                            <i class="fas fa-bell"></i>
                            <span id="taskCountBadge" style="position: absolute; top: -5px; right: -5px; background: #ef4444; color: white; font-size: 0.6rem; font-weight: 800; padding: 2px 5px; border-radius: 20px; display: none;">0</span>
                        </button>
                    </div>

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
                            <a href="<?php echo $url; ?>" class="lang-switch-link" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.7rem 1rem; text-decoration: none; color: #1e293b; font-size: 0.85rem; font-weight: <?php echo $is_active ? '700' : '500'; ?>; transition: background 0.15s; <?php echo $is_active ? 'background: #eef2ff; color: #4f46e5;' : ''; ?>" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='<?php echo $is_active ? '#eef2ff' : ''; ?>'">
                                <span style="font-size: 1.2rem;"><?php echo $info['flag']; ?></span>
                                <?php echo $info['name']; ?>
                                <?php if ($is_active): ?><i class="fas fa-check" style="margin-left: auto; color: #4f46e5; font-size: 0.7rem;"></i><?php endif; ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="user-profile-dropdown" style="position: relative;">
                        <button onclick="this.parentElement.classList.toggle('open')" style="background: rgba(255,255,255,0.05); padding: 0.5rem 1rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; gap: 1rem; cursor: pointer; color: var(--text-main); font-family: inherit; width: 100%;">
                            <div class="user-info d-none d-lg-block" style="text-align: right; margin-right: 0.75rem;">
                                <span class="user-name" style="display: block; font-weight: 700; color: var(--text-main); font-size: 0.9rem;"><?php echo e($_SESSION['full_name']); ?></span>
                                <span class="user-role" style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;"><?php echo isset($_SESSION['role']) ? $_SESSION['role'] : 'User'; ?></span>
                            </div>
                            <div class="avatar-wrapper" style="width: 36px; height: 36px; background: linear-gradient(135deg, var(--primary), #818cf8); color: white; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.9rem;">
                                <?php echo substr(isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'U', 0, 1); ?>
                            </div>
                        </button>
                        <div class="profile-menu" style="position: absolute; top: 110%; right: 0; background: white; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); overflow: hidden; min-width: 200px; z-index: 999; display: none;">
                            <a href="#" onclick="document.getElementById('leaveModal').style.display='flex'; this.closest('.user-profile-dropdown').classList.remove('open'); return false;" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1rem; text-decoration: none; color: #1e293b; font-size: 0.85rem; font-weight: 600; transition: background 0.15s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='white'">
                                <i class="fas fa-calendar-times" style="color: #ea580c; width: 16px; text-align: center;"></i> <?php echo __('menu.leave_request'); ?>
                            </a>
                            <a href="<?php echo $base_url; ?>modules/hr/change_password.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1rem; text-decoration: none; color: #1e293b; font-size: 0.85rem; font-weight: 600; transition: background 0.15s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='white'">
                                <i class="fas fa-key" style="color: #64748b; width: 16px; text-align: center;"></i> <?php echo __('menu.change_password'); ?>
                            </a>
                            <div style="height: 1px; background: #e2e8f0; margin: 0.25rem 0;"></div>
                            <a href="<?php echo $base_url; ?>logout.php?t=<?php echo time(); ?>" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1rem; text-decoration: none; color: #ef4444; font-size: 0.85rem; font-weight: 600; transition: background 0.15s;" onmouseover="this.style.background='#fef2f2'" onmouseout="this.style.background='white'">
                                <i class="fas fa-sign-out-alt" style="width: 16px; text-align: center;"></i> <?php echo __('menu.logout'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </header>
            <div class="content-body">
                <?php if (isset($_SESSION['flash_message'])): ?>
                    <div class="alert alert-<?php echo isset($_SESSION['flash_type']) ? $_SESSION['flash_type'] : 'success'; ?>" id="flashMessage">
                        <i class="fas <?php echo (isset($_SESSION['flash_type']) ? $_SESSION['flash_type'] : 'success') === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
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
// Language Switcher Auto-save logic
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.lang-switch-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            // Check if there is an active form that needs saving (like in session_view.php, chiro_history.php, etc.)
            let mainForm = null;
            // The forms we care about usually contain csrf_field and have method="POST"
            let forms = document.querySelectorAll('main form[method="POST"]');
            
            for (let i = 0; i < forms.length; i++) {
                let f = forms[i];
                // Ignore modal/utility forms or small components
                if (!f.closest('.modal') && f.id !== 'leave-form' && !f.classList.contains('delete-form') && !f.classList.contains('no-autosave')) {
                    mainForm = f;
                    break;
                }
            }

            if (mainForm) {
                e.preventDefault();
                
                // Pre-save synchronization for Quill js editors if present
                if (typeof assessmentEditor !== 'undefined' && document.getElementById('assessment-input')) {
                    document.getElementById('assessment-input').value = assessmentEditor.root.innerHTML;
                }
                if (typeof planEditor !== 'undefined' && document.getElementById('plan-input')) {
                    document.getElementById('plan-input').value = planEditor.root.innerHTML;
                }
                
                // Submit to the link's href to carry over language parameter
                mainForm.action = this.href;
                
                // Instead of traditional submit(), append a hidden input so the backend knows 
                // it was a background/language switch save if we ever want to differentiate.
                let langSwitchInput = document.createElement('input');
                langSwitchInput.type = 'hidden';
                langSwitchInput.name = 'lang_switch_autosave';
                langSwitchInput.value = '1';
                mainForm.appendChild(langSwitchInput);
                
                mainForm.submit();
            }
        });
    });
});

// Mobile Sidebar Submenu Toggle
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.nav-item-wrapper').forEach(wrapper => {
        const navItem = wrapper.querySelector('.nav-item');
        const submenu = wrapper.querySelector('.submenu');
        if (submenu && navItem) {
            if (!navItem.querySelector('.fa-chevron-down')) {
                const chevron = document.createElement('i');
                chevron.className = 'fas fa-chevron-down';
                chevron.style.marginLeft = 'auto';
                chevron.style.fontSize = '0.75rem';
                chevron.style.transition = 'transform 0.3s';
                navItem.appendChild(chevron);
            }
            navItem.addEventListener('click', function(e) {
                if (window.innerWidth <= 1024 || e.target.classList.contains('fa-chevron-down')) {
                    e.preventDefault();
                    const isOpen = wrapper.classList.contains('open');
                    document.querySelectorAll('.nav-item-wrapper').forEach(w => w.classList.remove('open'));
                    if (!isOpen) wrapper.classList.add('open');
                }
            });
        }
    });
});

// Language Switcher Toggle
document.addEventListener('click', function(e) {
    const switcher = document.querySelector('.lang-switcher');
    if (switcher) {
        const dropdown = switcher.querySelector('.lang-dropdown');
        if (switcher.classList.contains('open') && !switcher.contains(e.target)) {
            switcher.classList.remove('open');
            dropdown.style.display = 'none';
        }
        if (switcher.contains(e.target) && e.target.closest('button')) {
            dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
        }
    }

    const profileSwitcher = document.querySelector('.user-profile-dropdown');
    if (profileSwitcher) {
        const profileDropdown = profileSwitcher.querySelector('.profile-menu');
        if (profileSwitcher.classList.contains('open') && !profileSwitcher.contains(e.target)) {
            profileSwitcher.classList.remove('open');
            profileDropdown.style.display = 'none';
        }
        if (profileSwitcher.contains(e.target) && e.target.closest('button')) {
            profileDropdown.style.display = profileDropdown.style.display === 'none' ? 'block' : 'none';
        }
    }
});

function toggleMobileSidebar() {
    document.querySelector('.sidebar').classList.toggle('open');
    var overlay = document.getElementById('mobileSidebarOverlay');
    if (document.querySelector('.sidebar').classList.contains('open')) {
        overlay.style.display = 'block';
    } else {
        overlay.style.display = 'none';
    }
}
</script>

<!-- Mobile Sidebar Overlay -->
<div id="mobileSidebarOverlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 999; animation: fadeIn 0.2s;" onclick="toggleMobileSidebar()"></div>
