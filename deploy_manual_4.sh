#!/bin/bash
echo "Creating deployment package for Zalo Schedule Copy..."
tar -czvf fix_zalo_copy.tar.gz modules/appointments/index.php
echo "Package created: fix_zalo_copy.tar.gz"

VPS_USER='ubutu'
VPS_HOST='112.78.15.2'

echo "=========================================="
echo "Uploading to VPS... PLEASE ENTER PASSWORD: &m5L9[eUv"
echo "=========================================="
scp -o StrictHostKeyChecking=no fix_zalo_copy.tar.gz $VPS_USER@$VPS_HOST:/tmp/fix_zalo_copy.tar.gz

echo "=========================================="
echo "Extracting on VPS... PLEASE ENTER PASSWORD: &m5L9[eUv"
echo "=========================================="
ssh -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST "echo '&m5L9[eUv' | sudo -S tar -xzvf /tmp/fix_zalo_copy.tar.gz -C /var/www/crm_phong_kham/ && echo '&m5L9[eUv' | sudo -S chown -R www-data:www-data /var/www/crm_phong_kham/"

echo ""
echo "Deploy Zalo Schedule Copy finished successfully."
