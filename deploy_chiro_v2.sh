#!/bin/bash
SSH_PASS='&m5L9[eUv'
VPS_USER='ubutu'
VPS_HOST='112.78.15.2'

/opt/homebrew/bin/sshpass -p "$SSH_PASS" scp -o StrictHostKeyChecking=no modules/medical/forms/chiropractic_v2.php $VPS_USER@$VPS_HOST:/tmp/chiropractic_v2.php

/opt/homebrew/bin/sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST "echo '$SSH_PASS' | sudo -S cp /tmp/chiropractic_v2.php /var/www/crm_phong_kham/modules/medical/forms/chiropractic_v2.php && echo '$SSH_PASS' | sudo -S chown www-data:www-data /var/www/crm_phong_kham/modules/medical/forms/chiropractic_v2.php && echo '$SSH_PASS' | sudo -S systemctl reload php8.4-fpm"

echo "Deployed form V2!"
