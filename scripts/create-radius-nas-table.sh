#!/bin/bash

################################################################################
# Create nas table in RADIUS database
# This table stores authorized RADIUS clients (SSTP servers)
################################################################################

echo "════════════════════════════════════════════════════════════════"
echo "🔧 Creating nas table in RADIUS database"
echo "════════════════════════════════════════════════════════════════"
echo ""

# Create nas table
docker exec vmaster_radius_db mysql -uroot -prootpassword radius << 'SQL'
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

-- Show table structure
DESCRIBE nas;

-- Show existing entries
SELECT * FROM nas;
SQL

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ nas table created successfully!"
    echo ""
    echo "You can now:"
    echo "  • Add SSTP servers with RADIUS checkbox"
    echo "  • Use RADIUS Clients page"
    echo ""
else
    echo ""
    echo "❌ Failed to create nas table"
    echo "Please check if RADIUS database is running:"
    echo "  docker ps | grep radius"
    exit 1
fi
