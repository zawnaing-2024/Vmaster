#!/bin/bash

echo "🔍 VMaster System Verification"
echo "================================"
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check Docker
echo -n "1. Docker running: "
if docker info > /dev/null 2>&1; then
    echo -e "${GREEN}✅ OK${NC}"
else
    echo -e "${RED}❌ FAILED${NC}"
    exit 1
fi

# Check containers
echo -n "2. Containers running: "
if [ "$(docker-compose ps -q | wc -l)" -ge 3 ]; then
    echo -e "${GREEN}✅ OK${NC}"
else
    echo -e "${RED}❌ FAILED${NC}"
    echo "   Run: docker-compose up -d"
    exit 1
fi

# Check web container
echo -n "3. Web container healthy: "
if docker-compose ps web | grep -q "Up"; then
    echo -e "${GREEN}✅ OK${NC}"
else
    echo -e "${RED}❌ FAILED${NC}"
    exit 1
fi

# Check database container
echo -n "4. Database container healthy: "
if docker-compose ps db | grep -q "Up"; then
    echo -e "${GREEN}✅ OK${NC}"
else
    echo -e "${RED}❌ FAILED${NC}"
    exit 1
fi

# Check web server responding
echo -n "5. Web server responding: "
if curl -s http://localhost:8080 > /dev/null; then
    echo -e "${GREEN}✅ OK${NC}"
else
    echo -e "${RED}❌ FAILED${NC}"
    exit 1
fi

# Check admin panel
echo -n "6. Admin panel accessible: "
if curl -s http://localhost:8080/admin/login.php | grep -q "VMaster"; then
    echo -e "${GREEN}✅ OK${NC}"
else
    echo -e "${RED}❌ FAILED${NC}"
    exit 1
fi

# Check customer portal
echo -n "7. Customer portal accessible: "
if curl -s http://localhost:8080/customer/login.php | grep -q "VMaster"; then
    echo -e "${GREEN}✅ OK${NC}"
else
    echo -e "${RED}❌ FAILED${NC}"
    exit 1
fi

# Check for PHP errors in admin
echo -n "8. Admin panel no PHP errors: "
if curl -s http://localhost:8080/admin/login.php | grep -qE "Fatal error|Parse error"; then
    echo -e "${RED}❌ FAILED${NC}"
    exit 1
else
    echo -e "${GREEN}✅ OK${NC}"
fi

# Check database tables
echo -n "9. Database tables exist: "
if docker-compose exec -T db mysql -u root -prootpass vpn_cms_portal -e "SHOW TABLES;" 2>/dev/null | grep -q "client_accounts"; then
    echo -e "${GREEN}✅ OK${NC}"
else
    echo -e "${YELLOW}⚠️  WARNING${NC}"
    echo "   Run migration: docker-compose exec db mysql -u root -prootpass vpn_cms_portal < database/migration_to_clients.sql"
fi

# Check file permissions
echo -n "10. File permissions OK: "
if [ -r config/config.php ] && [ -r config/database.php ]; then
    echo -e "${GREEN}✅ OK${NC}"
else
    echo -e "${RED}❌ FAILED${NC}"
    echo "   Run: chmod 644 config/*.php"
    exit 1
fi

echo ""
echo "================================"
echo -e "${GREEN}✅ All checks passed!${NC}"
echo ""
echo "🌐 Access your system:"
echo "   Landing Page:  http://localhost:8080"
echo "   Admin Panel:   http://localhost:8080/admin/login.php"
echo "   Customer:      http://localhost:8080/customer/login.php"
echo "   phpMyAdmin:    http://localhost:8081"
echo ""
echo "🔐 Default Login:"
echo "   Username: admin"
echo "   Password: admin123"
echo ""
echo -e "${YELLOW}⚠️  Don't forget to change the default password!${NC}"
echo ""
echo "📚 Documentation:"
echo "   - QUICK_START.md - Get started quickly"
echo "   - PRODUCTION_DEPLOYMENT.md - Deploy to production"
echo "   - FINAL_UPDATE_SUMMARY.md - Complete changelog"
echo ""
echo "🎉 VMaster is ready to use!"

