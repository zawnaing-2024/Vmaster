# 🚀 V2Ray VPN Server with RADIUS Authentication Setup Guide

## Overview

This guide will help you set up a V2Ray VPN server that supports:
- ✅ VMess protocol with UUID authentication (standard V2Ray)
- ✅ RADIUS authentication integration (optional, for centralized management)
- ✅ Full integration with VMaster portal
- ✅ Automatic user management

---

## 📋 Table of Contents

1. [Server Requirements](#server-requirements)
2. [Install V2Ray Server](#install-v2ray-server)
3. [Configure V2Ray](#configure-v2ray)
4. [RADIUS Integration (Optional but Recommended)](#radius-integration)
5. [Connect to VMaster Portal](#connect-to-vmaster-portal)
6. [Testing & Verification](#testing--verification)
7. [Client Setup](#client-setup)
8. [Troubleshooting](#troubleshooting)

---

## 🖥️ Server Requirements

### Minimum Specifications:
- **OS:** Ubuntu 20.04/22.04 LTS (Recommended) or Debian 11+
- **RAM:** 512 MB minimum, 1 GB recommended
- **CPU:** 1 core minimum
- **Storage:** 10 GB minimum
- **Network:** Public IP address
- **Ports:** 10086 (or your chosen port) - TCP/UDP

### What You'll Need:
- Root or sudo access
- Domain name (optional but recommended)
- SSL certificate (optional, for TLS)

---

## 📥 Part 1: Install V2Ray Server

### Method A: Official Installation Script (Recommended)

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install required packages
sudo apt install -y curl wget unzip

# Download and install V2Ray
bash <(curl -L https://raw.githubusercontent.com/v2fly/fhs-install-v2ray/master/install-release.sh)
```

**Expected output:**
```
info: V2Ray v5.x.x is installed.
```

### Method B: Docker Installation (Alternative)

```bash
# Install Docker (if not already installed)
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# Run V2Ray in Docker
docker run -d \
  --name v2ray \
  --restart always \
  -p 10086:10086 \
  -v /etc/v2ray:/etc/v2ray \
  v2fly/v2fly-core:latest \
  run -c /etc/v2ray/config.json
```

---

## ⚙️ Part 2: Configure V2Ray

### Step 1: Generate Server UUID

```bash
# Install uuidgen (if not available)
sudo apt install uuid-runtime -y

# Generate a UUID for your server
uuid=$(uuidgen)
echo "Your V2Ray Server UUID: $uuid"
```

**Save this UUID - you'll need it!**

### Step 2: Create V2Ray Configuration

```bash
# Create config directory (if using direct install)
sudo mkdir -p /etc/v2ray
sudo nano /etc/v2ray/config.json
```

**Paste this configuration:**

```json
{
  "log": {
    "access": "/var/log/v2ray/access.log",
    "error": "/var/log/v2ray/error.log",
    "loglevel": "warning"
  },
  "inbounds": [
    {
      "port": 10086,
      "protocol": "vmess",
      "settings": {
        "clients": []
      },
      "streamSettings": {
        "network": "tcp",
        "security": "none"
      }
    }
  ],
  "outbounds": [
    {
      "protocol": "freedom",
      "settings": {}
    }
  ]
}
```

**Note:** The `clients` array is empty because VMaster will manage users via RADIUS.

### Step 3: Create Log Directory

```bash
sudo mkdir -p /var/log/v2ray
sudo chown nobody:nogroup /var/log/v2ray
```

### Step 4: Start V2Ray Service

```bash
# Enable and start V2Ray
sudo systemctl enable v2ray
sudo systemctl start v2ray

# Check status
sudo systemctl status v2ray
```

**Expected output:**
```
● v2ray.service - V2Ray Service
   Loaded: loaded
   Active: active (running)
```

---

## 🔐 Part 3: RADIUS Integration (Recommended for VMaster)

### Why Use RADIUS with V2Ray?

| Feature | V2Ray Alone | V2Ray + RADIUS |
|---------|-------------|----------------|
| User Management | Manual config edits | ✅ Automated via VMaster |
| Add/Remove Users | Restart required | ✅ Real-time via RADIUS |
| Centralized Control | ❌ No | ✅ Yes |
| Suspend Users | ❌ Delete & restart | ✅ Instant via RADIUS |
| Multi-Server Management | ❌ Hard | ✅ Easy |

### Step 1: Install FreeRADIUS

```bash
# Install FreeRADIUS and MySQL client
sudo apt update
sudo apt install -y freeradius freeradius-mysql mysql-client

# Stop FreeRADIUS for configuration
sudo systemctl stop freeradius
```

### Step 2: Configure RADIUS to Connect to VMaster RADIUS DB

```bash
# Edit SQL configuration
sudo nano /etc/freeradius/3.0/mods-available/sql
```

**Update these values:**

```conf
sql {
    driver = "rlm_sql_mysql"
    dialect = "mysql"
    
    # Connection to VMaster RADIUS database
    server = "YOUR_VMASTER_SERVER_IP"
    port = 3307
    login = "radius"
    password = "radiuspass"
    radius_db = "radius"
    
    # Read clients from database
    read_clients = yes
    client_table = "nas"
}
```

**Replace `YOUR_VMASTER_SERVER_IP` with your VMaster server's IP address.**

### Step 3: Enable SQL Module

```bash
# Enable SQL module
sudo ln -s /etc/freeradius/3.0/mods-available/sql /etc/freeradius/3.0/mods-enabled/

# Test configuration
sudo freeradius -X
```

Press `Ctrl+C` to stop test mode if successful.

### Step 4: Add V2Ray Server to RADIUS

On your **VMaster server**, run:

```bash
docker exec vmaster_radius_db mysql -uradius -pradiuspass radius << EOF
INSERT INTO nas (nasname, shortname, type, ports, secret, server, community, description)
VALUES (
  'YOUR_V2RAY_SERVER_IP',
  'v2ray-server-1',
  'other',
  1812,
  'testing123',
  '',
  '',
  'V2Ray VPN Server 1'
);
EOF
```

**Replace `YOUR_V2RAY_SERVER_IP` with your V2Ray server's IP.**

### Step 5: Install V2Ray RADIUS Plugin

Unfortunately, **native V2Ray doesn't support RADIUS authentication directly**. However, we have **two options**:

#### Option A: Use SoftEther VPN (Supports Both V2Ray Protocol + RADIUS)

SoftEther VPN supports:
- ✅ RADIUS authentication
- ✅ SSTP protocol
- ✅ L2TP/IPsec
- ✅ OpenVPN
- ✅ Can work alongside V2Ray

**Install SoftEther:**

```bash
# Download SoftEther
cd /tmp
wget https://github.com/SoftEtherVPN/SoftEtherVPN_Stable/releases/download/v4.41-9782-beta/softether-vpnserver-v4.41-9782-beta-2022.11.17-linux-x64-64bit.tar.gz

# Extract and install
tar xzf softether-vpnserver-*.tar.gz
cd vpnserver
make
sudo mv /tmp/vpnserver /opt/

# Create systemd service
sudo nano /etc/systemd/system/softether-vpnserver.service
```

**Add:**

```ini
[Unit]
Description=SoftEther VPN Server
After=network.target

[Service]
Type=forking
ExecStart=/opt/vpnserver/vpnserver start
ExecStop=/opt/vpnserver/vpnserver stop

[Install]
WantedBy=multi-user.target
```

**Start service:**

```bash
sudo systemctl enable softether-vpnserver
sudo systemctl start softether-vpnserver
```

**Configure RADIUS in SoftEther:**

```bash
# Connect to SoftEther management
/opt/vpnserver/vpncmd

# Select 1 (Management of VPN Server)
# Press Enter for localhost
# Press Enter for default port

# Inside vpncmd:
ServerPasswordSet
# Set a password

# Enable RADIUS
RadiusServerSet YOUR_VMASTER_IP 1812 testing123

# Exit
exit
```

#### Option B: Use V2Ray with External Auth Script (Advanced)

Create a custom authentication script that checks RADIUS before allowing connections.

---

## 🎯 Part 4: Connect V2Ray Server to VMaster Portal

### Step 1: Add V2Ray Server in VMaster Admin Panel

1. Login to VMaster admin panel: `https://vmaster.vip/admin`
2. Go to **VPN Servers**
3. Click **Add Server**
4. Fill in details:

```
Server Name: V2Ray Server 1
Server Type: V2Ray
Server Host: your_v2ray_server_ip (or domain)
Server Port: 10086
Max Accounts: 100
Status: Active
```

5. Click **Save**

### Step 2: Test Connection from VMaster

On your **VMaster server**:

```bash
# Test if V2Ray port is accessible
telnet YOUR_V2RAY_SERVER_IP 10086
```

Should connect successfully.

### Step 3: Create Test Account

1. Login as **customer** in VMaster portal
2. Go to **VPN Accounts**
3. Create a client first
4. Create V2Ray VPN account for that client
5. Note the **VMess link** provided

---

## 🧪 Part 5: Testing & Verification

### Test 1: Check V2Ray is Running

```bash
# On V2Ray server
sudo systemctl status v2ray
sudo netstat -tulpn | grep 10086
```

**Expected:**
```
tcp    0    0 0.0.0.0:10086    0.0.0.0:*    LISTEN    1234/v2ray
```

### Test 2: Check RADIUS Users

On **VMaster server**:

```bash
# List V2Ray RADIUS users
docker exec vmaster_radius_db mysql -uradius -pradiuspass radius -e "SELECT username, attribute, value FROM radcheck WHERE username LIKE 'v2ray_%'"
```

**Expected:**
```
username            attribute              value
v2ray_abc123xyz    Cleartext-Password     SecurePassword123!
```

### Test 3: Check Logs

```bash
# V2Ray logs
sudo tail -f /var/log/v2ray/access.log

# RADIUS logs (if using RADIUS)
sudo tail -f /var/log/freeradius/radius.log
```

---

## 📱 Part 6: Client Setup

### For Android (V2RayNG)

1. **Download V2RayNG** from Google Play Store
2. Click **+** button → **Import config from clipboard**
3. Paste the **VMess link** from VMaster portal
4. Click **Connect**

### For iOS (Shadowrocket)

1. **Download Shadowrocket** from App Store
2. Click **+** → **Type** → **VMess**
3. Paste the **VMess link**
4. Click **Connect**

### For Windows (V2RayN)

1. **Download V2RayN** from [GitHub](https://github.com/2dust/v2rayN/releases)
2. Extract and run `v2rayN.exe`
3. Click **Servers** → **Import bulk URL from clipboard**
4. Paste **VMess link**
5. Right-click server → **Set as active server**

### For Linux (V2Ray Core)

```bash
# Install V2Ray
bash <(curl -L https://raw.githubusercontent.com/v2fly/fhs-install-v2ray/master/install-release.sh)

# Create config from VMess link using online converter
# Or manually create /etc/v2ray/config.json

# Start V2Ray
sudo systemctl start v2ray
```

---

## 🔧 Part 7: Troubleshooting

### Issue 1: Can't Connect to V2Ray Server

**Check:**

```bash
# Firewall
sudo ufw allow 10086/tcp
sudo ufw allow 10086/udp

# Check if V2Ray is listening
sudo netstat -tulpn | grep 10086

# Check logs
sudo journalctl -u v2ray -f
```

### Issue 2: RADIUS Authentication Failing

**Check:**

```bash
# Test RADIUS connection
sudo freeradius -X

# Check RADIUS logs
sudo tail -f /var/log/freeradius/radius.log

# Verify user exists
# On VMaster server:
docker exec vmaster_radius_db mysql -uradius -pradiuspass radius -e "SELECT * FROM radcheck WHERE username='v2ray_abc123xyz'"
```

### Issue 3: High CPU Usage

**Optimize V2Ray config:**

```json
{
  "policy": {
    "levels": {
      "0": {
        "handshake": 4,
        "connIdle": 300,
        "uplinkOnly": 2,
        "downlinkOnly": 5
      }
    }
  }
}
```

Add this to your V2Ray config.

---

## 📊 Part 8: Monitoring & Maintenance

### Check Active Connections

```bash
# Count connections
sudo ss -tunap | grep 10086 | wc -l

# Show active connections
sudo ss -tunap | grep 10086
```

### Monitor Bandwidth

```bash
# Install vnstat
sudo apt install vnstat -y

# Check bandwidth
vnstat -l

# Daily stats
vnstat -d
```

### Backup Configuration

```bash
# Backup V2Ray config
sudo cp /etc/v2ray/config.json /root/v2ray-config-backup-$(date +%Y%m%d).json

# Backup RADIUS config (if using)
sudo tar czf /root/freeradius-backup-$(date +%Y%m%d).tar.gz /etc/freeradius/
```

---

## 🎯 Quick Reference

### V2Ray Commands

```bash
# Start/Stop/Restart
sudo systemctl start v2ray
sudo systemctl stop v2ray
sudo systemctl restart v2ray

# Check status
sudo systemctl status v2ray

# View logs
sudo tail -f /var/log/v2ray/access.log
sudo tail -f /var/log/v2ray/error.log

# Test config
v2ray test -config /etc/v2ray/config.json
```

### RADIUS Commands

```bash
# Start/Stop FreeRADIUS
sudo systemctl start freeradius
sudo systemctl stop freeradius

# Test mode (debug)
sudo freeradius -X

# Test user authentication
radtest v2ray_abc123xyz SecurePassword123! localhost 1812 testing123
```

### VMaster Integration Commands

```bash
# Check RADIUS users from VMaster
docker exec vmaster_radius_db mysql -uradius -pradiuspass radius -e "SELECT username FROM radcheck WHERE username LIKE 'v2ray_%'"

# Count V2Ray users
docker exec vmaster_radius_db mysql -uradius -pradiuspass radius -e "SELECT COUNT(*) as total FROM radcheck WHERE username LIKE 'v2ray_%'"

# Delete test user
docker exec vmaster_radius_db mysql -uradius -pradiuspass radius -e "DELETE FROM radcheck WHERE username='v2ray_test'"
```

---

## 🌟 Best Practices

### Security

1. ✅ **Use TLS/SSL** for V2Ray connections
2. ✅ **Change default RADIUS secret** from `testing123`
3. ✅ **Use strong passwords** for RADIUS users
4. ✅ **Enable firewall** and allow only necessary ports
5. ✅ **Regular updates**: `sudo apt update && sudo apt upgrade -y`

### Performance

1. ✅ **Use BBR** for better TCP performance:
   ```bash
   echo "net.core.default_qdisc=fq" | sudo tee -a /etc/sysctl.conf
   echo "net.ipv4.tcp_congestion_control=bbr" | sudo tee -a /etc/sysctl.conf
   sudo sysctl -p
   ```

2. ✅ **Optimize V2Ray config** for your use case
3. ✅ **Monitor server resources** regularly

### Maintenance

1. ✅ **Backup configs** weekly
2. ✅ **Monitor logs** for errors
3. ✅ **Update V2Ray** monthly
4. ✅ **Clean old logs**: `sudo journalctl --vacuum-time=7d`

---

## 📞 Support & Resources

### Official Documentation
- **V2Ray:** https://www.v2fly.org/
- **FreeRADIUS:** https://freeradius.org/documentation/
- **VMaster:** Check your admin panel

### Community
- V2Ray Telegram: https://t.me/v2fly_community
- V2Ray GitHub: https://github.com/v2fly/v2ray-core

---

## ✅ Final Checklist

Before going to production:

- [ ] V2Ray server installed and running
- [ ] Firewall configured (port 10086 open)
- [ ] V2Ray config created and tested
- [ ] RADIUS installed and connected to VMaster (optional)
- [ ] Server added to VMaster admin panel
- [ ] Test account created and verified
- [ ] Client connection tested successfully
- [ ] Monitoring set up
- [ ] Backup script configured
- [ ] SSL/TLS enabled (recommended)

---

## 🎉 Congratulations!

Your V2Ray server is now integrated with VMaster portal!

**What's automated:**
- ✅ User creation (via VMaster portal)
- ✅ RADIUS authentication (if enabled)
- ✅ User suspension/deletion
- ✅ Centralized management

**Enjoy your fully automated VPN management system! 🚀**

