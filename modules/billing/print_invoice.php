<?php
// modules/billing/print_invoice.php — Professional A4 Invoice Print
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('view_billing');

$db = getDB();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    die('Thiếu mã phiếu.');
}

$stmt = $db->prepare("
    SELECT i.*, p.full_name as patient_name, p.phone as patient_phone, p.address as patient_address,
           u.full_name as cashier_name
    FROM invoices i
    JOIN patients p ON i.patient_id = p.id
    JOIN users u ON i.created_by = u.id
    WHERE i.id = ?
");
$stmt->execute([$id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    die('Phiếu không tồn tại.');
}

$items = json_decode($invoice['items'], true) ?: [];
$invoice_date = $invoice['created_at'] ? date('d/m/Y', strtotime($invoice['created_at'])) : date('d/m/Y');
$invoice_time = $invoice['created_at'] ? date('H:i', strtotime($invoice['created_at'])) : date('H:i');

// Generate unique receipt code (obfuscated like medical records)
$receipt_code = 'PTT' . date('ym', strtotime($invoice['created_at'] ?: 'now')) . '-' . strtoupper(substr(md5('invoice_' . $invoice['id']), 0, 5));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Phiếu Tính Tiền - <?php echo e($invoice['patient_name']); ?></title>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; margin: 0; }
            @page { size: A4; margin: 15mm; }
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #1e293b;
            line-height: 1.6;
            background: #f1f5f9;
            padding: 40px;
            margin: 0;
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
            margin-bottom: 35px;
        }
        .doc-title h2 {
            margin: 0;
            text-transform: uppercase;
            font-size: 22px;
            letter-spacing: 2px;
            color: #1e293b;
        }
        .customer-box {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 35px;
        }
        .info-item { font-size: 14px; margin-bottom: 6px; }
        .info-item label { font-weight: 700; color: #64748b; width: 100px; display: inline-block; }

        /* Invoice Table */
        .inv-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        .inv-table thead th {
            background: #f1f5f9;
            padding: 12px 15px;
            text-align: left;
            font-size: 12px;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid #e2e8f0;
        }
        .inv-table thead th:last-child,
        .inv-table thead th:nth-child(3),
        .inv-table thead th:nth-child(4) {
            text-align: right;
        }
        .inv-table tbody td {
            padding: 12px 15px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }
        .inv-table tbody td:last-child,
        .inv-table tbody td:nth-child(3),
        .inv-table tbody td:nth-child(4) {
            text-align: right;
        }
        .inv-table tbody tr:last-child td {
            border-bottom: 2px solid #e2e8f0;
        }

        /* Totals */
        .totals-box {
            margin-left: auto;
            width: 320px;
            margin-bottom: 30px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
        }
        .total-row.grand {
            border-top: 2px solid #1e293b;
            margin-top: 5px;
            padding-top: 12px;
        }
        .total-row.grand span:first-child {
            font-weight: 900;
            font-size: 16px;
            color: #1e293b;
        }
        .total-row.grand span:last-child {
            font-weight: 900;
            font-size: 18px;
            color: #4f46e5;
        }

        /* Payment */
        .payment-box {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 35px;
        }
        .payment-title {
            font-size: 13px;
            font-weight: 800;
            color: #3b82f6;
            text-transform: uppercase;
            border-left: 4px solid #3b82f6;
            padding-left: 12px;
            margin-bottom: 15px;
        }
        .payment-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 14px;
        }

        /* Note */
        .note-box {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 30px;
            font-size: 13px;
            color: #92400e;
        }

        /* Footer Signature */
        .footer-sig {
            display: grid;
            grid-template-columns: 1fr 1fr;
            margin-top: 50px;
            text-align: center;
        }
        .sig-box {
            height: 120px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
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
        <!-- HEADER — Same professional style as medical records -->
        <div class="header">
            <div class="clinic-info">
                <h1><?php echo __('medical.print.clinic_name'); ?></h1>
                <p style="font-weight: 600; font-size: 11px;"><?php echo __('medical.print.clinic_intl_name'); ?></p>
                <p><?php echo __('medical.print.clinic_address'); ?></p>
            </div>
            <div style="text-align: right;">
                <p style="margin: 0; font-size: 12px; color: #94a3b8;">Số: <?php echo $receipt_code; ?></p>
                <p style="margin: 5px 0 0 0; font-weight: 700; color: #3b82f6;">Ngày: <?php echo $invoice_date; ?></p>
                <p style="margin: 3px 0 0 0; font-size: 12px; color: #94a3b8;">Giờ: <?php echo $invoice_time; ?></p>
            </div>
        </div>

        <!-- TITLE -->
        <div class="doc-title">
            <h2>Phiếu Tính Tiền</h2>
        </div>

        <!-- CUSTOMER INFO -->
        <div class="customer-box">
            <div>
                <div class="info-item"><label>Khách hàng:</label> <strong><?php echo e($invoice['patient_name']); ?></strong></div>
                <div class="info-item"><label>SĐT:</label> <?php echo e($invoice['patient_phone']); ?></div>
            </div>
            <div>
                <?php if (!empty($invoice['patient_address'])): ?>
                <div class="info-item"><label>Địa chỉ:</label> <?php echo e($invoice['patient_address']); ?></div>
                <?php endif; ?>
                <div class="info-item"><label>Mã phiếu:</label> <strong style="color: #4f46e5;"><?php echo e($invoice['invoice_no']); ?></strong></div>
            </div>
        </div>

        <!-- ITEMS TABLE -->
        <table class="inv-table">
            <thead>
                <tr>
                    <th style="width: 40px;">STT</th>
                    <th>Nội dung dịch vụ / Sản phẩm</th>
                    <th style="width: 60px;">SL</th>
                    <th style="width: 130px;">Đơn giá</th>
                    <th style="width: 140px;">Thành tiền</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $idx => $item):
                    $qty = $item['qty'] ?? 1;
                    $price = $item['price'] ?? 0;
                    $line_total = $qty * $price;
                ?>
                <tr>
                    <td style="text-align: center; color: #94a3b8; font-weight: 700;"><?php echo $idx + 1; ?></td>
                    <td style="font-weight: 600;">
                        <?php echo e($item['name']); ?>
                        <?php if (!empty($item['type']) && $item['type'] === 'use_package'): ?>
                            <span style="font-size: 11px; background: #eef2ff; color: #4f46e5; padding: 2px 6px; border-radius: 4px; margin-left: 4px;">Gói</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center;"><?php echo $qty; ?></td>
                    <td><?php echo $price > 0 ? number_format($price, 0, ',', '.') . ' đ' : 'Miễn phí'; ?></td>
                    <td style="font-weight: 700;"><?php echo $line_total > 0 ? number_format($line_total, 0, ',', '.') . ' đ' : 'Miễn phí'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- TOTALS -->
        <div class="totals-box">
            <div class="total-row">
                <span style="color: #64748b;">Tổng cộng:</span>
                <span style="font-weight: 700;"><?php echo format_money($invoice['subtotal']); ?></span>
            </div>
            <?php if (floatval($invoice['discount_amount']) > 0): ?>
            <div class="total-row" style="color: #ef4444;">
                <span>Giảm giá<?php echo !empty($invoice['discount_note']) ? ' (' . e($invoice['discount_note']) . ')' : ''; ?>:</span>
                <span style="font-weight: 700;">- <?php echo format_money($invoice['discount_amount']); ?></span>
            </div>
            <?php endif; ?>
            <div class="total-row grand">
                <span>THÀNH TIỀN:</span>
                <span><?php echo format_money($invoice['total_amount']); ?></span>
            </div>
        </div>

        <!-- PAYMENT BREAKDOWN -->
        <div class="payment-box">
            <div class="payment-title">Hình thức thanh toán</div>
            <?php if (floatval($invoice['cash_amount']) > 0): ?>
            <div class="payment-row">
                <span>💵 Tiền mặt</span>
                <span style="font-weight: 700;"><?php echo format_money($invoice['cash_amount']); ?></span>
            </div>
            <?php endif; ?>
            <?php if (floatval($invoice['transfer_personal_amount']) > 0): ?>
            <div class="payment-row">
                <span>🏦 CK Cá nhân</span>
                <span style="font-weight: 700;"><?php echo format_money($invoice['transfer_personal_amount']); ?></span>
            </div>
            <?php endif; ?>
            <?php if (floatval($invoice['transfer_company_amount']) > 0): ?>
            <div class="payment-row">
                <span>🏦 TK Công ty</span>
                <span style="font-weight: 700;"><?php echo format_money($invoice['transfer_company_amount']); ?></span>
            </div>
            <?php endif; ?>
            <?php if (floatval($invoice['transfer_amount']) > 0 && floatval($invoice['transfer_personal_amount']) == 0 && floatval($invoice['transfer_company_amount']) == 0): ?>
            <div class="payment-row">
                <span>🏦 Chuyển khoản (Cũ)</span>
                <span style="font-weight: 700;"><?php echo format_money($invoice['transfer_amount']); ?></span>
            </div>
            <?php endif; ?>
            <?php if (floatval($invoice['debt_amount']) > 0): ?>
            <div class="payment-row" style="color: #dc2626;">
                <span>📝 Ghi nợ</span>
                <span style="font-weight: 800;"><?php echo format_money($invoice['debt_amount']); ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- NOTE -->
        <?php if (!empty($invoice['note'])): ?>
        <div class="note-box">
            <strong>📌 Ghi chú:</strong> <?php echo e($invoice['note']); ?>
        </div>
        <?php endif; ?>

        <!-- SIGNATURE -->
        <div class="footer-sig">
            <div class="sig-box">
                <div>
                    <p>Khách hàng</p>
                    <p style="font-weight: 400; font-size: 12px; color: #94a3b8;">(Ký và ghi rõ họ tên)</p>
                </div>
            </div>
            <div class="sig-box">
                <div>
                    <p>Thu ngân</p>
                    <p style="font-weight: 400; font-size: 12px; color: #94a3b8;">(Ký và xác nhận)</p>
                </div>
                <p><?php echo e($invoice['cashier_name']); ?></p>
            </div>
        </div>

        <!-- FOOTER -->
        <div style="text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
            <p style="font-size: 12px; color: #94a3b8; margin: 0;">Cảm ơn quý khách đã sử dụng dịch vụ tại Simon Center 🙏</p>
            <p style="font-size: 11px; color: #cbd5e1; margin: 5px 0 0 0;"><?php echo __('medical.print.clinic_address'); ?></p>
        </div>
    </div>
</body>
</html>
