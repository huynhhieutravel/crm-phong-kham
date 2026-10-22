<?php
// scripts/comprehensive_qa.php - Comprehensive QA Audit Script for Simon Center CRM
ob_start();

$root_dir = is_dir('/var/www/crm_phong_kham') ? '/var/www/crm_phong_kham' : dirname(__DIR__);

echo "========================================================\n";
echo "   SIMON CENTER CRM - COMPREHENSIVE QA AUDIT REPORT    \n";
echo "========================================================\n";
echo "Timestamp: " . date('Y-m-d H:i:s T') . "\n";
echo "PHP Version: " . PHP_VERSION . " (" . PHP_SAPI . ")\n";
echo "OS: " . PHP_OS . " (" . php_uname() . ")\n\n";

$errors = [];
$warnings = [];
$passed = 0;

function report($section, $status, $detail = '') {
    global $errors, $warnings, $passed;
    if ($status === 'PASS') {
        $passed++;
        echo "  [PASS] $section" . ($detail ? ": $detail" : "") . "\n";
    } elseif ($status === 'WARN') {
        $warnings[] = "$section: $detail";
        echo "  [WARN] $section" . ($detail ? ": $detail" : "") . "\n";
    } else {
        $errors[] = "$section: $detail";
        echo "  [FAIL] $section" . ($detail ? ": $detail" : "") . "\n";
    }
}

// 1. EXTENSIONS AUDIT
echo "--- 1. PHP REQUIRED EXTENSIONS ---\n";
$required_exts = [
    'pdo' => 'pdo',
    'pdo_mysql' => 'pdo_mysql',
    'mbstring' => 'mbstring',
    'curl' => 'curl',
    'gd' => 'gd',
    'json' => 'json',
    'openssl' => 'openssl',
    'session' => 'session',
    'xml' => 'xml',
    'zip' => 'zip',
    'opcache' => 'Zend OPcache',
    'readline' => 'readline',
    'bcmath' => 'bcmath'
];
foreach ($required_exts as $label => $ext) {
    if (extension_loaded($ext) || extension_loaded($label)) {
        report("Extension: $label", "PASS", "loaded");
    } else {
        report("Extension: $label", "FAIL", "NOT loaded");
    }
}

// 2. PHP CONFIGURATION & OPCACHE
echo "\n--- 2. PHP INI CONFIGURATION ---\n";
$ini_checks = [
    'memory_limit' => ini_get('memory_limit'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'max_execution_time' => ini_get('max_execution_time'),
    'display_errors' => ini_get('display_errors'),
    'error_reporting' => ini_get('error_reporting'),
    'date.timezone' => ini_get('date.timezone') ?: 'Default UTC',
    'opcache.enable' => ini_get('opcache.enable') ? 'Enabled' : 'Disabled',
];
foreach ($ini_checks as $k => $v) {
    echo "  - $k: $v\n";
}
report("Memory Limit >= 128M", (int)ini_get('memory_limit') >= 128 || ini_get('memory_limit') == '-1' ? 'PASS' : 'WARN', ini_get('memory_limit'));

// 3. DATABASE CONNECTIVITY & TIMEZONE
echo "\n--- 3. DATABASE AUDIT (MariaDB/MySQL) ---\n";
try {
    require_once $root_dir . '/includes/db.php';
    $db = getDB();
    report("Database Connection", "PASS", "Connected to MariaDB via PDO");
    
    // Check timezone
    $tz = $db->query("SELECT @@session.time_zone as tz, NOW() as db_time")->fetch();
    report("DB Timezone +07:00", $tz['tz'] === '+07:00' ? 'PASS' : 'WARN', "tz: {$tz['tz']}, time: {$tz['db_time']}");

    // Core tables count
    $core_tables = [
        'users',
        'patients',
        'appointments',
        'invoices',
        'treatments',
        'medical_sessions',
        'prescriptions',
        'inventory',
        'medicines',
        'payment_methods',
        'roles',
        'branches'
    ];
    foreach ($core_tables as $table) {
        try {
            $count = $db->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
            report("Table: $table", "PASS", "$count rows");
        } catch (Exception $e) {
            report("Table: $table", "WARN", "Table error or not present: " . $e->getMessage());
        }
    }
} catch (Exception $e) {
    report("Database Connection", "FAIL", $e->getMessage());
}

// 4. SESSION & FILESYSTEM PERMISSIONS
echo "\n--- 4. FILESYSTEM & PERMISSIONS ---\n";
$session_dir = $root_dir . '/sessions';
if (is_dir($session_dir) && is_writable($session_dir)) {
    report("Session Directory Writable", "PASS", $session_dir);
} else {
    report("Session Directory Writable", "FAIL", "Cannot write to $session_dir");
}

$uploads_dir = $root_dir . '/uploads';
if (is_dir($uploads_dir) && is_writable($uploads_dir)) {
    report("Uploads Directory Writable", "PASS", $uploads_dir);
} else {
    report("Uploads Directory Writable", is_dir($uploads_dir) ? "WARN" : "INFO", is_dir($uploads_dir) ? "Not writable" : "Not present");
}

// 5. CRYPTO & VIETNAMESE MULTIBYTE TEST
echo "\n--- 5. CRYPTOGRAPHY & MULTIBYTE UTF-8 ---\n";
$test_pass = "TestPassword123!@#";
$hash = password_hash($test_pass, PASSWORD_BCRYPT);
if (password_verify($test_pass, $hash)) {
    report("Bcrypt Password Hashing & Verification", "PASS");
} else {
    report("Bcrypt Password Hashing & Verification", "FAIL");
}

$vietnamese_text = "Phòng Khám Simon Center - Khám & Điều Trị Đau Nhức Xương Khớp - Tiếng Việt Có Dấu: ă â đ ê ô ơ ư";
$encoded = json_encode(['text' => $vietnamese_text], JSON_UNESCAPED_UNICODE);
$decoded = json_decode($encoded, true);
if ($decoded['text'] === $vietnamese_text) {
    report("UTF-8 Multibyte JSON Encode/Decode", "PASS");
} else {
    report("UTF-8 Multibyte JSON Encode/Decode", "FAIL");
}

// 6. GD IMAGE GENERATION TEST
echo "\n--- 6. GD LIBRARY GENERATION ---\n";
if (function_exists('imagecreatetruecolor')) {
    $im = @imagecreatetruecolor(50, 50);
    if ($im) {
        imagedestroy($im);
        report("GD Image Creation", "PASS");
    } else {
        report("GD Image Creation", "WARN", "Cannot create truecolor image");
    }
} else {
    report("GD Image Creation", "FAIL", "imagecreatetruecolor function does not exist");
}

// 7. SUMMARY
echo "\n========================================================\n";
echo "SUMMARY: Passed: $passed | Warnings: " . count($warnings) . " | Errors: " . count($errors) . "\n";
echo "========================================================\n";
if (!empty($errors)) {
    echo "CRITICAL ISSUES DETECTED:\n";
    foreach ($errors as $err) echo "  - $err\n";
} else {
    echo "ALL CHECKS PASSED PERFECTLY (100% OPERATIONAL)\n";
}
