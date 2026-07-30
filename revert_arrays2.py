import sys

files = [
    'modules/medical/chiro_history_v2.php'
]

replacements = {
    "'Occiput' : 'Xương chẩm'": "'Occiput' : 'Xương chẩm (Occiput)'",
    "'Kiefergelenk (TMJ)' : 'Khớp thái dương hàm'": "'Kiefergelenk (TMJ)' : 'Khớp thái dương hàm (TMJ)'",
    "'Atlas (C1)' : 'Đốt đội (C1)'": "'Atlas (C1)' : 'Đốt đội (Atlas C1)'",
    "'Axis (C2)' : 'Đốt trục (C2)'": "'Axis (C2)' : 'Đốt trục (Axis C2)'",
    "<?php echo $is_de ? 'Iliopsoas' : 'Cơ thắt lưng chậu'; ?>": "<?php echo $is_de ? 'Iliopsoas' : 'Cơ thắt lưng chậu (Iliopsoas)'; ?>",
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
