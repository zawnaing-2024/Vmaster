#!/bin/bash
# Quick RADIUS Testing Script

echo "════════════════════════════════════════════════════"
echo "🧪 RADIUS Testing Script"
echo "════════════════════════════════════════════════════"
echo ""

# Test 1: Check RADIUS database connection
echo "Test 1: Checking RADIUS database connection..."
docker exec vpn_cms_db mysql -uroot -prootpassword -e "SELECT 1" radius > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "✅ RADIUS database connection: OK"
else
    echo "❌ RADIUS database connection: FAILED"
    exit 1
fi
echo ""

# Test 2: Check tables exist
echo "Test 2: Checking RADIUS tables..."
TABLES=$(docker exec vpn_cms_db mysql -uroot -prootpassword radius -e "SHOW TABLES" 2>/dev/null | wc -l)
if [ $TABLES -gt 1 ]; then
    echo "✅ RADIUS tables exist: $(($TABLES - 1)) tables found"
else
    echo "❌ RADIUS tables missing"
    exit 1
fi
echo ""

# Test 3: Check current users
echo "Test 3: Checking existing RADIUS users..."
USER_COUNT=$(docker exec vpn_cms_db mysql -uroot -prootpassword radius -e "SELECT COUNT(*) as count FROM radcheck" 2>/dev/null | tail -1)
echo "📊 Current users in RADIUS: $USER_COUNT"
echo ""

# Test 4: Create test user
echo "Test 4: Creating test RADIUS user..."
docker exec vpn_cms_db mysql -uroot -prootpassword radius -e "
INSERT INTO radcheck (username, attribute, op, value) 
VALUES ('testuser', 'Cleartext-Password', ':=', 'testpass123')
ON DUPLICATE KEY UPDATE value='testpass123';
" 2>/dev/null

if [ $? -eq 0 ]; then
    echo "✅ Test user 'testuser' created/updated"
else
    echo "❌ Failed to create test user"
    exit 1
fi
echo ""

# Test 5: Verify test user
echo "Test 5: Verifying test user in database..."
echo ""
docker exec vpn_cms_db mysql -uroot -prootpassword radius -e "
SELECT id, username, attribute, value 
FROM radcheck 
WHERE username='testuser'
" 2>/dev/null | grep -v "Warning"
echo ""

# Test 6: Show all users
echo "Test 6: Listing all RADIUS users..."
echo ""
docker exec vpn_cms_db mysql -uroot -prootpassword radius -e "
SELECT 
    id,
    username,
    attribute,
    value as password_hash
FROM radcheck 
ORDER BY id
" 2>/dev/null | grep -v "Warning"
echo ""

# Summary
echo "════════════════════════════════════════════════════"
echo "✅ RADIUS Testing Complete!"
echo "════════════════════════════════════════════════════"
echo ""
echo "Test Credentials Created:"
echo "  Username: testuser"
echo "  Password: testpass123"
echo ""
echo "Next Steps:"
echo "  1. Configure SoftEther to use RADIUS"
echo "  2. Try connecting with test credentials"
echo "  3. Check connection in RADIUS Management:"
echo "     http://localhost/admin/radius-management.php"
echo ""
echo "To delete test user:"
echo "  docker exec vpn_cms_db mysql -uroot -prootpassword radius \\"
echo "    -e \"DELETE FROM radcheck WHERE username='testuser'\""
echo ""

