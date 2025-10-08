# 🔐 Configure SSTP Server with RADIUS Authentication

This guide shows you how to configure your SSTP server to authenticate users via the RADIUS server.

---

## 📋 **Prerequisites**

Before starting, you need:

1. ✅ FreeRADIUS server installed on VMaster server
2. ✅ RADIUS server IP and shared secret
3. ✅ SSTP server (accel-ppp) installed and running
4. ✅ SSH access to SSTP server

---

## 🚀 **Step 1: Install FreeRADIUS on VMaster Server**

SSH to your VMaster server and run:

```bash
cd /var/www/vmaster
git pull origin main
chmod +x scripts/install-freeradius.sh
sudo bash scripts/install-freeradius.sh
```

**This will:**
- ✅ Install FreeRADIUS
- ✅ Connect to RADIUS MySQL database
- ✅ Add your SSTP server as authorized client
- ✅ Open firewall ports (1812/1813 UDP)
- ✅ Provide configuration details

**You'll need to provide:**
- SSTP Server IP address
- RADIUS Shared Secret (default: `testing123`)

**Time:** ~5 minutes

---

## 🔧 **Step 2: Configure SSTP Server (accel-ppp)**

### **2.1: Edit accel-ppp configuration**

SSH to your SSTP server:

```bash
sudo nano /etc/accel-ppp.conf
```

### **2.2: Add RADIUS module**

Find the `[modules]` section and add:

```ini
[modules]
log_file
radius          # <-- Add this line
pptp
sstp
ippool
```

### **2.3: Configure RADIUS settings**

Add this section (replace with your values):

```ini
[radius]
# RADIUS server configuration
# Format: server=IP,SECRET,auth-port=1812,acct-port=1813
server=YOUR_VMASTER_SERVER_IP,testing123,auth-port=1812,acct-port=1813,req-limit=50,fail-time=0

# NAS identification
nas-identifier=sstp-server
nas-ip-address=YOUR_SSTP_SERVER_IP

# Timeouts
acct-timeout=120
timeout=15
max-try=2

# Accounting
acct-on=1
acct-interim-interval=60
```

**Replace:**
- `YOUR_VMASTER_SERVER_IP` → Your VMaster public IP
- `YOUR_SSTP_SERVER_IP` → Your SSTP server IP
- `testing123` → Your RADIUS shared secret (if you changed it)

### **2.4: Disable local authentication (optional)**

If you want to ONLY use RADIUS (recommended):

Find the `[chap-secrets]` section and comment it out:

```ini
[modules]
log_file
radius
#chap-secrets    # <-- Comment this out to disable local auth
pptp
sstp
ippool
```

### **2.5: Save and close**

Press `Ctrl+X`, then `Y`, then `Enter`

---

## 🔄 **Step 3: Restart SSTP Service**

```bash
sudo systemctl restart accel-ppp
```

Check status:

```bash
sudo systemctl status accel-ppp
```

Should show: `Active: active (running)` ✅

---

## ✅ **Step 4: Test RADIUS Authentication**

### **4.1: Create test user in VMaster**

Login to VMaster → Customer → VPN Accounts → Create Account → SSTP

Example:
- Username: `testuser`
- Password: `TestPass123!`

### **4.2: Test from SSTP server**

```bash
radtest testuser TestPass123! YOUR_VMASTER_IP 1812 testing123
```

**Expected output:**
```
Received Access-Accept
```

✅ = RADIUS is working!

### **4.3: Test actual VPN connection**

1. Configure SSTP client on your computer
2. Use credentials: `testuser` / `TestPass123!`
3. Connect

If successful, you'll see in RADIUS logs:

```bash
# On VMaster server
sudo journalctl -u freeradius -f
```

You should see:
```
Login OK: [testuser] (from client sstp-server port 0)
```

---

## 🔍 **Troubleshooting**

### **Issue: RADIUS server not reachable**

**Test network connectivity:**
```bash
# On SSTP server
telnet YOUR_VMASTER_IP 1812
```

If fails:
- ✅ Check firewall on VMaster: `sudo ufw status`
- ✅ Ensure ports 1812/1813 UDP are open
- ✅ Check if FreeRADIUS is running: `sudo systemctl status freeradius`

### **Issue: Access-Reject received**

**Causes:**
1. Wrong username/password
2. User suspended in VMaster
3. Wrong shared secret

**Check user in database:**
```bash
docker exec vmaster_radius_db mysql -uroot -prootpassword radius \
  -e "SELECT * FROM radcheck WHERE username='testuser';"
```

### **Issue: Shared secret mismatch**

**Error in logs:**
```
WARNING: Unprintable characters in the password. Double-check the shared secret
```

**Fix:**
1. Check shared secret in VMaster: `/var/www/vmaster/config/radius.php`
2. Update SSTP accel-ppp.conf with correct secret
3. Restart both services

### **Issue: SSTP connects but no internet**

**Check IP pool configuration:**
```bash
sudo nano /etc/accel-ppp.conf
```

Ensure IP pool is configured:
```ini
[ip-pool]
gw-ip-address=10.0.0.1
10.0.0.2-254
```

And DNS:
```ini
[dns]
dns1=8.8.8.8
dns2=8.8.4.4
```

### **Issue: FreeRADIUS not starting**

**Check configuration:**
```bash
sudo freeradius -XC
```

**Check logs:**
```bash
sudo journalctl -u freeradius -n 100
```

**Common issues:**
- Port 1812/1813 already in use
- MySQL connection failed
- Syntax error in config

---

## 📊 **Monitoring**

### **View active VPN sessions**

```bash
docker exec vmaster_radius_db mysql -uroot -prootpassword radius -e "
SELECT username, nasipaddress, acctstarttime, 
       TIMESTAMPDIFF(MINUTE, acctstarttime, NOW()) as minutes_connected
FROM radacct 
WHERE acctstoptime IS NULL;"
```

### **View RADIUS authentication logs**

```bash
sudo journalctl -u freeradius -f
```

### **View SSTP connections**

```bash
sudo accel-cmd show sessions
```

---

## 🔐 **Security Best Practices**

### **1. Change default RADIUS secret**

Edit on VMaster:
```bash
sudo nano /var/www/vmaster/config/radius.php
```

Change:
```php
define('RADIUS_SHARED_SECRET', 'your-strong-secret-here');
```

Then update in FreeRADIUS:
```bash
docker exec vmaster_radius_db mysql -uroot -prootpassword radius -e "
UPDATE nas SET secret='your-strong-secret-here' WHERE nasname='SSTP_SERVER_IP';"
```

Restart FreeRADIUS:
```bash
sudo systemctl restart freeradius
```

Update on SSTP server (`/etc/accel-ppp.conf`):
```ini
server=VMASTER_IP,your-strong-secret-here,auth-port=1812,acct-port=1813
```

Restart SSTP:
```bash
sudo systemctl restart accel-ppp
```

### **2. Restrict RADIUS access**

Only allow your SSTP servers:

```bash
# On VMaster server
sudo ufw delete allow 1812/udp
sudo ufw delete allow 1813/udp
sudo ufw allow from SSTP_SERVER_IP to any port 1812 proto udp
sudo ufw allow from SSTP_SERVER_IP to any port 1813 proto udp
```

### **3. Enable RADIUS accounting**

Already configured in the script! Monitor user sessions in `radacct` table.

---

## 📝 **Quick Reference**

### **RADIUS Server (VMaster)**

| Setting | Value |
|---------|-------|
| IP | Your VMaster public IP |
| Auth Port | 1812 (UDP) |
| Acct Port | 1813 (UDP) |
| Secret | `testing123` (change in production!) |
| Database | `radius` on port 3307 |

### **SSTP Server Configuration File**

```
/etc/accel-ppp.conf
```

### **Restart Commands**

```bash
# VMaster
sudo systemctl restart freeradius

# SSTP Server
sudo systemctl restart accel-ppp
```

### **Test Command**

```bash
radtest USERNAME PASSWORD VMASTER_IP 1812 testing123
```

---

## ✅ **Success Checklist**

- [ ] FreeRADIUS installed on VMaster
- [ ] Firewall ports 1812/1813 open
- [ ] SSTP server added to RADIUS clients
- [ ] accel-ppp.conf configured with RADIUS
- [ ] Test user created in VMaster
- [ ] `radtest` returns Access-Accept
- [ ] VPN client can connect with RADIUS credentials
- [ ] User session appears in `radacct` table

---

## 🎉 **You're Done!**

Your SSTP server is now using RADIUS for authentication!

**Benefits:**
- ✅ Centralized user management
- ✅ Real-time user creation/deletion
- ✅ Session tracking and accounting
- ✅ Easy suspension/reactivation
- ✅ No need to manually edit config files

**Next:** Create VPN accounts in VMaster and they'll work automatically! 🚀
