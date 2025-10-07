#!/bin/bash

# VMaster Backup Script
# Backs up database and uploaded files

# Configuration
BACKUP_DIR="/var/backups/vmaster"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=30

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo "════════════════════════════════════════════════════"
echo "  VMaster Backup Script"
echo "════════════════════════════════════════════════════"
echo ""

# Create backup directory
mkdir -p $BACKUP_DIR

# Backup VMaster database
echo -n "📦 Backing up VMaster database... "
docker exec vpn_cms_db mysqldump -uroot -prootpassword vpn_cms_portal \
    > $BACKUP_DIR/vmaster_${DATE}.sql 2>/dev/null
if [ $? -eq 0 ]; then
    SIZE=$(du -h $BACKUP_DIR/vmaster_${DATE}.sql | cut -f1)
    echo -e "${GREEN}✅ Done (${SIZE})${NC}"
else
    echo -e "${RED}❌ Failed${NC}"
    exit 1
fi

# Backup RADIUS database (if exists)
if docker ps | grep -q radius_db; then
    echo -n "📦 Backing up RADIUS database... "
    docker exec radius_db mysqldump -uradius -pradiuspass radius \
        > $BACKUP_DIR/radius_${DATE}.sql 2>/dev/null
    if [ $? -eq 0 ]; then
        SIZE=$(du -h $BACKUP_DIR/radius_${DATE}.sql | cut -f1)
        echo -e "${GREEN}✅ Done (${SIZE})${NC}"
    else
        echo -e "${YELLOW}⚠️  Skipped${NC}"
    fi
fi

# Backup uploaded files
echo -n "📁 Backing up uploaded files... "
if [ -d "uploads" ]; then
    tar -czf $BACKUP_DIR/uploads_${DATE}.tar.gz uploads/ 2>/dev/null
    if [ $? -eq 0 ]; then
        SIZE=$(du -h $BACKUP_DIR/uploads_${DATE}.tar.gz | cut -f1)
        echo -e "${GREEN}✅ Done (${SIZE})${NC}"
    else
        echo -e "${YELLOW}⚠️  Failed${NC}"
    fi
else
    echo -e "${YELLOW}⚠️  No uploads directory${NC}"
fi

# Backup configuration files
echo -n "⚙️  Backing up configuration... "
tar -czf $BACKUP_DIR/config_${DATE}.tar.gz config/ 2>/dev/null
if [ $? -eq 0 ]; then
    SIZE=$(du -h $BACKUP_DIR/config_${DATE}.tar.gz | cut -f1)
    echo -e "${GREEN}✅ Done (${SIZE})${NC}"
else
    echo -e "${YELLOW}⚠️  Failed${NC}"
fi

# Create version info
echo "$DATE" > $BACKUP_DIR/backup_${DATE}.info
cat VERSION >> $BACKUP_DIR/backup_${DATE}.info 2>/dev/null
git log -1 --oneline >> $BACKUP_DIR/backup_${DATE}.info 2>/dev/null

echo ""
echo "════════════════════════════════════════════════════"
echo -e "${GREEN}✅ Backup Complete!${NC}"
echo "════════════════════════════════════════════════════"
echo ""
echo "📂 Backup location: $BACKUP_DIR"
echo "📅 Backup date: $DATE"
echo ""
ls -lh $BACKUP_DIR/*${DATE}* | awk '{print "  " $9 " (" $5 ")"}'
echo ""

# Clean old backups
echo "🧹 Cleaning backups older than $RETENTION_DAYS days..."
find $BACKUP_DIR -name "*.sql" -mtime +$RETENTION_DAYS -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +$RETENTION_DAYS -delete
find $BACKUP_DIR -name "*.info" -mtime +$RETENTION_DAYS -delete
echo -e "${GREEN}✅ Cleanup complete${NC}"
echo ""

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "To restore from this backup:"
echo "  docker exec -i vpn_cms_db mysql -uroot -prootpassword vpn_cms_portal < $BACKUP_DIR/vmaster_${DATE}.sql"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

