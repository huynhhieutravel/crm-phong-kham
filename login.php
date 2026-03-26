<?php
// login.php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/i18n.php'; // Add i18n support for login page

if (is_logged_in()) {
    redirect('/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (login($username, $password)) {
        redirect('/index.php');
    } else {
        $error = $auth_login_failed_trans ?? 'Tên đăng nhập hoặc mật khẩu không đúng.';
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo get_current_lang(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('auth.login_title'); ?> | Simon Center</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            position: relative;
        }
        .lang-switcher-login {
            position: absolute;
            top: 2rem;
            right: 2rem;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            display: flex;
            gap: 1rem;
        }
        .lang-switcher-login a {
            color: white;
            text-decoration: none;
            font-size: 1.2rem;
            opacity: 0.5;
            transition: opacity 0.2s;
        }
        .lang-switcher-login a.active, .lang-switcher-login a:hover {
            opacity: 1;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            padding: 2.5rem;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header i {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--text-muted);
        }
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background: white;
            font-size: 1rem;
            transition: all 0.2s;
        }
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        .error-message {
            background: #fee2e2;
            color: #ef4444;
            padding: 0.75rem;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="lang-switcher-login">
        <?php foreach(get_available_langs() as $code => $info): ?>
            <a href="?lang=<?php echo $code; ?>" class="<?php echo get_current_lang() === $code ? 'active' : ''; ?>" title="<?php echo $info['name']; ?>">
                <?php echo $info['flag']; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="login-card">
        <div class="login-header">
            <i class="fas fa-hand-holding-medical"></i>
            <h2>Simon Center</h2>
            <p style="color: var(--text-muted); margin-top: 0.5rem;"><?php echo __('auth.login_title'); ?></p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label"><?php echo __('auth.username'); ?></label>
                <input type="text" name="username" class="form-input" required placeholder="admin">
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo __('auth.password'); ?></label>
                <input type="password" name="password" class="form-input" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 0.8rem;">
                <?php echo __('auth.login_btn'); ?>
            </button>
        </form>
    </div>
</body>
</html>
