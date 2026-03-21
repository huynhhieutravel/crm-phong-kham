<?php
// includes/i18n.php — Comprehensive UI Internationalization System

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

/**
 * Load language dictionary
 * @param string $lang language code (vi, en, de, zh)
 * @return array
 */
function load_lang_dict($lang) {
    $file = __DIR__ . "/../lang/{$lang}.php";
    if (file_exists($file)) {
        return require $file;
    }
    return [];
}

/**
 * Main translation function `__()` with structured keys 
 * @param string $key e.g., 'patient.list.title'
 * @param array $params (Optional) dynamic variables to replace
 * @return string
 */
function __($key, $params = null) {
    static $dict = null;
    static $dict_fallback = null;
    static $loaded_lang = null;
    
    $default = $key;
    if (is_string($params)) {
        $default = $params;
        $params = [];
    }
    
    $lang = get_current_lang();
    
    // Load dicts if not loaded or language changed
    if ($dict === null || $loaded_lang !== $lang) {
        $dict = load_lang_dict($lang);
        if ($lang !== 'vi') {
            $dict_fallback = load_lang_dict('vi'); // Default fallback to VN
        } else {
            $dict_fallback = $dict; // Same
        }
        $loaded_lang = $lang;
    }
    
    // Resolve key
    $translated = $dict[$key] ?? $dict_fallback[$key] ?? $default;
    
    // Replace params like {name}
    if (!empty($params) && is_array($params)) {
        foreach ($params as $k => $v) {
            $translated = str_replace('{' . $k . '}', (string)$v, (string)$translated);
        }
    }
    
    return $translated;
}

// Backward compatibility for existing t() calls while we refactor
function t($key) {
    return __($key);
}
