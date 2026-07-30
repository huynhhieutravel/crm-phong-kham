<?php
$content = file_get_contents('modules/medical/chiro_history_v2.php');

// We want to replace patterns like: Đầu (Kopf) with <?php echo $is_de ? 'Kopf' : 'Đầu'; ? >
// Or better: $l == 'de' ? 'Kopf' : 'Đầu'
// But wait, it's HTML. So <?php echo $is_de ? 'Kopf' : 'Đầu'; ? > is right.
// However, there are many variations.
