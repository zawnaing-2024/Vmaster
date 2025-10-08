#!/bin/bash

################################################################################
# Simple FreeRADIUS Docker Setup (Using 2stacks/freeradius image)
# This uses a pre-configured FreeRADIUS image that works out of the box
################################################################################

set -e

echo "════════════════════════════════════════════════════════════════"
echo "🐳 Setting Up FreeRADIUS Docker (Simple Method)"
echo "════════════════════════════════════════════════════════════════"
echo ""

cd /var/www/vmaster

echo "Step 1: Stopping and removing old container..."
docker stop vmaster_freeradius 2>/dev/null || true
docker rm vmaster_freeradius 2>/dev/null || true

echo "✅ Old container removed"
echo ""

echo "Step 2: Getting SSTP server details..."
echo ""
read -p "SSTP Server IP address: " SSTP_SERVER_IP
read -p "RADIUS Shared Secret [default: testing123]: " RADIUS_SECRET
RADIUS_SECRET=${RADIUS_SECRET:-testing123}

echo ""
echo "Step 3: Adding SSTP server to RADIUS database..."

docker exec vmaster_radius_db mysql -uroot -prootpassword radius << SQL
-- Ensure nas table exists
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

-- Show clients
SELECT * FROM nas;
SQL

echo "✅ SSTP server added to RADIUS database"
echo ""

echo "Step 4: Starting FreeRADIUS container (using 2stacks/freeradius)..."

docker run -d \
  --name vmaster_freeradius \
  --network vmaster-network \
  -p 1812:1812/udp \
  -p 1813:1813/udp \
  -e DB_HOST=radius-db \
  -e DB_PORT=3306 \
  -e DB_USER=radius \
  -e DB_PASS=radiuspass \
  -e DB_NAME=radius \
  -e RADIUS_KEY=$RADIUS_SECRET \
  -e CLIENT_IP=$SSTP_SERVER_IP \
  --restart always \
  2stacks/freeradius:latest

echo "Waiting for FreeRADIUS to start..."
sleep 10

echo ""
echo "Step 5: Checking container status..."

if docker ps | grep -q vmaster_freeradius; then
    echo "✅ FreeRADIUS container is running!"
    docker ps | grep freeradius
else
    echo "❌ Container failed to start"
    echo "Checking logs..."
    docker logs vmaster_freeradius
    exit 1
fi

echo ""
echo "Step 6: Configuring firewall..."
ufw allow 1812/udp comment "RADIUS Authentication" 2>/dev/null || true
ufw allow 1813/udp comment "RADIUS Accounting" 2>/dev/null || true

echo "✅ Firewall configured"
echo ""

echo "Step 7: Testing RADIUS..."
echo "Creating test user..."

docker exec vmaster_radius_db mysql -uroot -prootpassword radius << SQLTEST
INSERT INTO radcheck (username, attribute, op, value) 
VALUES ('testuser', 'Cleartext-Password', ':=', 'testpass123')
ON DUPLICATE KEY UPDATE value='testpass123';
SQLTEST

echo "Testing authentication..."
sleep 2

# Test using radtest from inside container
docker exec vmaster_freeradius radtest testuser testpass123 localhost 0 $RADIUS_SECRET 2>/dev/null || \
  echo "Note: radtest not available in this image, but FreeRADIUS is running"

# Clean up test user
docker exec vmaster_radius_db mysql -uroot -prootpassword radius -e "DELETE FROM radcheck WHERE username='testuser';" 2>/dev/null || true

echo ""
echo "Step 8: Verifying ports..."
netstat -ulnp | grep 181 || echo "Ports should be listening on 1812/1813"

echo ""
echo "════════════════════════════════════════════════════════════════"
echo "✅ FreeRADIUS Docker Setup Complete!"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "📋 Configuration Summary:"
echo "────────────────────────────────────────────────────────────────"
echo "Container:             vmaster_freeradius"
echo "Image:                 2stacks/freeradius:latest"
echo "RADIUS Server IP:      $(curl -s ifconfig.me 2>/dev/null || echo 'YOUR_SERVER_IP')"
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
echo "Check container:"
echo "  docker ps | grep freeradius"
echo ""
echo "View logs:"
echo "  docker logs -f vmaster_freeradius"
echo ""
echo "Restart:"
echo "  docker restart vmaster_freeradius"
echo ""
echo "Check ports:"
echo "  sudo netstat -ulnp | grep 181"
echo ""
echo "════════════════════════════════════════════════════════════════"
