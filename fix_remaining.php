<?php
$file = 'modules/medical/chiro_history_v2.php';
$content = file_get_contents($file);

$replacements = [
    'phía trước (frontal)' => "<?php echo \$is_de ? 'frontal' : 'phía trước'; ?>",
    'bên hông (lateral)' => "<?php echo \$is_de ? 'lateral' : 'bên hông'; ?>",
    'một bên (einseitig)' => "<?php echo \$is_de ? 'einseitig' : 'một bên'; ?>",
    'hằng ngày (täglich)' => "<?php echo \$is_de ? 'täglich' : 'hằng ngày'; ?>",
    '2-3 lần/tuần (2-3x/W)' => "<?php echo \$is_de ? '2-3x/W' : '2-3 lần/tuần'; ?>",
    'thỉnh thoảng (sporadisch)' => "<?php echo \$is_de ? 'sporadisch' : 'thỉnh thoảng'; ?>",
    'Chóng mặt (Schwindel)' => "<?php echo \$is_de ? 'Schwindel' : 'Chóng mặt'; ?>",
    'ngày (Tage)' => "<?php echo \$is_de ? 'Tage' : 'ngày'; ?>",
    'tuần (Wochen)' => "<?php echo \$is_de ? 'Wochen' : 'tuần'; ?>",
    'tháng (Monate)' => "<?php echo \$is_de ? 'Monate' : 'tháng'; ?>",
    'năm (Jahre)' => "<?php echo \$is_de ? 'Jahre' : 'năm'; ?>",
    'liên tục (permanent)' => "<?php echo \$is_de ? 'permanent' : 'liên tục'; ?>",
    'Ù tai (Ohrgeräusch)' => "<?php echo \$is_de ? 'Ohrgeräusch' : 'Ù tai'; ?>",
    'tiếng ù (rauschen)' => "<?php echo \$is_de ? 'rauschen' : 'tiếng ù'; ?>",
    'tiếng rít (pfeifen)' => "<?php echo \$is_de ? 'pfeifen' : 'tiếng rít'; ?>",
    'Khớp thái dương hàm (Kiefergelenk)' => "<?php echo \$is_de ? 'Kiefergelenk' : 'Khớp thái dương hàm'; ?>",
    'lục cục (Knacken)' => "<?php echo \$is_de ? 'Knacken' : 'lục cục'; ?>",
    'không thể cử động / bị kẹt (Bewegungseinschränkung/Blockade)' => "<?php echo \$is_de ? 'Bewegungseinschränkung/Blockade' : 'không thể cử động / bị kẹt'; ?>"
];

foreach ($replacements as $search => $replace) {
    $content = str_replace($search, $replace, $content);
}

file_put_contents($file, $content);
echo "Processed $file\n";
?>
