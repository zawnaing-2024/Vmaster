#!/bin/bash

# VMaster Quick Update Script
# Zero-downtime update for production

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}  VMaster Quick Update - Version 1.0${NC}"
echo -e "${BLUE}════════════════════════════════════════════════════${NC}"
echo ""

# Check if running as root or with sudo
if [ "$EUID" -ne 0 ] && ! groups | grep -q docker; then
    echo -e "${RED}Please run with sudo or ensure your user is in the docker group${NC}"
    exit 1
fi

# Step 1: Check for updates
echo -e "${YELLOW}📡 Checking for updates...${NC}"
git fetch origin
UPDATES=$(git rev-list HEAD...origin/main --count)
if [ "$UPDATES" -eq 0 ]; then
    echo -e "${GREEN}✅ Already up to date!${NC}"
    exit 0
fi
echo -e "${GREEN}Found $UPDATES new commit(s)${NC}"
echo ""

# Show what will be updated
echo -e "${YELLOW}📋 Changes:${NC}"
git log HEAD..origin/main --oneline | head -10
echo ""

# Ask for confirmation
read -p "Continue with update? (y/n) " -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Update cancelled"
    exit 0
fi

# Step 2: Backup
echo ""
echo -e "${YELLOW}📦 Step 1/5: Creating backup...${NC}"
./scripts/backup.sh
if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Backup failed! Aborting update.${NC}"
    exit 1
fi

# Step 3: Pull code
echo ""
echo -e "${YELLOW}📥 Step 2/5: Pulling latest code...${NC}"
git pull origin main
if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Git pull failed! Check for conflicts.${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Code updated${NC}"

# Step 4: Update containers
echo ""
echo -e "${YELLOW}🐳 Step 3/5: Updating Docker containers...${NC}"
docker-compose up -d --build
if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Docker update failed!${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Containers updated${NC}"

# Step 5: Wait for services
echo ""
echo -e "${YELLOW}⏳ Step 4/5: Waiting for services to start...${NC}"
sleep 10

# Check if containers are running
if ! docker-compose ps | grep -q "Up"; then
    echo -e "${RED}❌ Some containers failed to start!${NC}"
    docker-compose ps
    exit 1
fi
echo -e "${GREEN}✅ All containers running${NC}"

# Step 6: Run migrations
echo ""
echo -e "${YELLOW}🔧 Step 5/5: Running database migrations...${NC}"
docker exec vpn_cms_web php /var/www/html/admin/run-migration.php 2>/dev/null
echo -e "${GREEN}✅ Migrations complete${NC}"

# Verification
echo ""
echo -e "${BLUE}════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}🎉 Update Complete!${NC}"
echo -e "${BLUE}════════════════════════════════════════════════════${NC}"
echo ""

# Show new version
if [ -f "VERSION" ]; then
    NEW_VERSION=$(cat VERSION)
    echo -e "📌 Current version: ${GREEN}v${NEW_VERSION}${NC}"
fi

# Show running containers
echo ""
echo "📊 Container Status:"
docker-compose ps

echo ""
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo "🧪 Please verify:"
echo "  1. Admin login: http://your-domain.com/admin/login.php"
echo "  2. Customer login: http://your-domain.com/customer/login.php"
echo "  3. Create test VPN account"
echo "  4. Check RADIUS (if enabled)"
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo "📝 Logs: docker logs -f vpn_cms_web"
echo "🔙 Rollback: git reset --hard HEAD~1 && docker-compose up -d"
echo ""

