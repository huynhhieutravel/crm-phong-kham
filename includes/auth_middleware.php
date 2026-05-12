<?php
// includes/auth_middleware.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/permissions.php';

// Prevent browser and Cloudflare/Proxy caching for all authenticated routes
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Session timeout: 30 ngày (cho phép nhân viên duy trì login lâu dài)
define('SESSION_TIMEOUT', 30 * 24 * 60 * 60); // 2592000 giây

if (is_logged_in()) {
    // Kiểm tra timeout
    if (isset($_SESSION['last_activity'])) {
        $elapsed = time() - $_SESSION['last_activity'];
        if ($elapsed > SESSION_TIMEOUT) {
            // Session hết hạn
            session_destroy();

            set_flash('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.', 'warning');
            redirect('/login.php');
        }
    }

    // Kiểm tra trạng thái tài khoản active từ Database (để kick active sessions của tài khoản vừa bị khóa)
    $db = getDB();
    $stmt = $db->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_status = $stmt->fetchColumn();

    if ($current_status !== 'active') {
        session_destroy();
        set_flash('Tài khoản của bạn đã bị ngưng hoạt động. Vui lòng liên hệ Quản trị viên.', 'error');
        redirect('/login.php');
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
        $sess_tok = $_SESSION['csrf_token'] ?? 'UNSET';
        set_flash("Yêu cầu không hợp lệ. Vui lòng thử lại. POST: $token | SESS: $sess_tok", 'error');
        if ($redirect_back) {
            redirect($redirect_back);
        }
        redirect('/index.php');
    }
    
    return true;
}

/**
 * Kiểm tra CSRF token cho AJAX/JSON API endpoints
 * Trả về JSON error thay vì redirect
 */
function verify_csrf_json() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return true;
    
    $token = $_POST['_csrf_token'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.']);
        exit;
    }
    
    return true;
}

