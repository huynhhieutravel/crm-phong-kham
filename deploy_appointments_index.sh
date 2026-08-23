#!/bin/bash
SSH_PASS='&m5L9[eUv'
VPS_USER='ubutu'
VPS_HOST='112.78.15.2'
APP_DIR='/var/www/crm_phong_kham'

echo "Creating tarball..."
tar -czvf deploy_appointments_index.tar.gz modules/appointments/index.php

echo "Uploading..."
/opt/homebrew/bin/sshpass -p "$SSH_PASS" scp -o StrictHostKeyChecking=no deploy_appointments_index.tar.gz $VPS_USER@$VPS_HOST:/tmp/deploy_appointments_index.tar.gz

echo "Extracting..."
/opt/homebrew/bin/sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST "echo '$SSH_PASS' | sudo -S tar -xzvf /tmp/deploy_appointments_index.tar.gz -C $APP_DIR/ && echo '$SSH_PASS' | sudo -S chown -R www-data:www-data $APP_DIR/modules/appointments/index.php"

echo "Deploy finished!"
