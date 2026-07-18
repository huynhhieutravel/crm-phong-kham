<?php
if (version_compare(PHP_VERSION, '7.0.0') >= 0) {
    echo "PHP Version: " . PHP_VERSION . " - [OK] Supports ?? operator";
    // Test the operator directly to be 100% sure
    $test = null ?? 'Success';
    echo "\nNull coalescing test: $test";
} else {
    echo "PHP Version: " . PHP_VERSION . " - [FAIL] Does NOT support ?? operator. Site will crash if restored.";
}
