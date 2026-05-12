<?php
require __DIR__ . '/../includes/db.php';
try {
    $db = getDB();
    
    $stmt = $db->query("
        SELECT ppay.*, p.full_name, pkg.name as package_name
        FROM package_payments ppay
        JOIN patient_packages pp ON ppay.patient_package_id = pp.id
        JOIN patients p ON pp.patient_id = p.id
        JOIN packages pkg ON pp.package_id = pkg.id
        WHERE p.full_name LIKE '%Than Cảng%' OR p.full_name LIKE '%Cảng%' OR p.name LIKE '%Than%'
        ORDER BY ppay.id DESC
    ");
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Duplicate Payments for Than Cảng:\n";
    print_r($payments);
    
} catch (Exception $e) {
    echo $e->getMessage();
}
