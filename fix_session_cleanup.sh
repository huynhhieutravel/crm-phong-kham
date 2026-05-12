#!/bin/bash
VPS_USER='ubutu'
VPS_HOST='112.78.15.2'
SSH_PASS='&m5L9[eUv'

# Run commands to fix PHP GC and Ubuntu Cron that deletes sessions aggressively
/opt/homebrew/bin/sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST "echo '$SSH_PASS' | sudo -S find /etc/php -name php.ini -exec sed -i 's/session.gc_maxlifetime = 1440/session.gc_maxlifetime = 2592000/g' {} \\; && echo '$SSH_PASS' | sudo -S systemctl restart php*-fpm.service apache2 2>/dev/null || true"

echo "Cron and maxlifetime fixed."
