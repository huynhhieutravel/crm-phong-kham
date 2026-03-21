<?php
// modules/medical/print_session.php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

$db = getDB();
$session_id = $_GET['id'] ?? 0;

if (!$session_id) {
    die("Thiếu mã buổi khám.");
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
    die("Không tìm thấy dữ liệu buổi khám.");
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
    <title>In Phiếu Khám - <?php echo e($session['patient_name']); ?></title>
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
        <button onclick="window.print()" class="btn-print">XÁC NHẬN IN PHIẾU</button>
    </div>

    <div class="print-container">
        <div class="header">
            <div class="clinic-info">
                <h1>PHÒNG KHÁM CHUYÊN KHOA</h1>
                <p>Địa chỉ: 123 Đường ABC, Quận XYZ, TP.HCM</p>
                <p>Hotline: 0123 456 789 - Website: example.com</p>
            </div>
            <div style="text-align: right;">
                <p style="margin: 0; font-size: 12px; color: #94a3b8;">Mã buổi khám: #<?php echo str_pad($session_id, 6, '0', STR_PAD_LEFT); ?></p>
                <p style="margin: 5px 0 0 0; font-weight: 700; color: #3b82f6;">Ngày: <?php echo $session['session_date'] ? date('d/m/Y', strtotime($session['session_date'])) : date('d/m/Y'); ?></p>
            </div>
        </div>

        <div class="doc-title">
            <h2>PHIẾU KẾT LUẬN & ĐIỀU TRỊ</h2>
        </div>

        <div class="patient-box">
            <div class="col">
                <div class="info-item"><label>Họ tên:</label> <strong><?php echo e($session['patient_name']); ?></strong></div>
                <div class="info-item"><label>Ngày sinh:</label> <?php echo $session['birthday'] ? date('d/m/Y', strtotime($session['birthday'])) : 'N/A'; ?> (<?php echo $age; ?> tuổi)</div>
                <div class="info-item"><label>Giới tính:</label> <?php echo $session['gender'] === 'male' ? 'Nam' : 'Nữ'; ?></div>
            </div>
            <div class="col">
                <div class="info-item"><label>SĐT:</label> <?php echo e($session['phone']); ?></div>
                <div class="info-item"><label>Địa chỉ:</label> <?php echo e($session['address']); ?></div>
                <div class="info-item"><label>Bác sĩ:</label> <strong><?php echo e($session['doctor_name']); ?></strong></div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">I. ĐÁNH GIÁ LÂM SÀNG (ASSESSMENT)</div>
            <div class="rich-content">
                <?php echo $session['assessment'] ?: '<i>(Chưa có thông tin)</i>'; ?>
            </div>
        </div>

        <div class="section">
            <div class="section-title">II. KẾ HOẠCH ĐIỀU TRỊ (PLAN)</div>
            <div class="rich-content">
                <?php echo $session['treatment_plan'] ?: '<i>(Chưa có thông tin)</i>'; ?>
            </div>
        </div>

        <div class="section">
            <div class="section-title">III. GHI CHÚ THÀNH PHẦN</div>
            <div style="font-size: 13px; color: #64748b;">
                Đã thực hiện: 
                <?php 
                $done = [];
                foreach($history_records as $rec) {
                    if($rec['type'] === 'chiro_history') $done[] = "Tiền sử Chiropractic";
                    if($rec['type'] === 'chiro_exam') $done[] = "Khám thực thể";
                    if($rec['type'] === 'chiropractic' || $rec['type'] === 'soap_note') $done[] = "Theo dõi nội bộ (SOAP)";
                    if($rec['type'] === 'dong_y') $done[] = "Khám Đông Y";
                }
                echo implode(', ', $done) ?: 'Chưa có thành phần nào';
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
            <div class="section-title">IV. HÌNH ẢNH ĐÍNH KÈM</div>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                <?php foreach ($all_att as $att): ?>
                <?php if (strpos($att['type'] ?? '', 'image') !== false): ?>
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
                <p>Khách hàng ký nhận</p>
                <p style="font-weight: 400; font-size: 12px; color: #94a3b8;">(Ký và ghi rõ họ tên)</p>
            </div>
            <div class="sig-box">
                <p>Bác sĩ chuyên khoa</p>
                <div style="font-size: 20px; color: #cbd5e1; margin: 20px 0;">(Ký và đóng dấu)</div>
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
