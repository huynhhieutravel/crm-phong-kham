import json

with open('translate_dict_en.json', 'r') as f:
    data = json.load(f)

for item in data:
    if not item['en']:
        print(f"DE: {item['de']} | VI: {item['vi']}")
