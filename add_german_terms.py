import sys

files = [
    'modules/medical/chiro_history_v2.php',
    'modules/medical/follow_up_v2.php'
]

replacements = {
    "<?php echo $is_de ? 'dorsal von HWS' : 'phía sau cột sống cổ'; ?>": "<?php echo $is_de ? 'dorsal von HWS' : 'phía sau cột sống cổ (dorsal von HWS)'; ?>",
    "<?php echo $is_de ? 'frontal' : 'phía trước'; ?>": "<?php echo $is_de ? 'frontal' : 'phía trước (frontal)'; ?>",
    "<?php echo $is_de ? 'lateral' : 'bên hông'; ?>": "<?php echo $is_de ? 'lateral' : 'bên hông (lateral)'; ?>",
    "<?php echo $is_de ? 'ventral' : 'phía trước'; ?>": "<?php echo $is_de ? 'ventral' : 'phía trước (ventral)'; ?>",
    "<?php echo $is_de ? 'dorsal' : 'phía sau'; ?>": "<?php echo $is_de ? 'dorsal' : 'phía sau (dorsal)'; ?>",
    "<?php echo $is_de ? 'lateral' : 'phía bên ngoài'; ?>": "<?php echo $is_de ? 'lateral' : 'phía bên ngoài (lateral)'; ?>",
    "<?php echo $is_de ? 'medial' : 'phía trong'; ?>": "<?php echo $is_de ? 'medial' : 'phía trong (medial)'; ?>",
    "<?php echo $is_de ? 'lateral' : 'phía ngoài'; ?>": "<?php echo $is_de ? 'lateral' : 'phía ngoài (lateral)'; ?>",
    "<?php echo $is_de ? 'proximal' : 'gần'; ?>": "<?php echo $is_de ? 'proximal' : 'gần (proximal)'; ?>",
    "<?php echo $is_de ? 'distal' : 'xa'; ?>": "<?php echo $is_de ? 'distal' : 'xa (distal)'; ?>",
    "<?php echo $is_de ? 'dorsal' : 'mặt mu'; ?>": "<?php echo $is_de ? 'dorsal' : 'mặt mu (dorsal)'; ?>",
    "<?php echo $is_de ? 'plantar' : 'mặt gan'; ?>": "<?php echo $is_de ? 'plantar' : 'mặt gan (plantar)'; ?>",
    
    "<?php echo $is_de ? 'Plattfuß' : 'Bàn chân bẹt'; ?>": "<?php echo $is_de ? 'Plattfuß' : 'Bàn chân bẹt (Plattfuß)'; ?>",
    "<?php echo $is_de ? 'Hohlfuß' : 'Bàn chân lõm/vòm cao'; ?>": "<?php echo $is_de ? 'Hohlfuß' : 'Bàn chân lõm/vòm cao (Hohlfuß)'; ?>",
    "<?php echo $is_de ? 'Sichelfuß' : 'Bàn chân hình liềm'; ?>": "<?php echo $is_de ? 'Sichelfuß' : 'Bàn chân hình liềm (Sichelfuß)'; ?>",
    "<?php echo $is_de ? 'Senkfuß' : 'Sụp vòm'; ?>": "<?php echo $is_de ? 'Senkfuß' : 'Sụp vòm (Senkfuß)'; ?>",
    "<?php echo $is_de ? 'Spreizfuß' : 'Bàn chân xòe/bè'; ?>": "<?php echo $is_de ? 'Spreizfuß' : 'Bàn chân xòe/bè (Spreizfuß)'; ?>",
    "<?php echo $is_de ? 'Knickfuß' : 'Bàn chân vẹo'; ?>": "<?php echo $is_de ? 'Knickfuß' : 'Bàn chân vẹo (Knickfuß)'; ?>",
    "<?php echo $is_de ? 'Fersensporn' : 'Gai gót chân'; ?>": "<?php echo $is_de ? 'Fersensporn' : 'Gai gót chân (Fersensporn)'; ?>",
    "<?php echo $is_de ? 'Morton Neurom' : 'U thần kinh Morton'; ?>": "<?php echo $is_de ? 'Morton Neurom' : 'U thần kinh Morton (Morton Neurom)'; ?>",
    
    "<?php echo $is_de ? 'Bein' : 'Cẳng chân'; ?>": "<?php echo $is_de ? 'Bein' : 'Cẳng chân (Bein)'; ?>",
    "<?php echo $is_de ? 'Knie' : 'Khớp gối'; ?>": "<?php echo $is_de ? 'Knie' : 'Khớp gối (Knie)'; ?>",
    "<?php echo $is_de ? 'Fibula' : 'xương mác'; ?>": "<?php echo $is_de ? 'Fibula' : 'xương mác (Fibula)'; ?>",
    "<?php echo $is_de ? 'Ödem' : 'Phù nề'; ?>": "<?php echo $is_de ? 'Ödem' : 'Phù nề (Ödem)'; ?>",
    "<?php echo $is_de ? 'Ellenbogen' : 'Khuỷu tay'; ?>": "<?php echo $is_de ? 'Ellenbogen' : 'Khuỷu tay (Ellenbogen)'; ?>",
    
    "<?php echo $is_de ? 'Tennisarm (TA)' : 'hội chứng khuỷu tay tennis'; ?>": "<?php echo $is_de ? 'Tennisarm (TA)' : 'hội chứng khuỷu tay tennis (TA)'; ?>",
    "<?php echo $is_de ? 'Golferarm (GA)' : 'hội chứng khuỷu tay golf'; ?>": "<?php echo $is_de ? 'Golferarm (GA)' : 'hội chứng khuỷu tay golf (GA)'; ?>",
    "<?php echo $is_de ? 'Carpaltunnelsyndrom - CTS' : 'Hội chứng ống cổ tay'; ?>": "<?php echo $is_de ? 'Carpaltunnelsyndrom - CTS' : 'Hội chứng ống cổ tay (Carpaltunnelsyndrom - CTS)'; ?>",
    
    "<?php echo $is_de ? 'Pollicis' : 'Ngón cái'; ?>": "<?php echo $is_de ? 'Pollicis' : 'Ngón cái (Pollicis)'; ?>",
    "<?php echo $is_de ? 'Digiti' : 'Ngón tay'; ?>": "<?php echo $is_de ? 'Digiti' : 'Ngón tay (Digiti)'; ?>",
    "<?php echo $is_de ? 'Sattelgelenk (SG)' : 'khớp yên'; ?>": "<?php echo $is_de ? 'Sattelgelenk (SG)' : 'khớp yên (SG)'; ?>",
    "<?php echo $is_de ? 'Grundgelenk (GG)' : 'khớp bàn-ngón'; ?>": "<?php echo $is_de ? 'Grundgelenk (GG)' : 'khớp bàn-ngón (GG)'; ?>",
    "<?php echo $is_de ? 'Metacarpalia (MTC)' : 'Xương bàn tay'; ?>": "<?php echo $is_de ? 'Metacarpalia (MTC)' : 'Xương bàn tay (MTC)'; ?>",
    
    "<?php echo $is_de ? 'OSG' : 'Khớp cổ chân trên'; ?>": "<?php echo $is_de ? 'OSG' : 'Khớp cổ chân trên (OSG)'; ?>",
    "<?php echo $is_de ? 'USG' : 'Khớp cổ chân dưới'; ?>": "<?php echo $is_de ? 'USG' : 'Khớp cổ chân dưới (USG)'; ?>",
    "<?php echo $is_de ? 'sup' : 'xoay ngửa'; ?>": "<?php echo $is_de ? 'sup' : 'xoay ngửa (sup)'; ?>",
    "<?php echo $is_de ? 'pron' : 'xoay sấp'; ?>": "<?php echo $is_de ? 'pron' : 'xoay sấp (pron)'; ?>",
    
    "<?php echo $is_de ? 'Kiefergelenk' : 'Khớp thái dương hàm'; ?>": "<?php echo $is_de ? 'Kiefergelenk' : 'Khớp thái dương hàm (TMJ)'; ?>"
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
