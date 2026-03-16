<?php
// includes/i18n.php

function get_current_lang() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    if (isset($_GET['lang'])) {
        $lang = $_GET['lang'];
        $allowed = ['vi', 'de', 'en'];
        if (in_array($lang, $allowed)) {
            $_SESSION['lang'] = $lang;
        }
    }
    
    return $_SESSION['lang'] ?? 'vi';
}

function set_lang($lang) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $allowed = ['vi', 'de', 'en'];
    if (in_array($lang, $allowed)) {
        $_SESSION['lang'] = $lang;
    }
}

function t($key) {
    static $translations = null;
    $lang = get_current_lang();
    
    if ($translations === null) {
        $translations = require __DIR__ . "/../languages/{$lang}.php";
    }
    
    return $translations[$key] ?? $key;
}
