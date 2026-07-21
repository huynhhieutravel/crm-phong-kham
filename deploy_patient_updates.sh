#!/bin/bash
SSH_PASS='&m5L9[eUv'
VPS_USER='ubutu'
VPS_HOST='112.78.15.2'
WEB_ROOT='/var/www/crm_phong_kham'

echo "1. Cập nhật Database: Thêm cột is_under_one vào bảng patients..."
/opt/homebrew/bin/sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST "echo '$SSH_PASS' | sudo -S mysql -e 'USE clinic_management; ALTER TABLE patients ADD COLUMN is_under_one TINYINT(1) DEFAULT 0 AFTER birthday;'" || echo "DB alter failed (might already exist)"

echo "2. Tải lên file add.php và edit.php..."
/opt/homebrew/bin/sshpass -p "$SSH_PASS" scp -o StrictHostKeyChecking=no modules/patients/add.php $VPS_USER@$VPS_HOST:/tmp/add.php
/opt/homebrew/bin/sshpass -p "$SSH_PASS" scp -o StrictHostKeyChecking=no modules/patients/edit.php $VPS_USER@$VPS_HOST:/tmp/edit.php

echo "3. Copy file vào đúng thư mục và set quyền..."
/opt/homebrew/bin/sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST "echo '$SSH_PASS' | sudo -S cp /tmp/add.php $WEB_ROOT/modules/patients/add.php && echo '$SSH_PASS' | sudo -S cp /tmp/edit.php $WEB_ROOT/modules/patients/edit.php && echo '$SSH_PASS' | sudo -S chown www-data:www-data $WEB_ROOT/modules/patients/add.php $WEB_ROOT/modules/patients/edit.php && rm /tmp/add.php /tmp/edit.php"

echo "✅ Deploy cập nhật Hồ sơ Bệnh nhân (Mối quan hệ, Dưới 1 tuổi, Lần khám cuối) hoàn tất!"
