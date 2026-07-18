#!/bin/bash
SSH_PASS='&m5L9[eUv'
VPS_USER='ubutu'
VPS_HOST='112.78.15.2'

tar -czvf fix_pkg.tar.gz modules/sales/manage_shared.php modules/sales/index.php modules/patients/view.php

/opt/homebrew/bin/sshpass -p "$SSH_PASS" scp -o StrictHostKeyChecking=no fix_pkg.tar.gz $VPS_USER@$VPS_HOST:/tmp/fix_pkg.tar.gz

/opt/homebrew/bin/sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST "echo '$SSH_PASS' | sudo -S tar -xzvf /tmp/fix_pkg.tar.gz -C /var/www/crm_phong_kham/ && echo '$SSH_PASS' | sudo -S chown -R www-data:www-data /var/www/crm_phong_kham/"

rm fix_pkg.tar.gz
echo "Deploy finished."
