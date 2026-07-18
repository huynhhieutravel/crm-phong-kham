#!/bin/bash
echo "Creating deployment package for Duplicate Checkin Fix..."
tar -czvf fix_duplicate.tar.gz modules/appointments/checkin.php modules/leads/convert.php
echo "Package created: fix_duplicate.tar.gz"

SSH_PASS='&m5L9[eUv'
VPS_USER='ubutu'
VPS_HOST='112.78.15.2'

echo "Uploading to VPS..."
/opt/homebrew/bin/sshpass -p "$SSH_PASS" scp -o StrictHostKeyChecking=no fix_duplicate.tar.gz $VPS_USER@$VPS_HOST:/tmp/fix_duplicate.tar.gz

echo "Extracting and applying on VPS..."
/opt/homebrew/bin/sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST "echo '$SSH_PASS' | sudo -S tar -xzvf /tmp/fix_duplicate.tar.gz -C /var/www/crm_phong_kham/ && echo '$SSH_PASS' | sudo -S chown -R www-data:www-data /var/www/crm_phong_kham/"

echo "Deploy finished successfully."
