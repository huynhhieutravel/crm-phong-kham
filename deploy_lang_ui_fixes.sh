#!/bin/bash
SSH_PASS='&m5L9[eUv'
VPS_USER='ubutu'
VPS_HOST='112.78.15.2'

# Create tar archive
tar -czvf deploy_lang_fixes.tar.gz lang/vi.php lang/en.php lang/de.php lang/zh.php

# Upload
/opt/homebrew/bin/sshpass -p "$SSH_PASS" scp -o StrictHostKeyChecking=no deploy_lang_fixes.tar.gz $VPS_USER@$VPS_HOST:/tmp/deploy_lang_fixes.tar.gz

# Extract and fix permissions
/opt/homebrew/bin/sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST "echo '$SSH_PASS' | sudo -S tar -xzvf /tmp/deploy_lang_fixes.tar.gz -C /var/www/crm_phong_kham/ && echo '$SSH_PASS' | sudo -S chown -R www-data:www-data /var/www/crm_phong_kham/"

echo "Deployment of language files complete."
