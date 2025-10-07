# 🔧 V2Ray Client Connection Troubleshooting Guide

## Quick Diagnosis Checklist

Run through these steps in order to fix your V2Ray connection issue.

---

## 🔍 Step 1: Verify V2Ray Server is Running

**On your V2Ray server, run:**

```bash
# Check if V2Ray service is running
sudo systemctl status v2ray
```

**Expected output:**
```
● v2ray.service - V2Ray Service
   Active: active (running)
```

**If NOT running:**
```bash
# Start V2Ray
sudo systemctl start v2ray

# Check for errors
sudo journalctl -u v2ray -n 50
```

---

## 🔍 Step 2: Check if V2Ray is Listening on Port

```bash
# Check if port 10086 is listening
sudo netstat -tulpn | grep 10086
# OR
sudo ss -tulpn | grep 10086
```

**Expected output:**
```
tcp    0    0 0.0.0.0:10086    0.0.0.0:*    LISTEN    1234/v2ray
```

**If NOT listening:**
```bash
# Check V2Ray config
sudo cat /etc/v2ray/config.json

# Test config syntax
v2ray test -config /etc/v2ray/config.json
```

---

## 🔍 Step 3: Verify Firewall is Open

```bash
# Check firewall status
sudo ufw status

# If active, check if port is allowed
sudo ufw status | grep 10086
```

**If port is NOT open:**
```bash
# Allow port 10086
sudo ufw allow 10086/tcp
sudo ufw allow 10086/udp

# Reload firewall
sudo ufw reload
```

**For cloud servers (AWS/GCP/Azure/etc):**
- Also check **Security Groups** or **Firewall Rules** in your cloud console
- Make sure port 10086 TCP/UDP is allowed from 0.0.0.0/0

---

## 🔍 Step 4: Test Port from Outside

**From your local computer (or VMaster server):**

```bash
# Test if port is accessible
telnet YOUR_V2RAY_SERVER_IP 10086
# OR
nc -zv YOUR_V2RAY_SERVER_IP 10086
```

**Expected:**
```
Connected to YOUR_V2RAY_SERVER_IP
```

**If connection refused:**
- Port is blocked by firewall
- V2Ray is not listening on that port
- Wrong IP address

---

## 🔍 Step 5: Verify V2Ray Configuration

```bash
# Check V2Ray config file
sudo cat /etc/v2ray/config.json
```

**Minimum working config should look like:**

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
        "network": "tcp"
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

**Common config issues:**
- ❌ Wrong port number
- ❌ Missing `inbounds` section
- ❌ Invalid JSON syntax
- ❌ Wrong protocol (should be `vmess`)

**Fix config:**
```bash
# Edit config
sudo nano /etc/v2ray/config.json

# Save and restart
sudo systemctl restart v2ray

# Check status
sudo systemctl status v2ray
```

---

## 🔍 Step 6: Check V2Ray Logs

```bash
# View error log
sudo tail -50 /var/log/v2ray/error.log

# View access log
sudo tail -50 /var/log/v2ray/access.log

# Real-time monitoring
sudo tail -f /var/log/v2ray/error.log
```

**Common errors and fixes:**

### Error: "failed to parse config"
```bash
# Test config syntax
v2ray test -config /etc/v2ray/config.json

# Fix JSON syntax errors
sudo nano /etc/v2ray/config.json
```

### Error: "address already in use"
```bash
# Check what's using the port
sudo lsof -i :10086

# Kill the process or change V2Ray port
```

### Error: "no such file or directory"
```bash
# Create log directory
sudo mkdir -p /var/log/v2ray
sudo chown nobody:nogroup /var/log/v2ray
sudo systemctl restart v2ray
```

---

## 🔍 Step 7: Verify VMess Link from VMaster

**From VMaster portal:**

1. Login as customer
2. Go to **VPN Accounts**
3. View the V2Ray account credentials
4. Copy the **VMess link** (starts with `vmess://`)

**The VMess link should look like:**
```
vmess://eyJ2IjoiMiIsInBzIjoiVjJSYXkgU2VydmVyIDEiLCJhZGQiOiIxLjIuMy40IiwicG9ydCI6IjEwMDg2IiwiaWQiOiIxMjM0NTY3OC0xMjM0LTEyMzQtMTIzNC0xMjM0NTY3ODkwYWIiLCJhaWQiOiIwIiwibmV0IjoidGNwIiwidHlwZSI6Im5vbmUiLCJob3N0IjoiIiwicGF0aCI6IiIsInRscyI6IiJ9
```

**Decode it to check:**
```bash
# On your computer
echo "eyJ2Ijoi..." | base64 -d
```

**Should show:**
```json
{
  "v":"2",
  "ps":"V2Ray Server 1",
  "add":"YOUR_SERVER_IP",
  "port":"10086",
  "id":"UUID-HERE",
  "aid":"0",
  "net":"tcp",
  "type":"none"
}
```

**Check:**
- ✅ `add` matches your V2Ray server IP
- ✅ `port` is `10086` (or your configured port)
- ✅ `id` is a valid UUID format

---

## 🔍 Step 8: Test V2Ray Client Configuration

### For V2RayNG (Android):

1. Open V2RayNG
2. Long press the server → **Edit**
3. Verify:
   - **Address:** Your V2Ray server IP
   - **Port:** 10086
   - **ID (UUID):** Valid UUID
   - **AlterID:** 0
   - **Network:** tcp

4. **Test connection:**
   - Tap the server
   - Click **Test Connection**
   - Should show latency (e.g., "123ms")

### For V2RayN (Windows):

1. Right-click V2RayN tray icon → **Server** → **Edit selected server**
2. Verify all settings
3. **Test Real Latency (Ping)**
4. If timeout, connection is blocked

### For Command Line Test:

```bash
# Create test config
cat > test-config.json << 'EOF'
{
  "inbounds": [{
    "port": 1080,
    "protocol": "socks",
    "settings": {"auth": "noauth"}
  }],
  "outbounds": [{
    "protocol": "vmess",
    "settings": {
      "vnext": [{
        "address": "YOUR_V2RAY_SERVER_IP",
        "port": 10086,
        "users": [{
          "id": "YOUR_UUID_HERE",
          "alterId": 0
        }]
      }]
    }
  }]
}
EOF

# Run V2Ray with test config
v2ray run -config test-config.json

# In another terminal, test connection
curl -x socks5://127.0.0.1:1080 https://ipinfo.io
```

---

## 🔍 Step 9: Common Issues & Solutions

### Issue 1: "Connection Timeout"

**Causes:**
- Firewall blocking port
- Wrong server IP
- V2Ray not running

**Fix:**
```bash
# Check all these
sudo systemctl status v2ray
sudo netstat -tulpn | grep 10086
sudo ufw status

# From outside, test port
telnet YOUR_SERVER_IP 10086
```

### Issue 2: "Connection Refused"

**Causes:**
- V2Ray not listening on that port
- Firewall blocking

**Fix:**
```bash
# Check V2Ray is running
sudo systemctl restart v2ray
sudo journalctl -u v2ray -f

# Check firewall
sudo ufw allow 10086/tcp
sudo ufw allow 10086/udp
```

### Issue 3: "Bad Request" or "Invalid Response"

**Causes:**
- Wrong UUID
- Config mismatch between server and client

**Fix:**
```bash
# Verify VMess link is correct
# Re-import the VMess link from VMaster portal
# Make sure UUID matches
```

### Issue 4: "Can Connect but No Internet"

**Causes:**
- Routing issue on V2Ray server
- DNS issue

**Fix:**
```bash
# Enable IP forwarding
sudo sysctl -w net.ipv4.ip_forward=1
echo "net.ipv4.ip_forward=1" | sudo tee -a /etc/sysctl.conf

# Check DNS in V2Ray config
# Add to outbound:
{
  "protocol": "freedom",
  "settings": {
    "domainStrategy": "UseIP"
  }
}

# Restart V2Ray
sudo systemctl restart v2ray
```

### Issue 5: "Works on Some Networks, Not Others"

**Causes:**
- Some ISPs block VPN traffic
- Port 10086 might be filtered

**Fix:**
```bash
# Change to port 443 (HTTPS) - less likely to be blocked
sudo nano /etc/v2ray/config.json
# Change "port": 10086 to "port": 443

# Allow new port
sudo ufw allow 443/tcp
sudo ufw delete allow 10086/tcp

# Restart V2Ray
sudo systemctl restart v2ray

# Update server port in VMaster portal
```

---

## 🔍 Step 10: Complete Diagnostic Script

**Run this on your V2Ray server:**

```bash
#!/bin/bash

echo "================================"
echo "V2Ray Diagnostic Script"
echo "================================"
echo ""

echo "1. V2Ray Service Status:"
sudo systemctl status v2ray --no-pager | grep -E "Active|Loaded"
echo ""

echo "2. V2Ray Port Listening:"
sudo netstat -tulpn | grep 10086 || echo "❌ Not listening on port 10086"
echo ""

echo "3. Firewall Status:"
sudo ufw status | grep -E "Status|10086" || echo "Firewall not active"
echo ""

echo "4. V2Ray Config Syntax:"
v2ray test -config /etc/v2ray/config.json && echo "✅ Config OK" || echo "❌ Config has errors"
echo ""

echo "5. Recent V2Ray Errors:"
sudo tail -10 /var/log/v2ray/error.log 2>/dev/null || echo "No error log found"
echo ""

echo "6. V2Ray Process:"
ps aux | grep v2ray | grep -v grep || echo "❌ V2Ray not running"
echo ""

echo "================================"
echo "Diagnostic Complete"
echo "================================"
```

**Save as `diagnose-v2ray.sh` and run:**
```bash
chmod +x diagnose-v2ray.sh
./diagnose-v2ray.sh
```

---

## 🎯 Most Common Fix (90% of cases)

**If nothing else works, do this:**

```bash
# 1. Stop V2Ray
sudo systemctl stop v2ray

# 2. Backup old config
sudo cp /etc/v2ray/config.json /etc/v2ray/config.json.backup

# 3. Create fresh minimal config
sudo cat > /etc/v2ray/config.json << 'EOF'
{
  "log": {
    "loglevel": "warning"
  },
  "inbounds": [{
    "port": 10086,
    "protocol": "vmess",
    "settings": {
      "clients": []
    },
    "streamSettings": {
      "network": "tcp"
    }
  }],
  "outbounds": [{
    "protocol": "freedom"
  }]
}
EOF

# 4. Open firewall
sudo ufw allow 10086/tcp
sudo ufw allow 10086/udp

# 5. Start V2Ray
sudo systemctl start v2ray
sudo systemctl status v2ray

# 6. Test port
telnet YOUR_SERVER_IP 10086
```

**Then create a new V2Ray account in VMaster portal and test!**

---

## 📞 Still Not Working?

**Share these outputs:**

```bash
# 1. V2Ray status
sudo systemctl status v2ray

# 2. Port check
sudo netstat -tulpn | grep 10086

# 3. Recent errors
sudo tail -20 /var/log/v2ray/error.log

# 4. Config file
sudo cat /etc/v2ray/config.json

# 5. Firewall status
sudo ufw status verbose
```

---

## ✅ Success Indicators

**V2Ray is working when:**
- ✅ `systemctl status v2ray` shows "active (running)"
- ✅ Port 10086 shows in `netstat` output
- ✅ `telnet YOUR_SERVER_IP 10086` connects
- ✅ Client shows connection latency (e.g., "123ms")
- ✅ Can browse internet through V2Ray

---

**Good luck! Follow these steps in order and your V2Ray will work! 🚀**

