import re
import json

files = [
    'modules/medical/chiro_history_v2.php',
    'modules/medical/follow_up_v2.php'
]

unique_strings = set()

for file in files:
    with open(file, 'r') as f:
        content = f.read()
    
    # Matches <?php echo $is_de ? 'GERMAN' : 'VIETNAMESE'; ?>
    matches = re.findall(r"<\?php echo \$is_de \? '([^']+)' : '([^']+)'; \?>", content)
    for m in matches:
        unique_strings.add(m)
        
    # Matches $is_de ? 'GERMAN' : 'VIETNAMESE'
    matches2 = re.findall(r"\$is_de \? '([^']+)' : '([^']+)'", content)
    for m in matches2:
        unique_strings.add(m)

result = []
for de, vi in unique_strings:
    result.append({"vi": vi, "de": de, "en": ""})

with open('translate_dict.json', 'w') as f:
    json.dump(result, f, indent=4, ensure_ascii=False)

print(f"Extracted {len(result)} unique strings to translate_dict.json")
