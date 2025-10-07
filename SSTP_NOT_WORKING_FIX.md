# 🔧 SSTP Account Creation Not Working - Fix Guide

## Problem
When customers try to create SSTP VPN accounts:
- ❌ No username/password generated
- ❌ RADIUS not creating users
- ❌ Server shows as active but accounts don't work

---

## Root Causes

### 1. RADIUS Not Running on Production
The SSTP integration expects RADIUS, but on production:
- `radius-db` container may not exist
- `docker-compose.prod.yml` doesn't include RADIUS services

### 2. No Credentials Pool
If RADIUS is disabled, the system falls back to the credentials pool, but:
- Pool table may be empty
- No SSTP credentials pre-loaded

---

## 🎯 Solution: Choose Your Approach

### **Option A: Enable RADIUS (Recommended for Automation)**

#### Step 1: Update Production Docker Compose

```bash
cd /var/www/vmaster
nano docker-compose.prod.yml
```

Add these services AFTER the existing `web` service:

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

  freeradius:
    image: freeradius/freeradius-server:latest
    container_name: vmaster_freeradius
    restart: always
    ports:
      - "127.0.0.1:1812:1812/udp"
      - "127.0.0.1:1813:1813/udp"
    environment:
      RADIUS_DB_HOST: radius-db
      RADIUS_DB_NAME: radius
      RADIUS_DB_USER: radius
      RADIUS_DB_PASS: radiuspass
    networks:
      - vmaster-network
    depends_on:
      - radius-db
```

Add to volumes section:

```yaml
volumes:
  db_data:
  radius_db_data:  # Add this line
```

#### Step 2: Update Web Service Environment

Add to the `web` service environment variables:

```yaml
  web:
    # ... existing config ...
    environment:
      # ... existing vars ...
      RADIUS_DB_HOST: radius-db
      RADIUS_ENABLED: "true"
```

#### Step 3: Deploy RADIUS

```bash
# Stop containers
docker-compose -f docker-compose.prod.yml down

# Start with RADIUS
docker-compose -f docker-compose.prod.yml up -d

# Import RADIUS schema
docker exec -i vmaster_radius_db mysql -uradius -pradiuspass radius < /var/www/vmaster/radius/schema.sql
```

#### Step 4: Test RADIUS

```bash
# Check if RADIUS DB is running
docker ps | grep radius

# Test connection
docker exec vmaster_web php /var/www/html/check-sstp-config.php
```

---

### **Option B: Use Credentials Pool (Simpler, Manual)**

If you don't want RADIUS complexity:

#### Step 1: Disable RADIUS

Edit `config/radius.php`:

```php
define('RADIUS_ENABLED', false); // Change to false
```

#### Step 2: Add SSTP Credentials to Pool

1. Login to admin panel
2. Go to **VPN Management > Credentials Pool**
3. Add SSTP credentials manually:
   - VPN Type: SSTP
   - Username: `sstp_user1`
   - Password: `secure_password_123`
   - Click **Add Credential**

Repeat for multiple credentials (recommend 50-100 credentials per server).

#### Step 3: Restart Web Container

```bash
docker restart vmaster_web
```

---

## 🧪 Testing

### Run Diagnostic Script

```bash
# Copy script to public folder
docker exec vmaster_web cp /var/www/html/check-sstp-config.php /var/www/html/check-sstp-config.php

# Access in browser
https://vmaster.vip/check-sstp-config.php
```

This will show:
- ✅ RADIUS status
- ✅ Credentials pool status
- ✅ Available SSTP servers
- ❌ Any issues found

---

### Test SSTP Account Creation

1. Login as a customer
2. Go to **VPN Accounts**
3. Create new account:
   - Select SSTP server
   - Create account

**Expected Results:**

**With RADIUS:**
- Username: `sstp_xxxxx` (auto-generated)
- Password: Random 16-char password
- Stored in RADIUS `radcheck` table

**With Pool:**
- Username: From pool
- Password: From pool
- Marked as assigned in pool

---

## 🔍 Troubleshooting

### "No RADIUS connection"

```bash
# Check RADIUS DB
docker logs vmaster_radius_db

# Check web container can reach it
docker exec vmaster_web ping radius-db
```

### "Pool is empty"

Add credentials via admin panel or SQL:

```bash
docker exec vmaster_db mysql -uroot -prootpassword vpn_cms_portal << EOF
INSERT INTO vpn_credentials_pool (vpn_type, username, password) VALUES 
('sstp', 'sstp_user1', 'Pass123!@#'),
('sstp', 'sstp_user2', 'Pass456!@#'),
('sstp', 'sstp_user3', 'Pass789!@#');
EOF
```

### "Account created but no username/password shown"

Check `vpn_accounts` table:

```bash
docker exec vmaster_db mysql -uroot -prootpassword vpn_cms_portal -e "SELECT id, account_username, account_password, server_id FROM vpn_accounts WHERE server_id IN (SELECT id FROM vpn_servers WHERE server_type = 'sstp') ORDER BY id DESC LIMIT 5"
```

---

## ✅ Recommended Setup

**For Production (Best):**
1. ✅ Enable RADIUS for full automation
2. ✅ No manual credential management needed
3. ✅ Automatic creation, suspension, deletion

**For Testing/Simple Setup:**
1. ⚠️ Use credentials pool
2. ⚠️ Manually add 50-100 credentials per server
3. ⚠️ Periodically refill pool when running low

---

## 📊 Verify Everything Works

After applying the fix, run:

```bash
# 1. Check configuration
curl https://vmaster.vip/check-sstp-config.php

# 2. Check RADIUS users (if using RADIUS)
docker exec vmaster_radius_db mysql -uradius -pradiuspass radius -e "SELECT username, attribute, value FROM radcheck LIMIT 10"

# 3. Check pool credentials (if using pool)
docker exec vmaster_db mysql -uroot -prootpassword vpn_cms_portal -e "SELECT vpn_type, username, is_assigned FROM vpn_credentials_pool WHERE vpn_type = 'sstp' LIMIT 10"
```

---

## 🎯 Quick Decision Guide

**Choose RADIUS if:**
- ✅ You want full automation
- ✅ You manage 100+ SSTP accounts
- ✅ You're comfortable with Docker

**Choose Pool if:**
- ✅ You have < 50 SSTP accounts
- ✅ You prefer simplicity
- ✅ You don't mind manual credential management

---

**Need help? Check the diagnostic output first!**

```bash
docker exec vmaster_web php /var/www/html/check-sstp-config.php | less
```

