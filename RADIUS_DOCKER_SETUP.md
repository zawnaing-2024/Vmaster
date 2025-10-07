# 🐳 RADIUS Docker Setup with GUI

Complete guide to setup FreeRADIUS with Docker and web-based GUI management.

---

## 🚀 Quick Start (5 Minutes)

### Step 1: Start RADIUS Docker Containers

```bash
cd "/Users/zawnainghtun/My Coding Project/VPN CMS Portal"

# Start all services including RADIUS
docker-compose -f docker-compose-radius.yml up -d

# Check status
docker-compose -f docker-compose-radius.yml ps
```

**Services Started:**
- ✅ VPNMaster Web (port 80)
- ✅ Main Database (port 3306)
- ✅ RADIUS Database (port 3307)
- ✅ FreeRADIUS Server (ports 1812, 1813)
- ✅ phpMyAdmin (port 8080)
- ✅ daloRADIUS GUI (port 8081)

### Step 2: Access Web Interfaces

**VMaster Admin Panel:**
```
http://localhost/admin
Username: admin
Password: admin123
```

**daloRADIUS (RADIUS GUI):**
```
http://localhost:8081
Username: administrator
Password: radius
```

**phpMyAdmin (Database GUI):**
```
http://localhost:8080
Server: radius-db
Username: radius
Password: radiuspass
```

### Step 3: Enable RADIUS in VMaster

```bash
# Edit config
nano config/radius.php

# Change this line:
define('RADIUS_ENABLED', false);

# To:
define('RADIUS_ENABLED', true);

# Save and restart
docker-compose -f docker-compose-radius.yml restart web
```

### Step 4: Test RADIUS

```bash
# Access VMaster Admin
http://localhost/admin/radius-management.php

# Should show:
✅ Connected
✅ Create users via GUI
✅ Manage users directly
```

---

## 🎨 GUI Options

### Option 1: VMaster Built-in GUI (Recommended)

**Access:**
```
http://localhost/admin/radius-management.php
```

**Features:**
- ✅ Create RADIUS users
- ✅ Delete users
- ✅ Suspend/reactivate users
- ✅ Change passwords
- ✅ View statistics
- ✅ Test connection
- ✅ Integrated with VMaster

**Perfect for:** Daily VPN user management

---

### Option 2: daloRADIUS (Full-Featured)

**Access:**
```
http://localhost:8081
```

**Features:**
- ✅ User management
- ✅ Group management
- ✅ Bandwidth management
- ✅ Session monitoring
- ✅ Usage reports
- ✅ Accounting logs
- ✅ Advanced configuration

**Perfect for:** Advanced RADIUS administration

**Default Login:**
```
Username: administrator
Password: radius
```

---

### Option 3: phpMyAdmin (Database Direct)

**Access:**
```
http://localhost:8080
```

**Features:**
- ✅ Direct SQL access
- ✅ Import/export users
- ✅ Bulk operations
- ✅ Database backup

**Perfect for:** Bulk user management, migrations

---

## 📊 Docker Architecture

```
┌─────────────────────────────────────────────────────┐
│ VMaster Web Container                               │
│  • Port 80                                          │
│  • Built-in RADIUS GUI                              │
│  • Connects to RADIUS DB                            │
└─────────────────────────────────────────────────────┘
         ↓ connects to
┌─────────────────────────────────────────────────────┐
│ RADIUS MySQL Database                               │
│  • Port 3307                                        │
│  • radcheck, radreply tables                        │
│  • User credentials stored here                     │
└─────────────────────────────────────────────────────┘
         ↑ reads from
┌─────────────────────────────────────────────────────┐
│ FreeRADIUS Server Container                         │
│  • Port 1812 (auth), 1813 (acct)                    │
│  • Authenticates VPN connections                    │
│  • Uses MySQL for user lookup                       │
└─────────────────────────────────────────────────────┘
         ↑ queries
┌─────────────────────────────────────────────────────┐
│ SoftEther VPN Server (your existing server)         │
│  • Forwards auth to RADIUS                          │
│  • Configured to use FreeRADIUS                     │
└─────────────────────────────────────────────────────┘
         ↑ connects
┌─────────────────────────────────────────────────────┐
│ VPN Clients                                         │
│  • Use RADIUS username/password                     │
│  • Authenticated via RADIUS                         │
└─────────────────────────────────────────────────────┘

BONUS: daloRADIUS Web GUI (port 8081)
  • Professional RADIUS management interface
  • Monitoring and reporting
```

---

## 🔧 Configuration

### Configure SoftEther to Use Docker RADIUS

**Get RADIUS container IP:**
```bash
docker inspect freeradius_server | grep IPAddress
# Example output: "IPAddress": "172.18.0.5"
```

**Configure SoftEther:**
```bash
# Access SoftEther
/usr/local/vpnserver/vpncmd

# Select management mode
1

# Connect to hub
Hub vpn_hub

# Set RADIUS server
RadiusServerSet 172.18.0.5 1812 vmaster_shared_secret_change_me

# Exit
exit
```

**Update clients.conf if needed:**
```bash
# Edit RADIUS client config
nano radius/clients.conf

# Add your SoftEther server IP
client softether_vpn {
    ipaddr = YOUR_SOFTETHER_IP
    secret = vmaster_shared_secret_change_me
    require_message_authenticator = no
    nas_type = other
}

# Restart RADIUS
docker-compose -f docker-compose-radius.yml restart freeradius
```

---

## ✅ Testing

### Test 1: RADIUS Connection

```bash
# Install radtest (if not in container)
docker exec -it freeradius_server bash

# Test authentication
radtest testuser testpass123 localhost 0 testing123

# Should see: Access-Accept
```

### Test 2: Create User via VMaster

```bash
# 1. Open VMaster RADIUS GUI
http://localhost/admin/radius-management.php

# 2. Click "Create RADIUS User"
Username: vpn_test001
Password: SecurePass123

# 3. Check database
http://localhost:8080
Server: radius-db
Database: radius
Table: radcheck

# Should see new user!
```

### Test 3: Auto-Create via VPN Account

```bash
# 1. Enable RADIUS in config/radius.php
# 2. Customer creates SSTP VPN account
# 3. Check RADIUS database
# 4. User should be created automatically!
```

---

## 🔄 Workflow

### Automatic User Creation

```
1. Customer creates SSTP VPN account in VMaster
         ↓
2. VMaster calls RadiusHandler->createUser()
         ↓
3. User inserted into RADIUS database (radcheck table)
         ↓
4. VPN client connects with username/password
         ↓
5. SoftEther forwards auth request to RADIUS
         ↓
6. RADIUS checks database
         ↓
7. Client authenticated and connected! ✅
```

### Automatic Suspension

```
1. Customer suspends client in VMaster
         ↓
2. VMaster calls RadiusHandler->suspendUser()
         ↓
3. Reject rule added to RADIUS database
         ↓
4. VPN client tries to connect
         ↓
5. RADIUS returns Access-Reject
         ↓
6. Client CANNOT connect! ✅
```

---

## 📋 Common Operations

### View All RADIUS Users (SQL)

```sql
-- Connect to RADIUS database
mysql -h localhost -P 3307 -u radius -pradiuspass radius

-- List all users
SELECT username, attribute, value 
FROM radcheck 
WHERE attribute = 'Cleartext-Password';

-- Check suspended users
SELECT username FROM radcheck 
WHERE attribute = 'Auth-Type' AND value = 'Reject';
```

### Bulk Create Users (SQL)

```sql
USE radius;

INSERT INTO radcheck (username, attribute, op, value) VALUES
('vpn_user001', 'Cleartext-Password', ':=', 'pass001'),
('vpn_user002', 'Cleartext-Password', ':=', 'pass002'),
('vpn_user003', 'Cleartext-Password', ':=', 'pass003');
```

### Backup RADIUS Database

```bash
# Backup
docker exec radius_db mysqldump -u radius -pradiuspass radius > radius_backup.sql

# Restore
docker exec -i radius_db mysql -u radius -pradiuspass radius < radius_backup.sql
```

---

## 🎯 Production Deployment

### Secure Configuration

**1. Change default passwords:**
```bash
# Edit docker-compose-radius.yml
MYSQL_ROOT_PASSWORD: YOUR_SECURE_PASSWORD
MYSQL_PASSWORD: YOUR_SECURE_PASSWORD

# Edit radius/clients.conf
secret = YOUR_SECURE_SHARED_SECRET
```

**2. Use environment variables:**
```bash
# Create .env file
RADIUS_DB_PASS=your_secure_password
RADIUS_SHARED_SECRET=your_shared_secret

# Update docker-compose-radius.yml to use ${RADIUS_DB_PASS}
```

**3. Enable SSL for RADIUS:**
```bash
# Configure RadSec (RADIUS over TLS)
# See FreeRADIUS documentation
```

---

## 🐛 Troubleshooting

### RADIUS Connection Failed

```bash
# Check RADIUS container status
docker ps | grep freeradius

# Check logs
docker logs freeradius_server

# Test connection
docker exec -it freeradius_server radiusd -X

# Check database connection
docker exec -it radius_db mysql -u radius -pradiuspass -e "SELECT 1"
```

### User Not Authenticating

```bash
# Check if user exists
docker exec -it radius_db mysql -u radius -pradiuspass radius \
  -e "SELECT * FROM radcheck WHERE username='testuser'"

# Check RADIUS logs
docker logs freeradius_server | grep testuser

# Test manually
docker exec -it freeradius_server \
  radtest testuser testpass123 localhost 0 testing123
```

### VMaster Can't Connect to RADIUS

```bash
# Check RADIUS_DB_HOST in config/radius.php
# Should be: 'radius-db' for Docker

# Test from web container
docker exec -it vpn_cms_web ping radius-db

# Check network
docker network inspect vpn_cms_portal_vpn-network
```

---

## 📚 Resources

**FreeRADIUS Documentation:**
- https://freeradius.org/documentation/

**daloRADIUS:**
- https://www.daloradius.com/

**Docker Hub:**
- https://hub.docker.com/r/freeradius/freeradius-server

---

## ✨ Summary

✅ **Complete Docker setup** - All services containerized
✅ **3 GUI options** - VMaster, daloRADIUS, phpMyAdmin
✅ **Fully automated** - No manual user management
✅ **Production ready** - Scalable and secure
✅ **Easy deployment** - One command to start

**Start command:**
```bash
docker-compose -f docker-compose-radius.yml up -d
```

**Access:**
- VMaster RADIUS GUI: http://localhost/admin/radius-management.php
- daloRADIUS: http://localhost:8081
- phpMyAdmin: http://localhost:8080

Perfect RADIUS setup with GUI! 🎉

