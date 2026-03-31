#!/bin/bash
# backup_crm.sh - Automates database dump and file compression for Simon Center CRM

# Configuration
BACKUP_DIR="/root/backups"
WEB_ROOT="/var/www/crm.simoncenter.vn"
DB_NAME="clinic_management"
DB_USER="crm_admin"
DB_PASS="CrmAdmin2026@Pass"
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_NAME="crm_full_backup_$DATE.tar.gz"

# Ensure backup directory exists
mkdir -p $BACKUP_DIR

echo "--- Starting Backup $DATE ---"

# 1. Database Dump
echo "Dumping database..."
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME > $WEB_ROOT/db_snapshot.sql

if [ $? -eq 0 ]; then
    echo "Database dump successful."
else
    echo "ERROR: Database dump failed!"
    exit 1
fi

# 2. Compress Files & Database Dump
echo "Compressing files (this may take a moment)..."
tar -czf $BACKUP_DIR/$BACKUP_NAME -C /var/www crm.simoncenter.vn

if [ $? -eq 0 ]; then
    echo "Compression successful: $BACKUP_DIR/$BACKUP_NAME"
else
    echo "ERROR: Compression failed!"
    rm $WEB_ROOT/db_snapshot.sql
    exit 1
fi

# 3. Cleanup
echo "Cleaning up temporary files..."
rm $WEB_ROOT/db_snapshot.sql

# 4. Cleanup old backups (optional - keep last 7 days)
# find $BACKUP_DIR -name "crm_full_backup_*.tar.gz" -mtime +7 -delete

echo "--- Backup Complete! ---"
echo "You can download this backup using SCP:"
echo "scp root@64.176.85.168:$BACKUP_DIR/$BACKUP_NAME ./"
