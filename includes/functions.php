<?php
// includes/functions.php

/**
 * Escape HTML for output
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Translation helper (to be implemented)
 */
function __($key, $default = '') {
    // Basic placeholder for now
    return $key;
}

/**
 * Format currency
 */
function format_money($amount) {
    return number_format($amount, 0, ',', '.') . '₫';
}

/**
 * Redirect helper
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Set flash message
 */
function set_flash($message, $type = 'success') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}
