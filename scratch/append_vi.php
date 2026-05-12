<?php
$vi_file = 'lang/vi.php';
$vi_content = file_get_contents($vi_file);

$missing = [
    "Hồ sơ y khoa",
    "In hồ sơ",
    "Khác:",
    "Tính chất:",
    "Kích hoạt bởi:",
    "Nguyên nhân:",
    "DẤU HIỆU CẤP CỨU CẦN LƯU Ý",
    "Nguyên nhân nghi ngờ",
    "Tiền sử can thiệp",
    "Phẫu thuật:",
    "Gãy xương:",
    "Implant/Niềng răng:",
    "Nhóm bệnh Cơ - Xương - Khớp",
    "Tiền sử chấn thương & Can thiệp (Surgical History)",
    "Gãy xương (Frakturen):",
    "Cột sống/Xương chậu tại:",
    "Phẫu thuật cột sống:",
    "Bắt vít/Nẹp/Thay đĩa đệm tại:",
    "Tai nạn xe cộ/ngã mạnh:",
    "Chấn thương vùng:",
    "Thuốc/Thực phẩm chức năng (Dùng từ:",
    "Ghi chú / Thuốc khác:",
    "Phim ảnh: X-Ray, MRI/CT",
    "Đã từng điều trị tại",
    "Không có triệu chứng ghi nhận",
    "Ghi chú bổ sung",
    "Chẩn đoán & Ghi chú lâm sàng",
    "Chưa ghi nhận chẩn đoán.",
    "PHẦN 4: KẾ HOẠCH (Plan)",
    "PHẦN I: THÔNG TIN CƠ BẢN & HUYẾT ÁP",
    "PHẦN II: VỌNG CHẨN (Nhìn)",
    "Thần sắc & Sắc mặt",
    "Thần:",
    "Mắt",
    "Niêm mạc môi",
    "PHẦN III: VĂN CHẨN (Nghe & Ngửi)",
    "Tiếng nói / Hơi thở",
    "Mùi cơ thể",
    "PHẦN IV: VẤN CHẨN (Hỏi)",
    "Tỉnh giấc:",
    "Tiêu hóa & Bài tiết",
    "Ăn uống:",
    "Màu tiểu tiện:",
    "NHIỆT",
    "HÀN",
    "PHẦN V: THIẾT CHẨN (Bắt mạch & Sờ nắn)",
    "ĐỘ SÂU",
    "TỐC ĐỘ",
    "HÌNH DẠNG",
    "LỰC",
    "Xúc chẩn (Sờ nắn)",
    "TỔNG KẾT NHANH (Bát cương)",
    "Quay lại hồ sơ"
];

$append_str = "\n    // Bổ sung các cụm từ cho view_form.php\n";
foreach ($missing as $i => $str) {
    $key = 'medical.view._auto_' . time() . '_' . $i;
    // escape quotes
    $val = str_replace("'", "\'", $str);
    $append_str .= "    '$key' => '$val',\n";
}

$vi_content = preg_replace('/\];\s*$/', $append_str . "];\n", $vi_content);
file_put_contents($vi_file, $vi_content);
echo "Appended to vi.php\n";
