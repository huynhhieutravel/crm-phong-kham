#!/bin/bash
echo "Creating deployment package for Billing Bug..."
tar -czvf fix_billing.tar.gz modules/billing/create_invoice_api.php modules/billing/update_invoice_status_api.php sync_invoices.php clean_duplicate_transactions.php
echo "Package created: fix_billing.tar.gz"

VPS_USER='ubutu'
VPS_HOST='112.78.15.2'

echo "=========================================="
echo "Uploading to VPS... PLEASE ENTER PASSWORD: &m5L9[eUv"
echo "=========================================="
scp -o StrictHostKeyChecking=no fix_billing.tar.gz $VPS_USER@$VPS_HOST:/tmp/fix_billing.tar.gz

echo "=========================================="
echo "Extracting on VPS... PLEASE ENTER PASSWORD: &m5L9[eUv"
echo "=========================================="
ssh -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST "echo '&m5L9[eUv' | sudo -S tar -xzvf /tmp/fix_billing.tar.gz -C /var/www/crm_phong_kham/ && echo '&m5L9[eUv' | sudo -S chown -R www-data:www-data /var/www/crm_phong_kham/ && echo '&m5L9[eUv' | sudo -S php /var/www/crm_phong_kham/clean_duplicate_transactions.php"

echo ""
echo "Deploy & Clean finished successfully."
