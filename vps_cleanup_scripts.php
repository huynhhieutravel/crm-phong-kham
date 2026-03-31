<?php
$files_to_delete = [
    'vps_final_handover.php',
    'migrate_vps.php',
    'vps_health_check.php',
    'debug_db.php',
    'test_php.php',
    'vps_cleanup_scripts.php' // Self destruct
];

foreach ($files_to_delete as $file) {
    if (file_exists($file)) {
        unlink($file);
        echo "Deleted: $file<br>";
    }
}
echo "Cleanup finished.";
