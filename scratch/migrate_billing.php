<?php
// Try common local MySQL credentials
header('Content-Type: text/plain; charset=utf-8');

$creds = [
    ['root', ''],
    ['root', 'root'],
    ['root', 'password'],
    ['crm_admin', 'CrmAdmin2026@Pass'],
];

$db = null;
foreach ($creds as [$user, $pass]) {
    try {
        $db = new PDO("mysql:host=127.0.0.1;dbname=clinic_management;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        echo "CONNECTED with user: $user\n\n";
        break;
    } catch (Exception $e) {
        echo "FAILED $user: " . $e->getMessage() . "\n";
    }
}

if (!$db) { echo "\n\nCould not connect!!"; exit; }

// Create tables
try {
    $db->query("SELECT 1 FROM invoices LIMIT 1");
    echo "[ALREADY EXISTS] invoices\n";
} catch (Exception $e) {
    $db->exec("CREATE TABLE invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_no VARCHAR(30) NOT NULL UNIQUE,
        patient_id INT NOT NULL,
        treatment_id INT NULL,
        patient_package_id INT NULL,
        payment_id INT NULL,
        items JSON NOT NULL,
        subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
        discount_amount DECIMAL(12,2) DEFAULT 0,
        discount_note VARCHAR(255) NULL,
        total_amount DECIMAL(12,2) NOT NULL,
        cash_amount DECIMAL(12,2) DEFAULT 0,
        transfer_amount DECIMAL(12,2) DEFAULT 0,
        package_deduct DECIMAL(12,2) DEFAULT 0,
        debt_amount DECIMAL(12,2) DEFAULT 0,
        voucher_id INT NULL,
        status ENUM('paid','partial','debt') DEFAULT 'paid',
        note TEXT NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id),
        FOREIGN KEY (created_by) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "[CREATED] invoices\n";
}

try {
    $db->query("SELECT 1 FROM products LIMIT 1");
    echo "[ALREADY EXISTS] products\n";
} catch (Exception $e) {
    $db->exec("CREATE TABLE products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        price DECIMAL(12,2) NOT NULL DEFAULT 0,
        category VARCHAR(50) DEFAULT 'general',
        status ENUM('active','inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "[CREATED] products\n";
}

try {
    $db->query("SELECT 1 FROM leave_requests LIMIT 1");
    echo "[ALREADY EXISTS] leave_requests\n";
} catch (Exception $e) {
    try {
        $db->exec("CREATE TABLE leave_requests (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `leave_type` varchar(50) NOT NULL,
          `start_date` date NOT NULL,
          `end_date` date NOT NULL,
          `reason` text DEFAULT NULL,
          `status` enum('pending','approved','rejected') DEFAULT 'pending',
          `manager_id` int(11) DEFAULT NULL,
          `manager_note` text DEFAULT NULL,
          `created_at` timestamp DEFAULT current_timestamp(),
          `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`),
          FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        echo "[CREATED] leave_requests\n";
    } catch (Exception $ce) {
        echo "[FAIL] leave_requests: " . $ce->getMessage() . "\n";
    }
}

try {
    $db->exec("ALTER TABLE users ADD COLUMN salary_per_patient DECIMAL(10,2) DEFAULT 0.00");
    echo "[ADDED COLUMN] salary_per_patient to users\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "[ALREADY EXISTS] salary_per_patient in users\n";
    } else {
        echo "[FAIL] Add salary_per_patient: " . $e->getMessage() . "\n";
    }
}

echo "\nALL DONE!";
