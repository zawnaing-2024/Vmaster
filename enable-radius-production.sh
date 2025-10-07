#!/bin/bash

echo "════════════════════════════════════════════════════════════════"
echo "  🚀 VMaster RADIUS Production Setup"
echo "════════════════════════════════════════════════════════════════"
echo ""

# Check if we're in the right directory
if [ ! -f "docker-compose.prod.yml" ]; then
    echo "❌ Error: docker-compose.prod.yml not found!"
    echo "Please run this script from /var/www/vmaster directory"
    exit 1
fi

echo "Step 1: Stopping containers..."
docker-compose -f docker-compose.prod.yml down

echo ""
echo "Step 2: Starting containers with RADIUS..."
docker-compose -f docker-compose.prod.yml up -d

echo ""
echo "Step 3: Waiting for RADIUS DB to be ready (30 seconds)..."
sleep 30

echo ""
echo "Step 4: Importing RADIUS schema..."
docker exec -i vmaster_radius_db mysql -uradius -pradiuspass radius < radius/schema.sql 2>&1 | grep -v "Warning" || echo "✅ Schema imported"

echo ""
echo "Step 5: Verifying RADIUS tables..."
docker exec vmaster_radius_db mysql -uradius -pradiuspass radius -e "SHOW TABLES" 2>&1 | grep -v "Warning"

echo ""
echo "Step 6: Enabling RADIUS in configuration..."
docker exec vmaster_web sed -i "s/define('RADIUS_ENABLED', false);/define('RADIUS_ENABLED', true);/" /var/www/html/config/radius.php 2>&1 || echo "Already enabled"

echo ""
echo "Step 7: Verifying RADIUS configuration..."
docker exec vmaster_web php -r "require '/var/www/html/config/radius.php'; echo 'RADIUS_ENABLED: ' . (RADIUS_ENABLED ? '✅ TRUE' : '❌ FALSE') . PHP_EOL; echo 'RADIUS_DB_HOST: ' . RADIUS_DB_HOST . PHP_EOL;"

echo ""
echo "Step 8: Testing RADIUS connection..."
docker exec vmaster_web php -r "require '/var/www/html/config/radius.php'; \$conn = getRadiusConnection(); echo \$conn ? '✅ RADIUS Database Connected!' : '❌ Connection Failed'; echo PHP_EOL;"

echo ""
echo "Step 9: Creating test RADIUS user..."
docker exec vmaster_web php -r "
require '/var/www/html/config/radius.php';
require '/var/www/html/includes/radius_handler.php';
\$radius = new RadiusHandler();
\$result = \$radius->createUser('test_sstp_001', 'TestPassword123!');
echo \$result ? '✅ Test user created successfully' : '❌ Failed to create test user';
echo PHP_EOL;
" 2>&1

echo ""
echo "Step 10: Verifying test user in RADIUS..."
docker exec vmaster_radius_db mysql -uradius -pradiuspass radius -e "SELECT username, attribute, value FROM radcheck WHERE username='test_sstp_001'" 2>&1 | grep -v "Warning"

echo ""
echo "Step 11: Cleaning up test user..."
docker exec vmaster_web php -r "
require '/var/www/html/config/radius.php';
require '/var/www/html/includes/radius_handler.php';
\$radius = new RadiusHandler();
\$radius->deleteUser('test_sstp_001');
echo '✅ Test user deleted';
echo PHP_EOL;
" 2>&1

echo ""
echo "Step 12: Restarting web container..."
docker restart vmaster_web
sleep 5

echo ""
echo "════════════════════════════════════════════════════════════════"
echo "  ✅ RADIUS SETUP COMPLETE!"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "📊 Container Status:"
docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}" | grep vmaster

echo ""
echo "📈 RADIUS Statistics:"
docker exec vmaster_radius_db mysql -uradius -pradiuspass radius -e "SELECT 'Total RADIUS Users' as Metric, COUNT(DISTINCT username) as Count FROM radcheck" 2>&1 | grep -v "Warning"

echo ""
echo "════════════════════════════════════════════════════════════════"
echo "🎯 What's Next?"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "1. ✅ RADIUS is now ENABLED and ready for automation"
echo "2. ✅ Create SSTP/V2Ray accounts → Auto-creates in RADIUS"
echo "3. ✅ Suspend client → Auto-suspends in RADIUS"
echo "4. ✅ Delete VPN account → Auto-deletes from RADIUS"
echo ""
echo "🧪 Test it:"
echo "   1. Login as customer"
echo "   2. Create an SSTP VPN account"
echo "   3. Check RADIUS: docker exec vmaster_radius_db mysql -uradius -pradiuspass radius -e \"SELECT username FROM radcheck\""
echo ""
echo "════════════════════════════════════════════════════════════════"
echo ""

