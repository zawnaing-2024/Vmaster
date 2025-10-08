#!/bin/bash

################################################################################
# Setup FreeRADIUS in Docker for VMaster
# This script configures FreeRADIUS to run as a Docker container
################################################################################

set -e

echo "════════════════════════════════════════════════════════════════"
echo "🐳 Setting Up FreeRADIUS in Docker"
echo "════════════════════════════════════════════════════════════════"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "Please run as root (use sudo)"
    exit 1
fi

VMASTER_DIR="/var/www/vmaster"

# Check if in correct directory
if [ ! -f "$VMASTER_DIR/docker-compose.prod.yml" ]; then
    echo "❌ Error: VMaster not found at $VMASTER_DIR"
    echo "Please ensure VMaster is installed in $VMASTER_DIR"
    exit 1
fi

cd "$VMASTER_DIR"

echo "Step 1: Pulling latest code from Git..."
git pull origin main

echo "✅ Code updated"
echo ""

echo "Step 2: Creating RADIUS config directories..."
mkdir -p radius/config/mods-enabled
mkdir -p radius/config/sites-enabled
mkdir -p radius/config/policy.d
mkdir -p radius/config/mods-config/sql/main/mysql
mkdir -p radius/logs

echo "✅ Directories created"
echo ""

echo "Step 3: Copying MySQL queries configuration..."

# Create MySQL queries file
cat > radius/config/mods-config/sql/main/mysql/queries.conf << 'QUERIES'
# MySQL-specific queries for FreeRADIUS

authorize_check_query = "\
    SELECT id, username, attribute, value, op \
    FROM ${authcheck_table} \
    WHERE username = '%{SQL-User-Name}' \
    ORDER BY id"

authorize_reply_query = "\
    SELECT id, username, attribute, value, op \
    FROM ${authreply_table} \
    WHERE username = '%{SQL-User-Name}' \
    ORDER BY id"

accounting_start_query = "\
    INSERT INTO ${acct_table1} \
    (acctsessionid, acctuniqueid, username, realm, nasipaddress, \
     nasportid, nasporttype, acctstarttime, acctupdatetime, \
     acctstoptime, acctsessiontime, acctauthentic, connectinfo_start, \
     connectinfo_stop, acctinputoctets, acctoutputoctets, calledstationid, \
     callingstationid, acctterminatecause, servicetype, framedprotocol, \
     framedipaddress) \
    VALUES \
    ('%{Acct-Session-Id}', '%{Acct-Unique-Session-Id}', '%{SQL-User-Name}', \
     '%{Realm}', '%{NAS-IP-Address}', '%{%{NAS-Port-ID}:-%{NAS-Port}}', \
     '%{NAS-Port-Type}', FROM_UNIXTIME(%{integer:Event-Timestamp}), \
     FROM_UNIXTIME(%{integer:Event-Timestamp}), NULL, 0, '%{Acct-Authentic}', \
     '%{Connect-Info}', '', 0, 0, '%{Called-Station-Id}', \
     '%{Calling-Station-Id}', '', '%{Service-Type}', '%{Framed-Protocol}', \
     '%{Framed-IP-Address}')"

accounting_update_query = "\
    UPDATE ${acct_table1} \
    SET acctupdatetime = FROM_UNIXTIME(%{integer:Event-Timestamp}), \
        acctsessiontime = %{%{Acct-Session-Time}:-NULL}, \
        acctinputoctets = %{%{Acct-Input-Octets}:-NULL}, \
        acctoutputoctets = %{%{Acct-Output-Octets}:-NULL} \
    WHERE acctsessionid = '%{Acct-Session-Id}' \
    AND username = '%{SQL-User-Name}' \
    AND nasipaddress = '%{NAS-IP-Address}'"

accounting_stop_query = "\
    UPDATE ${acct_table1} \
    SET acctstoptime = FROM_UNIXTIME(%{integer:Event-Timestamp}), \
        acctsessiontime = %{%{Acct-Session-Time}:-NULL}, \
        acctinputoctets = %{%{Acct-Input-Octets}:-NULL}, \
        acctoutputoctets = %{%{Acct-Output-Octets}:-NULL}, \
        acctterminatecause = '%{Acct-Terminate-Cause}', \
        connectinfo_stop = '%{Connect-Info}' \
    WHERE acctsessionid = '%{Acct-Session-Id}' \
    AND username = '%{SQL-User-Name}' \
    AND nasipaddress = '%{NAS-IP-Address}'"

post_auth_query = "\
    INSERT INTO ${postauth_table} \
    (username, pass, reply, authdate) \
    VALUES \
    ('%{SQL-User-Name}', '%{%{User-Password}:-%{Chap-Password}}', \
     '%{reply:Packet-Type}', NOW())"

QUERIES

echo "✅ MySQL queries configured"
echo ""

echo "Step 4: Checking existing RADIUS users..."
EXISTING_USERS=$(docker exec vmaster_radius_db mysql -uroot -prootpassword radius -se "SELECT COUNT(*) FROM radcheck WHERE attribute='Cleartext-Password';" 2>/dev/null || echo "0")
echo "✅ Found $EXISTING_USERS existing users in RADIUS database"
echo "   These users will work immediately after FreeRADIUS starts!"
echo ""

echo "Step 5: Getting SSTP server details..."
echo ""
echo "════════════════════════════════════════════════════════════════"
echo "📋 Please provide your SSTP server details:"
echo "════════════════════════════════════════════════════════════════"
read -p "SSTP Server IP address: " SSTP_SERVER_IP
read -p "RADIUS Shared Secret [default: testing123]: " RADIUS_SECRET
RADIUS_SECRET=${RADIUS_SECRET:-testing123}

echo ""
echo "Adding SSTP server to RADIUS clients..."

# Add client to database
docker exec vmaster_radius_db mysql -uroot -prootpassword radius << SQLCMD
-- Create nas table if not exists
CREATE TABLE IF NOT EXISTS nas (
    id int(10) NOT NULL AUTO_INCREMENT,
    nasname varchar(128) NOT NULL,
    shortname varchar(32) DEFAULT NULL,
    type varchar(30) DEFAULT 'other',
    ports int(5) DEFAULT NULL,
    secret varchar(60) NOT NULL DEFAULT 'secret',
    server varchar(64) DEFAULT NULL,
    community varchar(50) DEFAULT NULL,
    description varchar(200) DEFAULT 'RADIUS Client',
    PRIMARY KEY (id),
    KEY nasname (nasname)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Delete existing entry if any
DELETE FROM nas WHERE nasname = '$SSTP_SERVER_IP';

-- Insert SSTP server
INSERT INTO nas (nasname, shortname, type, secret, description) 
VALUES ('$SSTP_SERVER_IP', 'sstp-server', 'other', '$RADIUS_SECRET', 'SSTP VPN Server');

-- Show current clients
SELECT * FROM nas;
SQLCMD

echo "✅ SSTP server added to RADIUS database"
echo ""

echo "Step 6: Setting correct permissions..."
chown -R 101:101 radius/config
chown -R 101:101 radius/logs
chmod -R 755 radius/config
chmod -R 755 radius/logs

echo "✅ Permissions set"
echo ""

echo "Step 7: Starting FreeRADIUS container..."

# Stop existing container if running
docker-compose -f docker-compose.prod.yml stop freeradius 2>/dev/null || true
docker-compose -f docker-compose.prod.yml rm -f freeradius 2>/dev/null || true

# Start FreeRADIUS
docker-compose -f docker-compose.prod.yml up -d freeradius

echo "Waiting for FreeRADIUS to start..."
sleep 5

# Check if running
if docker ps | grep -q vmaster_freeradius; then
    echo "✅ FreeRADIUS container is running"
else
    echo "❌ FreeRADIUS failed to start"
    echo "Checking logs..."
    docker logs vmaster_freeradius
    exit 1
fi

echo ""
echo "Step 8: Configuring firewall..."
ufw allow 1812/udp comment "RADIUS Authentication"
ufw allow 1813/udp comment "RADIUS Accounting"

echo "✅ Firewall rules added"
echo ""

echo "Step 9: Testing RADIUS server..."
echo "Creating test user..."

docker exec vmaster_radius_db mysql -uroot -prootpassword radius << SQLTEST
INSERT INTO radcheck (username, attribute, op, value) 
VALUES ('testuser', 'Cleartext-Password', ':=', 'testpass123')
ON DUPLICATE KEY UPDATE value='testpass123';
SQLTEST

echo "Testing authentication..."
docker exec vmaster_freeradius radtest testuser testpass123 localhost 1812 $RADIUS_SECRET

if [ $? -eq 0 ]; then
    echo "✅ RADIUS authentication test PASSED!"
else
    echo "⚠️  RADIUS test failed, checking logs..."
    docker logs vmaster_freeradius --tail 50
fi

# Clean up test user
docker exec vmaster_radius_db mysql -uroot -prootpassword radius -e "DELETE FROM radcheck WHERE username='testuser';"

echo ""
echo "════════════════════════════════════════════════════════════════"
echo "✅ FreeRADIUS Docker Setup Complete!"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "📋 Configuration Summary:"
echo "────────────────────────────────────────────────────────────────"
echo "RADIUS Server IP:      $(curl -s ifconfig.me 2>/dev/null || echo 'YOUR_SERVER_IP')"
echo "RADIUS Auth Port:      1812"
echo "RADIUS Acct Port:      1813"
echo "Shared Secret:         $RADIUS_SECRET"
echo ""
echo "SSTP Server IP:        $SSTP_SERVER_IP"
echo "Status:                ✅ Authorized"
echo ""
echo "Container Name:        vmaster_freeradius"
echo "Config Location:       $VMASTER_DIR/radius/config"
echo "Logs Location:         $VMASTER_DIR/radius/logs"
echo ""
echo "════════════════════════════════════════════════════════════════"
echo "📝 Next Steps: Configure Your SSTP Server"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "On your SSTP server, edit /etc/accel-ppp.conf:"
echo ""
echo "  [modules]"
echo "  radius"
echo ""
echo "  [radius]"
echo "  server=$(curl -s ifconfig.me 2>/dev/null || echo 'YOUR_SERVER_IP'),${RADIUS_SECRET},auth-port=1812,acct-port=1813"
echo "  nas-identifier=sstp-server"
echo "  nas-ip-address=$SSTP_SERVER_IP"
echo ""
echo "Then restart SSTP:"
echo "  sudo systemctl restart accel-ppp"
echo ""
echo "════════════════════════════════════════════════════════════════"
echo "🔍 Useful Commands:"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "Check FreeRADIUS status:"
echo "  docker ps | grep freeradius"
echo ""
echo "View FreeRADIUS logs:"
echo "  docker logs -f vmaster_freeradius"
echo ""
echo "Restart FreeRADIUS:"
echo "  docker-compose -f $VMASTER_DIR/docker-compose.prod.yml restart freeradius"
echo ""
echo "Test authentication:"
echo "  docker exec vmaster_freeradius radtest USERNAME PASSWORD localhost 1812 $RADIUS_SECRET"
echo ""
echo "Add more SSTP servers:"
echo "  Login to VMaster → Admin Panel → RADIUS Clients"
echo ""
echo "════════════════════════════════════════════════════════════════"
echo ""
