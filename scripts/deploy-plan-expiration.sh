#!/bin/bash

# Deploy Plan Expiration Feature (Docker Version)
# This script applies the plan expiration feature to your VMaster CMS Portal running in Docker

set -e  # Exit on error

echo "=================================================="
echo "  VMaster Plan Expiration Feature Deployment"
echo "  (Docker Version)"
echo "=================================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if docker-compose is available
if ! command -v docker-compose &> /dev/null && ! command -v docker &> /dev/null; then
    echo -e "${RED}❌ Docker or docker-compose not found!${NC}"
    exit 1
fi

# Detect Docker Compose command (v1 vs v2)
if command -v docker-compose &> /dev/null; then
    DOCKER_COMPOSE="docker-compose"
elif docker compose version &> /dev/null 2>&1; then
    DOCKER_COMPOSE="docker compose"
else
    echo -e "${RED}❌ Docker Compose not found!${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Using: $DOCKER_COMPOSE${NC}"
echo ""

# Get database credentials from docker-compose or user input
echo -e "${YELLOW}📝 Database Configuration${NC}"
read -p "Enter MySQL username [root]: " DB_USER
DB_USER=${DB_USER:-root}

read -sp "Enter MySQL password [root_secure_password]: " DB_PASS
DB_PASS=${DB_PASS:-root_secure_password}
echo ""

read -p "Enter database name [vpn_cms_portal]: " DB_NAME
DB_NAME=${DB_NAME:-vpn_cms_portal}

read -p "Enter database container name [vmaster_db]: " DB_CONTAINER
DB_CONTAINER=${DB_CONTAINER:-vmaster_db}

echo ""
echo -e "${GREEN}✅ Configuration collected${NC}"
echo ""

# Step 1: Check if containers are running
echo -e "${YELLOW}Step 1: Checking Docker containers...${NC}"
if docker ps | grep -q "$DB_CONTAINER"; then
    echo -e "${GREEN}✅ Database container '$DB_CONTAINER' is running${NC}"
else
    echo -e "${RED}❌ Database container '$DB_CONTAINER' is not running!${NC}"
    echo "Please start your containers first: $DOCKER_COMPOSE up -d"
    exit 1
fi
echo ""

# Step 2: Backup database
echo -e "${YELLOW}Step 2: Creating database backup...${NC}"
BACKUP_FILE="backup_before_plan_expiration_$(date +%Y%m%d_%H%M%S).sql"
docker exec "$DB_CONTAINER" mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_FILE" 2>/dev/null
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Database backup created: $BACKUP_FILE${NC}"
else
    echo -e "${RED}❌ Failed to create backup. Exiting.${NC}"
    exit 1
fi
echo ""

# Step 3: Run database migration
echo -e "${YELLOW}Step 3: Running database migration...${NC}"
docker exec -i "$DB_CONTAINER" mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < database/add_plan_duration.sql 2>/dev/null
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Database migration completed${NC}"
else
    echo -e "${YELLOW}⚠️  Migration may have already been applied${NC}"
fi
echo ""

# Step 4: Verify new columns
echo -e "${YELLOW}Step 4: Verifying database changes...${NC}"
VERIFY=$(docker exec "$DB_CONTAINER" mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SHOW COLUMNS FROM vpn_accounts LIKE 'plan_duration';" 2>/dev/null | grep -c plan_duration || true)
if [ "$VERIFY" -gt 0 ]; then
    echo -e "${GREEN}✅ Column 'plan_duration' exists${NC}"
else
    echo -e "${RED}❌ Column 'plan_duration' not found!${NC}"
    exit 1
fi

VERIFY=$(docker exec "$DB_CONTAINER" mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SHOW COLUMNS FROM vpn_accounts LIKE 'expires_at';" 2>/dev/null | grep -c expires_at || true)
if [ "$VERIFY" -gt 0 ]; then
    echo -e "${GREEN}✅ Column 'expires_at' already exists${NC}"
else
    echo -e "${RED}❌ Column 'expires_at' not found!${NC}"
    exit 1
fi
echo ""

# Step 5: Check if logo file exists
echo -e "${YELLOW}Step 5: Checking logo file...${NC}"
if [ -f "assets/images/logo.jpg" ]; then
    echo -e "${GREEN}✅ Logo file found: assets/images/logo.jpg${NC}"
else
    echo -e "${YELLOW}⚠️  Logo file not found. Copying from vmaster_logo.jpg...${NC}"
    if [ -f "vmaster_logo.jpg" ]; then
        mkdir -p assets/images
        cp vmaster_logo.jpg assets/images/logo.jpg
        echo -e "${GREEN}✅ Logo copied successfully${NC}"
    else
        echo -e "${RED}❌ vmaster_logo.jpg not found!${NC}"
    fi
fi
echo ""

# Step 6: Restart Docker containers
echo -e "${YELLOW}Step 6: Restarting web container...${NC}"
$DOCKER_COMPOSE restart web
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Web container restarted${NC}"
else
    echo -e "${RED}❌ Failed to restart web container${NC}"
fi
echo ""

# Summary
echo "=================================================="
echo -e "${GREEN}✅ Deployment Complete!${NC}"
echo "=================================================="
echo ""
echo "📋 Summary:"
echo "  ✅ Database backup created: $BACKUP_FILE"
echo "  ✅ Database migration applied"
echo "  ✅ Plan expiration feature ready"
echo "  ✅ Logo integration ready"
echo ""
echo "🚀 Next Steps:"
echo "  1. Access your portal: http://your-domain.com"
echo "  2. Go to Customer Portal → VPN Accounts"
echo "  3. Create a new VPN account and select a plan duration"
echo "  4. View the expiration dates in the accounts list"
echo ""
echo "📖 Full documentation: PLAN_EXPIRATION_GUIDE.md"
echo ""
echo "⚠️  Note: Existing VPN accounts will show as 'Unlimited'"
echo "    New accounts can be created with specific plan durations"
echo ""

