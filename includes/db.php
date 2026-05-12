<?php
// includes/db.php

// Cấu hình Session 30 ngày và khởi tạo thư mục Session riêng
if (session_status() === PHP_SESSION_NONE) {
    $session_dir = __DIR__ . '/../sessions';
    if (!is_dir($session_dir)) {
        @mkdir($session_dir, 0700, true);
    }
    if (is_dir($session_dir)) {
        ini_set('session.save_path', $session_dir);
    }
    ini_set('session.gc_maxlifetime', 2592000);
    
    // Tách riêng Cookie cho dự án này, tránh xung đột PHPSESSID với dự án khác trên localhost/VPS
    session_name('CLINIC_SESSID');
    
    // Set cookie path rõ ràng
    if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
        session_set_cookie_params([
            'lifetime' => 2592000,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else {
        session_set_cookie_params(2592000, '/', '', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', true);
    }
    
    session_start();
}

function getDB() {
    static $db = null;
    if ($db === null) {
        $config = require __DIR__ . '/../config/database.php';
        $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
        // Hỗ trợ PHP 8.5+ cho MYSQL_ATTR_INIT_COMMAND
        $mysql_init_attr = defined('Pdo\Mysql::ATTR_INIT_COMMAND') ? \Pdo\Mysql::ATTR_INIT_COMMAND : \PDO::MYSQL_ATTR_INIT_COMMAND;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            $mysql_init_attr             => "SET time_zone = '+07:00'",
        ];
        try {
            $db = new PDO($dsn, $config['username'], $config['password'], $options);
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }
    }
    return $db;
}
