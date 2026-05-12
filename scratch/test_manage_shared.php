<?php
chdir('modules/sales');
$_GET['id'] = 12;
$_SESSION['user_id'] = 1;
$_SESSION['role_id'] = 1;
require 'manage_shared.php';
