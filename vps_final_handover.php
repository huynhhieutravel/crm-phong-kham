<?php
/**
 * FINAL HANDOVER SCRIPT - SIMON CENTER CRM
 * This script will:
 * 1. Truncate all transaction/history tables to remove sample data.
 * 2. Clear the users table.
 * 3. Initialize 3 admin accounts with secure passwords.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/db.php';

try {
    $db = getDB();
    echo "<h1>Simon Center CRM - Final Cleanup & Handover</h1>";

    // 1. Tables to TRUNCATE
    $tables_to_clear = [
        'appointments',
        'audit_logs',
        'lead_logs',
        'leads',
        'medical_history',
        'medical_sessions',
        'package_usage_logs',
        'patient_packages',
        'patients',
        'timekeeping',
        'transactions',
        'treatments',
        'vouchers'
    ];

    echo "<h3>1. Clearing Transaction Tables...</h3>";
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
    foreach ($tables_to_clear as $table) {
        try {
            $db->exec("TRUNCATE TABLE `$table` ");
            echo "- Table `$table` cleared.<br>";
        } catch (Exception $e) {
            echo "- <span style='color:red'>Error clearing `$table`: " . $e->getMessage() . "</span><br>";
        }
    }

    // 2. Clear Users table
    echo "<h3>2. Clearing Users Table...</h3>";
    $db->exec("TRUNCATE TABLE `users` ");
    echo "- Table `users` cleared.<br>";
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // 3. Create Admin Users
    echo "<h3>3. Creating Admin Accounts...</h3>";
    
    $admins = [
        [
            'username' => 'admin.huy',
            'full_name' => 'Admin Huy',
            'password' => 'aHuy#Simon2026!',
            'role_id'  => 1 // Administrator
        ],
        [
            'username' => 'admin',
            'full_name' => 'System Admin',
            'password' => 'Admin#Simon2026!',
            'role_id'  => 1
        ],
        [
            'username' => 'admin.simon',
            'full_name' => 'Simon Admin',
            'password' => 'Simon#Center2026!',
            'role_id'  => 1
        ]
    ];

    $stmt = $db->prepare("INSERT INTO users (username, full_name, password, role_id, status) VALUES (?, ?, ?, ?, 'active')");

    foreach ($admins as $admin) {
        $hashed_password = password_hash($admin['password'], PASSWORD_DEFAULT);
        $stmt->execute([
            $admin['username'],
            $admin['full_name'],
            $hashed_password,
            $admin['role_id']
        ]);
        echo "- User `{$admin['username']}` created successfully.<br>";
    }

    echo "<h2 style='color:green'>SYSTEM READY FOR PRODUCTION!</h2>";
    echo "<p>Please delete this file IMMEDIATELY after execution.</p>";

} catch (Exception $e) {
    echo "<h2>FATAL ERROR: " . $e->getMessage() . "</h2>";
}
