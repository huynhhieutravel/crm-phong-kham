<?php
// modules/medical/print_session.php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('view_medical');

$db = getDB();
$session_id = isset($_GET['id']) ? $_GET['id'] : 0;

if (!$session_id) {
    die(__('medical.print.err_missing_id'));
}

// Fetch Session Info
$stmt = $db->prepare("
    SELECT s.*, p.full_name as patient_name, p.birthday, p.gender, p.phone, p.address,
           u.full_name as doctor_name
    FROM medical_sessions s
    JOIN patients p ON s.patient_id = p.id
    JOIN users u ON s.doctor_id = u.id
    WHERE s.id = ?
");
$stmt->execute([$session_id]);
$session = $stmt->fetch();

if (!$session) {
    die(__('medical.print.err_not_found'));
}

// Fetch Med History Records
$stmt = $db->prepare("SELECT type, history_data FROM medical_history WHERE session_id = ?");
$stmt->execute([$session_id]);
$history_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

$age = $session['birthday'] ? date_diff(date_create($session['birthday']), date_create('today'))->y : 'N/A';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title><?php echo sprintf(__('medical.print.page_title'), e($session['patient_name'])); ?></title>
    <style>
        @media print {
            .no-print { display: none; }
            body { padding: 0; margin: 0; }
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #1e293b;
            line-height: 1.6;
            background: #f1f5f9;
            padding: 40px;
        }
        .print-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 50px;
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
            border-radius: 8px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #3b82f6;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .clinic-info h1 {
            margin: 0;
            color: #1d4ed8;
            font-size: 24px;
            font-weight: 800;
        }
        .clinic-info p {
            margin: 5px 0 0 0;
            font-size: 13px;
            color: #64748b;
        }
        .doc-title {
            text-align: center;
            margin-bottom: 40px;
        }
        .doc-title h2 {
            margin: 0;
            text-transform: uppercase;
            font-size: 22px;
            letter-spacing: 2px;
            color: #1e293b;
        }
        .patient-box {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 40px;
        }
        .info-item { margin-bottom: 8px; font-size: 14px; }
        .info-item label { font-weight: 700; color: #64748b; width: 120px; display: inline-block; }
        
        .section { margin-bottom: 40px; }
        .section-title {
            font-size: 16px;
            font-weight: 800;
            color: #3b82f6;
            text-transform: uppercase;
            border-left: 4px solid #3b82f6;
            padding-left: 12px;
            margin-bottom: 15px;
        }
        .rich-content {
            background: white;
            min-height: 50px;
            font-size: 15px;
        }
        .footer-sig {
            display: grid;
            grid-template-columns: 1fr 1fr;
            margin-top: 60px;
            text-align: center;
        }
        .sig-box { height: 120px; display: flex; flex-direction: column; justify-content: space-between; }
        .sig-box p { margin: 0; font-weight: 700; }
        
        .btn-print {
            padding: 12px 24px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: center;">
        <button onclick="window.print()" class="btn-print"><?php echo __('medical.print.btn_print'); ?></button>
    </div>

    <div class="print-container">
        <div class="header">
            <div class="clinic-info">
                <h1><?php echo __('medical.print.clinic_name'); ?></h1>
                <p style="font-weight: 600; font-size: 11px;"><?php echo __('medical.print.clinic_intl_name'); ?></p>
                <p><?php echo __('medical.print.clinic_tax_code'); ?></p>
                <p><?php echo __('medical.print.clinic_address'); ?></p>
            </div>
            <div style="text-align: right;">
                <p style="margin: 0; font-size: 12px; color: #94a3b8;"><?php echo __('medical.print.session_code_label'); ?><?php echo str_pad($session_id, 6, '0', STR_PAD_LEFT); ?></p>
                <p style="margin: 5px 0 0 0; font-weight: 700; color: #3b82f6;"><?php echo __('medical.print.date_label'); ?><?php echo $session['session_date'] ? date('d/m/Y', strtotime($session['session_date'])) : date('d/m/Y'); ?></p>
            </div>
        </div>

        <div class="doc-title">
            <h2><?php echo __('medical.print.doc_title'); ?></h2>
        </div>

        <div class="patient-box">
            <div class="col">
                <div class="info-item"><label><?php echo __('medical.print.patient_name'); ?></label> <strong><?php echo e($session['patient_name']); ?></strong></div>
                <div class="info-item"><label><?php echo __('medical.print.dob'); ?></label> <?php echo $session['birthday'] ? date('d/m/Y', strtotime($session['birthday'])) : 'N/A'; ?> <?php echo sprintf(__('medical.print.age_format'), $age); ?></div>
                <div class="info-item"><label><?php echo __('medical.print.gender_label'); ?></label> <?php echo ($session['gender'] === 'male') ? __('patient.male') : __('patient.female'); ?></div>
            </div>
            <div class="col">
                <div class="info-item"><label><?php echo __('medical.print.phone'); ?></label> <?php echo e($session['phone']); ?></div>
                <div class="info-item"><label><?php echo __('medical.print.address'); ?></label> <?php echo e($session['address']); ?></div>
                <div class="info-item"><label><?php echo __('medical.print.doctor'); ?></label> <strong><?php echo e($session['doctor_name']); ?></strong></div>
            </div>
        </div>

        <div class="section">
            <div class="section-title"><?php echo __('medical.print.sec1_title'); ?></div>
            <div class="rich-content">
                <?php echo $session['assessment'] ?: __('medical.print.no_info'); ?>
            </div>
        </div>

        <div class="section">
            <div class="section-title"><?php echo __('medical.print.sec2_title'); ?></div>
            <div class="rich-content">
                <?php echo $session['treatment_plan'] ?: __('medical.print.no_info'); ?>
            </div>
        </div>

        <div class="section">
            <div class="section-title"><?php echo __('medical.print.sec3_title'); ?></div>
            <div style="font-size: 13px; color: #64748b;">
                <?php echo __('medical.print.performed_label'); ?>
                <?php 
                $done = [];
                foreach($history_records as $rec) {
                    if($rec['type'] === 'chiro_history') $done[] = __('medical.print.type_chiro_history');
                    if($rec['type'] === 'chiro_exam') $done[] = __('medical.print.type_chiro_exam');
                    if($rec['type'] === 'chiropractic' || $rec['type'] === 'soap_note') $done[] = __('medical.print.type_soap');
                    if($rec['type'] === 'dong_y') $done[] = __('medical.print.type_dong_y');
                }
                echo implode(', ', $done) ?: __('medical.print.no_components');
                ?>
            </div>
        </div>

        <?php 
        // Load attachments
        $stmt_att = $db->prepare("SELECT attachments FROM medical_history WHERE session_id = ? AND attachments IS NOT NULL AND attachments != '[]'");
        $stmt_att->execute([$session_id]);
        $all_att = [];
        while ($r = $stmt_att->fetch()) {
            $a = json_decode($r['attachments'], true);
            if ($a) $all_att = array_merge($all_att, $a);
        }
        if (!empty($all_att)):
        ?>
        <div class="section" style="page-break-inside: avoid;">
            <div class="section-title"><?php echo __('medical.print.sec4_title'); ?></div>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                <?php foreach ($all_att as $att): ?>
                <?php if (strpos(isset($att['type']) ? $att['type'] : '', 'image') !== false): ?>
                <div style="text-align: center;">
                    <img src="<?php echo $att['path']; ?>" style="width: 100%; border-radius: 8px; border: 1px solid #e2e8f0;" alt="<?php echo e($att['name']); ?>">
                    <div style="font-size: 10px; color: #94a3b8; margin-top: 4px;"><?php echo e($att['name']); ?></div>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="footer-sig">
            <div class="sig-box">
                <p><?php echo __('medical.print.sig_customer'); ?></p>
                <p style="font-weight: 400; font-size: 12px; color: #94a3b8;"><?php echo __('medical.print.sig_note_1'); ?></p>
            </div>
            <div class="sig-box">
                <p><?php echo __('medical.print.sig_doctor'); ?></p>
                <div style="font-size: 20px; color: #cbd5e1; margin: 20px 0;"><?php echo __('medical.print.sig_note_2'); ?></div>
                <p><?php echo e($session['doctor_name']); ?></p>
            </div>
        </div>
    </div>

    <script>
        // Auto-open print dialog maybe?
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>
