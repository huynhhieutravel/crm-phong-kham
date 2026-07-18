#!/bin/bash

SSH_PASS='&m5L9[eUv'
VPS_USER='ubutu'
VPS_HOST='112.78.15.2'
DB_USER='crm_admin'
DB_PASS='CrmAdmin2026@Pass'
DB_NAME='clinic_management'

echo "1. Đang truy cập VPS ($VPS_HOST) để tạo file SQL dump..."
/opt/homebrew/bin/sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST "mysqldump -u $DB_USER -p'$DB_PASS' $DB_NAME > /tmp/database_backup.sql"

echo "2. Đang tải file SQL từ VPS về máy cục bộ..."
/opt/homebrew/bin/sshpass -p "$SSH_PASS" scp -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST:/tmp/database_backup.sql Backup/database_backup.sql

echo "3. Đang dọn dẹp file tạm trên VPS..."
/opt/homebrew/bin/sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST "rm /tmp/database_backup.sql"

echo "4. Đang nén thành bản Full A-Z (bao gồm cả Code và SQL Data)..."
cd Backup
tar -czf full_az_backup.tar.gz code_backup.tar.gz database_backup.sql

echo "🎉 HOÀN TẤT! Bản Full A-Z của bạn (bao gồm toàn bộ tài khoản và thông tin) đã được lưu tại: Backup/full_az_backup.tar.gz"
