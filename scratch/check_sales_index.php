<?php
$content = file_get_contents('modules/sales/index.php');
if (preg_match('/SELECT.*FROM patient_packages/', $content, $matches)) {
    echo "Found query in sales index\n";
} else {
    echo "No query found in sales index\n";
}
// just grep lines with query
