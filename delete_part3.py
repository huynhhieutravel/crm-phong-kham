with open("modules/medical/pathologie_v2.php", "r", encoding="utf-8") as f:
    lines = f.readlines()

new_lines = []
skip = False
for line in lines:
    if "<!-- PART 2: SƠ ĐỒ ĐIỂM ĐAU & GHI CHÚ -->" in line:
        skip = True
    
    if skip and '<div style="margin-top: 3.5rem; display: flex; gap: 1.5rem; justify-content: flex-end; border-top: 2px solid #f1f5f9; padding-top: 2rem;">' in line:
        skip = False

    if not skip:
        new_lines.append(line)

with open("modules/medical/pathologie_v2.php", "w", encoding="utf-8") as f:
    f.writelines(new_lines)

print("Deleted Part 3 successfully!")
