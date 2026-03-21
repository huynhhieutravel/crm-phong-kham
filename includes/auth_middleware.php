<?php
// includes/auth_middleware.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/permissions.php';

if (!is_logged_in() && basename($_SERVER['PHP_SELF']) !== 'login.php') {
    redirect('/login.php');
}
