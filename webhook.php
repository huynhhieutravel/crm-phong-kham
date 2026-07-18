<?php
/**
 * Meta Messenger Webhook Endpoint
 * - GET: Xác minh webhook (Meta gọi 1 lần khi đăng ký)
 * - POST: Nhận event từ Meta (tin nhắn, postback...)
 */

// Verify Token - phải khớp với giá trị điền trên Meta Developer Portal
define('VERIFY_TOKEN', 'SIMON_CENTER_2026_SECURE');

// === GET: Xác minh Webhook ===
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';

    if ($mode === 'subscribe' && $token === VERIFY_TOKEN) {
        // Meta gửi challenge → trả về nguyên challenge để xác nhận
        http_response_code(200);
        echo $challenge;
        exit;
    } else {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

// === POST: Nhận Event từ Meta ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    
    // Log ra file để debug
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/webhook_' . date('Y-m-d') . '.log';
    file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $input . "\n", FILE_APPEND);
    
    // Trả về 200 ngay lập tức (Meta yêu cầu phản hồi trong 20 giây)
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit;
}

// Fallback
http_response_code(405);
echo 'Method Not Allowed';
