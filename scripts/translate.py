import re
import json
import sys

def update_lang(file_path, mappings):
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()

    for key, new_val in mappings.items():
        new_val_escaped = new_val.replace("'", "\\'")
        pattern = r"(['\"]" + re.escape(key) + r"['\"]\s*=>\s*)['\"].*?['\"]"
        replacement = r"\g<1>'" + new_val_escaped + r"'"
        content = re.sub(pattern, replacement, content, flags=re.DOTALL)

    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(content)

if __name__ == "__main__":
    file_path = sys.argv[1]
    with open(sys.argv[2], 'r', encoding='utf-8') as fj:
        mappings = json.load(fj)
    update_lang(file_path, mappings)
