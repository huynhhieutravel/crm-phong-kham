<?php
// modules/leads/update_quick.php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/i18n.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    $db = getDB();

    if (isset($_POST['consultant_id'])) {
        $stmt = $db->prepare("UPDATE leads SET consultant_id = ? WHERE id = ?");
        $stmt->execute([$_POST['consultant_id'] ?: null, $id]);
        set_flash(__('leads.index.toast_saved_info_prefix') . __('leads.index.toast_label_tvv') . '!');
    }

    if (isset($_POST['consultation_status'])) {
        $status = $_POST['consultation_status'];
        $stmt = $db->prepare("UPDATE leads SET consultation_status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);

        // Auto-update system status based on consultation status if needed
        if ($status === 'Đã liên hệ' || $status === 'Hẹn gọi lại') {
            $db->prepare("UPDATE leads SET status = 'contacted' WHERE id = ?")->execute([$id]);
        } elseif ($status === 'Đã đặt lịch') {
            $db->prepare("UPDATE leads SET status = 'scheduled' WHERE id = ?")->execute([$id]);
        } elseif ($status === 'Hủy/Không nhu cầu') {
            $db->prepare("UPDATE leads SET status = 'cancelled' WHERE id = ?")->execute([$id]);
        }
        set_flash(__('leads.index.toast_saved_info_prefix') . __('leads.index.toast_label_status') . '!');
    }

    if (isset($_POST['source'])) {
        $stmt = $db->prepare("UPDATE leads SET source = ? WHERE id = ?");
        $stmt->execute([$_POST['source'], $id]);
        set_flash(__('leads.index.toast_saved_info_prefix') . __('leads.index.toast_label_source') . '!');
    }

    if (isset($_POST['medical_group'])) {
        $stmt = $db->prepare("UPDATE leads SET medical_group = ? WHERE id = ?");
        $stmt->execute([$_POST['medical_group'], $id]);
        set_flash(__('leads.index.toast_saved_info_prefix') . __('leads.index.toast_label_group') . '!');
    }

    // Handle AJAX Request for quick updates
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    redirect('index.php');
}
