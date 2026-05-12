#!/bin/bash
tar -czf update_invoice2.tar.gz modules/appointments/index.php includes/invoice_popup.php modules/billing/create_invoice_api.php
/opt/homebrew/bin/sshpass -p "&m5L9[eUv" scp -o StrictHostKeyChecking=no update_invoice2.tar.gz ubutu@112.78.15.2:/home/ubutu/
/opt/homebrew/bin/sshpass -p "&m5L9[eUv" ssh -o StrictHostKeyChecking=no ubutu@112.78.15.2 "echo '&m5L9[eUv' | sudo -S tar -xzf /home/ubutu/update_invoice2.tar.gz -C /var/www/crm_phong_kham/ && echo '&m5L9[eUv' | sudo -S chown -R www-data:www-data /var/www/crm_phong_kham/modules /var/www/crm_phong_kham/includes"
echo "Deploy 2 finished."
