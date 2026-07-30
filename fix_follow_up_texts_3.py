import sys

filename = 'modules/medical/follow_up_v2.php'
with open(filename, 'r') as f:
    content = f.read()

replacements = {
    "Trước (Zurück)": "<?php echo $is_de ? 'Zurück' : 'Trước'; ?>",
    "Sau (Weiter)": "<?php echo $is_de ? 'Weiter' : 'Sau'; ?>",
    "Hủy (Abbrechen)": "<?php echo $is_de ? 'Abbrechen' : 'Hủy'; ?>",
    "Hình vẽ so sánh (Vergleichsskizzen): Hình lần trước ↔ Hình lần này": "<?php echo $is_de ? 'Vergleichsskizzen: Vorheriges Bild ↔ Aktuelles Bild' : 'Hình vẽ so sánh: Hình lần trước ↔ Hình lần này'; ?>",
    "Diễn tiến so với lần trước (Verlauf im Vergleich zur letzten Behandlung):": "<?php echo $is_de ? 'Verlauf im Vergleich zur letzten Behandlung:' : 'Diễn tiến so với lần trước:'; ?>",
    "Danh sách điều trị – Chi trên (Behandlungsliste – Obere Extremitäten)": "<?php echo $is_de ? 'Behandlungsliste – Obere Extremitäten' : 'Danh sách điều trị – Chi trên'; ?>",
    "Danh sách điều trị – Chi dưới (Behandlungsliste – Untere Extremitäten)": "<?php echo $is_de ? 'Behandlungsliste – Untere Extremitäten' : 'Danh sách điều trị – Chi dưới'; ?>",
    "Cấu trúc / Khớp (Struktur / Gelenk)": "<?php echo $is_de ? 'Struktur / Gelenk' : 'Cấu trúc / Khớp'; ?>",
    "Khớp cùng-chậu (ISG) & Khung chậu (Becken)": "<?php echo $is_de ? 'ISG & Becken' : 'Khớp cùng-chậu (ISG) & Khung chậu'; ?>",
    "Xương cùng (Sacrum)": "<?php echo $is_de ? 'Sacrum' : 'Xương cùng'; ?>",
    "Xương sườn (Rippen)": "<?php echo $is_de ? 'Rippen' : 'Xương sườn'; ?>",
    "Tốt lên (Verbessert)": "<?php echo $is_de ? 'Verbessert' : 'Tốt lên'; ?>",
    "Giữ nguyên (Unverändert)": "<?php echo $is_de ? 'Unverändert' : 'Giữ nguyên'; ?>",
    "Tệ đi (Verschlechtert)": "<?php echo $is_de ? 'Verschlechtert' : 'Tệ đi'; ?>",
    "Sau - T (Dorsal - li)": "<?php echo $is_de ? 'Dorsal - li' : 'Sau - T'; ?>",
    "Sau - P (Dorsal - re)": "<?php echo $is_de ? 'Dorsal - re' : 'Sau - P'; ?>",
    "Sườn (Rippe)": "<?php echo $is_de ? 'Rippe' : 'Sườn'; ?>",
    "Trước - P (Ventral - re)": "<?php echo $is_de ? 'Ventral - re' : 'Trước - P'; ?>",
    "Trước - T (Ventral - li)": "<?php echo $is_de ? 'Ventral - li' : 'Trước - T'; ?>",
    "Khung chậu (Becken)": "<?php echo $is_de ? 'Becken' : 'Khung chậu'; ?>",
    "Khớp mu (Symphyse):": "<?php echo $is_de ? 'Symphyse:' : 'Khớp mu:'; ?>",
    "Xương cụt (Coccyx):": "<?php echo $is_de ? 'Coccyx:' : 'Xương cụt:'; ?>",
    "ra trước (ventral)": "<?php echo $is_de ? 'ventral' : 'ra trước'; ?>",
    "lên trên (cranial)": "<?php echo $is_de ? 'cranial' : 'lên trên'; ?>",
    "xuống dưới (caudal)": "<?php echo $is_de ? 'caudal' : 'xuống dưới'; ?>",
    "'rib1' => 'Xương sườn 1 (Rippe 1)', 'biceps' => 'Cơ nhị đầu (Biceps)', 'rotator' => 'Cơ chóp xoay (Rotatorenmanschette)',": "'rib1' => $is_de ? 'Rippe 1' : 'Xương sườn 1', 'biceps' => $is_de ? 'Biceps' : 'Cơ nhị đầu', 'rotator' => $is_de ? 'Rotatorenmanschette' : 'Cơ chóp xoay',",
    "'acg' => 'Khớp cùng–đòn (ACG)', 'scg' => 'Khớp ức–đòn (SCG)', 'coracoid' => 'Mỏm quạ (Coracoid)',": "'acg' => $is_de ? 'ACG' : 'Khớp cùng đòn', 'scg' => $is_de ? 'SCG' : 'Khớp ức đòn', 'coracoid' => $is_de ? 'Coracoid' : 'Mỏm quạ',",
    "'supras' => 'Cơ trên gai (Supraspinatus)',": "'supras' => $is_de ? 'Supraspinatus' : 'Cơ trên gai',",
    "'hwk' => 'Xương cổ tay / Hội chứng ống cổ tay (Handwurzelknochen / CTS)', 'cts' => 'Hội chứng ống cổ tay (CTS)',": "'hwk' => $is_de ? 'Handwurzelknochen / CTS' : 'Xương cổ tay / Hội chứng ống cổ tay', 'cts' => $is_de ? 'CTS' : 'Hội chứng ống cổ tay',",
    "Viêm lồi cầu ngoài (Tennisarm - TA):": "<?php echo $is_de ? 'Tennisarm (TA):' : 'Viêm lồi cầu ngoài (Tennisarm):'; ?>",
    "Viêm lồi cầu trong (Golferarm - GA):": "<?php echo $is_de ? 'Golferarm (GA):' : 'Viêm lồi cầu trong (Golferarm):'; ?>",
    "trong (med)": "<?php echo $is_de ? 'med' : 'trong'; ?>",
    "ngoài (lat)": "<?php echo $is_de ? 'lat' : 'ngoài'; ?>",
    "Xương bàn tay (Metacarpalia):": "<?php echo $is_de ? 'Metacarpalia:' : 'Xương bàn tay:'; ?>",
    "Ngón cái (Daumen):": "<?php echo $is_de ? 'Daumen:' : 'Ngón cái:'; ?>",
    "khớp yên (Sattelgelenk)": "<?php echo $is_de ? 'Sattelgelenk' : 'khớp yên'; ?>",
    "khớp bàn ngón (Grundgelenk)": "<?php echo $is_de ? 'Grundgelenk' : 'khớp bàn ngón'; ?>",
    "Khớp cổ chân dưới (USG):": "<?php echo $is_de ? 'USG:' : 'Khớp cổ chân dưới:'; ?>",
    "ngửa (sup)": "<?php echo $is_de ? 'sup' : 'ngửa'; ?>",
    "sấp (pron)": "<?php echo $is_de ? 'pron' : 'sấp'; ?>",
    "Khớp cổ chân trên (OSG):": "<?php echo $is_de ? 'OSG:' : 'Khớp cổ chân trên:'; ?>",
    "sau (post)": "<?php echo $is_de ? 'post' : 'sau'; ?>",
    "trước (ant)": "<?php echo $is_de ? 'ant' : 'trước'; ?>",
    "'cuboid' => 'Xương hộp (Os cuboideum)', 'naviculare' => 'Xương ghe (Os naviculare)', 'hallux' => 'Ngón cái vẹo ngoài (Hallux valgus)',": "'cuboid' => $is_de ? 'Os cuboideum' : 'Xương hộp', 'naviculare' => $is_de ? 'Os naviculare' : 'Xương ghe', 'hallux' => $is_de ? 'Hallux valgus' : 'Ngón cái vẹo ngoài',",
    "'knee' => 'Khớp gối (Kniegelenk)', 'patella' => 'Di động xương bánh chè (Patellamobilität)', 'hip' => 'Khớp háng (Hüftgelenk)', 'iliopsoas' => 'Cơ thắt lưng chậu (Iliopsoas)'": "'knee' => $is_de ? 'Kniegelenk' : 'Khớp gối', 'patella' => $is_de ? 'Patellamobilität' : 'Di động xương bánh chè', 'hip' => $is_de ? 'Hüftgelenk' : 'Khớp háng', 'iliopsoas' => $is_de ? 'Iliopsoas' : 'Cơ thắt lưng chậu'",
    "Xương chêm (Ossa cuneiformia):": "<?php echo $is_de ? 'Ossa cuneiformia:' : 'Xương chêm:'; ?>",
    "trong (mediale)": "<?php echo $is_de ? 'mediale' : 'trong'; ?>",
    "giữa (intermedium)": "<?php echo $is_de ? 'intermedium' : 'giữa'; ?>",
    "ngoài (laterale)": "<?php echo $is_de ? 'laterale' : 'ngoài'; ?>",
    "Xương bàn chân (Metatarsalia - MTT):": "<?php echo $is_de ? 'Metatarsalia (MTT):' : 'Xương bàn chân:'; ?>",
    "mu (dorsal)": "<?php echo $is_de ? 'dorsal' : 'mu'; ?>",
    "gan (plantar)": "<?php echo $is_de ? 'plantar' : 'gan'; ?>",
    "T | P": "<?php echo $is_de ? 'li | re' : 'T | P'; ?>"
}

for k, v in replacements.items():
    if k in content:
        content = content.replace(k, v)
        print(f"Replaced: {k[:30]}...")
    else:
        print(f"NOT FOUND: {k}")

with open(filename, 'w') as f:
    f.write(content)
