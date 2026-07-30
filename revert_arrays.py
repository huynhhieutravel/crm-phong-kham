import sys

files = [
    'modules/medical/follow_up_v2.php'
]

replacements = {
    "'rib1' => $is_de ? 'Rippe 1' : 'Xương sườn 1'": "'rib1' => $is_de ? 'Rippe 1' : 'Xương sườn 1 (Rippe 1)'",
    "'biceps' => $is_de ? 'Bizeps' : 'Cơ nhị đầu'": "'biceps' => $is_de ? 'Bizeps' : 'Cơ nhị đầu (Bizeps)'",
    "'rotator' => $is_de ? 'Rotatorenmanschette' : 'Cơ chóp xoay'": "'rotator' => $is_de ? 'Rotatorenmanschette' : 'Cơ chóp xoay (Rotatorenmanschette)'",
    "'acg' => $is_de ? 'ACG' : 'Khớp cùng đòn'": "'acg' => $is_de ? 'ACG' : 'Khớp cùng đòn (ACG)'",
    "'scg' => $is_de ? 'SCG' : 'Khớp ức đòn'": "'scg' => $is_de ? 'SCG' : 'Khớp ức đòn (SCG)'",
    "'coracoid' => $is_de ? 'Proc. coracoideus' : 'Mỏm quạ'": "'coracoid' => $is_de ? 'Proc. coracoideus' : 'Mỏm quạ (Proc. coracoideus)'",
    "'supras' => $is_de ? 'Supraspinatus' : 'Cơ trên gai'": "'supras' => $is_de ? 'Supraspinatus' : 'Cơ trên gai (Supraspinatus)'",
    "'hwk' => $is_de ? 'Handwurzelknochen / CTS' : 'Xương cổ tay / Hội chứng ống cổ tay'": "'hwk' => $is_de ? 'Handwurzelknochen / CTS' : 'Xương cổ tay / Hội chứng ống cổ tay (Handwurzelknochen / CTS)'",
    "'cts' => $is_de ? 'CTS' : 'Hội chứng ống cổ tay'": "'cts' => $is_de ? 'CTS' : 'Hội chứng ống cổ tay (CTS)'",
    "'cuboid' => $is_de ? 'Os cuboideum' : 'Xương hộp'": "'cuboid' => $is_de ? 'Os cuboideum' : 'Xương hộp (Os cuboideum)'",
    "'naviculare' => $is_de ? 'Os naviculare' : 'Xương ghe'": "'naviculare' => $is_de ? 'Os naviculare' : 'Xương ghe (Os naviculare)'",
    "'hallux' => $is_de ? 'Hallux valgus' : 'Ngón cái vẹo ngoài'": "'hallux' => $is_de ? 'Hallux valgus' : 'Ngón cái vẹo ngoài (Hallux valgus)'",
    "'knee' => $is_de ? 'Kniegelenk' : 'Khớp gối'": "'knee' => $is_de ? 'Kniegelenk' : 'Khớp gối (Kniegelenk)'",
    "'patella' => $is_de ? 'Patellamobilität' : 'Di động xương bánh chè'": "'patella' => $is_de ? 'Patellamobilität' : 'Di động xương bánh chè (Patellamobilität)'",
    "'hip' => $is_de ? 'Hüftgelenk' : 'Khớp háng'": "'hip' => $is_de ? 'Hüftgelenk' : 'Khớp háng (Hüftgelenk)'",
    "'iliopsoas' => $is_de ? 'Cơ thắt lưng chậu'": "'iliopsoas' => $is_de ? 'Cơ thắt lưng chậu' : 'Cơ thắt lưng chậu (Iliopsoas)'",
    "'Occiput' : 'Xương chẩm'": "'Occiput' : 'Xương chẩm (Occiput)'",
    "'Kiefergelenk (TMJ)' : 'Khớp thái dương hàm'": "'Kiefergelenk (TMJ)' : 'Khớp thái dương hàm (TMJ)'",
    "'Atlas (C1)' : 'Đốt đội (C1)'": "'Atlas (C1)' : 'Đốt đội (Atlas C1)'",
    "'Axis (C2)' : 'Đốt trục (C2)'": "'Axis (C2)' : 'Đốt trục (Axis C2)'",
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
