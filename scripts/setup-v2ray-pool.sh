#!/bin/bash

################################################################################
# Complete V2Ray Pool Setup Script
# Runs on VMaster production server
################################################################################

set -e

echo "════════════════════════════════════════════════════════════════"
echo "🚀 V2Ray Pool Setup for VMaster"
echo "════════════════════════════════════════════════════════════════"
echo ""

# Check if SQL file exists
if [ ! -f "/tmp/import_to_vmaster.sql" ]; then
    echo "❌ Error: /tmp/import_to_vmaster.sql not found!"
    echo ""
    echo "Please upload the SQL file first:"
    echo "  scp v2ray-pool-export/import_to_vmaster.sql ubuntu@YOUR_SERVER:/tmp/"
    echo ""
    exit 1
fi

echo "Step 1: Checking database connection..."
if docker exec vmaster_db mysql -uroot -prootpassword -e "SELECT 1" &>/dev/null; then
    echo "✅ Database connection OK"
else
    echo "❌ Cannot connect to database"
    exit 1
fi

echo ""
echo "Step 2: Checking if vpn_credentials_pool table exists..."
TABLE_EXISTS=$(docker exec vmaster_db mysql -uroot -prootpassword vpn_cms_portal \
    -sN -e "SHOW TABLES LIKE 'vpn_credentials_pool'" | wc -l)

if [ "$TABLE_EXISTS" -eq 0 ]; then
    echo "⚠️  Table doesn't exist. Creating..."
    docker exec vmaster_db mysql -uroot -prootpassword vpn_cms_portal << 'EOF'
CREATE TABLE IF NOT EXISTS vpn_credentials_pool (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vpn_type ENUM('sstp', 'v2ray') NOT NULL,
    username VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    is_assigned TINYINT(1) DEFAULT 0,
    assigned_to INT DEFAULT NULL,
    assigned_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_credential (vpn_type, username),
    INDEX idx_available (vpn_type, is_assigned)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
EOF
    echo "✅ Table created"
else
    echo "✅ Table exists"
fi

echo ""
echo "Step 3: Importing V2Ray UUIDs to pool..."
docker exec -i vmaster_db mysql -uroot -prootpassword < /tmp/import_to_vmaster.sql

if [ $? -eq 0 ]; then
    echo "✅ UUIDs imported successfully"
else
    echo "❌ Import failed"
    exit 1
fi

echo ""
echo "Step 4: Verifying import..."
docker exec vmaster_db mysql -uroot -prootpassword vpn_cms_portal << 'EOF'
SELECT 
    vpn_type,
    COUNT(*) as total,
    SUM(is_assigned=0) as available,
    SUM(is_assigned=1) as assigned
FROM vpn_credentials_pool
GROUP BY vpn_type;
EOF

echo ""
echo "Step 5: Updating VMaster configuration..."

# Check if RADIUS is enabled for V2Ray
RADIUS_CONFIG=$(docker exec vmaster_web cat /var/www/html/config/radius.php 2>/dev/null || echo "")

if echo "$RADIUS_CONFIG" | grep -q "RADIUS_ENABLED.*true"; then
    echo "⚠️  RADIUS is enabled. V2Ray should use pool instead."
    echo "   You can keep RADIUS for SSTP and use pool for V2Ray."
fi

echo ""
echo "Step 6: Testing pool query..."
TEST_RESULT=$(docker exec vmaster_db mysql -uroot -prootpassword vpn_cms_portal \
    -sN -e "SELECT COUNT(*) FROM vpn_credentials_pool WHERE vpn_type='v2ray' AND is_assigned=0" 2>/dev/null)

if [ "$TEST_RESULT" -gt 0 ]; then
    echo "✅ Pool has $TEST_RESULT available V2Ray credentials"
else
    echo "⚠️  No available credentials found"
fi

echo ""
echo "════════════════════════════════════════════════════════════════"
echo "✅ V2Ray Pool Setup Complete!"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "Next Steps:"
echo "1. Ensure UUIDs are added to X-UI panel"
echo "2. Test creating V2Ray account in VMaster portal"
echo "3. Verify customer receives VMess link with UUID from pool"
echo ""
echo "To check pool status anytime:"
echo "  docker exec vmaster_db mysql -uroot -prootpassword vpn_cms_portal \\"
echo "    -e \"SELECT COUNT(*) as available FROM vpn_credentials_pool \\"
echo "    WHERE vpn_type='v2ray' AND is_assigned=0\""
echo ""
echo "════════════════════════════════════════════════════════════════"

