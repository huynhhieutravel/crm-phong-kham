#!/bin/bash
echo "Creating deployment package for Report Bug..."
tar -czvf fix_report.tar.gz modules/reports/index.php
echo "Package created: fix_report.tar.gz"

VPS_USER='ubutu'
VPS_HOST='112.78.15.2'

echo "=========================================="
echo "Uploading to VPS... PLEASE ENTER PASSWORD: &m5L9[eUv"
echo "=========================================="
scp -o StrictHostKeyChecking=no fix_report.tar.gz $VPS_USER@$VPS_HOST:/tmp/fix_report.tar.gz

echo "=========================================="
echo "Extracting on VPS... PLEASE ENTER PASSWORD: &m5L9[eUv"
echo "=========================================="
ssh -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST "echo '&m5L9[eUv' | sudo -S tar -xzvf /tmp/fix_report.tar.gz -C /var/www/crm_phong_kham/ && echo '&m5L9[eUv' | sudo -S chown -R www-data:www-data /var/www/crm_phong_kham/"

echo ""
echo "Deploy Report Bug finished successfully."
