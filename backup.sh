#!/bin/bash
mkdir -p Backup
echo "============================================="
echo "BƯỚC 1: Đóng gói Code, Hình ảnh (Uploads) & Database trên VPS"
echo "Mật khẩu copy sẵn: &m5L9[eUv"
echo "============================================="
ssh ubutu@112.78.15.2 "mysqldump -u crm_admin -p'CrmAdmin2026@Pass' clinic_management > /tmp/database.sql && echo '&m5L9[eUv' | sudo -S tar -czf /tmp/vps_full_backup.tar.gz -C /var/www/crm_phong_kham . -C /tmp database.sql && echo '&m5L9[eUv' | sudo -S chown ubutu:ubutu /tmp/vps_full_backup.tar.gz && rm /tmp/database.sql"

echo "============================================="
echo "BƯỚC 2: Tải bản siêu backup từ VPS về máy tính"
echo "File này chứa RẤT NHIỀU ẢNH nên sẽ tốn vài phút. Xin kiên nhẫn!"
echo "Mật khẩu copy sẵn: &m5L9[eUv"
echo "============================================="
scp ubutu@112.78.15.2:/tmp/vps_full_backup.tar.gz Backup/vps_full_backup.tar.gz

echo "============================================="
echo "BƯỚC 3: Dọn dẹp rác trên VPS"
echo "Mật khẩu copy sẵn: &m5L9[eUv"
echo "============================================="
ssh ubutu@112.78.15.2 "rm /tmp/vps_full_backup.tar.gz"

echo "🎉 CHÚC MỪNG! Toàn bộ cơ ngơi hệ thống, data, ảnh bệnh án trên VPS đã được tải về: Backup/vps_full_backup.tar.gz"
