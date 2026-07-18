#!/bin/bash
VPS_USER='ubutu'
VPS_HOST='112.78.15.2'
SSH_PASS='&m5L9[eUv'

echo "1. Creating tar package..."
tar -czvf deploy_report_fix.tar.gz \
modules/reports/index.php \
modules/sales/manage_shared.php

echo "2. Uploading to VPS... (Nhập mật khẩu VPS khi được hỏi)"
scp -o StrictHostKeyChecking=no deploy_report_fix.tar.gz $VPS_USER@$VPS_HOST:/home/ubutu/deploy_report_fix.tar.gz

echo "3. Extracting and fixing permissions on VPS..."
ssh -o StrictHostKeyChecking=no -t $VPS_USER@$VPS_HOST "echo '$SSH_PASS' | sudo -S tar -xzvf /home/ubutu/deploy_report_fix.tar.gz -C /var/www/crm_phong_kham/ && echo '$SSH_PASS' | sudo -S chown -R www-data:www-data /var/www/crm_phong_kham/modules/reports /var/www/crm_phong_kham/modules/sales && echo '=== DEPLOY OK ==='"

echo "Deploy finished!"
