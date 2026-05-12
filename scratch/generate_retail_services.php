<?php
require_once __DIR__ . '/../includes/db.php';
$db = getDB();

$packages = $db->query("SELECT * FROM packages")->fetchAll(PDO::FETCH_ASSOC);

foreach ($packages as $pkg) {
    if ($pkg['total_sessions'] > 0) {
        $retail_name = $pkg['name'] . ' (Mua lẻ 1 buổi)';
        $retail_price = round($pkg['total_price'] / $pkg['total_sessions']);
        
        // Check if already exists
        $stmt = $db->prepare("SELECT id FROM services WHERE name = ?");
        $stmt->execute([$retail_name]);
        if (!$stmt->fetch()) {
            $insert = $db->prepare("INSERT INTO services (name, price, category) VALUES (?, ?, 'other')");
            $insert->execute([$retail_name, $retail_price]);
            echo "Added: $retail_name - $retail_price VND<br>";
        }
    }
}
echo "Done.";
