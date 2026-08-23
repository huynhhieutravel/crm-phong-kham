#!/bin/bash
# ============================================================
# FULL BACKUP SCRIPT — SIMON CENTER (QUAN LY PHONG KHAM)
# VPS: 112.78.15.2
# ============================================================

set -e

VPS="ubutu@112.78.15.2"
PASSWORD="&m5L9[eUv"
REMOTE_PATH="/var/www/crm_phong_kham"
PROJECT_ROOT="$(cd "$(dirname "$0")" && pwd)"
BACKUP_DIR="$PROJECT_ROOT/_backups"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_NAME="simon_center_full_backup_$TIMESTAMP"
REMOTE_TMP="/tmp/$BACKUP_NAME"

echo ""
echo "╔══════════════════════════════════════════════════╗"
echo "║      🚀 FULL BACKUP SIMON CENTER (PHÒNG KHÁM)    ║"
echo "║           IP: 112.78.15.2                        ║"
echo "╚══════════════════════════════════════════════════╝"
echo ""

mkdir -p "$BACKUP_DIR"

echo "📦 [1/4] Dumping MySQL Database trên VPS..."
sshpass -p "$PASSWORD" ssh -o StrictHostKeyChecking=no "$VPS" "
  echo '$PASSWORD' | sudo -S mysqldump -u crm_admin -p'CrmAdmin2026@Pass' clinic_management > $REMOTE_TMP.sql
"
echo "✅ Export database thành công."

echo ""
echo "🗜️  [2/4] Nén toàn bộ Code, DB và Tài liệu hình ảnh (uploads)..."
sshpass -p "$PASSWORD" ssh -o StrictHostKeyChecking=no "$VPS" "
  echo '$PASSWORD' | sudo -S mv $REMOTE_TMP.sql $REMOTE_PATH/database_backup.sql
  cd /var/www
  echo '$PASSWORD' | sudo -S tar -czf $REMOTE_TMP.tar.gz crm_phong_kham
  echo '$PASSWORD' | sudo -S rm $REMOTE_PATH/database_backup.sql
  echo '$PASSWORD' | sudo -S chown ubutu:ubutu $REMOTE_TMP.tar.gz
"
echo "✅ Đã nén thành công $REMOTE_TMP.tar.gz."

echo ""
echo "📥 [3/4] Tải file backup về máy local (_backups/)..."
sshpass -p "$PASSWORD" scp -o StrictHostKeyChecking=no "$VPS:$REMOTE_TMP.tar.gz" "$BACKUP_DIR/"
echo "✅ Đã tải file về thành công!"

echo ""
echo "🧹 [4/4] Dọn dẹp file tạm trên VPS..."
sshpass -p "$PASSWORD" ssh -o StrictHostKeyChecking=no "$VPS" "
  echo '$PASSWORD' | sudo -S rm -f $REMOTE_TMP.tar.gz
"
echo "✅ Hoàn tất!"

echo ""
echo "🎉 BACKUP TOÀN BỘ THÀNH CÔNG!"
echo "📍 File của bạn ở: _backups/${BACKUP_NAME}.tar.gz"
echo ""
