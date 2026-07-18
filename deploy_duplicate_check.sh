#!/bin/bash
echo "Creating deployment package..."
tar -czvf deploy_duplicate_check.tar.gz modules/appointments/checkin.php modules/leads/convert.php
echo "Package created: deploy_duplicate_check.tar.gz"

VPS_HOST='112.78.15.2'
VPS_USER='ubutu'
echo "Please upload deploy_duplicate_check.tar.gz to your VPS and extract it."
echo "Command to extract on VPS:"
echo "sudo tar -xzvf /tmp/deploy_duplicate_check.tar.gz -C /var/www/crm_phong_kham/ && sudo chown -R www-data:www-data /var/www/crm_phong_kham/"
