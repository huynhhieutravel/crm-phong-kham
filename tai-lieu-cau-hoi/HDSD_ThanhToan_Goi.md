# SỔ TAY NGHIỆP VỤ THANH TOÁN & QUẢN LÝ GÓI KHÁM (SIMON CENTER CRM)

Tài liệu này chuẩn hóa quy trình Thu ngân (Cashier), bán Gói khám (Packages) và quản trị doạn thu để nhân viên Lễ tân/Thu ngân nắm bắt rõ luật chơi của hệ thống, tránh những sai sót tài chính nghiêm trọng.

---

## 1. QUY TẮC THÉP TRONG THANH TOÁN (CHỐNG NHẬP LỐ NHẦM LẪN)

Phòng khám là nơi có sự chênh lệch lớn về độ lớn của các con số (từ vài trăm nghìn đến hàng chục triệu đồng). Chỉ một sơ suất gõ thừa số "0" của nhân viên sẽ làm méo mó toàn bộ Biểu đồ Doanh thu của chi nhánh. 

Hệ thống đã bật chế độ **"Chốt chặn Vượt ngân sách"**:
- **Không bao giờ được thu lố giá trị thật của Gói**: Ví dụ Gói khám 9.000.000đ, dù nhân viên lỡ tay gõ thành 90.000.000đ, hệ thống sẽ Báo lỗi Đỏ chót và TỪ CHỐI không cho lưu vào hệ thống.
- **Thu lỡ 1 đồng cũng bị chặn**: Bạn chỉ được phép nhập tối đa bằng tổng số tiền (Giá gói - Đã thu trước đó).
- **Luôn kiểm tra kỹ mệnh giá**: Hãy quan sát kỹ phần dấu phẩy ngăn cách hàng nghìn (VD: `9,000,000`) trước khi bấm nút <Hoàn tất Thanh toán>.

---

## 2. QUẢN LÝ GÓI KHÁM (PACKAGES) VÀ BUỔI ĐIỀU TRỊ

CRM Simon Center tối giản hóa tối đa quy trình gán Gói khám cho bệnh nhân, loại bỏ các bước thừa mứa làm luộm thuộm y bạ:

1. **Khi KH Mua Gói (Sale/Tư vấn)**:
   - Thêm Gói (Package) trực tiếp trong hồ sơ bệnh nhân.
   - Các gói này sẽ được cấp "Số buổi khám".

2. **Khi KH Tới Khám (Treatment/Session)**:
   - Bạn cứ việc Thêm Buổi khám (Add Session) bình thường.
   - **Tự động trừ lùi**: Hệ thống sẽ tự tìm xem Bệnh nhân này đang có Gói nào còn buổi không, và ngầm Xùy trừ (Deduct) 1 buổi một cách trơn tru. Bác sĩ/Lễ tân không cần phải làm thao tác "Chọn gói nào để trừ" một cách thủ công như trước đây.

3. **Gói Khám Doanh nghiệp (Corporate Packages)**:
   - Các công ty mua gói cho nhân viên (Gói khám Doanh nghiệp) sẽ được quản trị tách biệt ở Module Config.
   - Hệ thống tự phân bổ gói mà không làm rườm rà form Add Treatment của khách lẻ, giữ giao diện sạch nhất cho tốc độ thao tác tại quầy.

---

## 3. LỊCH SỬ GIAO DỊCH VÀ CÔNG NỢ

Làm cách nào để bạn biết hôm nay mình báo cáo thu bao nhiêu tiền?
- Xem mục **Lịch sử Thanh toán**: Hệ thống ghi đè chuẩn xác theo Từng Giao Dịch, ai thu, ngày giờ nào, hình thức (Tiền mặt/Chuyển khoản).
- **Công nợ (Debt)**: Hiển thị ngay trên Avatar của KH nếu họ còn nợ tiền Gói. Khi khách quay lại, đập vào mắt Lễ tân ngay để đòi nợ khéo léo.

---

## 4. BÁO CÁO DOANH THU & KPI
Mỗi đồng tiền Lễ tân thu qua phần mềm sẽ lập tức bắn thẳng vào biểu đồ Doanh thu (Chart) trên màn hình Dashboard của Quản lý và Giám đốc. 
- Giám đốc xem được Doanh thu Theo Ngày/Tháng.
- Toàn bộ đều được Real-time (Thời gian thực). Sai 1 đồng sẽ bắt nhân viên làm tường trình ngay cuối ca.

> **💡 Mẹo:** Nếu lỡ có sai sót, chỉ có Tài khoản System Admin hoặc Quán lý cao nhất mới có thể xóa giao dịch (Xóa Receipt). Thu ngân không có quyền "Hủy" sau khi đã xuất bill vào dữ liệu.
