#!/bin/bash
SSH_PASS='&m5L9[eUv'
VPS_USER='ubutu'
VPS_HOST='112.78.15.2'
APP_DIR='/var/www/crm_phong_kham'

echo "Creating tarball..."
tar -czvf deploy_updates.tar.gz modules/sales/topup.php modules/medical/follow_up_v2.php modules/medical/chiro_history_v2.php

echo "Uploading..."
/opt/homebrew/bin/sshpass -p "$SSH_PASS" scp -o StrictHostKeyChecking=no deploy_updates.tar.gz $VPS_USER@$VPS_HOST:/tmp/deploy_updates.tar.gz

echo "Extracting..."
/opt/homebrew/bin/sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST "echo '$SSH_PASS' | sudo -S tar -xzvf /tmp/deploy_updates.tar.gz -C $APP_DIR/ && echo '$SSH_PASS' | sudo -S chown -R www-data:www-data $APP_DIR/modules/sales/topup.php $APP_DIR/modules/medical/follow_up_v2.php $APP_DIR/modules/medical/chiro_history_v2.php"

echo "Deploy finished!"
