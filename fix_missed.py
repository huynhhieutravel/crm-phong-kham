import sys

files = [
    'modules/medical/chiro_history_v2.php'
]

replacements = {
    "<strong>Cẳng chân</strong>": "<strong><?php echo $is_de ? 'Bein' : 'Cẳng chân (Bein)'; ?></strong>",
    "<strong>Khớp gối</strong>": "<strong><?php echo $is_de ? 'Knie' : 'Khớp gối (Knie)'; ?></strong>",
    "<em>Bein</em>": "",
    "<em>Knie</em>": "",
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
