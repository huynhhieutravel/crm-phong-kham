<?php
/**
 * Permission-based Authorization System
 * Mở rộng role-based (has_role) thành permission-based (can)
 * 
 * Cách dùng:
 *   if (can('manage_patients')) { ... }
 *   if (can('view_reports')) { ... }
 *   require_permission('manage_appointments');
 */

// Default permissions per role
$GLOBALS['_role_permissions'] = [
    'admin' => [
        'manage_patients', 'view_patients',
        'manage_appointments', 'view_appointments',
        'manage_medical', 'view_medical',
        'manage_leads', 'view_leads',
        'manage_sales', 'view_sales',
        'manage_billing', 'view_billing',
        'manage_inventory', 'view_inventory',
        'manage_hr', 'view_hr',
        'view_reports', 'export_reports',
        'manage_users', 'manage_roles',
        'view_audit_logs',
        'manage_settings',
        'view_finances', 'manage_checkout',
        'view_cskh', 'manage_cskh',
    ],
    'manager' => [
        'manage_patients', 'view_patients',
        'manage_appointments', 'view_appointments',
        'manage_medical', 'view_medical',
        'manage_leads', 'view_leads',
        'manage_sales', 'view_sales',
        'manage_billing', 'view_billing',
        'manage_inventory', 'view_inventory',
        'manage_hr', 'view_hr',
        'view_reports', 'export_reports',
        'manage_users', 'manage_roles',
        'view_audit_logs',
        'view_finances', 'manage_checkout',
    ],
    'doctor' => [
        'view_patients', 'manage_patients',
        'view_appointments', 'manage_appointments',
        'manage_medical', 'view_medical',
        'view_reports',
    ],
    'receptionist' => [
        'view_patients', 'manage_patients',
        'manage_appointments', 'view_appointments',
        'view_medical',
        'manage_leads', 'view_leads',
        'manage_sales', 'view_sales',
        'manage_billing', 'view_billing',
        'manage_checkout',
    ],
    'technician' => [
        'view_patients',
        'view_appointments',
        'view_medical', 'manage_medical',
    ],
    'accountant' => [
        'view_patients',
        'view_appointments',
        'manage_sales', 'view_sales',
        'view_billing', 'manage_billing',
        'view_reports', 'export_reports',
        'view_finances', 'manage_checkout',
    ],
    'cskh' => [
        'view_patients', 'manage_patients',
        'manage_appointments', 'view_appointments',
        'manage_leads', 'view_leads',
        'view_medical',
        'view_cskh', 'manage_cskh',
    ],
];

/**
 * Check if current user has a specific permission
 */
function can($permission) {
    $role = isset($_SESSION['role']) ? $_SESSION['role'] : 'staff';
    
    // Admin always has all permissions
    if ($role === 'admin') return true;
    
    // Check DB custom permissions first (if table exists)
    static $db_perms = null;
    if ($db_perms === null) {
        try {
            $db = getDB();
            $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
            $stmt = $db->prepare("
                SELECT p.permission_key 
                FROM role_permissions rp
                JOIN permissions p ON rp.permission_id = p.id
                JOIN users u ON u.role_id = rp.role_id
                WHERE u.id = ?
            ");
            $stmt->execute([$user_id]);
            $db_perms = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            // Table doesn't exist yet, fall back to defaults
            $db_perms = array();
        }

    }
    
    // Check DB permissions
    if (in_array($permission, $db_perms)) return true;
    
    // Fall back to default role permissions
    $defaults = isset($GLOBALS['_role_permissions'][$role]) ? $GLOBALS['_role_permissions'][$role] : array();
    return in_array($permission, $defaults);
}

/**
 * Get all permissions for current user
 */
function get_user_permissions() {
    $role = isset($_SESSION['role']) ? $_SESSION['role'] : 'staff';
    $all = isset($GLOBALS['_role_permissions'][$role]) ? $GLOBALS['_role_permissions'][$role] : array();
    return $all;
}

/**
 * Require a permission — redirect with error if not authorized
 */
function require_permission($permission) {
    if (!can($permission)) {
        if (function_exists('set_flash')) {
            set_flash('Bạn không có quyền truy cập chức năng này.', 'error');
        }
        header('Location: /index.php');
        exit;
    }
}

/**
 * List of all available permissions with descriptions
 */
function get_all_permissions() {
    return [
        'manage_patients'       => 'Quản lý Bệnh nhân (thêm/sửa/xóa)',
        'view_patients'         => 'Xem Bệnh nhân',
        'manage_appointments'   => 'Quản lý Lịch hẹn',
        'view_appointments'     => 'Xem Lịch hẹn',
        'manage_medical'        => 'Quản lý Hồ sơ bệnh án',
        'view_medical'          => 'Xem Hồ sơ bệnh án',
        'manage_leads'          => 'Quản lý Lead Marketing',
        'view_leads'            => 'Xem Lead Marketing',
        'manage_sales'          => 'Quản lý Bán hàng',
        'view_sales'            => 'Xem Bán hàng',
        'manage_inventory'      => 'Quản lý Kho vật tư',
        'view_inventory'        => 'Xem Kho vật tư',
        'manage_hr'             => 'Quản lý Nhân sự',
        'view_hr'               => 'Xem Nhân sự',
        'view_reports'          => 'Xem Báo cáo',
        'export_reports'        => 'Xuất Báo cáo',
        'manage_users'          => 'Quản lý Người dùng',
        'manage_roles'          => 'Quản lý Vai trò',
        'view_audit_logs'       => 'Xem Nhật ký hệ thống',
        'manage_settings'       => 'Quản lý Cài đặt',
        'view_finances'         => 'Xem Tài chính Bệnh nhân',
        'manage_checkout'       => 'Thanh toán sau điều trị',
        'view_billing'          => 'Xem Phiếu Tính Tiền',
        'manage_billing'        => 'Quản lý Phiếu Tính Tiền',
        'view_cskh'             => 'Xem CSKH',
        'manage_cskh'           => 'Quản lý CSKH',
    ];
}
