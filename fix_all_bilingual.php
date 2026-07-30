<?php
$file = 'modules/medical/chiro_history_v2.php';
$content = file_get_contents($file);

$replacements = [
    'Hạn chế vận động về phía (Bewegung eingeschränkt nach):' => "<?php echo \$is_de ? 'Bewegung eingeschränkt nach:' : 'Hạn chế vận động về phía:'; ?>",
    'Đau lan (Ausstrahlung):' => "<?php echo \$is_de ? 'Ausstrahlung:' : 'Đau lan:'; ?>",
    'Phải (re)' => "<?php echo \$is_de ? 're' : 'Phải'; ?>",
    'Trái (li)' => "<?php echo \$is_de ? 'li' : 'Trái'; ?>",
    'khi (bei):' => "<?php echo \$is_de ? 'bei:' : 'khi:'; ?>",
    'vận động (Bewegung)' => "<?php echo \$is_de ? 'Bewegung' : 'vận động'; ?>",
    'nghỉ (Ruhe)' => "<?php echo \$is_de ? 'Ruhe' : 'nghỉ'; ?>",
    'đau nhói (stechend)' => "<?php echo \$is_de ? 'stechend' : 'đau nhói'; ?>",
    'đau âm ỉ (dumpf)' => "<?php echo \$is_de ? 'dumpf' : 'đau âm ỉ'; ?>",
    'tê bì (Taubheit)' => "<?php echo \$is_de ? 'Taubheit' : 'tê bì'; ?>",
    'yếu cơ (Schwäche)' => "<?php echo \$is_de ? 'Schwäche' : 'yếu cơ'; ?>",
    'liệt (Lähmung)' => "<?php echo \$is_de ? 'Lähmung' : 'liệt'; ?>",
    'Đau TK liên sườn (ICN):' => "<?php echo \$is_de ? 'ICN:' : 'Đau TK liên sườn:'; ?>",
    'bên hông (Leiste/Flanke)' => "<?php echo \$is_de ? 'Leiste/Flanke' : 'bên hông'; ?>",
    'Thang đau (Schmerzskala):' => "<?php echo \$is_de ? 'Schmerzskala:' : 'Thang đau:'; ?>",
    'lan theo hướng nhất định (Ausstrahlung in eine bestimmte Richtung)' => "<?php echo \$is_de ? 'Ausstrahlung in eine bestimmte Richtung' : 'lan theo hướng nhất định'; ?>",
    'lan (Ausstrahlung)' => "<?php echo \$is_de ? 'Ausstrahlung' : 'lan'; ?>",
    'phần trên (obere)' => "<?php echo \$is_de ? 'obere' : 'phần trên'; ?>",
    'phần giữa (mittlere)' => "<?php echo \$is_de ? 'mittlere' : 'phần giữa'; ?>",
    'phần dưới (untere)' => "<?php echo \$is_de ? 'untere' : 'phần dưới'; ?>",
    'bên phải (rechts)' => "<?php echo \$is_de ? 'rechts' : 'bên phải'; ?>",
    'bên trái (links)' => "<?php echo \$is_de ? 'links' : 'bên trái'; ?>",
    'xung quanh (diffus)' => "<?php echo \$is_de ? 'diffus' : 'xung quanh'; ?>",
    'liên tục (dauerhaft)' => "<?php echo \$is_de ? 'dauerhaft' : 'liên tục'; ?>",
    'thỉnh thoảng (gelegentlich)' => "<?php echo \$is_de ? 'gelegentlich' : 'thỉnh thoảng'; ?>",
    'nhiều lần (häufig)' => "<?php echo \$is_de ? 'häufig' : 'nhiều lần'; ?>",
    'bất chợt (plötzlich)' => "<?php echo \$is_de ? 'plötzlich' : 'bất chợt'; ?>",
    'khi ấn (bei Druck)' => "<?php echo \$is_de ? 'bei Druck' : 'khi ấn'; ?>",
    'về ban đêm (nachts)' => "<?php echo \$is_de ? 'nachts' : 'về ban đêm'; ?>",
    'khi ngồi (im Sitzen)' => "<?php echo \$is_de ? 'im Sitzen' : 'khi ngồi'; ?>",
    'khi đứng (im Stehen)' => "<?php echo \$is_de ? 'im Stehen' : 'khi đứng'; ?>",
    'khi đi (beim Gehen)' => "<?php echo \$is_de ? 'beim Gehen' : 'khi đi'; ?>",
    'khi ho / hắt hơi (beim Husten / Niesen)' => "<?php echo \$is_de ? 'beim Husten / Niesen' : 'khi ho / hắt hơi'; ?>",
    'sau khi ngủ dậy (nach dem Aufwachen)' => "<?php echo \$is_de ? 'nach dem Aufwachen' : 'sau khi ngủ dậy'; ?>",
    'khi cúi (beim Beugen)' => "<?php echo \$is_de ? 'beim Beugen' : 'khi cúi'; ?>",
    'khi ngửa (beim Strecken)' => "<?php echo \$is_de ? 'beim Strecken' : 'khi ngửa'; ?>",
    'khi nâng đồ (beim Heben)' => "<?php echo \$is_de ? 'beim Heben' : 'khi nâng đồ'; ?>",
    'lên chi trên (in die obere Extremität)' => "<?php echo \$is_de ? 'in die obere Extremität' : 'lên chi trên'; ?>",
    'lên chi dưới (in die untere Extremität)' => "<?php echo \$is_de ? 'in die untere Extremität' : 'lên chi dưới'; ?>",
    'vào mông (ins Gesäß)' => "<?php echo \$is_de ? 'ins Gesäß' : 'vào mông'; ?>",
    'sưng (Schwellung)' => "<?php echo \$is_de ? 'Schwellung' : 'sưng'; ?>",
    'nóng đỏ (Überwärmung / Rötung)' => "<?php echo \$is_de ? 'Überwärmung / Rötung' : 'nóng đỏ'; ?>",
    'co cứng (Muskelhartspann)' => "<?php echo \$is_de ? 'Muskelhartspann' : 'co cứng'; ?>",
    'hạn chế vận động (Bewegungseinschränkung)' => "<?php echo \$is_de ? 'Bewegungseinschränkung' : 'hạn chế vận động'; ?>",
    'chưa rõ (unbekannt)' => "<?php echo \$is_de ? 'unbekannt' : 'chưa rõ'; ?>"
];

foreach ($replacements as $search => $replace) {
    $content = str_replace($search, $replace, $content);
}

file_put_contents($file, $content);
echo "Processed $file\n";
?>
