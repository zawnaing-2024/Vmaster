#!/bin/bash

################################################################################
# Fix FreeRADIUS Docker Container Issues
# Diagnoses and fixes common FreeRADIUS container problems
################################################################################

set -e

echo "════════════════════════════════════════════════════════════════"
echo "🔧 FreeRADIUS Docker Troubleshooter"
echo "════════════════════════════════════════════════════════════════"
echo ""

VMASTER_DIR="/var/www/vmaster"

if [ ! -d "$VMASTER_DIR" ]; then
    echo "❌ VMaster directory not found at $VMASTER_DIR"
    exit 1
fi

cd "$VMASTER_DIR"

echo "Step 1: Checking container status..."
docker ps -a | grep freeradius || echo "Container not found"
echo ""

echo "Step 2: Checking container logs..."
echo "════════════════════════════════════════════════════════════════"
docker logs vmaster_freeradius --tail 50 2>&1 || echo "Cannot get logs"
echo "════════════════════════════════════════════════════════════════"
echo ""

echo "Step 3: Stopping problematic container..."
docker-compose -f docker-compose.prod.yml stop freeradius 2>/dev/null || true
docker-compose -f docker-compose.prod.yml rm -f freeradius 2>/dev/null || true
sleep 2

echo "✅ Container stopped"
echo ""

echo "Step 4: Using simplified FreeRADIUS configuration..."

# Create minimal working config
mkdir -p radius/config/mods-enabled
mkdir -p radius/config/sites-enabled
mkdir -p radius/config/policy.d
mkdir -p radius/config/mods-config/sql/main/mysql
mkdir -p radius/logs

# Simplified radiusd.conf
cat > radius/config/radiusd.conf << 'EOF'
prefix = /usr
exec_prefix = /usr
sysconfdir = /etc
localstatedir = /var
sbindir = ${exec_prefix}/sbin
logdir = /var/log/radius
raddbdir = /etc/raddb
radacctdir = ${logdir}/radacct

name = radiusd
confdir = ${raddbdir}
modconfdir = ${confdir}/mods-config
certdir = ${confdir}/certs
cadir = ${confdir}/certs
run_dir = ${localstatedir}/run/${name}
db_dir = ${raddbdir}
libdir = /usr/lib/freeradius
pidfile = ${run_dir}/${name}.pid

max_request_time = 30
cleanup_delay = 5
max_requests = 16384
hostname_lookups = no

log {
    destination = files
    colourise = yes
    file = ${logdir}/radius.log
    syslog_facility = daemon
    stripped_names = no
    auth = yes
    auth_badpass = yes
    auth_goodpass = yes
}

security {
    max_attributes = 200
    reject_delay = 1
    status_server = yes
}

thread pool {
    start_servers = 5
    max_servers = 32
    min_spare_servers = 3
    max_spare_servers = 10
    max_requests_per_server = 0
}

modules {
    $INCLUDE mods-enabled/
}

instantiate {
}

policy {
    $INCLUDE policy.d/
}

$INCLUDE sites-enabled/
EOF

# SQL module
cat > radius/config/mods-enabled/sql << 'EOF'
sql {
    driver = "rlm_sql_mysql"
    dialect = "mysql"
    
    server = "radius-db"
    port = 3306
    login = "radius"
    password = "radiuspass"
    radius_db = "radius"
    
    pool {
        start = 5
        min = 4
        max = 10
        spare = 3
        uses = 0
        retry_delay = 30
        lifetime = 0
        idle_timeout = 60
    }
    
    read_clients = yes
    client_table = "nas"
    
    $INCLUDE ${modconfdir}/sql/main/${dialect}/queries.conf
}
EOF

# PAP module
cat > radius/config/mods-enabled/pap << 'EOF'
pap {
    normalise = yes
}
EOF

# Default site
cat > radius/config/sites-enabled/default << 'EOF'
server default {
    listen {
        type = auth
        ipaddr = *
        port = 1812
    }
    
    listen {
        type = acct
        ipaddr = *
        port = 1813
    }
    
    authorize {
        preprocess
        sql
        pap
    }
    
    authenticate {
        Auth-Type PAP {
            pap
        }
    }
    
    preacct {
        preprocess
    }
    
    accounting {
        sql
    }
    
    post-auth {
        sql
    }
}
EOF

# Filter policy
cat > radius/config/policy.d/filter << 'EOF'
policy filter_username {
    if (!User-Name) {
        reject
    }
}
EOF

# MySQL queries
cat > radius/config/mods-config/sql/main/mysql/queries.conf << 'EOF'
authorize_check_query = "SELECT id, username, attribute, value, op FROM radcheck WHERE username = '%{SQL-User-Name}' ORDER BY id"
authorize_reply_query = "SELECT id, username, attribute, value, op FROM radreply WHERE username = '%{SQL-User-Name}' ORDER BY id"
accounting_start_query = "INSERT INTO radacct (acctsessionid, acctuniqueid, username, realm, nasipaddress, nasportid, nasporttype, acctstarttime, acctupdatetime, acctstoptime, acctsessiontime, acctauthentic, connectinfo_start, connectinfo_stop, acctinputoctets, acctoutputoctets, calledstationid, callingstationid, acctterminatecause, servicetype, framedprotocol, framedipaddress) VALUES ('%{Acct-Session-Id}', '%{Acct-Unique-Session-Id}', '%{SQL-User-Name}', '%{Realm}', '%{NAS-IP-Address}', '%{%{NAS-Port-ID}:-%{NAS-Port}}', '%{NAS-Port-Type}', FROM_UNIXTIME(%{integer:Event-Timestamp}), FROM_UNIXTIME(%{integer:Event-Timestamp}), NULL, 0, '%{Acct-Authentic}', '%{Connect-Info}', '', 0, 0, '%{Called-Station-Id}', '%{Calling-Station-Id}', '', '%{Service-Type}', '%{Framed-Protocol}', '%{Framed-IP-Address}')"
accounting_update_query = "UPDATE radacct SET acctupdatetime = FROM_UNIXTIME(%{integer:Event-Timestamp}), acctsessiontime = %{%{Acct-Session-Time}:-NULL}, acctinputoctets = %{%{Acct-Input-Octets}:-NULL}, acctoutputoctets = %{%{Acct-Output-Octets}:-NULL} WHERE acctsessionid = '%{Acct-Session-Id}' AND username = '%{SQL-User-Name}' AND nasipaddress = '%{NAS-IP-Address}'"
accounting_stop_query = "UPDATE radacct SET acctstoptime = FROM_UNIXTIME(%{integer:Event-Timestamp}), acctsessiontime = %{%{Acct-Session-Time}:-NULL}, acctinputoctets = %{%{Acct-Input-Octets}:-NULL}, acctoutputoctets = %{%{Acct-Output-Octets}:-NULL}, acctterminatecause = '%{Acct-Terminate-Cause}', connectinfo_stop = '%{Connect-Info}' WHERE acctsessionid = '%{Acct-Session-Id}' AND username = '%{SQL-User-Name}' AND nasipaddress = '%{NAS-IP-Address}'"
post_auth_query = "INSERT INTO radpostauth (username, pass, reply, authdate) VALUES ('%{SQL-User-Name}', '%{%{User-Password}:-%{Chap-Password}}', '%{reply:Packet-Type}', NOW())"
EOF

echo "✅ Configuration files created"
echo ""

echo "Step 5: Setting permissions..."
chown -R 101:101 radius/ 2>/dev/null || chmod -R 777 radius/
echo "✅ Permissions set"
echo ""

echo "Step 6: Ensuring RADIUS database is ready..."
docker-compose -f docker-compose.prod.yml up -d radius-db
sleep 5

# Test database connection
docker exec vmaster_radius_db mysql -uroot -prootpassword -e "SELECT 1;" 2>/dev/null
if [ $? -eq 0 ]; then
    echo "✅ Database is ready"
else
    echo "❌ Database not ready, waiting..."
    sleep 10
fi

echo ""
echo "Step 7: Starting FreeRADIUS with fresh config..."
docker-compose -f docker-compose.prod.yml up -d freeradius

echo "Waiting for container to start..."
sleep 10

# Check status
if docker ps | grep -q vmaster_freeradius; then
    echo "✅ FreeRADIUS container is running!"
    echo ""
    echo "Checking logs..."
    docker logs vmaster_freeradius --tail 20
else
    echo "❌ Container still not running"
    echo ""
    echo "Full logs:"
    docker logs vmaster_freeradius 2>&1
    echo ""
    echo "Trying alternative approach..."
    
    # Try using official FreeRADIUS image with host network
    echo "Using alternative configuration..."
    docker run -d \
        --name vmaster_freeradius \
        --network vmaster-network \
        -p 1812:1812/udp \
        -p 1813:1813/udp \
        -v "$VMASTER_DIR/radius/config:/etc/raddb:ro" \
        -v "$VMASTER_DIR/radius/logs:/var/log/radius" \
        --restart always \
        freeradius/freeradius-server:latest
    
    sleep 5
    
    if docker ps | grep -q vmaster_freeradius; then
        echo "✅ FreeRADIUS started with alternative method!"
    else
        echo "❌ Still failing. Manual intervention needed."
        echo ""
        echo "Please run for debugging:"
        echo "  docker run --rm -it --network vmaster-network freeradius/freeradius-server:latest radiusd -X"
        exit 1
    fi
fi

echo ""
echo "════════════════════════════════════════════════════════════════"
echo "✅ FreeRADIUS Should Be Running Now!"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "Verify with:"
echo "  docker ps | grep freeradius"
echo "  docker logs -f vmaster_freeradius"
echo ""
