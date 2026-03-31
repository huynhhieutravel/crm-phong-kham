<?php
// modules/medical/print_record.php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('view_medical');

$db = getDB();
$type = isset($_GET['type']) ? $_GET['type'] : '';
$id = isset($_GET['id']) ? $_GET['id'] : 0;

if (!$id || !in_array($type, ['history', 'treatment'])) {
    die("Invalid request / Yêu cầu không hợp lệ.");
}

$record = null;
$patient = null;
$user = null;
$data = [];
$record_date = '';
$document_title = '';
$record_type = '';

if ($type === 'history') {
    $stmt = $db->prepare("
        SELECT h.*, p.full_name as patient_name, p.birthday, p.gender, p.phone, p.address,
               u.full_name as doctor_name
        FROM medical_history h
        JOIN patients p ON h.patient_id = p.id
        JOIN users u ON h.created_by = u.id
        WHERE h.id = ?
    ");
    $stmt->execute([$id]);
    $record = $stmt->fetch();
    
    if ($record) {
        $patient = $record;
        $user = $record['doctor_name'];
        $data = json_decode($record['history_data'], true) ?: [];
        $record_date = $record['created_at'];
        $record_type = $record['type']; // chiro_history, chiropractic, dong_y
        
        $titles = [
            'chiro_history' => __('medical.type.chiro_history_full'),
            'chiropractic' => __('medical.type.chiropractic'),
            'soap_note' => __('medical.type.chiropractic'),
            'initial_exam' => __('medical.type.chiropractic'),
            'dong_y' => __('medical.type.dong_y')
        ];
        $document_title = $titles[$record_type] ?? 'Hồ sơ Y tế';
    }
} else { // type = treatment
    $stmt = $db->prepare("
        SELECT t.*, p.full_name as patient_name, p.birthday, p.gender, p.phone, p.address,
               u.full_name as technician_name
        FROM treatments t
        JOIN patients p ON t.patient_id = p.id
        JOIN users u ON t.technician_id = u.id
        WHERE t.id = ?
    ");
    $stmt->execute([$id]);
    $record = $stmt->fetch();
    
    if ($record) {
        $patient = $record;
        $user = $record['technician_name'];
        $data = ['session_data' => $record['session_data']];
        $record_date = $record['treatment_date'];
        $record_type = 'treatment';
        $document_title = 'Phác đồ điều trị';
    }
}

if (!$record) {
    die(__('medical.print.err_not_found'));
}

$age = $patient['birthday'] ? date_diff(date_create($patient['birthday']), date_create('today'))->y : 'N/A';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>In PDF - <?php echo e($document_title); ?> - <?php echo e($patient['patient_name']); ?></title>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { padding: 0 !important; margin: 0 !important; background: white !important; }
            .print-container { box-shadow: none !important; margin: 0 !important; padding: 0 !important; max-width: 100% !important; border: none !important; border-radius: 0 !important; }
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

        /* Checkbox lists */
        ul.checklist {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        ul.checklist li {
            padding-left: 1.5em;
            position: relative;
            margin-bottom: 0.2rem;
        }
        ul.checklist li::before {
            content: "\2713"; /* Checkmark */
            position: absolute;
            left: 0;
            color: #10b981;
            font-weight: bold;
        }
        .hidden-empty {
            display: none !important;
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: center; position: fixed; top: 15px; right: 20px; z-index: 100; display: flex; gap: 10px;">
        <button onclick="window.print()" class="btn-print"><i class="fas fa-print"></i> In Phiếu</button>
        <button onclick="saveAsPDF()" class="btn-print" style="background: #10b981;"><i class="fas fa-download"></i> Lưu PDF</button>
    </div>

    <script>
        function saveAsPDF() {
            alert('Để lưu file PDF gửi khách:\n\n1. Trong hộp thoại sắp hiện ra, tìm mục "Máy in" (Destination).\n2. Chọn "Lưu dưới dạng PDF" (Save as PDF).\n3. Bấm Lưu (Save).');
            window.print();
        }
    </script>

    <div class="print-container">
        <div class="header">
            <div class="clinic-info">
                <h1><?php echo __('medical.print.clinic_name'); ?></h1>
                <p style="font-weight: 600; font-size: 11px;"><?php echo __('medical.print.clinic_intl_name'); ?></p>
                <p><?php echo __('medical.print.clinic_address'); ?></p>
            </div>
            <div style="text-align: right;">
                <p style="margin: 0; font-size: 12px; color: #94a3b8;">Hồ sơ: <?php echo str_pad($record['id'], 6, '0', STR_PAD_LEFT); ?></p>
                <p style="margin: 5px 0 0 0; font-weight: 700; color: #3b82f6;">Ngày lập: <?php echo $record_date ? date('d/m/Y', strtotime($record_date)) : date('d/m/Y'); ?></p>
            </div>
        </div>

        <div class="doc-title">
            <h2><?php echo $document_title; ?></h2>
        </div>

        <div class="patient-box">
            <div>
                <div class="info-item"><label>Họ và tên:</label> <strong style="color: #0f172a; text-transform: uppercase;"><?php echo e($patient['patient_name']); ?></strong></div>
                <div class="info-item"><label>Ngày sinh:</label> <strong><?php echo $patient['birthday'] ? date('d/m/Y', strtotime($patient['birthday'])) : 'N/A'; ?></strong> (<?php echo $age; ?> tuổi)</div>
                <div class="info-item"><label>Giới tính:</label> <strong><?php echo $patient['gender'] == 'male' ? 'Nam' : ($patient['gender'] == 'female' ? 'Nữ' : 'Khác'); ?></strong></div>
            </div>
            <div>
                <div class="info-item"><label>Điện thoại:</label> <strong><?php echo e($patient['phone']); ?></strong></div>
                <div class="info-item"><label>Người phụ trách:</label> <strong><?php echo e($user); ?></strong></div>
            </div>
        </div>

        <div class="record-content">
            <?php
            // Route to correct template based on record_type
            if ($record_type === 'dong_y') {
                require 'forms/print_dong_y.php';
            } elseif ($record_type === 'chiropractic' || $record_type === 'soap_note' || $record_type === 'initial_exam') {
                // Form V2 logic
                if (isset($data['pain_locations']) || isset($data['subluxation'])) {
                    // It's V2 Data
                    require 'forms/print_chiro_v2.php';
                } else {
                    // Fallback to minimal V1 print rendering if needed (copy from print_session.php V1 format)
                    echo '<div class="rich-content" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; background: #f8fafc; font-size: 13px;">';
                    if (!empty($data['s']['vas'])) echo "<p><strong>Chủ quan (S) - VAS:</strong> {$data['s']['vas']}/10</p>";
                    if (!empty($data['o']['muscle_tone'])) echo "<p><strong>Khách quan (O):</strong> {$data['o']['muscle_tone']}</p>";
                    if (!empty($data['a']['physiotherapy'])) echo "<p><strong>Đánh giá (A):</strong> " . implode(', ', $data['a']['physiotherapy']) . "</p>";
                    if (!empty($data['p']['notes'])) echo "<p><strong>Kế hoạch (P):</strong> {$data['p']['notes']}</p>";
                    echo '</div>';
                }
            } elseif ($record_type === 'chiro_history') {
                require 'forms/print_chiro_history.php';
            } elseif ($record_type === 'treatment') {
                require 'forms/print_treatment.php';
            } else {
                echo "<p>Loại hồ sơ không được hỗ trợ in tự động.</p>";
            }
            ?>
        </div>

        <div class="footer-sig">
            <div class="sig-box">
                <p><?php echo __('medical.print.sig_customer'); ?></p>
                <p style="font-weight: 400; font-size: 12px; color: #94a3b8;"><?php echo __('medical.print.sig_note_1'); ?></p>
            </div>
            <div class="sig-box">
                <p><?php echo $record_type === 'treatment' ? 'KỸ THUẬT VIÊN' : __('medical.print.sig_doctor'); ?></p>
                <div style="font-size: 20px; color: #cbd5e1; margin: 20px 0;"><?php echo __('medical.print.sig_note_2'); ?></div>
                <p><?php echo e($user); ?></p>
            </div>
        </div>
    </div>
</body>
</html>
