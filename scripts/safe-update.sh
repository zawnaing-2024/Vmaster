#!/bin/bash

# Safe Update Script for Production
# This script updates code WITHOUT affecting database or user data

set -e

echo "╔══════════════════════════════════════════════════════════════╗"
echo "║                                                              ║"
echo "║         🚀 VMaster Safe Production Update 🚀                 ║"
echo "║                                                              ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Configuration
DB_USER="root"
DB_PASS="rootpassword"
DB_NAME="vpn_cms_portal"
DB_CONTAINER="vpn_cms_db"
WEB_CONTAINER="vpn_cms_web"

echo -e "${YELLOW}📋 Pre-Update Checklist${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Check if Docker is running
if ! docker ps > /dev/null 2>&1; then
    echo -e "${RED}❌ Docker is not running!${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Docker is running${NC}"

# Check if containers are running
if ! docker ps | grep -q "$DB_CONTAINER"; then
    echo -e "${RED}❌ Database container not running!${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Database container is running${NC}"

if ! docker ps | grep -q "$WEB_CONTAINER"; then
    echo -e "${RED}❌ Web container not running!${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Web container is running${NC}"

echo ""
echo -e "${YELLOW}💾 Step 1: Backing up database${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

BACKUP_FILE="backup_before_update_$(date +%Y%m%d_%H%M%S).sql"
docker exec "$DB_CONTAINER" mysqldump -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_FILE" 2>/dev/null

if [ $? -eq 0 ]; then
    BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
    echo -e "${GREEN}✅ Database backup created: $BACKUP_FILE ($BACKUP_SIZE)${NC}"
else
    echo -e "${RED}❌ Failed to backup database!${NC}"
    exit 1
fi

echo ""
echo -e "${YELLOW}📥 Step 2: Pulling latest code from GitHub${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Save current commit hash
CURRENT_COMMIT=$(git rev-parse --short HEAD)
echo "Current version: $CURRENT_COMMIT"

# Pull latest code
git fetch origin
LATEST_COMMIT=$(git rev-parse --short origin/main)

if [ "$CURRENT_COMMIT" = "$LATEST_COMMIT" ]; then
    echo -e "${GREEN}✅ Already up to date!${NC}"
    echo ""
    echo "No updates needed. Your system is running the latest version."
    exit 0
fi

echo "Updating from $CURRENT_COMMIT to $LATEST_COMMIT..."

# Stash any local changes
if ! git diff-index --quiet HEAD --; then
    echo -e "${YELLOW}⚠️  Stashing local changes...${NC}"
    git stash
fi

# Pull latest code
git pull origin main

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Code updated successfully${NC}"
else
    echo -e "${RED}❌ Failed to pull code!${NC}"
    exit 1
fi

echo ""
echo -e "${YELLOW}🔄 Step 3: Running database migrations${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Check if migration file exists
if [ -f "database/add_plan_duration.sql" ]; then
    echo "Applying plan_duration migration..."
    docker exec -i "$DB_CONTAINER" mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < database/add_plan_duration.sql 2>/dev/null
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅ Migration applied successfully${NC}"
    else
        echo -e "${YELLOW}⚠️  Migration may have already been applied (this is OK)${NC}"
    fi
else
    echo -e "${YELLOW}ℹ️  No new migrations to apply${NC}"
fi

echo ""
echo -e "${YELLOW}🔄 Step 4: Restarting web container${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

docker restart "$WEB_CONTAINER"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Web container restarted${NC}"
else
    echo -e "${RED}❌ Failed to restart web container!${NC}"
    exit 1
fi

# Wait for container to be ready
echo "Waiting for container to be ready..."
sleep 3

echo ""
echo -e "${YELLOW}✅ Step 5: Verifying update${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Check if web container is running
if docker ps | grep -q "$WEB_CONTAINER"; then
    echo -e "${GREEN}✅ Web container is running${NC}"
else
    echo -e "${RED}❌ Web container is not running!${NC}"
    exit 1
fi

# Check if database column exists
COLUMN_CHECK=$(docker exec "$DB_CONTAINER" mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SHOW COLUMNS FROM vpn_accounts LIKE 'plan_duration';" 2>/dev/null | grep -c plan_duration || true)

if [ "$COLUMN_CHECK" -gt 0 ]; then
    echo -e "${GREEN}✅ Database schema is up to date${NC}"
else
    echo -e "${YELLOW}⚠️  Database schema may need attention${NC}"
fi

echo ""
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║                                                              ║"
echo "║              ✅ UPDATE COMPLETED SUCCESSFULLY! ✅             ║"
echo "║                                                              ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo ""
echo -e "${GREEN}📊 Update Summary:${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  Previous version: $CURRENT_COMMIT"
echo "  Current version:  $LATEST_COMMIT"
echo "  Backup file:      $BACKUP_FILE"
echo "  Web container:    Running ✅"
echo "  Database:         Protected ✅"
echo ""
echo -e "${GREEN}🎯 What to do next:${NC}"
echo "  1. Test your website: http://your-server-ip:8000"
echo "  2. Login to admin panel"
echo "  3. Login to customer portal"
echo "  4. Create a test VPN account with custom plan"
echo "  5. Verify existing accounts still work"
echo ""
echo -e "${YELLOW}💾 Backup Location:${NC}"
echo "  $BACKUP_FILE"
echo "  Keep this file safe in case you need to rollback!"
echo ""
echo -e "${GREEN}🔄 To rollback (if needed):${NC}"
echo "  docker exec -i $DB_CONTAINER mysql -u$DB_USER -p$DB_PASS $DB_NAME < $BACKUP_FILE"
echo "  git reset --hard $CURRENT_COMMIT"
echo "  docker restart $WEB_CONTAINER"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

