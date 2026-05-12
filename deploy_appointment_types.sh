#!/bin/bash
SSH_PASS='&m5L9[eUv'
VPS_USER='ubutu'
VPS_HOST='112.78.15.2'

echo "1. Creating tar package..."
tar -czvf deploy_appointment_types.tar.gz \
lang/vi.php \
lang/en.php \
modules/appointments/add.php \
modules/appointments/edit.php \
modules/appointments/index.php \
modules/appointments/timeline.php \
modules/appointments/dashboard.php \
database.sql

echo "2. Uploading to VPS..."
/opt/homebrew/bin/sshpass -p "$SSH_PASS" scp -o StrictHostKeyChecking=no deploy_appointment_types.tar.gz $VPS_USER@$VPS_HOST:/tmp/deploy_appointment_types.tar.gz

echo "3. Extracting and fixing permissions on VPS..."
/opt/homebrew/bin/sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST "echo '$SSH_PASS' | sudo -S tar -xzvf /tmp/deploy_appointment_types.tar.gz -C /var/www/crm_phong_kham/ && echo '$SSH_PASS' | sudo -S chown -R www-data:www-data /var/www/crm_phong_kham/"

echo "4. Running database ALTER command on VPS..."
cat << 'EOF' > update_db_tmp.php
<?php
require_once __DIR__ . '/includes/db.php';
$db = getDB();
try {
    $db->exec("ALTER TABLE appointments MODIFY COLUMN type enum('consultation','treatment','re_exam','adjustment','dong_y_60','dong_y_90','chiro','support_other') DEFAULT 'consultation'");
    echo "DB Updated successfully.\n";
} catch (Exception $e) {
    echo "DB Error: " . $e->getMessage() . "\n";
}
EOF

/opt/homebrew/bin/sshpass -p "$SSH_PASS" scp -o StrictHostKeyChecking=no update_db_tmp.php $VPS_USER@$VPS_HOST:/tmp/update_db_tmp.php
/opt/homebrew/bin/sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST "echo '$SSH_PASS' | sudo -S cp /tmp/update_db_tmp.php /var/www/crm_phong_kham/update_db_tmp.php && echo '$SSH_PASS' | sudo -S php /var/www/crm_phong_kham/update_db_tmp.php && echo '$SSH_PASS' | sudo -S rm /var/www/crm_phong_kham/update_db_tmp.php"

rm update_db_tmp.php
echo "Deploy finished."
