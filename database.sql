-- Database Schema for Clinic Management System (Chiropractic & Đông Y)
-- Version 1.0

CREATE DATABASE IF NOT EXISTS clinic_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE clinic_management;

-- =============================================
-- CORE TABLES
-- =============================================

-- Roles table
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(20) NOT NULL UNIQUE,
    display_name VARCHAR(50) NOT NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO roles (name, display_name) VALUES 
('admin', 'Administrator'),
('doctor', 'Bác sĩ'),
('technician', 'Kỹ thuật viên'),
('receptionist', 'Lễ tân'),
('accountant', 'Kế toán');

-- Branches table
CREATE TABLE IF NOT EXISTS branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    address TEXT,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO branches (name, address, phone) VALUES ('Phòng khám Chính', 'Địa chỉ phòng khám', '0123456789');

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE,
    phone VARCHAR(20),
    role_id INT NOT NULL,
    branch_id INT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Default Admin User (Password: admin123)
INSERT IGNORE INTO users (username, password, full_name, role_id, branch_id)
VALUES ('admin', '$2y$10$VSzHZ6wqvdKfr9syOmp38OdQro34ZDqO7zRuSPSYhwANY0XlArsCu', 'System Admin', 1, 1);

-- =============================================
-- CLINIC TABLES
-- =============================================

-- Patients table
CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    gender ENUM('male', 'female', 'other') NULL,
    birthday DATE NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    address TEXT,
    zalo_number VARCHAR(20),
    source VARCHAR(50) COMMENT 'Nguồn: Facebook, Zalo, Giới thiệu...',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (phone)
) ENGINE=InnoDB;

-- Leads table (Marketing)
CREATE TABLE IF NOT EXISTS leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    gender ENUM('male', 'female', 'other') NULL,
    birthday DATE NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    address TEXT,
    zalo_number VARCHAR(20),
    source VARCHAR(50) COMMENT 'Nguồn: Facebook, Zalo, TikTok...',
    medical_group VARCHAR(100) COMMENT 'Nhóm bệnh: Cột sống, Xương khớp...',
    consultant_id INT NULL,
    notes TEXT,
    status ENUM('new', 'contacted', 'scheduled', 'converted', 'cancelled') DEFAULT 'new',
    consultation_status VARCHAR(50) DEFAULT NULL,
    contact_time DATETIME DEFAULT NULL,
    recontact_time DATETIME DEFAULT NULL,
    appointment_booking_time DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (phone),
    FOREIGN KEY (consultant_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Medical Records (Tiền sử bệnh)
-- Dùng JSON để lưu trữ các câu trả lời checkbox để linh hoạt và tối ưu cho mobile
CREATE TABLE IF NOT EXISTS medical_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    type ENUM('chiropractic', 'dong_y') NOT NULL,
    history_data JSON NOT NULL COMMENT 'Dữ liệu các câu hỏi tiền sử dạng checkbox/text',
    attachments JSON NULL COMMENT 'Danh sách file hình ảnh X-quang, body scan...',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Treatment Sessions (Nhật ký điều trị)
CREATE TABLE IF NOT EXISTS treatments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    technician_id INT NOT NULL COMMENT 'KTV thực hiện',
    service_id INT NULL,
    package_id INT NULL COMMENT 'Nếu dùng gói thì link tới đây',
    session_data TEXT COMMENT 'Ghi chú chi tiết buổi điều trị',
    photo_before VARCHAR(255) NULL,
    photo_after VARCHAR(255) NULL,
    treatment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (technician_id) REFERENCES users(id),
    INDEX (treatment_date)
) ENGINE=InnoDB;

-- Appointments table
CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NULL,
    lead_id INT NULL,
    doctor_id INT NULL,
    branch_id INT NOT NULL,
    appointment_date DATETIME NOT NULL,
    status ENUM('scheduled', 'confirmed', 'arrived', 'no_show', 'cancelled', 'completed') DEFAULT 'scheduled',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL,
    FOREIGN KEY (doctor_id) REFERENCES users(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB;

-- =============================================
-- SALES & PACKAGES
-- =============================================

-- Services table
CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    price DECIMAL(12,2) NOT NULL,
    category ENUM('chiropractic', 'dong_y', 'other') NOT NULL,
    description TEXT
) ENGINE=InnoDB;

-- Packages table (Gói dịch vụ)
CREATE TABLE IF NOT EXISTS packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    total_sessions INT NOT NULL COMMENT 'Số buổi trong gói',
    total_price DECIMAL(12,2) NOT NULL,
    is_corporate BOOLEAN DEFAULT FALSE COMMENT 'Gói cho công ty/nhóm',
    valid_days INT DEFAULT 365 COMMENT 'Hạn sử dụng'
) ENGINE=InnoDB;

-- Patient Packages (Gói khách hàng mua)
CREATE TABLE IF NOT EXISTS patient_packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL COMMENT 'Chủ gói (hoặc đại diện công ty)',
    package_id INT NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    paid_amount DECIMAL(12,2) DEFAULT 0,
    sessions_remaining INT NOT NULL,
    status ENUM('active', 'exhausted', 'expired') DEFAULT 'active',
    purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expire_date DATE NULL,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (package_id) REFERENCES packages(id)
) ENGINE=InnoDB;

-- Corporate Package Usage (Nếu là gói công ty, nhiều người dùng chung)
CREATE TABLE IF NOT EXISTS package_usage_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_package_id INT NOT NULL,
    patient_id INT NOT NULL COMMENT 'Người thực tế sử dụng buổi này',
    treatment_id INT NOT NULL,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_package_id) REFERENCES patient_packages(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (treatment_id) REFERENCES treatments(id)
) ENGINE=InnoDB;

-- =============================================
-- INVENTORY & ACCOUNTING
-- =============================================

-- Items table (Vật tư/Hàng hóa)
CREATE TABLE IF NOT EXISTS inventory_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    unit VARCHAR(20) NOT NULL COMMENT 'Gói, gram, cái...',
    stock_quantity DECIMAL(10,2) DEFAULT 0,
    min_stock DECIMAL(10,2) DEFAULT 0,
    base_price DECIMAL(12,2) DEFAULT 0 COMMENT 'Giá nhập',
    sell_price DECIMAL(12,2) DEFAULT 0 COMMENT 'Giá bán',
    category VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Transaction logs (Nhập/Xuất/Thu/Chi)
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('income', 'expense') NOT NULL,
    category VARCHAR(50) NOT NULL COMMENT 'Dịch vụ, Gói, Bán hàng, Nhập kho, Lương...',
    amount DECIMAL(12,2) NOT NULL,
    reference_id INT NULL COMMENT 'Link tới treatment_id, patient_package_id, v.v.',
    description TEXT,
    branch_id INT NOT NULL,
    created_by INT NOT NULL,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Vouchers table
CREATE TABLE IF NOT EXISTS vouchers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    discount_type ENUM('percent', 'amount') NOT NULL,
    discount_value DECIMAL(12,2) NOT NULL,
    min_spend DECIMAL(12,2) DEFAULT 0,
    expire_date DATE NULL,
    usage_limit INT DEFAULT 1,
    used_count INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active'
) ENGINE=InnoDB;

-- =============================================
-- HR
-- =============================================

-- Timekeeping (Chấm công)
CREATE TABLE IF NOT EXISTS timekeeping (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    check_in DATETIME NOT NULL,
    check_out DATETIME NULL,
    work_date DATE NOT NULL,
    shift ENUM('morning', 'afternoon', 'full') DEFAULT 'full',
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;
