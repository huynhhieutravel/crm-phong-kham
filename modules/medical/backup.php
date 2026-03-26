<?php
// modules/medical/backup.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_medical');

error_reporting(0);
ini_set('display_errors', 0);


$db = getDB();

// Tables to backup for Medical Records
$tables = ['patients', 'medical_history', 'medical_sessions', 'treatments', 'appointments'];

$backup_sql = "-- Medical Records Backup\n";
$backup_sql .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n\n";

foreach ($tables as $table) {
    $backup_sql .= "-- Table: $table\n";
    $backup_sql .= "DROP TABLE IF EXISTS `$table`;\n";
    
    // Get create table
    $stmt = $db->query("SHOW CREATE TABLE `$table` ");
    $create_row = $stmt->fetch(PDO::FETCH_ASSOC);
    $backup_sql .= $create_row['Create Table'] . ";\n\n";
    
    // Get data
    $stmt = $db->query("SELECT * FROM `$table` ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($rows)) {
        $backup_sql .= "INSERT INTO `$table` VALUES ";
        $insert_rows = [];
        foreach ($rows as $row) {
            $values = array_map(function($v) use ($db) {
                if ($v === null) return 'NULL';
                return $db->quote($v);
            }, $row);
            $insert_rows[] = "(" . implode(', ', $values) . ")";
        }
        $backup_sql .= implode(",\n", $insert_rows) . ";\n\n";
    }
}

// Set headers for download
header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename=medical_records_backup_' . date('Ymd_His') . '.sql');

echo $backup_sql;
exit();
