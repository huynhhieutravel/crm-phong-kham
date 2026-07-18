#!/bin/bash
echo "Creating deployment package for Payments Fix..."
tar -czvf fix_payments.tar.gz modules/billing/index.php modules/billing/create_invoice_api.php modules/billing/update_invoice_status_api.php includes/invoice_popup.php modules/sales/checkout.php modules/sales/manage_shared.php run_migration.php
echo "Package created: fix_payments.tar.gz"

VPS_USER='ubutu'
VPS_HOST='112.78.15.2'

echo "Uploading to VPS..."
scp -o StrictHostKeyChecking=no fix_payments.tar.gz $VPS_USER@$VPS_HOST:/tmp/fix_payments.tar.gz

echo "Extracting and applying on VPS..."
ssh -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST "echo '&m5L9[eUv' | sudo -S tar -xzvf /tmp/fix_payments.tar.gz -C /var/www/crm_phong_kham/ && echo '&m5L9[eUv' | sudo -S chown -R www-data:www-data /var/www/crm_phong_kham/"

echo "Running Database Migration on VPS..."
ssh -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST "echo '&m5L9[eUv' | sudo -S php /var/www/crm_phong_kham/run_migration.php"

echo "Deploy finished successfully."
