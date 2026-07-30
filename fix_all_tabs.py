import re
import os

files = [
    "/Users/huynhtronghieu/Documents/WORK Hiếu/quan-ly-phong-kham/modules/medical/chiro_history_v2.php",
    "/Users/huynhtronghieu/Documents/WORK Hiếu/quan-ly-phong-kham/modules/medical/pathologie_v2.php"
]

# Pattern 1: phần trên, phần giữa, phần dưới
pattern_parts = r'<div style="display: flex; align-items: center; gap: 0\.5rem; margin-bottom: 0\.2rem;"><label>(<input type="checkbox"[^>]+>.*?)</label>\s*(<input type="number"[^>]+>)</div>\s*<div style="display: flex; align-items: center; gap: 0\.5rem; margin-bottom: 0\.2rem;"><label>(<input type="checkbox"[^>]+>.*?)</label>\s*(<input type="number"[^>]+>)</div>\s*<div style="display: flex; align-items: center; gap: 0\.5rem; margin-bottom: 0\.2rem;"><label>(<input type="checkbox"[^>]+>.*?)</label>\s*(<input type="number"[^>]+>)</div>'

def repl_parts(m):
    return f'''<div style="display: grid; grid-template-columns: max-content auto; gap: 0.4rem 1rem; align-items: center; margin-bottom: 0.5rem;">
    <label style="margin-bottom:0;">{m.group(1)}</label>
    {m.group(2)}
    <label style="margin-bottom:0;">{m.group(3)}</label>
    {m.group(4)}
    <label style="margin-bottom:0;">{m.group(5)}</label>
    {m.group(6)}
</div>'''

# Pattern 2: cấp tính, mạn tính, tái phát
pattern_status = r'<div style="display: flex; align-items: center; gap: 0\.5rem;"><label>(<input type="checkbox"[^>]+>.*?)</label>\s*(<input type="text"[^>]+>)]</div>\s*<div style="display: flex; align-items: center; gap: 0\.5rem;"><label>(<input type="checkbox"[^>]+>.*?)</label>\s*(<input type="text"[^>]+>)]</div>\s*<div style="display: flex; align-items: center; gap: 0\.5rem;"><label>(<input type="checkbox"[^>]+>.*?)</label>\s*(<input type="text"[^>]+>)]</div>'

def repl_status(m):
    return f'''<div style="display: grid; grid-template-columns: max-content auto max-content; gap: 0.4rem 0.4rem; align-items: center;">
    <label style="margin-bottom:0;">{m.group(1)}</label>
    {m.group(2)}
    <span style="font-weight: 600;">]</span>
    
    <label style="margin-bottom:0;">{m.group(3)}</label>
    {m.group(4)}
    <span style="font-weight: 600;">]</span>
    
    <label style="margin-bottom:0;">{m.group(5)}</label>
    {m.group(6)}
    <span style="font-weight: 600;">]</span>
</div>'''

# Pattern 3: đau nhói, đau âm ỉ, tê bì + yếu cơ, liệt
pattern_symptoms = r'<div style="margin-top: 0\.5rem; display: flex; gap: 1rem; flex-wrap: wrap;"><label>(<input type="checkbox"[^>]+>.*?)</label>\s*<label>(<input type="checkbox"[^>]+>.*?)</label>\s*<label>(<input type="checkbox"[^>]+>.*?)</label></div>\s*<div style="display: flex; gap: 1rem; align-items: center;"><label>(<input type="checkbox"[^>]+>.*?)</label>\s*<label>(<input type="checkbox"[^>]+>.*?)</label></div>'

def repl_symptoms(m):
    return f'''<div style="display: grid; grid-template-columns: max-content max-content max-content; gap: 0.5rem 1rem; margin-top: 0.5rem; margin-bottom: 0.5rem;">
    <label style="margin-bottom:0;">{m.group(1)}</label>
    <label style="margin-bottom:0;">{m.group(2)}</label>
    <label style="margin-bottom:0;">{m.group(3)}</label>
    <label style="margin-bottom:0;">{m.group(4)}</label>
    <label style="margin-bottom:0;">{m.group(5)}</label>
</div>'''

for file_path in files:
    if not os.path.exists(file_path): continue
    with open(file_path, 'r') as f:
        content = f.read()

    original_content = content
    content = re.sub(pattern_parts, repl_parts, content)
    content = re.sub(pattern_status, repl_status, content)
    content = re.sub(pattern_symptoms, repl_symptoms, content)

    if content != original_content:
        with open(file_path, 'w') as f:
            f.write(content)
        print(f"Updated {file_path}")
    else:
        print(f"No changes in {file_path}")

print("Done")
