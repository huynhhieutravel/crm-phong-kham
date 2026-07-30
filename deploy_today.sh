#!/bin/bash
SSH_PASS='&m5L9[eUv'
VPS_USER='ubutu'
VPS_HOST='112.78.15.2'

# 1. Package files
echo "Packaging files..."
tar -czvf deploy_today.tar.gz \
  assets/js/confirm-modal.js \
  includes/coin_functions.php \
  modules/billing/get_catalog_api.php \
  modules/medical/chiro_history_v2.php \
  modules/medical/follow_up_v2.php \
  modules/reports/daily_revenue.php \
  modules/sales/add_package.php \
  modules/sales/checkout.php \
  modules/sales/config_packages.php \
  modules/sales/topup.php \
  modules/sales/cancel_coin_topup_api.php \
  modules/sales/coin_history_api.php \
  modules/patients/view.php

# 2. Upload the tar package to /tmp
echo "Uploading to VPS..."
/opt/homebrew/bin/sshpass -p "$SSH_PASS" scp -o StrictHostKeyChecking=no deploy_today.tar.gz $VPS_USER@$VPS_HOST:/tmp/deploy_today.tar.gz

# 3. Extract and overwrite via SSH with sudo
echo "Extracting on VPS..."
/opt/homebrew/bin/sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST "echo '$SSH_PASS' | sudo -S tar -xzvf /tmp/deploy_today.tar.gz -C /var/www/crm_phong_kham/ && echo '$SSH_PASS' | sudo -S chown -R www-data:www-data /var/www/crm_phong_kham/"

echo "Deploy finished."
