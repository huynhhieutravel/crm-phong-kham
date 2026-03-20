<?php
// includes/functions.php
require_once __DIR__ . '/db.php';

/**
 * Escape HTML for output
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Translation helper (to be implemented)
 */
function __($key, $default = '') {
    // Basic placeholder for now
    return $key;
}

/**
 * Format currency
 */
function format_money($amount) {
    return number_format($amount, 0, ',', '.') . '₫';
}

/**
 * Redirect helper
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Set flash message
 */
function set_flash($message, $type = 'success') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

/**
 * Get start and end dates for a period
 * @param string $period (today, week, month, quarter, year, custom)
 * @param string|null $start (YYYY-MM-DD)
 * @param string|null $end (YYYY-MM-DD)
 * @return array [start, end, label]
 */
function get_date_range($period = 'month', $start = null, $end = null) {
    $now = new DateTime();
    $start_date = '';
    $end_date = $now->format('Y-m-d 23:59:59');
    $label = '';

    switch ($period) {
        case 'today':
            $start_date = $now->format('Y-m-d 00:00:00');
            $label = 'Hôm nay';
            break;
        case 'week':
            $now->modify('monday this week');
            $start_date = $now->format('Y-m-d 00:00:00');
            $label = 'Tuần này';
            break;
        case 'month':
            $start_date = $now->format('Y-m-01 00:00:00');
            $label = 'Tháng này (' . $now->format('m/Y') . ')';
            break;
        case 'quarter':
            $month = (int)$now->format('n');
            $quarter = ceil($month / 3);
            $start_month = ($quarter - 1) * 3 + 1;
            $start_date = $now->format("Y-") . sprintf("%02d", $start_month) . "-01 00:00:00";
            $label = "Quý $quarter (" . $now->format('Y') . ")";
            break;
        case 'year':
            $start_date = $now->format('Y-01-01 00:00:00');
            $label = 'Năm ' . $now->format('Y');
            break;
        case 'custom':
            $start_date = $start ? $start . ' 00:00:00' : $now->format('Y-m-01 00:00:00');
            $end_date = $end ? $end . ' 23:59:59' : $now->format('Y-m-d 23:59:59');
            $label = 'Từ ' . date('d/m/Y', strtotime($start_date)) . ' đến ' . date('d/m/Y', strtotime($end_date));
            break;
        default:
            $start_date = $now->format('Y-m-01 00:00:00');
            $label = 'Tháng này';
    }

    return [
        'start' => $start_date,
        'end'   => $end_date,
        'label' => $label
    ];
}

/**
 * Render pagination UI
 */
function render_pagination($total_count, $limit, $current_page) {
    if ($total_count <= $limit) return '';
    
    $total_pages = ceil($total_count / $limit);
    $current_page = max(1, min((int)$current_page, $total_pages));
    
    // Get current URL and remove 'page' parameter
    $params = $_GET;
    unset($params['page']);
    $query = http_build_query($params);
    $base_url = '?' . ($query ? $query . '&' : '');
    
    $html = '<div class="pagination-container" style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; padding: 1rem; background: white; border-radius: 12px; box-shadow: var(--shadow-sm); border: 1px solid var(--border-color);">';
    
    // Info
    $start_item = ($current_page - 1) * $limit + 1;
    $end_item = min($current_page * $limit, $total_count);
    $html .= '<div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">';
    $html .= 'Hiển thị <span style="color: var(--text-main);">' . $start_item . '-' . $end_item . '</span> trong <span style="color: var(--text-main);">' . $total_count . '</span> kết quả';
    $html .= '</div>';
    
    // Controls
    $html .= '<div style="display: flex; gap: 0.5rem; align-items: center;">';
    
    // Previous
    if ($current_page > 1) {
        $html .= '<a href="' . $base_url . 'page=' . ($current_page - 1) . '" class="page-btn"><i class="fas fa-chevron-left"></i></a>';
    } else {
        $html .= '<span class="page-btn disabled"><i class="fas fa-chevron-left"></i></span>';
    }
    
    // Page numbers
    $range = 2; // How many pages to show before and after current
    for ($i = 1; $i <= $total_pages; $i++) {
        if ($i == 1 || $i == $total_pages || ($i >= $current_page - $range && $i <= $current_page + $range)) {
            $active = ($i == $current_page) ? 'active' : '';
            $html .= '<a href="' . $base_url . 'page=' . $i . '" class="page-btn ' . $active . '">' . $i . '</a>';
        } elseif ($i == $current_page - $range - 1 || $i == $current_page + $range + 1) {
            $html .= '<span style="color: #94a3b8; padding: 0 0.25rem;">...</span>';
        }
    }
    
    // Next
    if ($current_page < $total_pages) {
        $html .= '<a href="' . $base_url . 'page=' . ($current_page + 1) . '" class="page-btn"><i class="fas fa-chevron-right"></i></a>';
    } else {
        $html .= '<span class="page-btn disabled"><i class="fas fa-chevron-right"></i></span>';
    }
    
    $html .= '</div></div>';
    
    // Add CSS inline once in the page or via header.php. 
    // Here we add it once using a static variable to avoid duplication if multiple pagination on same page.
    static $css_added = false;
    if (!$css_added) {
        $html .= '<style>
            .page-btn {
                min-width: 32px;
                height: 32px;
                padding: 0 0.5rem;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 8px;
                background: #f1f5f9;
                color: var(--text-main);
                text-decoration: none;
                font-size: 0.85rem;
                font-weight: 700;
                transition: all 0.2s;
                border: 1px solid transparent;
            }
            .page-btn:hover:not(.disabled) { background: #e2e8f0; color: var(--primary); border-color: var(--primary); }
            .page-btn.active { background: var(--primary); color: white; }
            .page-btn.disabled { opacity: 0.5; cursor: not-allowed; background: #f8fafc; }
        </style>';
        $css_added = true;
    }
    
    return $html;
}

/**
 * Get SQL LIMIT/OFFSET clause
 */
function get_sql_limit($limit, $current_page) {
    if ($limit <= 0) return '';
    $offset = ($current_page - 1) * $limit;
    return " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
}
/**
 * Get human-readable time elapsed string
 */
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $days = $diff->d;
    $weeks = floor($days / 7);
    $days_remaining = $days % 7;

    $string = array(
        'y' => 'năm',
        'm' => 'tháng',
    );
    
    // Custom handling for weeks and remaining days
    if ($weeks > 0) $string['w'] = $weeks . ' tuần';
    if ($days_remaining > 0) $string['d'] = $days_remaining . ' ngày';
    
    // Other properties
    $others = array(
        'h' => 'giờ',
        'i' => 'phút',
        's' => 'giây',
    );
    
    foreach ($others as $k => $v) {
        if ($diff->$k) {
            $string[$k] = $diff->$k . ' ' . $v;
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' trước' : 'vừa xong';
}

function log_audit($user_id, $action, $table, $target_id, $old_data = null, $new_data = null) {
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO audit_logs (user_id, action, target_table, target_id, old_data, new_data, ip_address)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user_id,
        $action,
        $table,
        $target_id,
        $old_data ? json_encode($old_data, JSON_UNESCAPED_UNICODE) : null,
        $new_data ? json_encode($new_data, JSON_UNESCAPED_UNICODE) : null,
        $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
    ]);
}

/**
 * Ensure all required columns exist in the patients table (self-healing)
 */
function ensure_patient_columns($db) {
    $columns_to_add = [
        'customer_id' => "VARCHAR(50) NULL AFTER id",
        'occupation' => "VARCHAR(100) NULL AFTER branch",
        'guardian_name' => "VARCHAR(100) NULL AFTER occupation",
        'guardian_id_card' => "VARCHAR(20) NULL AFTER guardian_name",
        'guardian_phone' => "VARCHAR(20) NULL AFTER guardian_id_card",
        'guardian_relationship' => "VARCHAR(50) NULL AFTER guardian_phone",
        'zalo_number' => "VARCHAR(20) NULL AFTER guardian_relationship",
        'label' => "VARCHAR(50) NULL AFTER consultant_id",
        'facebook_link' => "VARCHAR(255) NULL AFTER notes",
        'instagram_link' => "VARCHAR(255) NULL AFTER facebook_link",
        'twitter_link' => "VARCHAR(255) NULL AFTER instagram_link"
    ];

    foreach ($columns_to_add as $column => $definition) {
        try {
            $check = $db->query("SHOW COLUMNS FROM patients LIKE '$column'");
            if ($check->rowCount() == 0) {
                $db->exec("ALTER TABLE patients ADD COLUMN $column $definition");
            }
        } catch (Exception $e) {
            // Log or ignore if table doesn't exist yet
        }
    }
}

/**
 * Get lead sources with human-readable labels
 */
function get_lead_sources() {
    return [
        'Facebook' => 'Facebook',
        'Zalo' => 'Zalo',
        'TikTok' => 'TikTok',
        'Google' => 'Google',
        'Referral' => 'Người giới thiệu',
        'Chương Trình' => 'Chương Trình',
        'Website' => 'Website',
        'Other' => 'Khác'
    ];
}

/**
 * Get medical groups for leads/patients
 */
function get_medical_groups() {
    return [
        'Thoát vị đĩa đệm',
        'Thoái hóa cột sống',
        'Đau thần kinh tọa',
        'Cong vẹo cột sống',
        'Phục hồi chức năng',
        'Cơ xương khớp khác'
    ];
}
