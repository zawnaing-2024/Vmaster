# 🚀 Production RADIUS Setup for Full Automation

## Overview
This guide will set up RADIUS on your production server to enable:
- ✅ Automatic SSTP/V2Ray account creation
- ✅ Automatic account suspension when client is disabled
- ✅ Automatic account deletion when VPN account is deleted
- ✅ No manual intervention needed

---

## 📋 Prerequisites

- Ubuntu 22.04 server
- Docker and Docker Compose installed
- VMaster already running on production
- Domain: vmaster.vip

---

## 🔧 Step 1: Update docker-compose.prod.yml

Add RADIUS services to your production Docker Compose file.

**On your Ubuntu server:**

```bash
cd /var/www/vmaster
nano docker-compose.prod.yml
```

**Add these services AFTER the `web` service:**

```yaml
  radius-db:
    image: mariadb:10.6
    container_name: vmaster_radius_db
    restart: always
    environment:
      MYSQL_ROOT_PASSWORD: rootpassword
      MYSQL_DATABASE: radius
      MYSQL_USER: radius
      MYSQL_PASSWORD: radiuspass
    volumes:
      - radius_db_data:/var/lib/mysql
    networks:
      - vmaster-network
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      timeout: 5s
      retries: 10
```

**Update the `web` service environment variables:**

Add these to the existing environment section:

```yaml
  web:
    # ... existing config ...
    environment:
      # ... existing environment variables ...
      RADIUS_DB_HOST: radius-db
      RADIUS_ENABLED: "true"
```

**Update the volumes section at the bottom:**

```yaml
volumes:
  db_data:
  radius_db_data:  # Add this line
```

**Save and exit:** Press `Ctrl+O`, `Enter`, `Ctrl+X`

---

## 🚀 Step 2: Deploy RADIUS

```bash
cd /var/www/vmaster

# Stop current containers
docker-compose -f docker-compose.prod.yml down

# Start with RADIUS
docker-compose -f docker-compose.prod.yml up -d

# Wait for RADIUS DB to be ready (30 seconds)
sleep 30

# Check if containers are running
docker ps
```

**You should see:**
- `vmaster_web`
- `vmaster_db`
- `vmaster_radius_db`
- `nginx` (if using)

---

## 📊 Step 3: Import RADIUS Database Schema

```bash
cd /var/www/vmaster

# Import RADIUS schema
docker exec -i vmaster_radius_db mysql -uradius -pradiuspass radius < radius/schema.sql

# Verify tables created
docker exec vmaster_radius_db mysql -uradius -pradiuspass radius -e "SHOW TABLES"
```

**Expected output:**
```
Tables_in_radius
nas
radacct
radcheck
radgroupcheck
radgroupreply
radpostauth
radreply
radusergroup
```

---

## ✅ Step 4: Verify RADIUS Configuration

```bash
# Check RADIUS config
docker exec vmaster_web php -r "require '/var/www/html/config/radius.php'; echo 'RADIUS_ENABLED: ' . (RADIUS_ENABLED ? 'TRUE' : 'FALSE') . PHP_EOL; echo 'RADIUS_DB_HOST: ' . RADIUS_DB_HOST . PHP_EOL;"

# Test RADIUS connection
docker exec vmaster_web php -r "require '/var/www/html/config/radius.php'; \$conn = getRadiusConnection(); echo \$conn ? '✅ RADIUS Connected' : '❌ Connection Failed'; echo PHP_EOL;"
```

**Expected output:**
```
RADIUS_ENABLED: TRUE
RADIUS_DB_HOST: radius-db
✅ RADIUS Connected
```

---

## 🔄 Step 5: Enable RADIUS in Code

```bash
# Make sure RADIUS is enabled
docker exec vmaster_web sed -i "s/define('RADIUS_ENABLED', false);/define('RADIUS_ENABLED', true);/" /var/www/html/config/radius.php

# Verify
docker exec vmaster_web grep "RADIUS_ENABLED" /var/www/html/config/radius.php

# Restart web container
docker restart vmaster_web
```

---

## 🧪 Step 6: Test RADIUS User Creation

Let's test if RADIUS can create users:

```bash
docker exec vmaster_web php -r "
require '/var/www/html/config/radius.php';
require '/var/www/html/includes/radius_handler.php';
\$radius = new RadiusHandler();
\$result = \$radius->createUser('test_user_001', 'TestPassword123!');
echo \$result ? '✅ Test user created successfully' : '❌ Failed to create user';
echo PHP_EOL;
"

# Check if user exists in RADIUS
docker exec vmaster_radius_db mysql -uradius -pradiuspass radius -e "SELECT username, attribute, value FROM radcheck WHERE username='test_user_001'"
```

**Expected output:**
```
✅ Test user created successfully

username        attribute       value
test_user_001   Cleartext-Password      TestPassword123!
```

---

## 🎯 Step 7: Test Complete Workflow

### A. Create SSTP Account (via Customer Portal)

1. Login as customer
2. Go to **VPN Accounts**
3. Select SSTP server
4. Create account

**Check RADIUS:**
```bash
# List all RADIUS users
docker exec vmaster_radius_db mysql -uradius -pradiuspass radius -e "SELECT username FROM radcheck"
```

You should see `sstp_xxxxxxxxxx` created!

### B. Suspend Client (Test Auto-Suspension)

1. Login as customer
2. Go to **Clients**
3. Change client status to **Disabled**

**Check RADIUS:**
```bash
# Check user is suspended (Auth-Type = Reject)
docker exec vmaster_radius_db mysql -uradius -pradiuspass radius -e "SELECT username, attribute, value FROM radcheck WHERE username LIKE 'sstp_%' ORDER BY id DESC LIMIT 5"
```

Should show `Auth-Type = Reject` for suspended users.

### C. Delete VPN Account (Test Auto-Deletion)

1. Delete VPN account from portal

**Check RADIUS:**
```bash
# User should be removed from RADIUS
docker exec vmaster_radius_db mysql -uradius -pradiuspass radius -e "SELECT COUNT(*) as total FROM radcheck WHERE username='sstp_xxxxxxxxxx'"
```

Should return `0` if deleted successfully.

---

## 📊 Monitoring RADIUS

### Check RADIUS Users

```bash
# Count total RADIUS users
docker exec vmaster_radius_db mysql -uradius -pradiuspass radius -e "SELECT COUNT(DISTINCT username) as total_users FROM radcheck"

# List recent RADIUS users
docker exec vmaster_radius_db mysql -uradius -pradiuspass radius -e "SELECT username, attribute, value FROM radcheck ORDER BY id DESC LIMIT 10"

# Check suspended users
docker exec vmaster_radius_db mysql -uradius -pradiuspass radius -e "SELECT username FROM radcheck WHERE attribute='Auth-Type' AND value='Reject'"
```

### Check RADIUS Logs

```bash
# Web container logs (RADIUS handler)
docker logs vmaster_web --tail 50 | grep -i radius

# RADIUS DB logs
docker logs vmaster_radius_db --tail 50
```

---

## 🔒 Security Best Practices

### 1. Change Default Passwords

**Edit docker-compose.prod.yml:**
```yaml
  radius-db:
    environment:
      MYSQL_ROOT_PASSWORD: YOUR_STRONG_PASSWORD_HERE
      MYSQL_PASSWORD: YOUR_RADIUS_DB_PASSWORD_HERE
```

**Update config/radius.php:**
```php
define('RADIUS_DB_PASS', 'YOUR_RADIUS_DB_PASSWORD_HERE');
```

### 2. Backup RADIUS Database

```bash
# Create backup script
nano /root/backup-radius.sh
```

**Add:**
```bash
#!/bin/bash
BACKUP_DIR="/root/backups/radius"
mkdir -p $BACKUP_DIR
docker exec vmaster_radius_db mysqldump -uradius -pradiuspass radius > $BACKUP_DIR/radius_$(date +%Y%m%d_%H%M%S).sql
find $BACKUP_DIR -name "radius_*.sql" -mtime +7 -delete
```

**Make executable and schedule:**
```bash
chmod +x /root/backup-radius.sh
crontab -e
# Add: 0 2 * * * /root/backup-radius.sh
```

---

## 🔧 Troubleshooting

### RADIUS Connection Failed

```bash
# Check RADIUS DB is running
docker ps | grep radius

# Check RADIUS DB logs
docker logs vmaster_radius_db

# Test connection manually
docker exec vmaster_radius_db mysql -uradius -pradiuspass -e "SELECT 1"
```

### Users Not Created in RADIUS

```bash
# Check web container logs
docker logs vmaster_web --tail 100 | grep -i "radius\|sstp"

# Verify RADIUS_ENABLED
docker exec vmaster_web php -r "require '/var/www/html/config/radius.php'; var_dump(RADIUS_ENABLED);"

# Check radius_handler.php exists
docker exec vmaster_web ls -la /var/www/html/includes/radius_handler.php
```

### Users Not Deleted from RADIUS

```bash
# Check if deleteUser is being called
docker logs vmaster_web --tail 100 | grep -i "delete.*radius"

# Manually test deletion
docker exec vmaster_web php -r "
require '/var/www/html/config/radius.php';
require '/var/www/html/includes/radius_handler.php';
\$radius = new RadiusHandler();
\$result = \$radius->deleteUser('test_user_001');
echo \$result ? '✅ Deleted' : '❌ Failed';
echo PHP_EOL;
"
```

---

## 📈 Performance Tuning

For production with 100+ users:

```bash
# Optimize RADIUS DB
docker exec vmaster_radius_db mysql -uradius -pradiuspass radius << 'EOF'
ALTER TABLE radcheck ADD INDEX idx_username (username);
ALTER TABLE radacct ADD INDEX idx_username (username);
ALTER TABLE radacct ADD INDEX idx_acctstarttime (acctstarttime);
OPTIMIZE TABLE radcheck;
OPTIMIZE TABLE radacct;
EOF
```

---

## ✅ Final Checklist

- [ ] RADIUS DB container running
- [ ] RADIUS schema imported
- [ ] RADIUS connection tested
- [ ] RADIUS_ENABLED = true
- [ ] Test user created successfully
- [ ] SSTP account creates RADIUS user
- [ ] Client suspension suspends RADIUS user
- [ ] VPN deletion removes RADIUS user
- [ ] Backup script configured
- [ ] Passwords changed from defaults

---

## 🎉 Success Criteria

After completing this setup:

1. **Create SSTP Account** → RADIUS user created automatically
2. **Suspend Client** → All client's VPN accounts suspended in RADIUS
3. **Delete VPN Account** → RADIUS user deleted automatically
4. **No Manual Intervention** → Everything is automated!

---

## 📞 Quick Commands Cheatsheet

```bash
# Check RADIUS status
docker ps | grep radius

# View RADIUS users
docker exec vmaster_radius_db mysql -uradius -pradiuspass radius -e "SELECT username FROM radcheck"

# Count RADIUS users
docker exec vmaster_radius_db mysql -uradius -pradiuspass radius -e "SELECT COUNT(DISTINCT username) as total FROM radcheck"

# Restart everything
docker-compose -f docker-compose.prod.yml restart

# View logs
docker logs vmaster_web --tail 50
docker logs vmaster_radius_db --tail 50
```

---

**You're all set for full RADIUS automation! 🚀**

