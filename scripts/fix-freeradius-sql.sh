#!/bin/bash

################################################################################
# Fix FreeRADIUS SQL Module to Work with Docker MySQL
################################################################################

echo "════════════════════════════════════════════════════════════════"
echo "🔧 Fixing FreeRADIUS SQL Configuration"
echo "════════════════════════════════════════════════════════════════"
echo ""

# Stop FreeRADIUS
sudo systemctl stop freeradius
sudo killall freeradius 2>/dev/null || true

echo "Step 1: Creating SQL module with inline queries..."

# Create SQL module with all queries inline (no external file)
sudo tee /etc/freeradius/3.0/mods-available/sql > /dev/null << 'EOF'
sql {
    driver = "rlm_sql_mysql"
    dialect = "mysql"
    
    server = "127.0.0.1"
    port = 3307
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
    
    # Inline queries (no external file needed)
    authorize_check_query = "SELECT id, username, attribute, value, op FROM ${authcheck_table} WHERE username = '%{SQL-User-Name}' ORDER BY id"
    authorize_reply_query = "SELECT id, username, attribute, value, op FROM ${authreply_table} WHERE username = '%{SQL-User-Name}' ORDER BY id"
    
    # Table names
    authcheck_table = "radcheck"
    authreply_table = "radreply"
    groupcheck_table = "radgroupcheck"
    groupreply_table = "radgroupreply"
    usergroup_table = "radusergroup"
    postauth_table = "radpostauth"
    acct_table1 = "radacct"
    acct_table2 = "radacct"
}
EOF

echo "✅ SQL module configured"
echo ""

echo "Step 2: Enabling SQL module..."
sudo ln -sf /etc/freeradius/3.0/mods-available/sql /etc/freeradius/3.0/mods-enabled/sql

echo "Step 3: Configuring authorize section..."

# Update default site to use sql properly
sudo tee /etc/freeradius/3.0/sites-available/default > /dev/null << 'EOF'
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
        filter_username
        preprocess
        
        # Query SQL for user
        sql
        
        # If SQL found the user, it should have set Cleartext-Password
        # Force PAP authentication
        if (ok) {
            update control {
                Auth-Type := PAP
            }
        }
    }
    
    authenticate {
        Auth-Type PAP {
            pap
        }
    }
    
    preacct {
        preprocess
        acct_unique
        suffix
    }
    
    accounting {
        sql
    }
    
    post-auth {
        sql
        Post-Auth-Type REJECT {
            sql
        }
    }
}
EOF

sudo ln -sf /etc/freeradius/3.0/sites-available/default /etc/freeradius/3.0/sites-enabled/default

echo "✅ Site configured"
echo ""

echo "Step 4: Testing configuration..."
sudo freeradius -CX

if [ $? -eq 0 ]; then
    echo "✅ Configuration is valid"
else
    echo "❌ Configuration error!"
    exit 1
fi

echo ""
echo "Step 5: Starting FreeRADIUS..."
sudo systemctl start freeradius
sleep 2

if sudo systemctl is-active --quiet freeradius; then
    echo "✅ FreeRADIUS is running"
else
    echo "❌ FreeRADIUS failed to start"
    sudo journalctl -u freeradius -n 20
    exit 1
fi

echo ""
echo "Step 6: Creating test user..."
docker exec vmaster_radius_db mysql -uroot -prootpassword radius -e "
DELETE FROM radcheck WHERE username='testuser';
INSERT INTO radcheck (username, attribute, op, value) 
VALUES ('testuser', 'Cleartext-Password', ':=', 'testpass123');"

echo "Step 7: Testing authentication..."
sleep 2

RESULT=$(echo 'User-Name = "testuser", User-Password = "testpass123"' | radclient -x 127.0.0.1:1812 auth One@2025 2>&1)

if echo "$RESULT" | grep -q "Access-Accept"; then
    echo ""
    echo "════════════════════════════════════════════════════════════════"
    echo "✅ SUCCESS! RADIUS Authentication Works!"
    echo "════════════════════════════════════════════════════════════════"
    echo ""
    echo "Test result:"
    echo "$RESULT" | grep "Access-Accept"
    echo ""
    echo "Now test with your real users:"
    echo "  echo 'User-Name = \"sstp_31a0ad7a06\", User-Password = \"fF#8(((9Drfmrzhg\"' | radclient -x 127.0.0.1:1812 auth One@2025"
    echo ""
else
    echo ""
    echo "❌ Authentication still failing"
    echo "Running debug mode to see error..."
    sudo systemctl stop freeradius
    sudo freeradius -X &
    sleep 3
    echo 'User-Name = "testuser", User-Password = "testpass123"' | radclient 127.0.0.1:1812 auth One@2025
    sleep 2
    sudo killall freeradius
fi

# Cleanup
docker exec vmaster_radius_db mysql -uroot -prootpassword radius -e "DELETE FROM radcheck WHERE username='testuser';" 2>/dev/null || true

echo ""
echo "════════════════════════════════════════════════════════════════"
