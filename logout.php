<?php
// logout.php
require_once 'includes/db.php';

// Xoá toàn bộ biến trong RAM
$_SESSION = array();

// Chống cache từ Cloudflare / Trình duyệt
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Ép PHP ghi file session rỗng ngay lập tức xuống ổ cứng (để chống lỗi permission khi huỷ session_destroy không xoá được file)
session_write_close();

// Xoá cookie an toàn
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, 
        $params["path"], $params["domain"], 
        $params["secure"], $params["httponly"]
    );
}

// Chuyển hướng
header("Location: /login.php");
exit;
