<?php
// modules/medical/forms/print_treatment.php
// Expected variables: $data, $patient, $record
?>

<div class="section-title">NỘI DUNG ĐIỀU TRỊ</div>
<div class="rich-content" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 25px; background: #f8fafc; font-size: 14px; line-height: 1.8;">
    <?php echo nl2br(e($data['session_data'] ?? 'Không có nội dung điều trị.')); ?>
</div>

<?php 
// Load attachments specifically for treatments (treatments don't have attachments natively yet, but just in case)
if (!empty($record['attachments'])):
    $att = json_decode($record['attachments'], true);
    if ($att && !empty($att)):
?>
<div class="section" style="page-break-inside: avoid; margin-top: 30px;">
    <div class="section-title">HÌNH ẢNH ĐÍNH KÈM</div>
    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
        <?php foreach ($att as $a): ?>
        <?php if (strpos(isset($a['type']) ? $a['type'] : '', 'image') !== false): ?>
        <div style="text-align: center;">
            <img src="<?php echo $a['path']; ?>" style="width: 100%; border-radius: 8px; border: 1px solid #e2e8f0;" alt="<?php echo e($a['name']); ?>">
            <div style="font-size: 10px; color: #94a3b8; margin-top: 4px;"><?php echo e($a['name']); ?></div>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>
<?php 
    endif;
endif; 
?>
