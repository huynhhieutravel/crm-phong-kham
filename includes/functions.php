<?php
// includes/functions.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/i18n.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');

/**
 * Escape HTML for output
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}



/**
 * Format currency
 */
function format_money($amount) {
    return number_format((float)($amount ?? 0), 0, ',', '.') . '₫';
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

    $sel_date = ($_GET['sel_date'] ?? '') ?: $now->format('Y-m-d');
    $sel_week = ($_GET['sel_week'] ?? '') ?: $now->format('Y') . '-W' . $now->format('W');
    $sel_month = (int)($_GET['sel_month'] ?? $now->format('n'));
    $sel_quarter = (int)($_GET['sel_quarter'] ?? ceil((int)$now->format('n') / 3));
    $sel_year = (int)($_GET['sel_year'] ?? $now->format('Y'));

    switch ($period) {
        case 'today':
            $start_date = $sel_date . ' 00:00:00';
            $end_date = $sel_date . ' 23:59:59';
            $label = ($sel_date === $now->format('Y-m-d')) ? __('common.today') : __('common.day_prefix') . date('d/m/Y', strtotime($sel_date));
            break;
        case 'tomorrow':
            $tomorrow = clone $now;
            $tomorrow->modify('+1 day');
            $tomorrow_date = $tomorrow->format('Y-m-d');
            $start_date = $tomorrow_date . ' 00:00:00';
            $end_date = $tomorrow_date . ' 23:59:59';
            $label = __('common.tomorrow');
            break;
        case 'week':
            $week_date = new DateTime();
            $week_year = (int)substr($sel_week, 0, 4);
            $week_num = (int)substr($sel_week, -2);
            $week_date->setISODate($week_year, $week_num);
            $start_date = $week_date->format('Y-m-d 00:00:00');
            $week_date->modify('+6 days');
            $end_date = $week_date->format('Y-m-d 23:59:59');
            
            $current_week = (int)$now->format('Y') . '-W' . $now->format('W');
            $label = ($sel_week === $now->format('Y') . '-W' . $now->format('W')) ? __('common.this_week') : __('filter.week') . " $week_num/$week_year";
            break;
        case 'month':
            $start_date = sprintf("%04d-%02d-01 00:00:00", $sel_year, $sel_month);
            $end_date = date('Y-m-t 23:59:59', strtotime($start_date));
            $label = __('common.month_prefix') . $sel_month . __('common.month_suffix') . "/$sel_year";
            break;
        case 'quarter':
            $start_month = ($sel_quarter - 1) * 3 + 1;
            $start_date = sprintf("%04d-%02d-01 00:00:00", $sel_year, $start_month);
            $end_date_tmp = sprintf("%04d-%02d-01", $sel_year, $start_month + 2);
            $end_date = date('Y-m-t 23:59:59', strtotime($end_date_tmp));
            $label = __('common.quarter_prefix') . $sel_quarter . __('common.quarter_suffix') . " ($sel_year)";
            break;
        case 'year':
            $start_date = sprintf("%04d-01-01 00:00:00", $sel_year);
            $end_date = sprintf("%04d-12-31 23:59:59", $sel_year);
            $label = __('common.year_prefix') . $sel_year . __('common.year_suffix');
            break;
        case 'custom':
            // H3 FIX: Validate date format to prevent malformed queries
            if ($start && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) $start = null;
            if ($end && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) $end = null;
            $start_date = $start ? $start . ' 00:00:00' : $now->format('Y-m-01 00:00:00');
            $end_date = $end ? $end . ' 23:59:59' : $now->format('Y-m-d 23:59:59');
            $label = __('common.from') . ' ' . date('d/m/Y', strtotime($start_date)) . ' ' . __('common.to') . ' ' . date('d/m/Y', strtotime($end_date));
            break;
        default:
            $start_date = $now->format('Y-m-01 00:00:00');
            $label = __('common.this_month');
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
    $html .= __('pagination.showing') . ' <span style="color: var(--text-main);">' . $start_item . '-' . $end_item . '</span> ' . __('pagination.of') . ' <span style="color: var(--text-main);">' . $total_count . '</span> ' . __('pagination.results');
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
    try {
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
    } catch (Exception $e) {
        // Silently fail logging if table missing to avoid crashing the main app
        error_log("Audit log failed: " . $e->getMessage());
    }
}


/**
 * Ensure all required columns exist in the patients table (self-healing)
 */
function ensure_patient_columns($db) {
    // Temporarily disabled to debug 500 errors
    return;
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
        'Referral' => 'KH giới thiệu',
        'Acquaintance' => 'Người quen',
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
        'Hội chứng Cổ Vai Gáy' => 'medical_group.neck_shoulder',
        'Thoát vị đĩa đệm & Đau thần kinh tọa' => 'medical_group.disc_herniation',
        'Cong vẹo cột sống ở trẻ em/thanh thiếu niên' => 'medical_group.scoliosis',
        'Chấn thương thể thao' => 'medical_group.sports_injury',
        'Tê bì tay chân & Hội chứng ống cổ tay' => 'medical_group.numbness_carpal',
        'Rối loạn khớp thái dương hàm (TMJ)' => 'medical_group.tmj',
        'Thoái hóa khớp gối' => 'medical_group.knee_osteoarthritis',
        'Trẻ Chậm Nói, Chậm Vận Động do Sai lệch Cột sống' => 'medical_group.delayed_development',
        'Đông y' => 'medical_group.dong_y'
    ];
}

/**
 * Get translation key for patient labels
 */
function get_patient_label_translation($label) {
    $map = [
        'Khách mới'    => 'patient.label.new',
        'Cần chăm sóc' => 'patient.label.needs_care',
        'Đang điều trị' => 'patient.label.in_treatment',
        'Vip'          => 'patient.label.vip',
        'Khách cũ'     => 'patient.label.returning'
    ];
    
    return isset($map[$label]) ? __($map[$label]) : $label;
}

/**
 * Synchronize and get sticky appointment date
 */
function get_sticky_appointment_date() {

    if (isset($_GET['date']) && !empty($_GET['date'])) {
        // Use provided date and save to session
        $_SESSION['last_appointment_date'] = $_GET['date'];
        return $_GET['date'];
    }
    
    // Check session
    if (isset($_SESSION['last_appointment_date']) && !empty($_SESSION['last_appointment_date'])) {
        return $_SESSION['last_appointment_date'];
    }
    
    // Default to today
    return date('Y-m-d');
}

/**
 * Auto-generate Patient Code (Format: YYMM-XXXX)
 */
function generate_patient_code() {
    $db = getDB();
    $prefix = date('ym'); // e.g., 2603 for March 2026
    
    // Lock or basic query for latest code with this prefix
    $stmt = $db->query("SELECT customer_id FROM patients WHERE customer_id LIKE '{$prefix}-%' ORDER BY id DESC LIMIT 1");
    $last_code = $stmt->fetchColumn();
    
    if ($last_code) {
        $parts = explode('-', $last_code);
        $next_seq = intval(end($parts)) + 1;
    } else {
        $next_seq = 1;
    }
    
    return sprintf("%s-%04d", $prefix, $next_seq);
}

/**
 * Auto-generate Lead Code (Format: L{YYMM}-{XXXX})
 */
function generate_lead_code($db = null) {
    if (!$db) {
        $db = getDB();
    }
    $prefix = 'L' . date('ym'); // e.g., L2603
    
    // Query for latest code with this prefix
    $stmt = $db->query("SELECT lead_code FROM leads WHERE lead_code LIKE '{$prefix}-%' ORDER BY id DESC LIMIT 1");
    $last_code = $stmt->fetchColumn();
    
    if ($last_code) {
        $parts = explode('-', $last_code);
        $next_seq = intval(end($parts)) + 1;
    } else {
        $next_seq = 1;
    }
    
    return sprintf("%s-%04d", $prefix, $next_seq);
}
