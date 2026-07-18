#!/bin/bash
SSH_PASS='&m5L9[eUv'
VPS_USER='ubutu'
VPS_HOST='112.78.15.2'

echo "1. Creating tar package..."
tar -czvf deploy_sticky_date.tar.gz \
modules/appointments/index.php \
modules/appointments/timeline.php \
modules/appointments/staff_timeline.php \
modules/appointments/edit.php \
modules/appointments/add.php \
modules/appointments/delete.php

echo "2. Uploading to VPS..."
/opt/homebrew/bin/sshpass -p "$SSH_PASS" scp -o StrictHostKeyChecking=no deploy_sticky_date.tar.gz $VPS_USER@$VPS_HOST:/tmp/deploy_sticky_date.tar.gz

echo "3. Extracting and fixing permissions on VPS..."
/opt/homebrew/bin/sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST "echo '$SSH_PASS' | sudo -S tar -xzvf /tmp/deploy_sticky_date.tar.gz -C /var/www/crm_phong_kham/ && echo '$SSH_PASS' | sudo -S chown -R www-data:www-data /var/www/crm_phong_kham/modules/appointments/"

echo "Deploy finished."
