# SỔ TAY NGHIỆP VỤ CHĂM SÓC KHÁCH HÀNG (SIMON CENTER CRM)

Tài liệu này giải thích chi tiết cơ chế hoạt động, luồng nghiệp vụ (Workflow) và các thông số cài đặt tự động của hệ thống CSKH dành cho nhân viên Call Center / Lễ tân tại Phòng khám.

---

## 1. MỤC ĐÍCH CỦA MODULE CSKH
Module CSKH được thiết kế dưới dạng **Inbox Action Board (Màn hình Hành động Kép)**, giúp nhân viên không bao giờ bỏ sót bất kỳ một lịch hẹn hoặc khách hàng nào cần chăm sóc. Hệ thống sẽ **Tự động Quét (Auto-Scan)** hồ sơ bệnh án, lịch hẹn, gói khám mỗi đêm (06:00 AM) và đẩy "Công việc" (Task) vào màn hình của nhân viên.

---

## 2. Ý NGHĨA CỦA CÁC CHỈ SỐ VÀ MÀU SẮC (SLA)

Hệ thống phân loại mức độ khẩn cấp của cuộc gọi bằng Bảng màu, giúp bạn nhận biết ngay ai cần được ưu tiên xử lý trước:

- 🔴 **Màu Đỏ (Cần xử lý ngay)**: Thường là các Task **T-1 (Nhắc lịch khám ngày mai)** hoặc cảnh báo Gói khám sắp hết. Nếu trễ, phòng khám có thể trống lịch hoặc thất thoát doanh thu.
- 🟡 **Màu Vàng (Đang chờ chốt)**: Khách hàng chưa đồng ý luôn trong cuộc gọi trước, báo cần "Suy nghĩ thêm". Hệ thống tự hẹn lại sang T+2 để nhân viên gọi lại bám đuổi.
- 🟢 **Màu Xanh (Đã ổn định)**: Thường là các kịch bản hỏi thăm sức khoẻ, hoặc khách hàng đã chốt được lịch/mua gói xong.
- ⚪ **Màu Xám (Lưu ý / Tạm ẩn)**: Khách hàng "Từ chối tiếp tục", sai số điện thoại, hoặc từ chối điều trị. Hệ thống đưa vào kho lưu trữ để làm Báo cáo (Không hiện lên quấy rầy danh sách chính).

---

## 3. CÁC KỊCH BẢN TỰ ĐỘNG CHÍNH (CSKH RULES)

Hệ thống có một khối Động cơ (Cron Engine) tự động sinh ra các kịch bản sau:

1. **Rule T-1 (Nhắc lịch trước 1 ngày)**: 
   - **Kích hoạt**: Đúng 1 ngày trước khi tới Lịch hẹn khám (Appointment).
   - **Mục tiêu**: Gọi điện nhắc bệnh nhân tránh quên lịch, xác nhận lại giờ giấc.
2. **Rule T+3 (Hỏi thăm sau khám 3 ngày)**: 
   - **Kích hoạt**: Bệnh nhân không có lịch hẹn tiếp theo, tính từ ngày khám cuối (Session) + 3 ngày.
   - **Mục tiêu**: Chốt lịch mổ/khám tiếp. Nhắc hỏi thăm đau nhức sau điều trị. Xin đánh giá (Review).
3. **Cảnh báo Gói sắp hết**:
   - **Kích hoạt**: Bệnh nhân có Package nhưng số buổi còn lại <= 2.
   - **Mục tiêu**: Lễ tân mồi trước kịch bản gia hạn gói (Renew) hoặc Upsell.

> *Lưu ý: Nếu Bệnh nhân đã tự đặt lịch mới (Có Appointment trong tương lai), hệ thống thông minh sẽ KHÔNG sinh ra Task T+3 hay T+7 nữa để tránh gọi làm phiền thừa thãi.*

---

## 4. LUỒNG THAO TÁC (WORKFLOW) TẠI MÀN HÌNH INBOX

Thay vì mở nhiều tab dính load trang chậm chạp, màn hình làm việc được chia làm 2 nửa:

### Cột Danh sách chờ (Bên Trái)
- Hiển thị danh sách khách hàng cần gọi trong ngày hôm nay.
- Cuộn siêu tốc. Click vào ai, thông tin người đó lập tức bật lên ở Nửa Khung Phải.

### Cột Xử lý Cuộc gọi (Bên Phải)
Khi bạn bắt máy bấm gọi cho khách, hãy thao tác trên Form này theo đúng trình tự:

1. **Xem tóm tắt Medical**: Đọc nhanh "Lần khám cuối", "Lịch hẹn tới", "Gói còn lại" để nắm tình hình trước khi giao tiếp.
2. **Chọn Trạng thái kết nối**:
   - `KH Nghe máy`: Mở khóa phẩn chốt kết quả.
   - `Bận / Cúp máy`: Lưu lại, hệ thống sẽ tự đếm **Số lần Retry** (Tối đa gọi 3 lần, nếu 3 lần vẫn bận sẽ tự đánh tịt).
   - `Không mầm / Sai số`: Đánh rớt Task, ném vào Khay Màu Xám.
3. **Chốt Kết quả & Thái độ (Nếu nghe máy)**:
   - `Đã chốt hẹn`: Xong việc, cất Task.
   - `Cần suy nghĩ thêm`: Khách bận, chưa chắc chắn. Task chuyển màu Vàng, hôm sau nổi lên lại để bám đuôi.
   - `Từ chối`: Đánh rớt Task.
   - `Chỉ hỏi thăm`: Hoàn tất các Task T+3, T+7 chỉ mang tính chất hỏi thăm vui vẻ.
4. **Viết Ghi chú (Notes)**: Bắt buộc ghi vắn tắt phàn nàn hoặc yêu cầu của KH để người sau gọi còn nhớ.
5. **Bấm "LƯU & GỌI NGƯỜI TIẾP THEO"**: 
   - Hệ thống tự lưu dữ liệu âm thầm (Không chớp đổi trang).
   - Tự động nảy danh sách nhảy sang người bên dưới. Bạn cứ tiếp tục gọi cho đến khi Hòm thư rỗng!

---

## 5. PHẢN HỒI VÀ GÓP Ý DÀNH CHO NHÂN VIÊN
- Form Checkbox Mẹo (Nhắc nghỉ ngơi, Xin Review Map) được thiết kế để Lễ Tân tránh quên kịch bản. Nếu các bạn thấy cần thêm Kịch bản Checkbox nào, hãy báo lại Quản lý để kỹ thuật cập nhật thêm trong vòng 1 nốt nhạc!
- Nếu số lượng Task "Vàng" tồn đọng quá lớn, Quản lý sẽ cân nhắc việc kéo giãn Rule T+3 thành T+5 để giảm tải cho team Call Center.
