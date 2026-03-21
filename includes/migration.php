<?php
/**
 * Database Migration Script
 * Tự kiểm tra và bổ sung bảng/cột thiếu mỗi lần deploy.
 * Gọi hàm run_migrations($db) từ bất kỳ đâu (ví dụ: index.php hoặc chạy riêng).
 */

function run_migrations($db = null) {
    if (!$db) {
        require_once __DIR__ . '/db.php';
        $db = getDB();
    }

    $log = [];

    // =============================================
    // 1. Tạo bảng nếu chưa tồn tại
    // =============================================

    $tables = [
        'medical_sessions' => "
            CREATE TABLE IF NOT EXISTS medical_sessions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                patient_id INT NOT NULL,
                doctor_id INT NOT NULL,
                appointment_id INT NULL,
                session_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                status ENUM('active', 'completed') DEFAULT 'active',
                notes TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
                FOREIGN KEY (doctor_id) REFERENCES users(id)
            ) ENGINE=InnoDB
        ",
        'reexam_rules' => "
            CREATE TABLE IF NOT EXISTS reexam_rules (
                id INT AUTO_INCREMENT PRIMARY KEY,
                patient_id INT NOT NULL,
                service_name VARCHAR(100),
                frequency INT COMMENT 'Số ngày giữa mỗi lần tái khám',
                next_due_at DATE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
            ) ENGINE=InnoDB
        ",
        'audit_logs' => "
            CREATE TABLE IF NOT EXISTS audit_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                action VARCHAR(50) NOT NULL,
                target_table VARCHAR(50),
                target_id INT,
                old_data JSON,
                new_data JSON,
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            ) ENGINE=InnoDB
        ",
        'permissions' => "
            CREATE TABLE IF NOT EXISTS permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                permission_key VARCHAR(50) NOT NULL UNIQUE,
                description VARCHAR(200),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB
        ",
        'role_permissions' => "
            CREATE TABLE IF NOT EXISTS role_permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                role_id INT NOT NULL,
                permission_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_role_perm (role_id, permission_id),
                FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
                FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB
        "
    ];

    foreach ($tables as $name => $sql) {
        try {
            $check = $db->query("SHOW TABLES LIKE '$name'")->rowCount();
            if ($check == 0) {
                $db->exec($sql);
                $log[] = "✅ Tạo bảng '$name'";
            }
        } catch (Exception $e) {
            $log[] = "❌ Lỗi tạo bảng '$name': " . $e->getMessage();
        }
    }

    // =============================================
    // 2. Thêm cột thiếu vào bảng hiện có
    // =============================================

    $columns = [
        // [table, column, definition]
        ['medical_history', 'session_id', 'INT NULL'],
        ['medical_history', 'created_by', 'INT NULL'],
        ['appointments', 'type', "VARCHAR(50) DEFAULT 'regular'"],
        ['appointments', 'reexam_rule_id', 'INT NULL'],
        ['patients', 'consultant_id', 'INT NULL'],
        ['patients', 'label', "VARCHAR(50) DEFAULT 'Khách mới'"],
        ['patients', 'customer_id', 'VARCHAR(50) NULL'],
        ['patients', 'branch', 'VARCHAR(50) NULL'],
        ['patients', 'occupation', 'VARCHAR(100) NULL'],
        ['patients', 'district', 'VARCHAR(100) NULL'],
        ['patients', 'city', 'VARCHAR(100) NULL'],
        ['patients', 'medical_history', 'TEXT NULL'],
        ['patients', 'guardian_name', 'VARCHAR(100) NULL'],
        ['patients', 'guardian_phone', 'VARCHAR(20) NULL'],
        ['patients', 'guardian_id_card', 'VARCHAR(20) NULL'],
        ['patients', 'guardian_relationship', 'VARCHAR(50) NULL'],
    ];

    foreach ($columns as [$table, $column, $definition]) {
        try {
            $check = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'")->rowCount();
            if ($check == 0) {
                $db->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
                $log[] = "✅ Thêm cột '$column' vào '$table'";
            }
        } catch (Exception $e) {
            $log[] = "❌ Lỗi thêm cột '$column' vào '$table': " . $e->getMessage();
        }
    }

    // =============================================
    // 3. Sửa cột type medical_history nếu còn ENUM
    // =============================================
    try {
        $result = $db->query("SHOW COLUMNS FROM medical_history LIKE 'type'")->fetch(PDO::FETCH_ASSOC);
        if ($result && strpos(strtolower($result['Type']), 'enum') !== false) {
            $db->exec("ALTER TABLE medical_history MODIFY COLUMN type VARCHAR(50) NOT NULL");
            $log[] = "✅ Chuyển cột 'type' medical_history từ ENUM sang VARCHAR(50)";
        }
    } catch (Exception $e) {
        $log[] = "❌ Lỗi sửa cột 'type': " . $e->getMessage();
    }

    return $log;
}

// Cho phép chạy trực tiếp từ trình duyệt: /includes/migration.php
if (php_sapi_name() !== 'cli' && basename($_SERVER['SCRIPT_FILENAME']) === 'migration.php') {
    session_start();
    require_once __DIR__ . '/db.php';
    $db = getDB();
    $results = run_migrations($db);
    
    echo "<h2>🔧 Database Migration Results</h2><pre>";
    if (empty($results)) {
        echo "✅ Database đã cập nhật đầy đủ, không cần thay đổi gì.";
    } else {
        foreach ($results as $r) echo $r . "\n";
    }
    echo "</pre>";
}
