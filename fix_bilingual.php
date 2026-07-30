<?php
$files = [
    'modules/medical/chiro_history_v2.php',
    'modules/medical/follow_up_v2.php'
];

$replacements = [
    'Họ Tên (Name)' => "<?php echo \$is_de ? 'Name' : 'Họ Tên'; ?>",
    'Năm Sinh (Geburtsjahr)' => "<?php echo \$is_de ? 'Geburtsjahr' : 'Năm Sinh'; ?>",
    'Ngày Sinh (Geburtsdatum)' => "<?php echo \$is_de ? 'Geburtsdatum' : 'Ngày Sinh'; ?>",
    '< 1T (< 1J)' => "<?php echo \$is_de ? '< 1J' : '< 1T'; ?>",
    'Quan Hệ (Beziehung)' => "<?php echo \$is_de ? 'Beziehung' : 'Quan Hệ'; ?>",
    '-- Chọn khách hàng (Patient wählen) --' => "<?php echo \$is_de ? '-- Patient wählen --' : '-- Chọn khách hàng --'; ?>",
    'Giới Tính (Geschlecht)' => "<?php echo \$is_de ? 'Geschlecht' : 'Giới Tính'; ?>",
    'Lần Khám (Besuch)' => "<?php echo \$is_de ? 'Besuch' : 'Lần Khám'; ?>",
    'Điều Trị Gần Nhất (Letzte Behandlung)' => "<?php echo \$is_de ? 'Letzte Behandlung' : 'Điều Trị Gần Nhất'; ?>",
    'Trọng lượng phần chân (Beinbelastung) (kg)' => "<?php echo \$is_de ? 'Beinbelastung (kg)' : 'Trọng lượng phần chân (kg)'; ?>",
    'Trái (li)...' => "<?php echo \$is_de ? 'li...' : 'Trái...'; ?>",
    'Phải (re)...' => "<?php echo \$is_de ? 're...' : 'Phải...'; ?>",
    'Hình ảnh chụp bệnh nhân (Patientenbilder) (Tối đa 4 ảnh / max. 4 Bilder)' => "<?php echo \$is_de ? 'Patientenbilder (max. 4 Bilder)' : 'Hình ảnh chụp bệnh nhân (Tối đa 4 ảnh)'; ?>",
    'Tải ảnh lên (Hochladen)' => "<?php echo \$is_de ? 'Hochladen' : 'Tải ảnh lên'; ?>",
    'Nhấn để xem lớn (Klicken zum Vergrößern)' => "<?php echo \$is_de ? 'Klicken zum Vergrößern' : 'Nhấn để xem lớn'; ?>",
    'Xoá ảnh này (Dieses Bild löschen)' => "<?php echo \$is_de ? 'Dieses Bild löschen' : 'Xoá ảnh này'; ?>",
    'Lưu Bệnh Án (Speichern)' => "<?php echo \$is_de ? 'Speichern' : 'Lưu Bệnh Án'; ?>",
    'Lưu Follow-Up (Speichern)' => "<?php echo \$is_de ? 'Speichern' : 'Lưu Follow-Up'; ?>",
    'Tạo mới (Neu erstellen)' => "<?php echo \$is_de ? 'Neu erstellen' : 'Tạo mới'; ?>",
    'Ô ghi chú (Notizfeld)' => "<?php echo \$is_de ? 'Notizfeld' : 'Ô ghi chú'; ?>",
    'Ô ghi chú (Notizen):' => "<?php echo \$is_de ? 'Notizen:' : 'Ô ghi chú:'; ?>",
    'Nhập ghi chú hoặc thông tin bổ sung tại đây... (Hier Anmerkungen oder zusätzliche Informationen eingeben...)' => "<?php echo \$is_de ? 'Hier Anmerkungen oder zusätzliche Informationen eingeben...' : 'Nhập ghi chú hoặc thông tin bổ sung tại đây...'; ?>",
    'Sau khi bấm Lưu, ghi chú này sẽ được gom và hiển thị chung với phần ghi chú ở trên. Ghi chú trong phần Bệnh sử sẽ được hiển thị lại trên các bản Tái khám (Follow-Up) về sau.<br><em>(Nach dem Speichern werden diese Notizen zusammengefasst und gemeinsam mit den obigen Notizen angezeigt. Notizen aus der Anamnese werden auf den späteren Follow-Up-Bögen erneut angezeigt.)</em>' => "<?php echo \$is_de ? 'Nach dem Speichern werden diese Notizen zusammengefasst und gemeinsam mit den obigen Notizen angezeigt. Notizen aus der Anamnese werden auf den späteren Follow-Up-Bögen erneut angezeigt.' : 'Sau khi bấm Lưu, ghi chú này sẽ được gom và hiển thị chung với phần ghi chú ở trên. Ghi chú trong phần Bệnh sử sẽ được hiển thị lại trên các bản Tái khám (Follow-Up) về sau.'; ?>",
    'PHẦN 2 – Bệnh lý hiện tại (Aktuelle Pathologie)' => "<?php echo \$is_de ? 'TEIL 2 – AKTUELLE PATHOLOGIE' : 'PHẦN 2 – BỆNH LÝ HIỆN TẠI'; ?>",
    'Đầu (Kopf)' => "<?php echo \$is_de ? 'Kopf' : 'Đầu'; ?>",
    'Cột sống cổ (HWS)' => "<?php echo \$is_de ? 'HWS' : 'Cột sống cổ'; ?>",
    'CS ngực (BWS)' => "<?php echo \$is_de ? 'BWS' : 'CS ngực'; ?>",
    'CS thắt lưng (LWS)' => "<?php echo \$is_de ? 'LWS' : 'CS thắt lưng'; ?>",
    'Vai (Schulter)' => "<?php echo \$is_de ? 'Schulter' : 'Vai'; ?>",
    'Chi trên (Obere Extr.)' => "<?php echo \$is_de ? 'Obere Extr.' : 'Chi trên'; ?>",
    'Chi dưới (Untere Extr.)' => "<?php echo \$is_de ? 'Untere Extr.' : 'Chi dưới'; ?>",
    'Bàn & cổ chân (Fuß & Sprunggelenk)' => "<?php echo \$is_de ? 'Fuß & Sprunggelenk' : 'Bàn & cổ chân'; ?>",
    'Triệu chứng / Vùng (Symptom / Bereich)' => "<?php echo \$is_de ? 'Symptom / Bereich' : 'Triệu chứng / Vùng'; ?>",
    'Tính chất / Vị trí (Eigenschaften / Ort)' => "<?php echo \$is_de ? 'Eigenschaften / Ort' : 'Tính chất / Vị trí'; ?>",
    'Tần suất / Trạng thái (Häufigkeit / Status)' => "<?php echo \$is_de ? 'Häufigkeit / Status' : 'Tần suất / Trạng thái'; ?>",
    'Triệu chứng & chi tiết (Symptome & Details)' => "<?php echo \$is_de ? 'Symptome & Details' : 'Triệu chứng & chi tiết'; ?>",
    'Trạng thái / Lan (Status / Ausstrahlung)' => "<?php echo \$is_de ? 'Status / Ausstrahlung' : 'Trạng thái / Lan'; ?>",
    'Vùng (Bereich)' => "<?php echo \$is_de ? 'Bereich' : 'Vùng'; ?>",
    'Triệu chứng / Vị trí (Symptome / Ort)' => "<?php echo \$is_de ? 'Symptome / Ort' : 'Triệu chứng / Vị trí'; ?>",
    'Hạn chế (Einschränkungen)' => "<?php echo \$is_de ? 'Einschränkungen' : 'Hạn chế'; ?>",
    'Khớp / Vùng (Gelenk / Bereich)' => "<?php echo \$is_de ? 'Gelenk / Bereich' : 'Khớp / Vùng'; ?>",
    'Triệu chứng / Định khu (Symptome / Lokalisation)' => "<?php echo \$is_de ? 'Symptome / Lokalisation' : 'Triệu chứng / Định khu'; ?>",
    'Trạng thái (Status)' => "<?php echo \$is_de ? 'Status' : 'Trạng thái'; ?>",
    'Triệu chứng & phát hiện (Symptome & Befunde)' => "<?php echo \$is_de ? 'Symptome & Befunde' : 'Triệu chứng & phát hiện'; ?>",
    'Hình ảnh lâm sàng (Klinisches Bild)' => "<?php echo \$is_de ? 'Klinisches Bild' : 'Hình ảnh lâm sàng'; ?>",
    'Định khu & giải phẫu (Lokalisation & Anatomie)' => "<?php echo \$is_de ? 'Lokalisation & Anatomie' : 'Định khu & giải phẫu'; ?>",
    'Biến dạng bàn chân (Fußdeformitäten)' => "<?php echo \$is_de ? 'Fußdeformitäten' : 'Biến dạng bàn chân'; ?>",
    "Chọn ảnh, nhấn <strong>Tải ảnh lên (Hochladen)</strong>, sau đó nhấn <strong>Lưu Bệnh Án (Speichern)</strong> ở cuối trang để hoàn tất." => "<?php echo \$is_de ? 'Bild auswählen, auf <strong>Hochladen</strong> klicken, dann am Ende der Seite auf <strong>Speichern</strong> klicken.' : 'Chọn ảnh, nhấn <strong>Tải ảnh lên</strong>, sau đó nhấn <strong>Lưu Bệnh Án</strong> ở cuối trang để hoàn tất.'; ?>",
    "'Nam (Männlich)'" => "\$is_de ? 'Männlich' : 'Nam'",
    "'Nữ (Weiblich)'" => "\$is_de ? 'Weiblich' : 'Nữ'",
    "'Chưa rõ (Unbekannt)'" => "\$is_de ? 'Unbekannt' : 'Chưa rõ'",
    "'Khác (Andere)'" => "\$is_de ? 'Andere' : 'Khác'",
    "'Bản thân (Selbst)'" => "\$is_de ? 'Selbst' : 'Bản thân'",
    "'Lần đầu (Erster Besuch)'" => "\$is_de ? 'Erster Besuch' : 'Lần đầu'",
];

foreach ($files as $file) {
    $content = file_get_contents($file);
    
    // Add $is_de variable definition near the top if not exists
    if (strpos($content, '$is_de =') === false) {
        $content = str_replace(
            "require_once '../../includes/i18n.php';", 
            "require_once '../../includes/i18n.php';\n\$is_de = (\$_SESSION['lang'] ?? 'vi') == 'de';", 
            $content
        );
    }
    
    foreach ($replacements as $search => $replace) {
        $content = str_replace($search, $replace, $content);
    }
    
    file_put_contents($file, $content);
    echo "Processed $file\n";
}
?>
