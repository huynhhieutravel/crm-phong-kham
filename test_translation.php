<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$_SESSION['lang'] = 'en';
require 'includes/i18n.php';

echo "Testing Translation Output:\n";
echo "1. " . __('Tính chất:') . "\n";
echo "2. " . __('medical.history.symptom_duration_label', 'Thời gian triệu chứng') . "\n";
echo "3. " . __('Ngồi nhiều') . "\n";
