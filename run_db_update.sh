#!/bin/bash
SSH_PASS='&m5L9[eUv'
VPS_USER='ubutu'
VPS_HOST='112.78.15.2'

/opt/homebrew/bin/sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST "mysql -u crm_phong_kham -pcrm_phong_kham crm_phong_kham -e 'ALTER TABLE packages DROP FOREIGN KEY fk_packages_product;' ; mysql -u crm_phong_kham -pcrm_phong_kham crm_phong_kham -e 'ALTER TABLE packages DROP COLUMN linked_product_id;'"

echo "DB updated."
