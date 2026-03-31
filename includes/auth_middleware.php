<?php
// includes/auth_middleware.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/permissions.php';

// Session timeout: 30 ngày (cho phép nhân viên duy trì login lâu dài)
define('SESSION_TIMEOUT', 30 * 24 * 60 * 60); // 2592000 giây

if (is_logged_in()) {
    // Kiểm tra timeout
    if (isset($_SESSION['last_activity'])) {
        $elapsed = time() - $_SESSION['last_activity'];
        if ($elapsed > SESSION_TIMEOUT) {
            // Session hết hạn
            session_destroy();
            session_start();
            set_flash('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.', 'warning');
            redirect('/login.php');
        }
    }
    // Cập nhật thời gian hoạt động cuối
    $_SESSION['last_activity'] = time();
}

if (!is_logged_in() && basename($_SERVER['PHP_SELF']) !== 'login.php') {
    redirect('/login.php');
}

// --- CSRF Token ---
/**
 * Tạo CSRF token và lưu vào session
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Tạo hidden input chứa CSRF token cho form
 */
function csrf_field() {
    return '<input type="hidden" name="_csrf_token" value="' . csrf_token() . '">';
}

/**
 * Kiểm tra CSRF token từ POST request
 * Gọi hàm này ở đầu mỗi handler POST quan trọng
 */
function verify_csrf($redirect_back = null) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return true;
    
    $token = $_POST['_csrf_token'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        set_flash('Yêu cầu không hợp lệ. Vui lòng thử lại.', 'error');
        if ($redirect_back) {
            redirect($redirect_back);
        }
        redirect('/index.php');
    }
    
    // Tạo token mới sau khi verify thành công (one-time use)
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return true;
}
