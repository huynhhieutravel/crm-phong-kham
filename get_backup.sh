#!/bin/bash
# get_backup.sh - Triggers VPS backup, downloads it locally, and cleans up VPS storage.

# Configuration
VPS_HOST="64.176.85.168"
VPS_USER="root"
LOCAL_DEST="./backups"
REMOTE_SCRIPT="/root/backup_crm.sh"

# Ensure local backup directory exists
mkdir -p $LOCAL_DEST

echo "--- Step 1: Triggering Backup on VPS ---"
# Run the script and capture the output to find the filename
# Note: sshpass can be used if password is not in SSH keys
# export SSHPASS='#Aa2,zdf87a@Q{3q'
# result=$(sshpass -e sshpass -e ssh -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST "$REMOTE_SCRIPT")

result=$(sshpass -e ssh -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST "$REMOTE_SCRIPT")
echo "$result"

# Extract filename from output (looking for /root/backups/crm_full_backup_*.tar.gz)
REMOTE_FILE=$(echo "$result" | grep -o "/root/backups/crm_full_backup_[0-9_]*\.tar\.gz" | head -n 1)

if [ -z "$REMOTE_FILE" ]; then
    echo "ERROR: Could not find backup filename in VPS output."
    exit 1
fi

echo "--- Step 2: Downloading Backup ($REMOTE_FILE) ---"
# sshpass -e scp -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST:$REMOTE_FILE $LOCAL_DEST/
sshpass -e scp -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST:$REMOTE_FILE $LOCAL_DEST/

if [ $? -eq 0 ]; then
    echo "Download Successful: $LOCAL_DEST/$(basename $REMOTE_FILE)"
else
    echo "ERROR: Download failed!"
    exit 1
fi

echo "--- Step 3: Cleaning up VPS storage ---"
sshpass -e ssh -o StrictHostKeyChecking=no $VPS_USER@$VPS_HOST "rm $REMOTE_FILE"

if [ $? -eq 0 ]; then
    echo "VPS cleanup successful. Storage is clear."
else
    echo "WARNING: Failed to delete backup from VPS. Please check manually."
fi

echo "--- Done! ---"
