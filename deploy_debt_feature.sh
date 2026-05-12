#!/bin/bash
# Deploy: Debt Management Feature
# Files: manage_shared.php, index.php (sales), migrate_package_payments.php

echo "📦 Packing files..."
tar -czf deploy_debt_feature.tar.gz \
    modules/sales/manage_shared.php \
    modules/sales/index.php \
    migrate_package_payments.php

echo "🚀 Uploading to VPS..."
/opt/homebrew/bin/sshpass -p "&m5L9[eUv" scp -o StrictHostKeyChecking=no deploy_debt_feature.tar.gz ubutu@112.78.15.2:/home/ubutu/

echo "📂 Extracting on VPS..."
/opt/homebrew/bin/sshpass -p "&m5L9[eUv" ssh -o StrictHostKeyChecking=no ubutu@112.78.15.2 "echo '&m5L9[eUv' | sudo -S tar -xzf /home/ubutu/deploy_debt_feature.tar.gz -C /var/www/crm_phong_kham/ && echo '&m5L9[eUv' | sudo -S chown -R www-data:www-data /var/www/crm_phong_kham/modules/sales /var/www/crm_phong_kham/migrate_package_payments.php"

echo "🗄️ Running migration on VPS..."
/opt/homebrew/bin/sshpass -p "&m5L9[eUv" ssh -o StrictHostKeyChecking=no ubutu@112.78.15.2 "cd /var/www/crm_phong_kham && php migrate_package_payments.php"

echo "✅ Deploy complete!"
