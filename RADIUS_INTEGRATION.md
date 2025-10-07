# 🔐 RADIUS Integration for Automated SSTP/VPN Management

## 🎯 The Problem & Solution

### Current Manual Method:
```
1. Admin manually creates users in SoftEther
2. Admin adds credentials to VMaster pool
3. VMaster assigns credentials to customers
4. When customer deleted → Admin manually removes from SoftEther
❌ Too much manual work!
```

### RADIUS Automated Method:
```
1. VMaster creates user in RADIUS automatically
2. SoftEther authenticates against RADIUS
3. User can connect immediately
4. When customer deleted → VMaster deletes from RADIUS
✅ Fully automated!
```

---

## 🏗️ Architecture

```
┌─────────────────────────────────────────────────────────┐
│ VMaster Portal                                          │
│  • Customer creates VPN account                         │
│  • VMaster calls RADIUS API                             │
│  • Creates user in RADIUS                               │
└─────────────────────────────────────────────────────────┘
         ↓
┌─────────────────────────────────────────────────────────┐
│ RADIUS Server (FreeRADIUS)                              │
│  • Stores user credentials                              │
│  • Authenticates VPN connections                        │
│  • Can add/delete users via API/CLI                     │
└─────────────────────────────────────────────────────────┘
         ↓
┌─────────────────────────────────────────────────────────┐
│ SoftEther VPN Server                                    │
│  • Configured to use RADIUS authentication              │
│  • Forwards auth requests to RADIUS                     │
│  • No manual user management needed!                    │
└─────────────────────────────────────────────────────────┘
         ↓
┌─────────────────────────────────────────────────────────┐
│ VPN Client                                              │
│  • Connects with RADIUS username/password               │
│  • RADIUS authenticates                                 │
│  • SoftEther allows connection                          │
└─────────────────────────────────────────────────────────┘
```

---

## 📋 Setup Steps

### 1. Install FreeRADIUS Server

**On Ubuntu/Debian:**
```bash
# Update system
sudo apt update

# Install FreeRADIUS
sudo apt install freeradius freeradius-utils -y

# Start service
sudo systemctl start freeradius
sudo systemctl enable freeradius
```

**On CentOS/RHEL:**
```bash
sudo yum install freeradius freeradius-utils -y
sudo systemctl start radiusd
sudo systemctl enable radiusd
```

---

### 2. Configure FreeRADIUS

**Edit clients configuration:**
```bash
sudo nano /etc/freeradius/3.0/clients.conf
```

Add SoftEther server as a client:
```conf
client softether_vpn {
    ipaddr = YOUR_SOFTETHER_SERVER_IP
    secret = YOUR_SHARED_SECRET
    require_message_authenticator = no
    nas_type = other
}
```

**Edit users file:**
```bash
sudo nano /etc/freeradius/3.0/users
```

Add test user:
```
testuser Cleartext-Password := "testpass123"
    Reply-Message = "Hello from RADIUS"
```

**Restart FreeRADIUS:**
```bash
sudo systemctl restart freeradius
```

---

### 3. Configure SoftEther for RADIUS

**Access SoftEther management:**
```bash
/usr/local/vpnserver/vpncmd
```

**Select hub:**
```
3 (Use of VPN Tools)
ServerPasswordSet
[Enter password]

1 (Management of VPN Server)
[Enter server IP or press Enter for localhost]
Hub vpn_hub
```

**Enable RADIUS authentication:**
```
RadiusServerSet
RADIUS Server Address: YOUR_RADIUS_SERVER_IP
RADIUS Server Port: 1812
Shared Secret: YOUR_SHARED_SECRET
Retry Interval: 5
```

**Set authentication method:**
```
ServerCertRegenerate [server CN]
SecureNatEnable
```

**Test configuration:**
```bash
# From RADIUS server
radtest testuser testpass123 localhost 0 testing123

# Should see:
# Received Access-Accept
```

---

### 4. Test RADIUS Authentication

**Connect with VPN client:**
```
Server: your-softether-server.com
Username: testuser
Password: testpass123
```

**Check RADIUS logs:**
```bash
sudo tail -f /var/log/freeradius/radius.log
```

Should see successful authentication!

---

## 🔧 VMaster Integration Options

### Option 1: radclient Command (Simple)

**Add user via shell command:**
```php
function createRadiusUser($username, $password) {
    $command = sprintf(
        'echo "User-Name=%s,User-Password=%s" | radclient -x %s:1812 auth %s',
        escapeshellarg($username),
        escapeshellarg($password),
        escapeshellarg(RADIUS_SERVER_IP),
        escapeshellarg(RADIUS_SECRET)
    );
    
    exec($command, $output, $returnCode);
    return $returnCode === 0;
}
```

**Delete user:**
```php
function deleteRadiusUser($username) {
    // Edit users file via SSH
    $command = sprintf(
        'ssh radius-server "sudo sed -i \'/%s/d\' /etc/freeradius/3.0/users && sudo systemctl reload freeradius"',
        escapeshellarg($username)
    );
    
    exec($command);
}
```

---

### Option 2: FreeRADIUS MySQL Backend (Recommended)

**Configure MySQL storage:**

1. **Install MySQL module:**
```bash
sudo apt install freeradius-mysql -y
```

2. **Create RADIUS database:**
```sql
CREATE DATABASE radius;
CREATE USER 'radius'@'localhost' IDENTIFIED BY 'radiuspass';
GRANT ALL ON radius.* TO 'radius'@'localhost';
FLUSH PRIVILEGES;

USE radius;
SOURCE /etc/freeradius/3.0/mods-config/sql/main/mysql/schema.sql;
```

3. **Configure FreeRADIUS to use MySQL:**
```bash
sudo nano /etc/freeradius/3.0/mods-available/sql
```

```conf
sql {
    dialect = "mysql"
    driver = "rlm_sql_mysql"
    
    server = "localhost"
    port = 3306
    login = "radius"
    password = "radiuspass"
    radius_db = "radius"
}
```

4. **Enable SQL module:**
```bash
sudo ln -s /etc/freeradius/3.0/mods-available/sql /etc/freeradius/3.0/mods-enabled/
sudo systemctl restart freeradius
```

5. **VMaster can now manage users directly in MySQL!**

```php
function createRadiusUser($username, $password) {
    // Connect to RADIUS database
    $radiusDb = new PDO('mysql:host=radius-server;dbname=radius', 'radius', 'radiuspass');
    
    // Add user to radcheck table
    $stmt = $radiusDb->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Cleartext-Password', ':=', ?)");
    $stmt->execute([$username, $password]);
    
    return true;
}

function deleteRadiusUser($username) {
    $radiusDb = new PDO('mysql:host=radius-server;dbname=radius', 'radius', 'radiuspass');
    
    $stmt = $radiusDb->prepare("DELETE FROM radcheck WHERE username = ?");
    $stmt->execute([$username]);
    
    return true;
}

function suspendRadiusUser($username) {
    $radiusDb = new PDO('mysql:host=radius-server;dbname=radius', 'radius', 'radiuspass');
    
    // Add disabled attribute
    $stmt = $radiusDb->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Auth-Type', ':=', 'Reject')");
    $stmt->execute([$username]);
    
    return true;
}
```

---

### Option 3: FreeRADIUS REST API (Modern)

**Install rlm_rest module:**
```bash
sudo apt install freeradius-rest -y
```

**Create REST API endpoint** (using PHP/Python/Node.js):

```python
# radius_api.py (Flask example)
from flask import Flask, request, jsonify
import subprocess

app = Flask(__name__)

@app.route('/radius/user', methods=['POST'])
def create_user():
    data = request.json
    username = data['username']
    password = data['password']
    
    # Add to database or users file
    # ... implementation ...
    
    return jsonify({'success': True, 'username': username})

@app.route('/radius/user/<username>', methods=['DELETE'])
def delete_user(username):
    # Remove from database or users file
    # ... implementation ...
    
    return jsonify({'success': True})

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)
```

**VMaster calls REST API:**
```php
function createRadiusUser($username, $password) {
    $ch = curl_init('http://radius-server:5000/radius/user');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'username' => $username,
        'password' => $password
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true)['success'];
}
```

---

## 🎯 Recommended Approach for VMaster

### **Use MySQL Backend** (Best for VMaster)

**Why:**
- ✅ Direct database access
- ✅ No SSH/CLI commands needed
- ✅ Fast and reliable
- ✅ Easy to implement
- ✅ Can share same database as VMaster
- ✅ Built-in support in FreeRADIUS

**Implementation:**

1. **Setup RADIUS with MySQL backend**
2. **VMaster connects to RADIUS database**
3. **Automatic user management:**

```
Customer creates VPN account:
  ↓
VMaster generates username/password
  ↓
VMaster inserts into RADIUS database:
  INSERT INTO radcheck (username, attribute, op, value)
  VALUES ('vpn_user001', 'Cleartext-Password', ':=', 'password123')
  ↓
User can connect immediately via SSTP!
  ↓
Customer suspends client:
  ↓
VMaster adds reject rule:
  INSERT INTO radcheck (username, attribute, op, value)
  VALUES ('vpn_user001', 'Auth-Type', ':=', 'Reject')
  ↓
User cannot connect anymore!
  ↓
Customer deletes client:
  ↓
VMaster deletes from RADIUS:
  DELETE FROM radcheck WHERE username = 'vpn_user001'
  ↓
User removed completely!
```

---

## 📊 Comparison: Pool vs RADIUS

### Current Pool Method:
```
✅ Simple to implement
❌ Manual SoftEther user creation
❌ Manual deletion needed
❌ Not real-time
❌ Admin must manage server
⚠️  Credentials recycled (security concern)
```

### RADIUS Method:
```
✅ Fully automated
✅ Real-time user creation
✅ Automatic deletion
✅ Real-time suspension
✅ No server management needed
✅ Unique credentials per user
✅ Centralized authentication
✅ Works with multiple VPN servers
✅ Industry standard
```

---

## 🚀 Migration Path

### Phase 1: Setup RADIUS
1. Install FreeRADIUS with MySQL backend
2. Configure SoftEther to use RADIUS
3. Test with manual users

### Phase 2: VMaster Integration
1. Add RADIUS database connection to VMaster
2. Create RADIUS user management functions
3. Update SSTP account creation flow
4. Test automatic user creation

### Phase 3: Full Automation
1. Automatic user creation when customer creates VPN
2. Automatic suspension when client suspended
3. Automatic deletion when client deleted
4. Migrate existing pool users to RADIUS

### Phase 4: Advanced Features
1. User session tracking
2. Bandwidth limiting per user
3. Connection time limits
4. Multiple VPN server support
5. User activity logs

---

## 💡 Benefits Summary

### For Admin:
- ✅ **Zero manual work** - Everything automated
- ✅ **Real-time control** - Instant user management
- ✅ **Centralized** - One RADIUS for all VPN servers
- ✅ **Scalable** - Handle thousands of users
- ✅ **Secure** - Industry-standard authentication

### For Customers:
- ✅ **Instant activation** - No waiting for admin
- ✅ **Unique credentials** - Not recycled
- ✅ **Immediate suspension** - When needed
- ✅ **Reliable** - Professional setup

---

## 📋 Quick Start Commands

**Test RADIUS authentication:**
```bash
# Install test tools
sudo apt install freeradius-utils

# Test user
radtest username password radius-server 0 shared-secret

# Should see: Access-Accept
```

**Check RADIUS logs:**
```bash
sudo tail -f /var/log/freeradius/radius.log
```

**Add user manually (for testing):**
```sql
USE radius;
INSERT INTO radcheck (username, attribute, op, value) 
VALUES ('testuser', 'Cleartext-Password', ':=', 'testpass123');
```

**Test from SoftEther:**
```bash
# Connect SSTP client
# Username: testuser
# Password: testpass123
# Should authenticate via RADIUS!
```

---

## 🎯 Next Steps

**Want me to implement RADIUS integration in VMaster?**

I can:
1. ✅ Add RADIUS database configuration
2. ✅ Create RADIUS user management functions
3. ✅ Update SSTP account creation to use RADIUS
4. ✅ Add automatic user deletion on client suspend
5. ✅ Migrate from pool system to RADIUS
6. ✅ Add admin panel for RADIUS management

**This would make SSTP management as automatic as Outline!** 🚀

Let me know if you want me to implement this!

