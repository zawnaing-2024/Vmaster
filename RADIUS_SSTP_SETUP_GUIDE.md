# RADIUS + SSTP Setup Guide for VMaster

This guide will help you set up RADIUS authentication with SoftEther VPN for automated SSTP account management.

---

## 🎯 Overview

RADIUS (Remote Authentication Dial-In User Service) allows automatic user authentication without manually creating accounts on the VPN server. When integrated with VMaster:

1. **VMaster creates users in RADIUS database**
2. **SoftEther VPN checks RADIUS for authentication**
3. **Users can connect automatically** - no manual server configuration needed!

---

## 📋 Prerequisites

- VMaster CMS running with Docker
- SoftEther VPN Server installed
- RADIUS containers running (from `docker-compose-radius.yml`)

---

## Step 1: Verify RADIUS is Running

```bash
# Check RADIUS database is running
docker ps | grep radius_db

# Should show container running on port 3307
```

---

## Step 2: Enable RADIUS in VMaster

1. **Edit the RADIUS configuration:**
   ```bash
   cd /Users/zawnainghtun/My\ Coding\ Project/VPN\ CMS\ Portal
   nano config/radius.php
   ```

2. **Change RADIUS_ENABLED to true:**
   ```php
   define('RADIUS_ENABLED', true);  // Change from false to true
   ```

3. **Save the file** (Ctrl+O, Enter, Ctrl+X)

---

## Step 3: Configure SoftEther VPN Server for RADIUS

### A. Using SoftEther VPN Server Manager (GUI):

1. **Download SoftEther VPN Server Manager:**
   - Windows: https://www.softether-download.com/
   - macOS: Available via Homebrew or direct download

2. **Connect to your SoftEther server**

3. **Enable RADIUS Authentication:**
   - Click on "Virtual Hub" → Select your hub
   - Click "Manage Virtual Hub"
   - Go to "User Authentication" → "External Authentication Server Settings"
   - Click "Configure RADIUS Authentication"

4. **Enter RADIUS Server Details:**
   ```
   RADIUS Server Hostname: localhost (or your server IP)
   Port Number: 1812
   Shared Secret: testing123
   ```

5. **Set Authentication Method:**
   - Select "Use RADIUS for all users"
   - Click "OK"

### B. Using Command Line (vpncmd):

```bash
# Connect to SoftEther
vpncmd /SERVER localhost

# Select your hub
Hub DEFAULT

# Set RADIUS server
RadiusServerSet localhost:1812 testing123

# Enable RADIUS authentication
SecureNatEnable
```

---

## Step 4: Add SSTP Server in VMaster

1. **Login to VMaster Admin Panel:**
   ```
   http://localhost/admin/login.php
   Username: admin
   Password: admin123
   ```

2. **Go to "VPN Servers"**

3. **Click "Add Server"**

4. **Fill in SSTP Server Details:**
   ```
   Server Name: My SSTP Server
   Server Type: SSTP
   Server Host: your-server-ip or domain
   Server Port: 443 (or 5555 if using default SoftEther)
   API URL: (leave empty for RADIUS)
   API Key: (leave empty for RADIUS)
   Description: SSTP with RADIUS authentication
   ```

5. **Click "Add Server"**

---

## Step 5: Test RADIUS Integration

### Test 1: Create a RADIUS User from VMaster

1. **Go to Admin → RADIUS Management:**
   ```
   http://localhost/admin/radius-management.php
   ```

2. **Add a test user:**
   ```
   Username: testuser
   Password: testpass123
   ```

3. **Verify in database:**
   ```bash
   docker exec vpn_cms_db mysql -uroot -prootpassword radius -e "SELECT * FROM radcheck"
   ```

### Test 2: Create SSTP Account via Customer Portal

1. **Login as a customer**

2. **Go to "VPN Accounts"**

3. **Create new VPN account:**
   - Select Client: Your client
   - Select Server: Your SSTP server
   - Click "Create Account"

4. **VMaster will:**
   - Generate random username/password
   - Create user in RADIUS database
   - Store credentials in VMaster
   - Display credentials to customer

5. **Try connecting:**
   - Use the provided username/password
   - Connect via SSTP VPN client
   - SoftEther will check RADIUS
   - Connection should succeed!

---

## Step 6: How It Works

```
┌──────────────┐
│   Customer   │
│   Creates    │
│ VPN Account  │
└──────┬───────┘
       │
       ▼
┌──────────────────────┐
│  VMaster PHP Code    │
│  (vpn_handler.php)   │
└──────┬───────────────┘
       │
       ▼
┌──────────────────────┐     ┌──────────────────┐
│  RADIUS Database     │     │  VMaster MySQL   │
│  (radcheck table)    │     │  (vpn_accounts)  │
│  - Username          │     │  - Credentials   │
│  - Password Hash     │     │  - Server info   │
└──────────────────────┘     └──────────────────┘
       │
       │ Authentication Check
       ▼
┌──────────────────────┐
│  SoftEther VPN       │
│  Checks RADIUS       │
│  Allows/Denies       │
└──────────────────────┘
       │
       ▼
┌──────────────────────┐
│  User Connects!      │
│  SSTP Tunnel Active  │
└──────────────────────┘
```

---

## 🔧 VMaster RADIUS Management Features

Access: `http://localhost/admin/radius-management.php`

### Available Actions:

1. **Create User** - Add new RADIUS user
2. **Delete User** - Remove user from RADIUS
3. **Suspend User** - Temporarily disable (sets password to REJECT)
4. **Reactivate User** - Re-enable suspended user
5. **Change Password** - Update user password
6. **View All Users** - See all RADIUS users

---

## 🎯 Automatic Integration

When **RADIUS is ENABLED** in `config/radius.php`:

### Creating SSTP/V2Ray Account:
```php
1. Customer creates VPN account
2. VMaster generates username/password
3. VMaster calls RadiusHandler->createUser()
4. User added to RADIUS database
5. Credentials stored in VMaster
6. User can connect immediately!
```

### Deleting Account:
```php
1. Customer deletes VPN account
2. VMaster calls RadiusHandler->deleteUser()
3. User removed from RADIUS
4. Connection no longer works
```

### Suspending Account:
```php
1. Customer suspends client
2. VMaster calls RadiusHandler->suspendUser()
3. User cannot authenticate
4. Existing connections may continue until timeout
```

---

## 📊 Database Schema

### RADIUS Tables (in `radius` database):

```sql
-- User credentials
CREATE TABLE radcheck (
  id int PRIMARY KEY AUTO_INCREMENT,
  username varchar(64) NOT NULL,
  attribute varchar(64) NOT NULL,
  op char(2) NOT NULL,
  value varchar(253) NOT NULL
);

-- Usage tracking
CREATE TABLE radacct (
  radacctid bigint PRIMARY KEY AUTO_INCREMENT,
  username varchar(64),
  nasipaddress varchar(15),
  acctsessiontime int,
  acctinputoctets bigint,
  acctoutputoctets bigint,
  ...
);
```

---

## 🔍 Troubleshooting

### Issue 1: RADIUS Not Working

**Check RADIUS database:**
```bash
docker exec vpn_cms_db mysql -uroot -prootpassword radius -e "SELECT * FROM radcheck"
```

**Check if RADIUS is enabled:**
```bash
grep RADIUS_ENABLED config/radius.php
```

Should show: `define('RADIUS_ENABLED', true);`

### Issue 2: SoftEther Not Authenticating

**Check SoftEther logs:**
```bash
# On SoftEther server
tail -f /var/log/softether/server_log_*.log
```

**Verify RADIUS settings in SoftEther:**
```bash
vpncmd /SERVER localhost
Hub DEFAULT
RadiusServerGet
```

### Issue 3: Password Mismatch

RADIUS uses MD5/SHA hashing. VMaster sends:
```
Cleartext-Password := "actualpassword"
```

SoftEther should be configured to use PAP or CHAP.

---

## 🎉 Success Checklist

✅ RADIUS database running  
✅ `config/radius.php` has `RADIUS_ENABLED = true`  
✅ SoftEther configured with RADIUS server  
✅ RADIUS Management page shows users  
✅ Test user can connect via SSTP  
✅ Creating VPN account in VMaster works  
✅ User appears in RADIUS database  
✅ SSTP connection succeeds  

---

## 🚀 Next Steps

1. **Enable RADIUS** - Edit `config/radius.php`
2. **Configure SoftEther** - Point to RADIUS server
3. **Test** - Create account and try connecting
4. **Monitor** - Check `radacct` table for usage stats
5. **Scale** - Add more SSTP servers as needed

---

## 📞 Support

If you encounter issues:
1. Check Docker containers are running
2. Verify RADIUS database has users
3. Check SoftEther RADIUS configuration
4. Review VMaster admin notifications
5. Check logs for errors

---

**RADIUS eliminates manual account creation and enables true automation! 🎊**

