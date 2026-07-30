import os

pathologie = "modules/medical/pathologie_v2.php"
chiro = "modules/medical/chiro_history_v2.php"

with open(pathologie, "r") as f:
    p_lines = f.readlines()

start_idx = -1
for i, line in enumerate(p_lines):
    if "<!-- ============================================== -->" in line and "PHẦN 2: BỆNH LÝ HIỆN TẠI (AKTUELLE PATHOLOGIE)" in p_lines[i+1]:
        start_idx = i
        break

end_idx = -1
if start_idx != -1:
    for i in range(start_idx, len(p_lines)):
        if "</script>" in p_lines[i]:
            end_idx = i
            break

if start_idx != -1 and end_idx != -1:
    block_to_insert = p_lines[start_idx:end_idx+1]
    
    with open(chiro, "r") as f:
        c_lines = f.readlines()
        
    insert_idx = -1
    for i, line in enumerate(c_lines):
        if "Trọng lượng phần chân (kg)" in line:
            # Go back to find the parent div
            insert_idx = i - 2
            break
            
    if insert_idx != -1:
        c_lines.insert(insert_idx, "".join(block_to_insert) + "\n            ")
        with open(chiro, "w") as f:
            f.writelines(c_lines)
        print("Success")
    else:
        print("Could not find insert index in chiro")
else:
    print("Could not find block in pathologie")
