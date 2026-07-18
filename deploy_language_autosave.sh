#!/bin/bash
SSH_PASS='&m5L9[eUv'
VPS_USER='ubutu'
VPS_HOST='112.78.15.2'

# 1. Package files
# Included modified files for localization and autosave fix
tar -czvf deploy_language_autosave.tar.gz \
    modules/medical/chiro_history.php \
    modules/medical/form.php \
    modules/medical/session_view.php \
    modules/medical/follow_up.php \
    modules/medical/add_treatment.php \
    modules/patients/view.php \
    templates/header.php \
    lang/vi.php \
    lang/en.php \
    lang/de.php \
    lang/zh.php

# 2. Upload the tar package to /tmp
/opt/homebrew/bin/sshpass -p "$SSH_PASS" scp -o StrictHostKeyChecking=no deploy_language_autosave.tar.gz $VPS_USER@$VPS_HOST:/tmp/deploy_language_autosave.tar.gz

# 3. Extract and overwrite via SSH with sudo
/opt/homebrew/bin/sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST "echo '$SSH_PASS' | sudo -S tar -xzvf /tmp/deploy_language_autosave.tar.gz -C /var/www/crm_phong_kham/ && echo '$SSH_PASS' | sudo -S chown -R www-data:www-data /var/www/crm_phong_kham/"

echo "Feature deploy finished."
