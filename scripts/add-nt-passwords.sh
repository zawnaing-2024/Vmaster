#!/bin/bash

################################################################################
# Add NT-Password hashes for all existing RADIUS users
# Required for MS-CHAP authentication (MikroTik, Windows)
################################################################################

echo "════════════════════════════════════════════════════════════════"
echo "🔧 Adding NT-Password for MS-CHAP Authentication"
echo "════════════════════════════════════════════════════════════════"
echo ""

cd /var/www/vmaster

echo "Step 1: Getting all SSTP users from RADIUS database..."

# Get users with Cleartext-Password
USERS=$(docker exec vmaster_radius_db mysql -uroot -prootpassword radius -N -s -e "
SELECT username, value FROM radcheck 
WHERE attribute='Cleartext-Password' 
AND username LIKE 'sstp_%';")

if [ -z "$USERS" ]; then
    echo "❌ No SSTP users found in RADIUS database"
    exit 1
fi

COUNT=$(echo "$USERS" | wc -l)
echo "✅ Found $COUNT SSTP users"
echo ""

echo "Step 2: Generating NT-Password hashes..."

echo "$USERS" | while IFS=$'\t' read -r username password; do
    if [ -z "$username" ] || [ -z "$password" ]; then
        continue
    fi
    
    echo "Processing: $username"
    
    # Generate NT-Password hash (MD4 of UTF-16LE password)
    HASH=$(echo -n "$password" | iconv -t UTF-16LE 2>/dev/null | openssl md4 2>/dev/null | awk '{print toupper($2)}')
    
    if [ -z "$HASH" ]; then
        echo "  ⚠️  Failed to generate hash for $username"
        continue
    fi
    
    # Add NT-Password to database
    docker exec vmaster_radius_db mysql -uroot -prootpassword radius -e "
    INSERT INTO radcheck (username, attribute, op, value) 
    VALUES ('$username', 'NT-Password', ':=', '$HASH')
    ON DUPLICATE KEY UPDATE value='$HASH';" 2>/dev/null
    
    if [ $? -eq 0 ]; then
        echo "  ✅ Added NT-Password: ${HASH:0:16}..."
    else
        echo "  ❌ Failed to add NT-Password"
    fi
done

echo ""
echo "Step 3: Verifying..."

# Show sample user with both passwords
SAMPLE_USER=$(docker exec vmaster_radius_db mysql -uroot -prootpassword radius -N -s -e "
SELECT username FROM radcheck 
WHERE attribute='Cleartext-Password' 
AND username LIKE 'sstp_%' 
LIMIT 1;")

if [ -n "$SAMPLE_USER" ]; then
    echo "Sample user: $SAMPLE_USER"
    docker exec vmaster_radius_db mysql -uroot -prootpassword radius -e "
    SELECT username, attribute, LEFT(value, 20) as value_preview 
    FROM radcheck 
    WHERE username='$SAMPLE_USER' 
    ORDER BY attribute;"
fi

echo ""
echo "════════════════════════════════════════════════════════════════"
echo "✅ NT-Password hashes added for all SSTP users!"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "Users now have both:"
echo "  • Cleartext-Password (for PAP/SoftEther)"
echo "  • NT-Password (for MS-CHAP/MikroTik)"
echo ""
echo "Try connecting to MikroTik VPN now - should work! 🚀"
echo ""
echo "════════════════════════════════════════════════════════════════"

