import sys
import os

files = [
    'modules/medical/chiro_history_v2.php',
    'modules/medical/follow_up_v2.php'
]

replacements = {
    "<?php echo $is_de ? 'TEIL 2 – AKTUELLE PATHOLOGIE' : 'PHẦN 2 – BỆNH LÝ HIỆN TẠI'; ?>": "<?php echo $is_de ? 'TEIL 2 – AKTUELLE PATHOLOGIE' : 'PHẦN 2 (tiếng Việt) – Bệnh lý hiện tại (Aktuelle Pathologie)'; ?>",
    "<?php echo $is_de ? 'Kopf' : 'Đầu'; ?>": "<?php echo $is_de ? 'Kopf' : 'Đầu (Kopf)'; ?>",
    "<?php echo $is_de ? 'HWS' : 'Cột sống cổ'; ?>": "<?php echo $is_de ? 'HWS' : 'Cột sống cổ (HWS)'; ?>",
    "<?php echo $is_de ? 'BWS' : 'CS ngực'; ?>": "<?php echo $is_de ? 'BWS' : 'CS ngực (BWS)'; ?>",
    "<?php echo $is_de ? 'LWS' : 'CS thắt lưng'; ?>": "<?php echo $is_de ? 'LWS' : 'CS thắt lưng (LWS)'; ?>",
    "<?php echo $is_de ? 'Schulter' : 'Vai'; ?>": "<?php echo $is_de ? 'Schulter' : 'Vai (Schulter)'; ?>",
    "<?php echo $is_de ? 'Obere Extr.' : 'Chi trên'; ?>": "<?php echo $is_de ? 'Obere Extr.' : 'Chi trên (Obere Extr.)'; ?>",
    "<?php echo $is_de ? 'Untere Extr.' : 'Chi dưới'; ?>": "<?php echo $is_de ? 'Untere Extr.' : 'Chi dưới (Untere Extr.)'; ?>",
    "<?php echo $is_de ? 'Fuß & Sprunggelenk' : 'Bàn & cổ chân'; ?>": "<?php echo $is_de ? 'Fuß & Sprunggelenk' : 'Bàn & cổ chân (Fuß & Sprunggelenk)'; ?>",
    
    "<?php echo $is_de ? 'Symptom / Bereich' : 'Triệu chứng / Vùng'; ?>": "<?php echo $is_de ? 'Symptom / Bereich' : 'Triệu chứng / Vùng (Symptom / Bereich)'; ?>",
    "<?php echo $is_de ? 'Eigenschaften / Ort' : 'Tính chất / Vị trí'; ?>": "<?php echo $is_de ? 'Eigenschaften / Ort' : 'Tính chất / Vị trí (Eigenschaften / Ort)'; ?>",
    "<?php echo $is_de ? 'Häufigkeit / Status' : 'Tần suất / Trạng thái'; ?>": "<?php echo $is_de ? 'Häufigkeit / Status' : 'Tần suất / Trạng thái (Häufigkeit / Status)'; ?>",
    "<?php echo $is_de ? 'Symptome & Details' : 'Triệu chứng & chi tiết'; ?>": "<?php echo $is_de ? 'Symptome & Details' : 'Triệu chứng & chi tiết (Symptome & Details)'; ?>",
    "<?php echo $is_de ? 'Status / Ausstrahlung' : 'Trạng thái / Lan'; ?>": "<?php echo $is_de ? 'Status / Ausstrahlung' : 'Trạng thái / Lan (Status / Ausstrahlung)'; ?>",
    "<?php echo $is_de ? 'Bereich' : 'Vùng'; ?>": "<?php echo $is_de ? 'Bereich' : 'Vùng (Bereich)'; ?>",
    "<?php echo $is_de ? 'Symptome / Ort' : 'Triệu chứng / Vị trí'; ?>": "<?php echo $is_de ? 'Symptome / Ort' : 'Triệu chứng / Vị trí (Symptome / Ort)'; ?>",
    "<?php echo $is_de ? 'Einschränkungen' : 'Hạn chế'; ?>": "<?php echo $is_de ? 'Einschränkungen' : 'Hạn chế (Einschränkungen)'; ?>",
    "<?php echo $is_de ? 'Gelenk / Bereich' : 'Khớp / Vùng'; ?>": "<?php echo $is_de ? 'Gelenk / Bereich' : 'Khớp / Vùng (Gelenk / Bereich)'; ?>",
    "<?php echo $is_de ? 'Symptome / Lokalisation' : 'Triệu chứng / Định khu'; ?>": "<?php echo $is_de ? 'Symptome / Lokalisation' : 'Triệu chứng / Định khu (Symptome / Lokalisation)'; ?>",
    "<?php echo $is_de ? 'Status' : 'Trạng thái'; ?>": "<?php echo $is_de ? 'Status' : 'Trạng thái (Status)'; ?>",
    "<?php echo $is_de ? 'Symptome & Befunde' : 'Triệu chứng & phát hiện'; ?>": "<?php echo $is_de ? 'Symptome & Befunde' : 'Triệu chứng & phát hiện (Symptome & Befunde)'; ?>",
    "<?php echo $is_de ? 'Klinisches Bild' : 'Hình ảnh lâm sàng'; ?>": "<?php echo $is_de ? 'Klinisches Bild' : 'Hình ảnh lâm sàng (Klinisches Bild)'; ?>",
    "<?php echo $is_de ? 'Lokalisation & Anatomie' : 'Định khu & giải phẫu'; ?>": "<?php echo $is_de ? 'Lokalisation & Anatomie' : 'Định khu & giải phẫu (Lokalisation & Anatomie)'; ?>",
    "<?php echo $is_de ? 'Fußdeformitäten' : 'Biến dạng bàn chân'; ?>": "<?php echo $is_de ? 'Fußdeformitäten' : 'Biến dạng bàn chân (Fußdeformitäten)'; ?>",

    "<?php echo $is_de ? 'Behandlungsliste – Obere Extremitäten' : 'Danh sách điều trị – Chi trên'; ?>": "<?php echo $is_de ? 'Behandlungsliste – Obere Extremitäten' : 'Danh sách điều trị – Chi trên (Behandlungsliste – Obere Extremitäten)'; ?>",
    "<?php echo $is_de ? 'Behandlungsliste – Untere Extremitäten' : 'Danh sách điều trị – Chi dưới'; ?>": "<?php echo $is_de ? 'Behandlungsliste – Untere Extremitäten' : 'Danh sách điều trị – Chi dưới (Behandlungsliste – Untere Extremitäten)'; ?>",
    "<?php echo $is_de ? 'Struktur / Gelenk' : 'Cấu trúc / Khớp'; ?>": "<?php echo $is_de ? 'Struktur / Gelenk' : 'Cấu trúc / Khớp (Struktur / Gelenk)'; ?>",
    "<?php echo $is_de ? 'ISG & Becken' : 'Khớp cùng-chậu (ISG) & Khung chậu'; ?>": "<?php echo $is_de ? 'ISG & Becken' : 'Khớp cùng-chậu (ISG) & Khung chậu (Becken)'; ?>",
    "<?php echo $is_de ? 'Sacrum' : 'Xương cùng'; ?>": "<?php echo $is_de ? 'Sacrum' : 'Xương cùng (Sacrum)'; ?>",
    "<?php echo $is_de ? 'Rippen' : 'Xương sườn'; ?>": "<?php echo $is_de ? 'Rippen' : 'Xương sườn (Rippen)'; ?>",
    "<?php echo $is_de ? 'Becken' : 'Khung chậu'; ?>": "<?php echo $is_de ? 'Becken' : 'Khung chậu (Becken)'; ?>",
    "<?php echo $is_de ? 'Symphyse:' : 'Khớp mu:'; ?>": "<?php echo $is_de ? 'Symphyse:' : 'Khớp mu (Symphyse):'; ?>",
    "<?php echo $is_de ? 'Os coccygis:' : 'Xương cụt:'; ?>": "<?php echo $is_de ? 'Os coccygis:' : 'Xương cụt (Os coccygis):'; ?>",
    "<?php echo $is_de ? 'ventral' : 'ra trước'; ?>": "<?php echo $is_de ? 'ventral' : 'ra trước (ventral)'; ?>",
    "<?php echo $is_de ? 'cranial' : 'lên trên'; ?>": "<?php echo $is_de ? 'cranial' : 'lên trên (cranial)'; ?>",
    "<?php echo $is_de ? 'caudal' : 'xuống dưới'; ?>": "<?php echo $is_de ? 'caudal' : 'xuống dưới (caudal)'; ?>",
    "<?php echo $is_de ? 'Rippe 1' : 'Xương sườn 1'": "<?php echo $is_de ? 'Rippe 1' : 'Xương sườn 1 (Rippe 1)'",
    "<?php echo $is_de ? 'Bizeps' : 'Cơ nhị đầu'": "<?php echo $is_de ? 'Bizeps' : 'Cơ nhị đầu (Bizeps)'",
    "<?php echo $is_de ? 'Rotatorenmanschette' : 'Cơ chóp xoay'": "<?php echo $is_de ? 'Rotatorenmanschette' : 'Cơ chóp xoay (Rotatorenmanschette)'",
    "<?php echo $is_de ? 'ACG' : 'Khớp cùng đòn'": "<?php echo $is_de ? 'ACG' : 'Khớp cùng đòn (ACG)'",
    "<?php echo $is_de ? 'SCG' : 'Khớp ức đòn'": "<?php echo $is_de ? 'SCG' : 'Khớp ức đòn (SCG)'",
    "<?php echo $is_de ? 'Proc. coracoideus' : 'Mỏm quạ'": "<?php echo $is_de ? 'Proc. coracoideus' : 'Mỏm quạ (Proc. coracoideus)'",
    "<?php echo $is_de ? 'Supraspinatus' : 'Cơ trên gai'": "<?php echo $is_de ? 'Supraspinatus' : 'Cơ trên gai (Supraspinatus)'",
    "<?php echo $is_de ? 'Handwurzelknochen / CTS' : 'Xương cổ tay / Hội chứng ống cổ tay'": "<?php echo $is_de ? 'Handwurzelknochen / CTS' : 'Xương cổ tay / Hội chứng ống cổ tay (Handwurzelknochen / CTS)'",
    "<?php echo $is_de ? 'CTS' : 'Hội chứng ống cổ tay'": "<?php echo $is_de ? 'CTS' : 'Hội chứng ống cổ tay (CTS)'",
    "<?php echo $is_de ? 'Tennisarm (TA):' : 'Viêm lồi cầu ngoài (Tennisarm):'; ?>": "<?php echo $is_de ? 'Tennisarm (TA):' : 'Viêm lồi cầu ngoài (Tennisarm - TA):'; ?>",
    "<?php echo $is_de ? 'Golferarm (GA):' : 'Viêm lồi cầu trong (Golferarm):'; ?>": "<?php echo $is_de ? 'Golferarm (GA):' : 'Viêm lồi cầu trong (Golferarm - GA):'; ?>",
    "<?php echo $is_de ? 'med' : 'trong'; ?>": "<?php echo $is_de ? 'med' : 'trong (med)'; ?>",
    "<?php echo $is_de ? 'lat' : 'ngoài'; ?>": "<?php echo $is_de ? 'lat' : 'ngoài (lat)'; ?>",
    "<?php echo $is_de ? 'Metacarpalia:' : 'Xương bàn tay:'; ?>": "<?php echo $is_de ? 'Metacarpalia:' : 'Xương bàn tay (Metacarpalia):'; ?>",
    "<?php echo $is_de ? 'Daumen:' : 'Ngón cái:'; ?>": "<?php echo $is_de ? 'Daumen:' : 'Ngón cái (Daumen):'; ?>",
    "<?php echo $is_de ? 'Sattelgelenk' : 'khớp yên'; ?>": "<?php echo $is_de ? 'Sattelgelenk' : 'khớp yên (Sattelgelenk)'; ?>",
    "<?php echo $is_de ? 'Grundgelenk' : 'khớp bàn ngón'; ?>": "<?php echo $is_de ? 'Grundgelenk' : 'khớp bàn ngón (Grundgelenk)'; ?>",
    "<?php echo $is_de ? 'USG:' : 'Khớp cổ chân dưới:'; ?>": "<?php echo $is_de ? 'USG:' : 'Khớp cổ chân dưới (USG):'; ?>",
    "<?php echo $is_de ? 'sup' : 'ngửa'; ?>": "<?php echo $is_de ? 'sup' : 'ngửa (sup)'; ?>",
    "<?php echo $is_de ? 'pron' : 'sấp'; ?>": "<?php echo $is_de ? 'pron' : 'sấp (pron)'; ?>",
    "<?php echo $is_de ? 'OSG:' : 'Khớp cổ chân trên:'; ?>": "<?php echo $is_de ? 'OSG:' : 'Khớp cổ chân trên (OSG):'; ?>",
    "<?php echo $is_de ? 'post' : 'sau'; ?>": "<?php echo $is_de ? 'post' : 'sau (post)'; ?>",
    "<?php echo $is_de ? 'ant' : 'trước'; ?>": "<?php echo $is_de ? 'ant' : 'trước (ant)'; ?>",
    "<?php echo $is_de ? 'Os cuboideum' : 'Xương hộp'": "<?php echo $is_de ? 'Os cuboideum' : 'Xương hộp (Os cuboideum)'",
    "<?php echo $is_de ? 'Os naviculare' : 'Xương ghe'": "<?php echo $is_de ? 'Os naviculare' : 'Xương ghe (Os naviculare)'",
    "<?php echo $is_de ? 'Hallux valgus' : 'Ngón cái vẹo ngoài'": "<?php echo $is_de ? 'Hallux valgus' : 'Ngón cái vẹo ngoài (Hallux valgus)'",
    "<?php echo $is_de ? 'Kniegelenk' : 'Khớp gối'": "<?php echo $is_de ? 'Kniegelenk' : 'Khớp gối (Kniegelenk)'",
    "<?php echo $is_de ? 'Patellamobilität' : 'Di động xương bánh chè'": "<?php echo $is_de ? 'Patellamobilität' : 'Di động xương bánh chè (Patellamobilität)'",
    "<?php echo $is_de ? 'Hüftgelenk' : 'Khớp háng'": "<?php echo $is_de ? 'Hüftgelenk' : 'Khớp háng (Hüftgelenk)'",
    "<?php echo $is_de ? 'Iliopsoas' : 'Cơ thắt lưng chậu'": "<?php echo $is_de ? 'Iliopsoas' : 'Cơ thắt lưng chậu (Iliopsoas)'",
    "<?php echo $is_de ? 'Ossa cuneiformia:' : 'Xương chêm:'; ?>": "<?php echo $is_de ? 'Ossa cuneiformia:' : 'Xương chêm (Ossa cuneiformia):'; ?>",
    "<?php echo $is_de ? 'mediale' : 'trong'; ?>": "<?php echo $is_de ? 'mediale' : 'trong (mediale)'; ?>",
    "<?php echo $is_de ? 'intermedium' : 'giữa'; ?>": "<?php echo $is_de ? 'intermedium' : 'giữa (intermedium)'; ?>",
    "<?php echo $is_de ? 'laterale' : 'ngoài'; ?>": "<?php echo $is_de ? 'laterale' : 'ngoài (laterale)'; ?>",
    "<?php echo $is_de ? 'Metatarsalia (MTT):' : 'Xương bàn chân:'; ?>": "<?php echo $is_de ? 'Metatarsalia (MTT):' : 'Xương bàn chân (Metatarsalia - MTT):'; ?>",
    "<?php echo $is_de ? 'dorsal' : 'mu'; ?>": "<?php echo $is_de ? 'dorsal' : 'mu (dorsal)'; ?>",
    "<?php echo $is_de ? 'plantar' : 'gan'; ?>": "<?php echo $is_de ? 'plantar' : 'gan (plantar)'; ?>",
    
    "<?php echo $is_de ? 'Occiput' : 'Xương chẩm'": "<?php echo $is_de ? 'Occiput' : 'Xương chẩm (Occiput)'",
    "<?php echo $is_de ? 'Kiefergelenk (TMJ)' : 'Khớp thái dương hàm'": "<?php echo $is_de ? 'Kiefergelenk (TMJ)' : 'Khớp thái dương hàm (TMJ)'",
    "<?php echo $is_de ? 'Atlas (C1)' : 'Đốt đội (C1)'": "<?php echo $is_de ? 'Atlas (C1)' : 'Đốt đội (Atlas C1)'",
    "<?php echo $is_de ? 'Axis (C2)' : 'Đốt trục (C2)'": "<?php echo $is_de ? 'Axis (C2)' : 'Đốt trục (Axis C2)'",
}

for file in files:
    with open(file, 'r') as f:
        content = f.read()
    
    for k, v in replacements.items():
        if k in content:
            content = content.replace(k, v)
            print(f"[{file}] Replaced: {k[:40]}...")
    
    with open(file, 'w') as f:
        f.write(content)
