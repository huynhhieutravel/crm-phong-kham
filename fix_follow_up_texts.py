import sys

filename = 'modules/medical/follow_up_v2.php'
with open(filename, 'r') as f:
    content = f.read()

replacements = {
    "'Follow-up V2 (Mẫu Mới)'": "$is_de ? 'Follow-up V2 (Neu)' : 'Follow-up V2 (Mẫu Mới)'",
    "Ghi chú Bệnh sử (Anamnese-Notizen)": "<?php echo $is_de ? 'Anamnese-Notizen' : 'Ghi chú Bệnh sử'; ?>",
    "Chỉ xem lại lịch sử, không cho sửa. (Nur Historie ansehen, keine Bearbeitung.)": "<?php echo $is_de ? 'Nur Historie ansehen, keine Bearbeitung.' : 'Chỉ xem lại lịch sử, không cho sửa.'; ?>",
    "Ghi chú điều trị HÔM NAY (Behandlungsnotizen HEUTE)": "<?php echo $is_de ? 'Behandlungsnotizen HEUTE' : 'Ghi chú điều trị HÔM NAY'; ?>",
    "Nhập ghi chú cho buổi điều trị này...": "<?php echo $is_de ? 'Notizen für diese Behandlung eingeben...' : 'Nhập ghi chú cho buổi điều trị này...'; ?>",
    "Danh sách Điều trị & Nắn chỉnh (Behandlungs- & Justierungsliste)": "<?php echo $is_de ? 'Behandlungs- & Justierungsliste' : 'Danh sách Điều trị & Nắn chỉnh'; ?>",
    "Lưu ý: Các ô <strong style=\"color: #94a3b8;\">màu xám</strong> là các vị trí đã được nắn chỉnh trong lần điều trị gần nhất. Bạn có thể check đè lên để cập nhật cho lần này.": "<?php echo $is_de ? 'Hinweis: Grau markierte Felder wurden in der letzten Sitzung behandelt. Sie können diese überschreiben, um sie für dieses Mal zu aktualisieren.' : 'Lưu ý: Các ô <strong style=\"color: #94a3b8;\">màu xám</strong> là các vị trí đã được nắn chỉnh trong lần điều trị gần nhất. Bạn có thể check đè lên để cập nhật cho lần này.'; ?>",
    "Vùng nắn chỉnh - <?php echo $is_de ? 'HWS' : 'Cột sống cổ'; ?>": "<?php echo $is_de ? 'Justierungsbereich - HWS' : 'Vùng nắn chỉnh - Cột sống cổ'; ?>",
    "Vùng nắn chỉnh - Cột sống thắt lưng (LWS)": "<?php echo $is_de ? 'Justierungsbereich - LWS' : 'Vùng nắn chỉnh - Cột sống thắt lưng (LWS)'; ?>",
    "Vùng nắn chỉnh - Cột sống ngực (BWS)": "<?php echo $is_de ? 'Justierungsbereich - BWS' : 'Vùng nắn chỉnh - Cột sống ngực (BWS)'; ?>",
    "<th>T (li)</th>": "<th><?php echo $is_de ? 'li' : 'T'; ?></th>",
    "<th>P (re)</th>": "<th><?php echo $is_de ? 're' : 'P'; ?></th>",
    "<th>Vị trí (Position)</th>": "<th><?php echo $is_de ? 'Position' : 'Vị trí'; ?></th>",
    "<th>Đốt sống (Wirbel)</th>": "<th><?php echo $is_de ? 'Wirbel' : 'Đốt sống'; ?></th>",
    "<th>Trước (Ventral)</th>": "<th><?php echo $is_de ? 'Ventral' : 'Trước'; ?></th>",
    "<th>Sau (Dorsal)</th>": "<th><?php echo $is_de ? 'Dorsal' : 'Sau'; ?></th>",
    "> T (li)</label>": "> <?php echo $is_de ? 'li' : 'T'; ?></label>",
    "> P (re)</label>": "> <?php echo $is_de ? 're' : 'P'; ?></label>",
    "Lần điều trị gần nhất (Letzte Behandlung) <span style=\"font-size: 0.75rem; font-weight: 500; opacity: 0.8;\">(<?php echo $prev_fu_date ?: 'Chưa rõ'; ?>)</span>": "<?php echo $is_de ? 'Letzte Behandlung' : 'Lần điều trị gần nhất'; ?> <span style=\"font-size: 0.75rem; font-weight: 500; opacity: 0.8;\">(<?php echo $prev_fu_date ?: ($is_de ? 'Unbekannt' : 'Chưa rõ'); ?>)</span>",
    "<span style=\"font-size: 0.75rem; font-weight: 500; opacity: 0.8;\">(<?php echo $history_date ?: 'Chưa rõ'; ?>)</span>": "<span style=\"font-size: 0.75rem; font-weight: 500; opacity: 0.8;\">(<?php echo $history_date ?: ($is_de ? 'Unbekannt' : 'Chưa rõ'); ?>)</span>",
    "'occiput' => 'Xương chẩm (Occiput)', 'tmj' => 'Khớp thái dương hàm (Kiefergelenk / TMJ)',": "'occiput' => $is_de ? 'Occiput' : 'Xương chẩm', 'tmj' => $is_de ? 'Kiefergelenk (TMJ)' : 'Khớp thái dương hàm',",
    "'c1' => 'Đốt đội - C1 (Atlas)', 'c2' => 'Đốt trục - C2 (Axis)',": "'c1' => $is_de ? 'Atlas (C1)' : 'Đốt đội (C1)', 'c2' => $is_de ? 'Axis (C2)' : 'Đốt trục (C2)',"
}

for k, v in replacements.items():
    if k in content:
        content = content.replace(k, v)
        print(f"Replaced: {k[:30]}...")
    else:
        print(f"NOT FOUND: {k}")

with open(filename, 'w') as f:
    f.write(content)
