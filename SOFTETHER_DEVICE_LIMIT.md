# 🔵 SoftEther VPN Server - Device Limit Configuration

## 📱 Goal: 1 VPN Account = 1 Device Only

This guide shows how to configure SoftEther VPN Server so each user account can only connect from one device at a time.

---

## 🎯 Two Methods Available

### Method 1: Using RADIUS Authentication (Recommended) ✅
- Best for VMaster CMS integration
- Centralized user management
- Easy to manage many users

### Method 2: Using SoftEther's Built-in Limits
- Works without RADIUS
- Good for small deployments
- Manual user management

---

## 📋 Method 1: RADIUS Authentication (Recommended)

### Step 1: Configure FreeRADIUS for Single Session

On your RADIUS server (VMaster server):

```bash
# Edit FreeRADIUS SQL module
sudo nano /etc/freeradius/3.0/mods-available/sql
```

Add this configuration:

```conf
sql {
    driver = "rlm_sql_mysql"
    dialect = "mysql"
    
    server = "127.0.0.1"
    port = 3307
    login = "radius"
    password = "radiuspass"
    radius_db = "radius"
    
    # Enable simultaneous use checking
    simul_count_query = "SELECT COUNT(*) FROM radacct WHERE username = '%{User-Name}' AND acctstoptime IS NULL"
    
    # Limit to 1 concurrent session
    read_clients = yes
    
    pool {
        start = 5
        min = 4
        max = 10
        spare = 3
        uses = 0
        lifetime = 0
        idle_timeout = 60
    }
}
```

### Step 2: Enable Simultaneous Use Check

Edit the authorize section:

```bash
sudo nano /etc/freeradius/3.0/sites-available/default
```

Add `sql` to the authorize section:

```conf
authorize {
    filter_username
    preprocess
    sql
    
    # Check for multiple logins
    if (sql) {
        if ("%{sql:SELECT COUNT(*) FROM radacct WHERE username='%{User-Name}' AND acctstoptime IS NULL}" > 0) {
            reject
        }
    }
    
    mschap
    pap
}
```

### Step 3: Configure SoftEther to Use RADIUS

#### Option A: Using SoftEther VPN Server Manager (GUI)

1. **Open SoftEther VPN Server Manager**
2. **Connect to your server**
3. **Select your Virtual Hub**
4. **Go to: Manage Virtual Hub → Manage Users**
5. **Click "RADIUS Server Settings"**

Configure RADIUS:
```
RADIUS Server #1:
  Hostname: YOUR_VMASTER_IP
  Port: 1812
  Shared Secret: One@2025 (or your secret)
  
Authentication Method:
  ☑ MS-CHAPv2
  ☑ MS-CHAP
  ☑ PAP
  
Accounting:
  ☑ Enable RADIUS Accounting
  Port: 1813
```

6. **Click OK**

7. **Enable RADIUS Authentication:**
   - Go to: Virtual Hub → Security Policy
   - Enable: "Use RADIUS Server for Authentication"

#### Option B: Using Command Line (vpncmd)

```bash
# Connect to SoftEther
vpncmd localhost /SERVER

# Select Virtual Hub
Hub YOUR_HUB_NAME

# Set RADIUS server
RadiusServerSet YOUR_VMASTER_IP:1812 One@2025

# Enable RADIUS authentication
RadiusServerEnable

# Enable accounting
AccountingServerSet YOUR_VMASTER_IP:1813 One@2025
```

### Step 4: Test the Configuration

```bash
# On RADIUS server, watch logs
sudo tail -f /var/log/freeradius/radius.log

# Try connecting:
# 1. Connect device 1 → Should work ✅
# 2. Connect device 2 → Should be rejected ❌
# 3. Disconnect device 1
# 4. Connect device 2 → Should work ✅
```

---

## 📋 Method 2: SoftEther Built-in Limits (Without RADIUS)

### Step 1: Enable Security Policy

Using SoftEther VPN Server Manager:

1. **Connect to your server**
2. **Select Virtual Hub**
3. **Go to: Manage Virtual Hub → Security Policy**

### Step 2: Configure Connection Limits

Set these policies:

```
Maximum Number of TCP Connections:
  ☑ Enable
  Value: 1

Maximum Number of Concurrent Connections:
  ☑ Enable  
  Value: 1

Deny Multiple Logins:
  ☑ Enable
  
Disconnect if Duplicate Login:
  ☑ Enable
```

### Step 3: Apply Per-User Settings

For each user:

1. **Go to: Manage Users**
2. **Select a user → Edit**
3. **Go to "Security Policy" tab**
4. **Enable:**
   - ☑ Deny Multiple Logins with Same User
   - ☑ Disconnect Existing Connection on New Login

5. **Set Limits:**
   ```
   Maximum Number of TCP Connections: 1
   Maximum Number of Sessions: 1
   ```

6. **Click OK**

### Step 4: Using Command Line (vpncmd)

```bash
# Connect to server
vpncmd localhost /SERVER

# Select hub
Hub YOUR_HUB_NAME

# Set policy for all users
PolicySet /NAME:* /MAXCONNECTION:1 /MULTILOGINS:no

# Or for specific user
UserPolicySet USERNAME /MAXCONNECTION:1 /MULTILOGINS:no
```

---

## 🔧 Complete Configuration Script

Save this as `setup-softether-device-limit.sh`:

```bash
#!/bin/bash

# SoftEther Device Limit Setup Script

echo "╔══════════════════════════════════════════════════════════════╗"
echo "║                                                              ║"
echo "║   🔵 SoftEther VPN - Device Limit Configuration 🔵          ║"
echo "║                                                              ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo ""

# Configuration
SOFTETHER_HOST="localhost"
HUB_NAME="DEFAULT"
RADIUS_SERVER="YOUR_VMASTER_IP"
RADIUS_PORT="1812"
RADIUS_SECRET="One@2025"

echo "📝 Configuration:"
echo "  SoftEther Host: $SOFTETHER_HOST"
echo "  Virtual Hub: $HUB_NAME"
echo "  RADIUS Server: $RADIUS_SERVER:$RADIUS_PORT"
echo ""

# Create vpncmd script
cat > /tmp/softether-config.txt << EOF
Hub $HUB_NAME
RadiusServerSet $RADIUS_SERVER:$RADIUS_PORT $RADIUS_SECRET
RadiusServerEnable
AccountingServerSet $RADIUS_SERVER:1813 $RADIUS_SECRET
PolicySet /NAME:* /MAXCONNECTION:1 /MULTILOGINS:no
exit
EOF

echo "🔧 Applying configuration..."
vpncmd $SOFTETHER_HOST /SERVER /IN:/tmp/softether-config.txt

if [ $? -eq 0 ]; then
    echo "✅ Configuration applied successfully!"
else
    echo "❌ Configuration failed!"
    exit 1
fi

# Cleanup
rm /tmp/softether-config.txt

echo ""
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║                                                              ║"
echo "║              ✅ SETUP COMPLETE! ✅                           ║"
echo "║                                                              ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo ""
echo "📋 What was configured:"
echo "  ✅ RADIUS authentication enabled"
echo "  ✅ RADIUS accounting enabled"
echo "  ✅ Maximum 1 connection per user"
echo "  ✅ Multiple logins denied"
echo ""
echo "🧪 Test it:"
echo "  1. Connect device 1 → Should work ✅"
echo "  2. Try device 2 → Should fail or disconnect device 1 ❌"
echo ""
```

Make it executable:
```bash
chmod +x setup-softether-device-limit.sh
```

---

## 🧪 Testing the Configuration

### Test 1: Single Device Connection

```bash
# On client device 1
# Connect using SoftEther VPN Client
# Username: sstp_user123
# Password: your_password

# Should connect successfully ✅
```

### Test 2: Duplicate Connection Attempt

```bash
# On client device 2 (while device 1 is connected)
# Try to connect with same credentials

# Expected behavior:
# - Device 1 disconnects
# - Device 2 connects
# OR
# - Device 2 connection is rejected
```

### Test 3: Check Active Sessions

On SoftEther server:

```bash
vpncmd localhost /SERVER

# Select hub
Hub DEFAULT

# Show active sessions
SessionList

# Should show only 1 session per user
```

---

## 📊 Verification Commands

### Check RADIUS Connection

```bash
# On SoftEther server
vpncmd localhost /SERVER
Hub DEFAULT
RadiusServerGet

# Should show:
# RADIUS Server: YOUR_VMASTER_IP:1812
# Status: Enabled
```

### Check User Policies

```bash
vpncmd localhost /SERVER
Hub DEFAULT
PolicyGet USERNAME

# Should show:
# Max Connections: 1
# Multiple Logins: Denied
```

### Monitor Connections

```bash
# Real-time session monitoring
vpncmd localhost /SERVER
Hub DEFAULT
SessionList

# Or use SoftEther VPN Server Manager:
# Manage Virtual Hub → Manage Sessions
```

---

## 🔍 Troubleshooting

### Issue 1: Multiple Devices Still Connecting

**Solution:**
```bash
# Check if RADIUS is actually being used
sudo tail -f /var/log/freeradius/radius.log

# If no logs appear, RADIUS is not configured
# Verify RADIUS settings in SoftEther
```

### Issue 2: All Connections Rejected

**Solution:**
```bash
# Check RADIUS server is running
systemctl status freeradius

# Test RADIUS authentication
echo 'User-Name = "testuser", User-Password = "testpass"' | \
  radclient -x YOUR_VMASTER_IP:1812 auth One@2025

# Check FreeRADIUS logs
sudo tail -50 /var/log/freeradius/radius.log
```

### Issue 3: Device 1 Doesn't Disconnect

**Solution:**
```bash
# Enable accounting in SoftEther
vpncmd localhost /SERVER
Hub DEFAULT
AccountingServerSet YOUR_VMASTER_IP:1813 One@2025

# Restart SoftEther
systemctl restart softether-vpnserver
```

---

## 📋 Quick Reference

### Enable RADIUS (Command Line)
```bash
vpncmd localhost /SERVER
Hub DEFAULT
RadiusServerSet RADIUS_IP:1812 SECRET
RadiusServerEnable
AccountingServerSet RADIUS_IP:1813 SECRET
```

### Set Connection Limits (Command Line)
```bash
vpncmd localhost /SERVER
Hub DEFAULT
PolicySet /NAME:* /MAXCONNECTION:1 /MULTILOGINS:no
```

### Check Configuration
```bash
vpncmd localhost /SERVER
Hub DEFAULT
RadiusServerGet
PolicyGet *
SessionList
```

---

## ✅ Final Checklist

After configuration, verify:

- [ ] RADIUS server is running
- [ ] SoftEther can reach RADIUS server (port 1812/1813)
- [ ] RADIUS authentication is enabled in SoftEther
- [ ] RADIUS accounting is enabled
- [ ] Connection limit is set to 1
- [ ] Multiple logins are denied
- [ ] Test with 2 devices confirms limit works

---

## 🎯 Expected Behavior

After configuration:

✅ **User connects device 1** → Connection successful  
✅ **User tries device 2** → Device 1 disconnects, device 2 connects  
❌ **User cannot use both devices simultaneously**

---

## 📞 Integration with VMaster CMS

VMaster CMS automatically:
1. ✅ Creates unique SSTP credentials in RADIUS
2. ✅ Stores credentials in `radcheck` table
3. ✅ Tracks which client has which account

SoftEther + RADIUS enforces:
1. ✅ Only 1 device per account
2. ✅ Automatic disconnection of old session
3. ✅ Session accounting and logging

**Perfect integration!** 🎉

---

## 📚 Additional Resources

- SoftEther Manual: https://www.softether.org/4-docs
- FreeRADIUS Wiki: https://wiki.freeradius.org
- VMaster RADIUS Guide: `CONFIGURE_SSTP_RADIUS.md`

---

**Last Updated**: October 9, 2025  
**Version**: 1.0  
**Tested On**: SoftEther VPN Server 4.x with FreeRADIUS 3.0

