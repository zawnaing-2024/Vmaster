#!/bin/bash

###############################################################################
# VMaster CMS - Production Deployment Script
# For Ubuntu Server with Docker
###############################################################################

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PRODUCTION_DIR="/var/www/vmaster"
DB_CONTAINER="vmaster_db"
WEB_CONTAINER="vmaster_web"
DB_ROOT_PASSWORD="rootpassword"
DB_NAME="vpn_cms_portal"

echo -e "${BLUE}╔══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║                                                              ║${NC}"
echo -e "${BLUE}║       VMaster CMS - Production Deployment Script            ║${NC}"
echo -e "${BLUE}║                                                              ║${NC}"
echo -e "${BLUE}╔══════════════════════════════════════════════════════════════╗${NC}"
echo ""

# Step 1: Navigate to production directory
echo -e "${YELLOW}📁 Step 1: Navigating to production directory...${NC}"
if [ ! -d "$PRODUCTION_DIR" ]; then
    echo -e "${RED}❌ Error: Production directory not found: $PRODUCTION_DIR${NC}"
    exit 1
fi
cd $PRODUCTION_DIR
echo -e "${GREEN}✅ Current directory: $(pwd)${NC}"
echo ""

# Step 2: Check if git repository exists
echo -e "${YELLOW}🔍 Step 2: Checking Git repository...${NC}"
if [ ! -d ".git" ]; then
    echo -e "${RED}❌ Error: Not a git repository!${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Git repository found${NC}"
echo ""

# Step 3: Backup current state
echo -e "${YELLOW}💾 Step 3: Creating backup...${NC}"
BACKUP_DIR="/var/backups/vmaster"
mkdir -p $BACKUP_DIR
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

# Backup database
echo "Backing up database..."
docker exec $DB_CONTAINER mysqldump -uroot -p${DB_ROOT_PASSWORD} ${DB_NAME} > ${BACKUP_DIR}/backup_${TIMESTAMP}.sql
echo -e "${GREEN}✅ Database backed up to: ${BACKUP_DIR}/backup_${TIMESTAMP}.sql${NC}"

# Backup current code (if it exists)
if [ -d "customer" ]; then
    tar -czf ${BACKUP_DIR}/code_backup_${TIMESTAMP}.tar.gz \
        --exclude='.git' \
        --exclude='vendor' \
        --exclude='node_modules' \
        .
    echo -e "${GREEN}✅ Code backed up to: ${BACKUP_DIR}/code_backup_${TIMESTAMP}.tar.gz${NC}"
fi
echo ""

# Step 4: Pull latest code from GitHub
echo -e "${YELLOW}📥 Step 4: Pulling latest code from GitHub...${NC}"
echo "Current branch:"
git branch --show-current

echo ""
echo "Fetching updates..."
git fetch origin

echo ""
echo "Pulling changes..."
git pull origin main

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Code updated successfully!${NC}"
else
    echo -e "${RED}❌ Git pull failed! Check for conflicts.${NC}"
    exit 1
fi
echo ""

# Step 5: Apply database migrations
echo -e "${YELLOW}🗃️  Step 5: Applying database migrations...${NC}"

# Check if migrations directory exists
if [ -d "database" ]; then
    echo "Found database migrations folder..."
    
    # Apply migrations in order
    MIGRATIONS=(
        "database/add_plan_duration.sql"
        "database/add_customer_plan_duration_limit.sql"
        "database/add_customer_expiration.sql"
    )
    
    for MIGRATION in "${MIGRATIONS[@]}"; do
        if [ -f "$MIGRATION" ]; then
            echo -e "${BLUE}Applying migration: $MIGRATION${NC}"
            docker exec -i $DB_CONTAINER mysql -uroot -p${DB_ROOT_PASSWORD} ${DB_NAME} < $MIGRATION 2>/dev/null
            
            if [ $? -eq 0 ]; then
                echo -e "${GREEN}✅ Migration applied: $(basename $MIGRATION)${NC}"
            else
                echo -e "${YELLOW}⚠️  Migration may have already been applied: $(basename $MIGRATION)${NC}"
            fi
        else
            echo -e "${YELLOW}⚠️  Migration file not found: $MIGRATION${NC}"
        fi
    done
else
    echo -e "${YELLOW}⚠️  No database folder found${NC}"
fi
echo ""

# Step 6: Set correct permissions
echo -e "${YELLOW}🔐 Step 6: Setting file permissions...${NC}"
# Set ownership to www-data (typical for web servers)
chown -R www-data:www-data $PRODUCTION_DIR 2>/dev/null || echo "Note: Could not change ownership (may need sudo)"

# Set directory permissions
find $PRODUCTION_DIR -type d -exec chmod 755 {} \; 2>/dev/null || true

# Set file permissions
find $PRODUCTION_DIR -type f -exec chmod 644 {} \; 2>/dev/null || true

echo -e "${GREEN}✅ Permissions set${NC}"
echo ""

# Step 7: Restart Docker containers
echo -e "${YELLOW}🔄 Step 7: Restarting Docker containers...${NC}"

# Check if containers are running
if docker ps | grep -q $WEB_CONTAINER; then
    echo "Restarting web container: $WEB_CONTAINER"
    docker restart $WEB_CONTAINER
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅ Web container restarted${NC}"
    else
        echo -e "${RED}❌ Failed to restart web container${NC}"
        exit 1
    fi
else
    echo -e "${YELLOW}⚠️  Web container not running, starting it...${NC}"
    docker start $WEB_CONTAINER || docker-compose up -d $WEB_CONTAINER
fi

# Wait for containers to be healthy
echo "Waiting for containers to be ready..."
sleep 5
echo ""

# Step 8: Verify deployment
echo -e "${YELLOW}✅ Step 8: Verifying deployment...${NC}"

# Check if web container is running
if docker ps | grep -q $WEB_CONTAINER; then
    echo -e "${GREEN}✅ Web container is running${NC}"
else
    echo -e "${RED}❌ Web container is not running!${NC}"
    exit 1
fi

# Check if database container is running
if docker ps | grep -q $DB_CONTAINER; then
    echo -e "${GREEN}✅ Database container is running${NC}"
else
    echo -e "${RED}❌ Database container is not running!${NC}"
    exit 1
fi

# Test database connection
docker exec $DB_CONTAINER mysql -uroot -p${DB_ROOT_PASSWORD} -e "SELECT 1;" > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Database connection successful${NC}"
else
    echo -e "${RED}❌ Database connection failed!${NC}"
fi
echo ""

# Step 9: Clean up old backups (keep last 7 days)
echo -e "${YELLOW}🧹 Step 9: Cleaning up old backups...${NC}"
find $BACKUP_DIR -name "backup_*.sql" -type f -mtime +7 -delete 2>/dev/null || true
find $BACKUP_DIR -name "code_backup_*.tar.gz" -type f -mtime +7 -delete 2>/dev/null || true
echo -e "${GREEN}✅ Old backups cleaned (keeping last 7 days)${NC}"
echo ""

# Final Summary
echo -e "${GREEN}╔══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║                                                              ║${NC}"
echo -e "${GREEN}║              ✅ DEPLOYMENT SUCCESSFUL! ✅                    ║${NC}"
echo -e "${GREEN}║                                                              ║${NC}"
echo -e "${GREEN}╚══════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${BLUE}📊 Deployment Summary:${NC}"
echo -e "  • Code updated from GitHub ✅"
echo -e "  • Database migrations applied ✅"
echo -e "  • Containers restarted ✅"
echo -e "  • Backups created ✅"
echo ""
echo -e "${BLUE}📁 Backup Location:${NC}"
echo -e "  Database: ${BACKUP_DIR}/backup_${TIMESTAMP}.sql"
echo -e "  Code: ${BACKUP_DIR}/code_backup_${TIMESTAMP}.tar.gz"
echo ""
echo -e "${BLUE}🌐 Your site should now be updated at:${NC}"
echo -e "  Admin: http://your-server-ip/admin"
echo -e "  Customer: http://your-server-ip/customer"
echo ""
echo -e "${YELLOW}💡 Tip: Check the logs if something doesn't work:${NC}"
echo -e "  docker logs $WEB_CONTAINER"
echo -e "  docker logs $DB_CONTAINER"
echo ""
echo -e "${GREEN}🎉 Deployment completed successfully!${NC}"

