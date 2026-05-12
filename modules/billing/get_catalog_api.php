<?php
// modules/billing/get_catalog_api.php — Returns services + products + packages + active patient packages

require_once '../../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$db = getDB();

try {
    $services = $db->query("SELECT id, name, price, category FROM services ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $products = $db->query("SELECT id, name, price, category FROM products WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $packages = $db->query("SELECT id, name, total_price as price, total_sessions FROM packages ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

    $active_packages = [];
    if (!empty($_GET['patient_id'])) {
        $patient_id = (int)$_GET['patient_id'];
        $stmt = $db->prepare("
            SELECT pp.id as patient_package_id, p.name as name_raw, p.id as package_id, 
                   pp.sessions_remaining as remaining_sessions,
                   owner.full_name as owner_name,
                   pp.patient_id as owner_id
            FROM patient_packages pp
            JOIN packages p ON pp.package_id = p.id
            JOIN patients owner ON pp.patient_id = owner.id
            WHERE pp.sessions_remaining > 0
              AND pp.status = 'active'
              AND (
                  pp.patient_id = ?
                  OR pp.id IN (SELECT patient_package_id FROM package_shared_users WHERE patient_id = ?)
              )
        ");
        $stmt->execute([$patient_id, $patient_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rows as $r) {
            if ($r['owner_id'] != $patient_id) {
                $r['name'] = $r['name_raw'] . ' (Gói của ' . $r['owner_name'] . ')';
            } else {
                $r['name'] = $r['name_raw'];
            }
            $active_packages[] = $r;
        }
    }

    echo json_encode([
        'success' => true,
        'services' => $services,
        'products' => $products,
        'packages' => $packages,
        'active_packages' => $active_packages
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
