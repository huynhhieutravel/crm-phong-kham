with open("modules/medical/chiro_history_v2.php", "r", encoding="utf-8") as f:
    content = f.read()

with open("new_section.html", "r", encoding="utf-8") as f:
    new_html = f.read()

target = '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; margin-top: 2.5rem;">'
replacement = new_html + "\n            " + target

if target in content:
    content = content.replace(target, replacement)
    with open("modules/medical/chiro_history_v2.php", "w", encoding="utf-8") as f:
        f.write(content)
    print("Injected successfully!")
else:
    print("Target not found!")
