#!/bin/bash
SSH_PASS='&m5L9[eUv'
VPS_USER='ubutu'
VPS_HOST='112.78.15.2'

tar -czvf deploy_autosave_fix.tar.gz modules/medical/form.php modules/medical/chiro_history.php modules/medical/follow_up.php

/opt/homebrew/bin/sshpass -p "$SSH_PASS" scp -o StrictHostKeyChecking=no deploy_autosave_fix.tar.gz $VPS_USER@$VPS_HOST:/tmp/

/opt/homebrew/bin/sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST "echo '$SSH_PASS' | sudo -S tar -xzvf /tmp/deploy_autosave_fix.tar.gz -C /var/www/crm_phong_kham/ && echo '$SSH_PASS' | sudo -S chown -R www-data:www-data /var/www/crm_phong_kham/ && echo '$SSH_PASS' | sudo -S systemctl reload php8.4-fpm"

echo "Deployed autosave fixes!"
