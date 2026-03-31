# Hướng dẫn Đồng bộ & Migration (VPS)

Tài liệu này ghi lại các bài học quan trọng để tránh lỗi 500 khi cập nhật hệ thống từ Local lên VPS.

## 🚨 Lỗi thường gặp (Root Cause of 500 Errors)
1. **Lệch số lượng cột trong SQL**: Khi viết lệnh `INSERT` hoặc `UPDATE` thủ công, nếu số lượng `?` không khớp với số lượng dữ liệu truyền vào hàm `execute()`, PHP sẽ lỗi 500 lập tức.
2. **Thiếu cột trên VPS**: Khi thêm tính năng mới ở Local (ví dụ: thêm "Giờ kết thúc"), chúng ta thường thêm cột vào Database Local nhưng QUÊN chạy lệnh SQL tương tự trên VPS.

## 🛠️ Quy trình chuẩn khi thêm Cột dữ liệu mới
Khi bạn thêm một cột mới vào hệ thống, hãy thực hiện theo 3 bước sau:

### Bước 1: Viết Code PHP dạng "Schema-Safe"
Luôn dùng mẫu sau để code không bị chết kể cả khi DB chưa kịp update:
```php
$available_cols = $db->query("SHOW COLUMNS FROM table_name")->fetchAll(PDO::FETCH_COLUMN);
$data = [];
if (in_array('new_column', $available_cols)) {
    $data['new_column'] = $_POST['new_column'];
}
// Sau đó build SQL INSERT/UPDATE dựa trên array $data
```

### Bước 2: Tạo script Migration
Tạo file `.sql` hoặc file `.php` (như `migrate_vps.php`) chứa lệnh `ALTER TABLE table_name ADD COLUMN ...`.

### Bước 3: Triển khai lên VPS (Bắt buộc)
1. Chạy file deployment: `bash tmp/deploy_vps.sh`.
2. Truy cập trình duyệt chạy file migration ngay lập tức: `https://crm.simoncenter.vn/migrate_vps.php`.

## 📌 Ghi chú quan trọng cho module Patients
Dữ liệu Bệnh nhân thường xuyên thay đổi các trường (Zalo, Facebook, Người giám hộ...). Khi sửa file `add.php` hoặc `edit.php` trong module này, **PHẢI** kiểm tra đếm kỹ số lượng tham số truyền vào hàm `execute()`.

---
*Tài liệu được cập nhật bởi Antigravity — 2026-03-22*
