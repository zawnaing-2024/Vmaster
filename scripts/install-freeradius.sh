#!/bin/bash

################################################################################
# Install and Configure FreeRADIUS on VMaster Server
# This makes RADIUS publicly accessible for SSTP authentication
################################################################################

set -e

echo "════════════════════════════════════════════════════════════════"
echo "🔐 Installing FreeRADIUS Server"
echo "════════════════════════════════════════════════════════════════"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "Please run as root (use sudo)"
    exit 1
fi

echo "Step 1: Installing FreeRADIUS packages..."
apt-get update
apt-get install -y freeradius freeradius-mysql freeradius-utils

echo "✅ FreeRADIUS installed"
echo ""

echo "Step 2: Stopping FreeRADIUS service..."
systemctl stop freeradius

echo "Step 3: Configuring FreeRADIUS to use MySQL..."

# Get MySQL credentials from docker or environment
MYSQL_HOST="127.0.0.1"
MYSQL_PORT="3307"
MYSQL_DB="radius"
MYSQL_USER="radius"
MYSQL_PASS="radiuspass"

echo "MySQL Configuration:"
echo "  Host: $MYSQL_HOST"
echo "  Port: $MYSQL_PORT"
echo "  Database: $MYSQL_DB"
echo "  User: $MYSQL_USER"
echo ""

# Backup original config
cp /etc/freeradius/3.0/mods-available/sql /etc/freeradius/3.0/mods-available/sql.backup.$(date +%Y%m%d)

# Configure SQL module
cat > /etc/freeradius/3.0/mods-available/sql << 'SQLCONFIG'
sql {
    driver = "rlm_sql_mysql"
    dialect = "mysql"

    # Connection info
    server = "127.0.0.1"
    port = 3307
    login = "radius"
    password = "radiuspass"
    radius_db = "radius"

    # Set to 'yes' to read radius clients from the database ('nas' table)
    read_clients = yes

    # Table configuration
    client_table = "nas"

    # Read database-specific queries
    $INCLUDE ${modconfdir}/${.:name}/main/${dialect}/queries.conf
}
SQLCONFIG

echo "✅ SQL module configured"
echo ""

# Enable SQL module
ln -sf /etc/freeradius/3.0/mods-available/sql /etc/freeradius/3.0/mods-enabled/sql

echo "Step 4: Configuring RADIUS clients..."

# Get SSTP server IPs from user
echo ""
echo "════════════════════════════════════════════════════════════════"
echo "📋 Please provide your SSTP server details:"
echo "════════════════════════════════════════════════════════════════"
read -p "SSTP Server IP address: " SSTP_SERVER_IP
read -p "RADIUS Shared Secret [default: testing123]: " RADIUS_SECRET
RADIUS_SECRET=${RADIUS_SECRET:-testing123}

# Add client to FreeRADIUS database
echo ""
echo "Adding SSTP server to RADIUS clients..."

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

echo "✅ SSTP server added to RADIUS clients"
echo ""

echo "Step 5: Configuring FreeRADIUS to listen on all interfaces..."

# Edit radiusd.conf to listen on all interfaces
sed -i 's/ipaddr = .*/ipaddr = 0.0.0.0/' /etc/freeradius/3.0/radiusd.conf

echo "✅ FreeRADIUS configured to listen on 0.0.0.0"
echo ""

echo "Step 6: Testing FreeRADIUS configuration..."
freeradius -XC

if [ $? -eq 0 ]; then
    echo "✅ Configuration is valid"
else
    echo "❌ Configuration error!"
    echo "Please check the output above"
    exit 1
fi

echo ""
echo "Step 7: Starting FreeRADIUS service..."
systemctl enable freeradius
systemctl start freeradius

sleep 2

if systemctl is-active --quiet freeradius; then
    echo "✅ FreeRADIUS is running"
else
    echo "❌ FreeRADIUS failed to start"
    echo "Checking logs..."
    journalctl -u freeradius -n 50
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
radtest testuser testpass123 localhost 1812 $RADIUS_SECRET

if [ $? -eq 0 ]; then
    echo "✅ RADIUS authentication test PASSED!"
else
    echo "⚠️  RADIUS test failed, but service is running"
    echo "Check logs: journalctl -u freeradius -f"
fi

# Clean up test user
docker exec vmaster_radius_db mysql -uroot -prootpassword radius -e "DELETE FROM radcheck WHERE username='testuser';"

echo ""
echo "════════════════════════════════════════════════════════════════"
echo "✅ FreeRADIUS Installation Complete!"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "📋 Configuration Summary:"
echo "────────────────────────────────────────────────────────────────"
echo "RADIUS Server IP:      $(curl -s ifconfig.me)"
echo "RADIUS Auth Port:      1812"
echo "RADIUS Acct Port:      1813"
echo "Shared Secret:         $RADIUS_SECRET"
echo ""
echo "SSTP Server IP:        $SSTP_SERVER_IP"
echo "Status:                ✅ Authorized"
echo ""
echo "════════════════════════════════════════════════════════════════"
echo "📝 Next Steps: Configure Your SSTP Server"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "On your SSTP server, run:"
echo ""
echo "  sudo nano /etc/accel-ppp.conf"
echo ""
echo "Add/modify these sections:"
echo ""
echo "  [modules]"
echo "  radius"
echo ""
echo "  [radius]"
echo "  server=$(curl -s ifconfig.me),testing123,auth-port=1812,acct-port=1813,req-limit=50,fail-time=0"
echo "  nas-identifier=sstp-server"
echo "  nas-ip-address=$SSTP_SERVER_IP"
echo "  acct-timeout=120"
echo "  timeout=15"
echo "  max-try=2"
echo ""
echo "Then restart SSTP:"
echo "  sudo systemctl restart accel-ppp"
echo ""
echo "════════════════════════════════════════════════════════════════"
echo "🔍 Monitoring Commands:"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "Check FreeRADIUS status:"
echo "  sudo systemctl status freeradius"
echo ""
echo "View live logs:"
echo "  sudo journalctl -u freeradius -f"
echo ""
echo "Test authentication:"
echo "  radtest USERNAME PASSWORD $(curl -s ifconfig.me) 1812 $RADIUS_SECRET"
echo ""
echo "View connected users:"
echo "  docker exec vmaster_radius_db mysql -uroot -prootpassword radius \\"
echo "    -e 'SELECT * FROM radacct WHERE acctstoptime IS NULL;'"
echo ""
echo "════════════════════════════════════════════════════════════════"
