#!/bin/bash
VPS_USER='ubutu'
VPS_HOST='112.78.15.2'
SSH_PASS='&m5L9[eUv'

echo "1. Creating tar package..."
tar -czvf deploy_time_filter.tar.gz \
modules/billing/index.php \
modules/reports/index.php \
modules/reports/daily_revenue.php \
modules/appointments/index.php \
modules/appointments/update_appointment.php

echo "2. Uploading to VPS... (Nhập mật khẩu VPS khi được hỏi)"
scp -o StrictHostKeyChecking=no deploy_time_filter.tar.gz $VPS_USER@$VPS_HOST:/home/ubutu/deploy_time_filter.tar.gz

echo "3. Extracting and fixing permissions on VPS..."
ssh -o StrictHostKeyChecking=no -t $VPS_USER@$VPS_HOST "echo '$SSH_PASS' | sudo -S tar -xzvf /home/ubutu/deploy_time_filter.tar.gz -C /var/www/crm_phong_kham/ && echo '$SSH_PASS' | sudo -S chown -R www-data:www-data /var/www/crm_phong_kham/modules/reports /var/www/crm_phong_kham/modules/billing /var/www/crm_phong_kham/modules/appointments && echo '=== DEPLOY OK ==='"

echo "Deploy finished!"
