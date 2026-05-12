#!/bin/bash
SSH_PASS='&m5L9[eUv'
VPS_USER='ubutu'
VPS_HOST='112.78.15.2'

/opt/homebrew/bin/sshpass -p "$SSH_PASS" scp -o StrictHostKeyChecking=no fix_db.sql $VPS_USER@$VPS_HOST:/tmp/fix_db.sql

/opt/homebrew/bin/sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST "mysql -u crm_admin -p'CrmAdmin2026@Pass' clinic_management < /tmp/fix_db.sql"

echo "DB fixes deployed and executed."
