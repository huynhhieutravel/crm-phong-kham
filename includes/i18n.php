<?php
// includes/i18n.php — Internationalization System

$GLOBALS['_lang_config'] = [
    'vi' => ['flag' => '🇻🇳', 'name' => 'Tiếng Việt'],
    'en' => ['flag' => '🇬🇧', 'name' => 'English'],
    'de' => ['flag' => '🇩🇪', 'name' => 'Deutsch'],
    'zh' => ['flag' => '🇨🇳', 'name' => '中文'],
];

function get_available_langs() {
    return $GLOBALS['_lang_config'];
}

function get_current_lang() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    if (isset($_GET['lang'])) {
        $lang = $_GET['lang'];
        $allowed = array_keys($GLOBALS['_lang_config']);
        if (in_array($lang, $allowed)) {
            $_SESSION['lang'] = $lang;
        }
    }
    
    return $_SESSION['lang'] ?? 'vi';
}

function set_lang($lang) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $allowed = array_keys($GLOBALS['_lang_config']);
    if (in_array($lang, $allowed)) {
        $_SESSION['lang'] = $lang;
    }
}

function t($key) {
    static $translations = null;
    static $loaded_lang = null;
    $lang = get_current_lang();
    
    // Reload if language changed
    if ($translations === null || $loaded_lang !== $lang) {
        $file = __DIR__ . "/../languages/{$lang}.php";
        $translations = file_exists($file) ? require $file : [];
        $loaded_lang = $lang;
    }
    
    return $translations[$key] ?? $key;
}
