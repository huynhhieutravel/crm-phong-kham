<?php
session_start();
$_SESSION['lang'] = 'en';

require 'includes/i18n.php';

echo "Kết quả: " . __('Kết quả thăm khám của bác sĩ.') . "\n";
echo "Khác: " . __('Khác:') . "\n";
echo "4.: " . __('4. ĐÁNH GIÁ & KẾ HOẠCH (ASSESSMENT & PLAN - A/P)') . "\n";
