#!/bin/bash
SSH_PASS='&m5L9[eUv'
VPS_USER='ubutu'
VPS_HOST='112.78.15.2'

# 1. Package files
tar -czvf fix_leave_roles.tar.gz api/leave_requests.php modules/hr/index.php modules/hr/leaves.php

# 2. Upload the tar package to /tmp
/opt/homebrew/bin/sshpass -p "$SSH_PASS" scp -o StrictHostKeyChecking=no fix_leave_roles.tar.gz $VPS_USER@$VPS_HOST:/tmp/fix_leave_roles.tar.gz

# 3. Extract and overwrite via SSH with sudo
/opt/homebrew/bin/sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST "echo '$SSH_PASS' | sudo -S tar -xzvf /tmp/fix_leave_roles.tar.gz -C /var/www/crm_phong_kham/ && echo '$SSH_PASS' | sudo -S chown -R www-data:www-data /var/www/crm_phong_kham/"

echo "Fix deployed."
