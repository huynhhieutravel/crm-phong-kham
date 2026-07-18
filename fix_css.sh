#!/bin/bash
SSH_PASS='&m5L9[eUv'
VPS_USER='ubutu'
VPS_HOST='112.78.15.2'

/opt/homebrew/bin/sshpass -p "$SSH_PASS" scp -o StrictHostKeyChecking=no assets/css/style.css $VPS_USER@$VPS_HOST:/tmp/style.css

/opt/homebrew/bin/sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST "echo '$SSH_PASS' | sudo -S mv /tmp/style.css /var/www/crm_phong_kham/assets/css/style.css && echo '$SSH_PASS' | sudo -S chown www-data:www-data /var/www/crm_phong_kham/assets/css/style.css"

echo "CSS overflow fixed."
