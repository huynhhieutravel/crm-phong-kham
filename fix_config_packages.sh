#!/bin/bash
SSH_PASS='&m5L9[eUv'
VPS_USER='ubutu'
VPS_HOST='112.78.15.2'

/opt/homebrew/bin/sshpass -p "$SSH_PASS" scp -o StrictHostKeyChecking=no modules/sales/config_packages.php $VPS_USER@$VPS_HOST:/tmp/config_packages.php

/opt/homebrew/bin/sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST "echo '$SSH_PASS' | sudo -S mv /tmp/config_packages.php /var/www/crm_phong_kham/modules/sales/config_packages.php && echo '$SSH_PASS' | sudo -S chown www-data:www-data /var/www/crm_phong_kham/modules/sales/config_packages.php"

echo "Config Packages fixed."
