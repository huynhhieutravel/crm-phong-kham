#!/bin/bash
echo "Đóng gói các file đã sửa chữa..."
tar -czvf fix_logout.tar.gz logout.php templates/sidebar.php includes/db.php config/database.php
echo "Đã tạo file fix_logout.tar.gz. Bạn có thể tự upload file này lên server giải nén, hoặc cung cấp mã SSH để Antigravity làm tự động."
