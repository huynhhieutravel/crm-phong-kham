#!/bin/bash
SSH_PASS='&m5L9[eUv'
VPS_USER='ubutu'
VPS_HOST='112.78.15.2'

/opt/homebrew/bin/sshpass -p "$SSH_PASS" scp -o StrictHostKeyChecking=no includes/auth_middleware.php $VPS_USER@$VPS_HOST:/tmp/auth_middleware.php

/opt/homebrew/bin/sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST "echo '$SSH_PASS' | sudo -S mv /tmp/auth_middleware.php /var/www/crm_phong_kham/includes/auth_middleware.php && echo '$SSH_PASS' | sudo -S chown www-data:www-data /var/www/crm_phong_kham/includes/auth_middleware.php"

echo "Suspend logic fixed."
