<?php
require 'includes/db.php';
$db = getDB();
$stmt = $db->query("SELECT id, history_data FROM medical_history WHERE patient_id = 90 AND type = 'chiropractic' ORDER BY id DESC LIMIT 5");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($rows);
// test migrate
function migrate_to_v2($input_data) {
    if (!empty($input_data) && (isset($input_data['spine']) || isset($input_data['s']) || isset($input_data['o']) || isset($input_data['a']))) {
        return ['migrated' => true, 'data' => '...'];
    }
    return $input_data;
}

if (!empty($rows)) {
    echo "\n\nMIGRATED 1:\n";
    print_r(migrate_to_v2(json_decode($rows[0]['history_data'], true)));
}
