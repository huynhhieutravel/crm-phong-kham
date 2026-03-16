<?php
// modules/reports/index.php
require_once '../../includes/db.php';
$page_title = 'Báo cáo & Thống kê';
$current_page = 'reports';
require_once '../../templates/header.php';

$db = getDB();
$month = date('m');
$year = date('Y');

// Total revenue this month
$stmt = $db->prepare("SELECT SUM(amount) FROM transactions WHERE type = 'income' AND MONTH(transaction_date) = ? AND YEAR(transaction_date) = ?");
$stmt->execute([$month, $year]);
$revenue = $stmt->fetchColumn() ?: 0;

// Transactions list
$stmt = $db->prepare("SELECT * FROM transactions ORDER BY transaction_date DESC LIMIT 50");
$stmt->execute();
$transactions = $stmt->fetchAll();
?>

<div class="card" style="margin-bottom: 2rem; background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%); color: white; border: none;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <p style="opacity: 0.8; font-size: 0.9rem;">Doanh thu tháng này (T<?php echo $month; ?>/<?php echo $year; ?>)</p>
            <h2 style="font-size: 2.5rem; margin: 0.5rem 0;"><?php echo format_money($revenue); ?></h2>
        </div>
        <a href="export_excel.php" class="btn" style="background: rgba(255,255,255,0.2); color: white;">
            <i class="fas fa-file-excel"></i> Xuất file Excel
        </a>
    </div>
</div>

<div class="card">
    <h3 style="margin-bottom: 1.5rem;">Lịch sử giao dịch Thu/Chi</h3>
    <table class="table" style="width: 100%;">
        <thead>
            <tr style="text-align: left; border-bottom: 2px solid var(--border-color);">
                <th style="padding: 1rem;">Thời gian</th>
                <th style="padding: 1rem;">Loại</th>
                <th style="padding: 1rem;">Hạng mục</th>
                <th style="padding: 1rem;">Số tiền</th>
                <th style="padding: 1rem;">Mô tả</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($transactions as $tx): ?>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 1rem;"><?php echo date('d/m/Y H:i', strtotime($tx['transaction_date'])); ?></td>
                    <td style="padding: 1rem;">
                        <span style="color: <?php echo $tx['type'] === 'income' ? '#10b981' : '#ef4444'; ?>; font-weight: 700;">
                            <?php echo $tx['type'] === 'income' ? 'THU (+)' : 'CHI (-)'; ?>
                        </span>
                    </td>
                    <td style="padding: 1rem;"><?php echo strtoupper($tx['category']); ?></td>
                    <td style="padding: 1rem; font-weight: 700;"><?php echo format_money($tx['amount']); ?></td>
                    <td style="padding: 1rem; color: var(--text-muted);"><?php echo e($tx['description']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../templates/footer.php'; ?>
