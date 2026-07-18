#!/bin/bash
SSH_PASS='&m5L9[eUv'
VPS_USER='ubutu'
VPS_HOST='112.78.15.2'

/opt/homebrew/bin/sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST "echo '$SSH_PASS' | sudo -S tail -n 50 /var/log/nginx/error.log; echo '$SSH_PASS' | sudo -S tail -n 50 /var/log/php8.1-fpm.log;"
