<?php
// modules/leads/update_quick.php

require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_leads');
require_once '../../includes/i18n.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // H2 FIX: Cast ID to int
    $id = (int)($_POST['id'] ?? 0);
    
    if (!$id) {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }
        set_flash('ID không hợp lệ.', 'error');
        redirect('index.php');
    }

    $db = getDB();

    // C2 FIX: Verify CSRF for non-AJAX requests. For AJAX, verify via token in POST data
    $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    if (!$is_ajax) {
        verify_csrf('index.php');
    } else {
        // For AJAX: verify CSRF token from POST
        $token = $_POST['_csrf_token'] ?? '';
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }
    }

    $available_cols = $db->query("SHOW COLUMNS FROM leads")->fetchAll(PDO::FETCH_COLUMN);

    if (isset($_POST['consultant_id']) && in_array('consultant_id', $available_cols)) {
        $stmt = $db->prepare("UPDATE leads SET consultant_id = ? WHERE id = ?");
        $stmt->execute([$_POST['consultant_id'] ?: null, $id]);
        set_flash(__('leads.index.toast_saved_info_prefix') . __('leads.index.toast_label_tvv') . '!');
    }

    if (isset($_POST['consultation_status']) && in_array('consultation_status', $available_cols)) {
        $status = $_POST['consultation_status'];
        $stmt = $db->prepare("UPDATE leads SET consultation_status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);

        // Auto-update system status based on consultation status if it exists
        if (in_array('status', $available_cols)) {
            if ($status === 'Đã liên hệ' || $status === 'Hẹn gọi lại') {
                $db->prepare("UPDATE leads SET status = 'contacted' WHERE id = ?")->execute([$id]);
            } elseif ($status === 'Đã đặt lịch') {
                $db->prepare("UPDATE leads SET status = 'scheduled' WHERE id = ?")->execute([$id]);
            } elseif ($status === 'Hủy/Không nhu cầu') {
                $db->prepare("UPDATE leads SET status = 'cancelled' WHERE id = ?")->execute([$id]);
            }
        }
        set_flash(__('leads.index.toast_saved_info_prefix') . __('leads.index.toast_label_status') . '!');
    }

    if (isset($_POST['source']) && in_array('source', $available_cols)) {
        $stmt = $db->prepare("UPDATE leads SET source = ? WHERE id = ?");
        $stmt->execute([$_POST['source'], $id]);
        set_flash(__('leads.index.toast_saved_info_prefix') . __('leads.index.toast_label_source') . '!');
    }

    if (isset($_POST['medical_group']) && in_array('medical_group', $available_cols)) {
        $stmt = $db->prepare("UPDATE leads SET medical_group = ? WHERE id = ?");
        $stmt->execute([$_POST['medical_group'], $id]);
        set_flash(__('leads.index.toast_saved_info_prefix') . __('leads.index.toast_label_group') . '!');
    }

    // Handle AJAX Request for quick updates
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    redirect('index.php');
}
