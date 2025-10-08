#!/bin/bash

################################################################################
# Create all required RADIUS tables
# This creates the complete FreeRADIUS schema
################################################################################

echo "════════════════════════════════════════════════════════════════"
echo "🔧 Creating all RADIUS tables"
echo "════════════════════════════════════════════════════════════════"
echo ""

docker exec vmaster_radius_db mysql -uroot -prootpassword radius << 'SQL'

-- NAS table (RADIUS clients)
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

-- radcheck table (user credentials)
CREATE TABLE IF NOT EXISTS radcheck (
    id int(11) unsigned NOT NULL AUTO_INCREMENT,
    username varchar(64) NOT NULL DEFAULT '',
    attribute varchar(64) NOT NULL DEFAULT '',
    op char(2) NOT NULL DEFAULT '==',
    value varchar(253) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY username (username(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- radreply table (user reply attributes)
CREATE TABLE IF NOT EXISTS radreply (
    id int(11) unsigned NOT NULL AUTO_INCREMENT,
    username varchar(64) NOT NULL DEFAULT '',
    attribute varchar(64) NOT NULL DEFAULT '',
    op char(2) NOT NULL DEFAULT '=',
    value varchar(253) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY username (username(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- radgroupcheck table (group check attributes)
CREATE TABLE IF NOT EXISTS radgroupcheck (
    id int(11) unsigned NOT NULL AUTO_INCREMENT,
    groupname varchar(64) NOT NULL DEFAULT '',
    attribute varchar(64) NOT NULL DEFAULT '',
    op char(2) NOT NULL DEFAULT '==',
    value varchar(253) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY groupname (groupname(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- radgroupreply table (group reply attributes)
CREATE TABLE IF NOT EXISTS radgroupreply (
    id int(11) unsigned NOT NULL AUTO_INCREMENT,
    groupname varchar(64) NOT NULL DEFAULT '',
    attribute varchar(64) NOT NULL DEFAULT '',
    op char(2) NOT NULL DEFAULT '=',
    value varchar(253) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY groupname (groupname(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- radusergroup table (user to group mapping)
CREATE TABLE IF NOT EXISTS radusergroup (
    username varchar(64) NOT NULL DEFAULT '',
    groupname varchar(64) NOT NULL DEFAULT '',
    priority int(11) NOT NULL DEFAULT '1',
    KEY username (username(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- radacct table (accounting/session tracking)
CREATE TABLE IF NOT EXISTS radacct (
    radacctid bigint(21) NOT NULL AUTO_INCREMENT,
    acctsessionid varchar(64) NOT NULL DEFAULT '',
    acctuniqueid varchar(32) NOT NULL DEFAULT '',
    username varchar(64) NOT NULL DEFAULT '',
    groupname varchar(64) NOT NULL DEFAULT '',
    realm varchar(64) DEFAULT '',
    nasipaddress varchar(15) NOT NULL DEFAULT '',
    nasportid varchar(15) DEFAULT NULL,
    nasporttype varchar(32) DEFAULT NULL,
    acctstarttime datetime NULL DEFAULT NULL,
    acctupdatetime datetime NULL DEFAULT NULL,
    acctstoptime datetime NULL DEFAULT NULL,
    acctinterval int(12) DEFAULT NULL,
    acctsessiontime int(12) unsigned DEFAULT NULL,
    acctauthentic varchar(32) DEFAULT NULL,
    connectinfo_start varchar(50) DEFAULT NULL,
    connectinfo_stop varchar(50) DEFAULT NULL,
    acctinputoctets bigint(20) DEFAULT NULL,
    acctoutputoctets bigint(20) DEFAULT NULL,
    calledstationid varchar(50) NOT NULL DEFAULT '',
    callingstationid varchar(50) NOT NULL DEFAULT '',
    acctterminatecause varchar(32) NOT NULL DEFAULT '',
    servicetype varchar(32) DEFAULT NULL,
    framedprotocol varchar(32) DEFAULT NULL,
    framedipaddress varchar(15) NOT NULL DEFAULT '',
    PRIMARY KEY (radacctid),
    UNIQUE KEY acctuniqueid (acctuniqueid),
    KEY username (username),
    KEY framedipaddress (framedipaddress),
    KEY acctsessionid (acctsessionid),
    KEY acctsessiontime (acctsessiontime),
    KEY acctstarttime (acctstarttime),
    KEY acctinterval (acctinterval),
    KEY acctstoptime (acctstoptime),
    KEY nasipaddress (nasipaddress)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- radpostauth table (authentication log)
CREATE TABLE IF NOT EXISTS radpostauth (
    id int(11) NOT NULL AUTO_INCREMENT,
    username varchar(64) NOT NULL DEFAULT '',
    pass varchar(64) NOT NULL DEFAULT '',
    reply varchar(32) NOT NULL DEFAULT '',
    authdate timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Show created tables
SHOW TABLES;

SQL

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ All RADIUS tables created successfully!"
    echo ""
    echo "Tables created:"
    docker exec vmaster_radius_db mysql -uroot -prootpassword radius -e "SHOW TABLES;"
    echo ""
    echo "Now restart FreeRADIUS:"
    echo "  docker restart vmaster_freeradius"
    echo ""
else
    echo ""
    echo "❌ Failed to create tables"
    exit 1
fi
