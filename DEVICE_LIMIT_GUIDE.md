# 📱 VPN Device Limit Guide

## Understanding Device Limits for VPN Accounts

### 🎯 Your Requirement:
**"One VPN account = One device only"**

Example:
- John has 2 Outline keys → Can use on 2 devices (1 key per device)
- John has 3 SSTP accounts → Can use on 3 devices (1 account per device)
- ❌ John cannot use 1 VPN account on multiple devices simultaneously

---

## 📋 How It Works by VPN Type

### 1️⃣ **Outline VPN** 🔵

**Default Behavior:**
- ✅ One access key = One device at a time (by default)
- ❌ Cannot share one key across multiple devices simultaneously
- Each key is unique and tied to one connection

**How to Enforce:**
```bash
# Outline Server Settings
# By default, Outline limits concurrent connections per key

# To check current settings:
docker exec outline-server cat /opt/outline/access.txt

# Each access key (ss://...) can only be used by one device at a time
```

**VMaster CMS:**
- ✅ Each VPN account = One unique Outline key
- ✅ Automatically enforced by Outline server
- ✅ No additional configuration needed

---

### 2️⃣ **SSTP VPN** 🟢

**Default Behavior:**
- ⚠️ By default, SSTP allows multiple concurrent connections with same username/password
- Need to configure server to limit concurrent sessions

**How to Enforce (Using accel-ppp):**

Edit `/etc/accel-ppp.conf`:

```ini
[common]
single-session=replace

[ppp]
# Limit to 1 session per user
max-starting=1
max-sessions=1

[radius]
# If using RADIUS, add this
nas-identifier=your-server-name
```

**Explanation:**
- `single-session=replace` → If user logs in from another device, disconnect the first one
- `max-sessions=1` → Only allow 1 active session per username

**Restart accel-ppp:**
```bash
systemctl restart accel-ppp
```

**VMaster CMS:**
- ✅ Each VPN account = Unique username/password
- ⚠️ Need to configure accel-ppp server (one-time setup)
- ✅ After configuration, automatically enforced

---

### 3️⃣ **V2Ray VPN** 🟣

**Default Behavior:**
- ⚠️ By default, V2Ray allows multiple devices with same UUID
- Need to configure connection limits

**How to Enforce:**

Edit `/etc/v2ray/config.json`:

```json
{
  "inbounds": [{
    "port": 10086,
    "protocol": "vmess",
    "settings": {
      "clients": [{
        "id": "uuid-here",
        "alterId": 0
      }]
    },
    "streamSettings": {
      "network": "tcp"
    }
  }],
  "policy": {
    "levels": {
      "0": {
        "connIdle": 300,
        "downlinkOnly": 0,
        "handshake": 4,
        "uplinkOnly": 0,
        "statsUserUplink": true,
        "statsUserDownlink": true
      }
    },
    "system": {
      "statsInboundUplink": true,
      "statsInboundDownlink": true
    }
  }
}
```

**Using X-UI Panel:**
1. Go to Inbound settings
2. Enable "Limit IP" → Set to `1`
3. This limits each UUID to 1 concurrent IP address

**VMaster CMS:**
- ✅ Each VPN account = Unique UUID
- ⚠️ Need to configure V2Ray/X-UI (one-time setup)
- ✅ After configuration, automatically enforced

---

## 🔧 Implementation Steps

### Step 1: Configure Your VPN Servers (One-Time Setup)

#### For Outline:
```bash
# Already enforced by default ✅
# No configuration needed
```

#### For SSTP (accel-ppp):
```bash
# SSH to SSTP server
ssh root@sstp-server-ip

# Edit config
nano /etc/accel-ppp.conf

# Add these lines under [common] section:
single-session=replace

# Under [ppp] section:
max-sessions=1

# Save and restart
systemctl restart accel-ppp

# Verify
systemctl status accel-ppp
```

#### For V2Ray (X-UI Panel):
```bash
# Option 1: Via X-UI Web Panel
1. Login to X-UI: http://your-server:54321
2. Go to "Inbounds"
3. Edit your inbound
4. Find "Limit IP" setting
5. Set to: 1
6. Save

# Option 2: Via Command Line
ssh root@v2ray-server-ip
docker exec x-ui x-ui setting -limitip 1
```

---

### Step 2: Test the Limits

#### Test Outline:
```
1. Create VPN account in VMaster
2. Get Outline access key
3. Connect device 1 → ✅ Works
4. Try to connect device 2 with same key → ❌ Fails or disconnects device 1
```

#### Test SSTP:
```
1. Create VPN account in VMaster
2. Get username/password
3. Connect device 1 → ✅ Works
4. Try to connect device 2 with same credentials → ❌ Device 1 disconnects
```

#### Test V2Ray:
```
1. Create VPN account in VMaster
2. Get VMess link
3. Connect device 1 → ✅ Works
4. Try to connect device 2 with same UUID → ❌ Fails or disconnects device 1
```

---

## 📊 Summary Table

| VPN Type | Default Behavior | Configuration Needed | Enforcement |
|----------|------------------|---------------------|-------------|
| **Outline** | ✅ 1 device per key | ❌ None | Automatic |
| **SSTP** | ⚠️ Multiple devices | ✅ accel-ppp config | After config |
| **V2Ray** | ⚠️ Multiple devices | ✅ X-UI limit IP | After config |

---

## 🎯 What VMaster CMS Does

### ✅ Already Handled:
1. **Unique Credentials** - Each VPN account gets unique credentials
2. **Account Management** - Easy creation/deletion of accounts
3. **User Tracking** - Know which client has which account

### ⚠️ Server-Side Configuration Required:
1. **SSTP Servers** - Configure `single-session=replace` in accel-ppp
2. **V2Ray Servers** - Configure "Limit IP = 1" in X-UI panel
3. **Outline Servers** - Already enforced by default ✅

---

## 📝 Configuration Scripts

### Quick Setup for SSTP Server:

```bash
#!/bin/bash
# Run this on your SSTP server

# Backup config
cp /etc/accel-ppp.conf /etc/accel-ppp.conf.backup

# Add single-session setting
sed -i '/\[common\]/a single-session=replace' /etc/accel-ppp.conf

# Add max-sessions setting
sed -i '/\[ppp\]/a max-sessions=1' /etc/accel-ppp.conf

# Restart service
systemctl restart accel-ppp

echo "✅ SSTP server now limits 1 device per account"
```

### Quick Setup for V2Ray (X-UI):

```bash
#!/bin/bash
# Run this on your V2Ray server with X-UI

# Set limit IP via X-UI API
curl -X POST "http://localhost:54321/panel/api/inbounds/update" \
  -H "Content-Type: application/json" \
  -d '{"limitIp": 1}'

echo "✅ V2Ray now limits 1 device per UUID"
```

---

## 🚨 Important Notes

### 1. **VMaster CMS Role:**
- VMaster creates **unique credentials** for each account
- VMaster **cannot** enforce device limits (this is server-side)
- VMaster shows which accounts exist and who owns them

### 2. **Server-Side Enforcement:**
- Device limits are enforced by the **VPN servers themselves**
- You must configure each VPN server **once**
- After configuration, limits apply to all accounts automatically

### 3. **User Experience:**
- When user tries to connect from 2nd device:
  - **Outline**: Connection fails or times out
  - **SSTP**: First device disconnects, 2nd device connects
  - **V2Ray**: Connection fails or first device disconnects

---

## ✅ Recommended Setup

### For New Deployments:

1. **Outline Servers**: ✅ Use as-is (already enforced)

2. **SSTP Servers**: 
   ```bash
   # Add to /etc/accel-ppp.conf
   [common]
   single-session=replace
   
   [ppp]
   max-sessions=1
   ```

3. **V2Ray Servers**:
   - Set "Limit IP = 1" in X-UI panel
   - Or use connection limit in V2Ray config

### For Existing Deployments:

1. SSH to each VPN server
2. Apply configuration (see scripts above)
3. Restart VPN service
4. Test with 2 devices
5. Verify only 1 device can connect

---

## 🎉 Final Result

After configuration:

✅ **John has 2 Outline accounts** → Can use 2 devices (1 per account)  
✅ **John has 3 SSTP accounts** → Can use 3 devices (1 per account)  
✅ **John has 1 V2Ray account** → Can use 1 device only  

❌ **John cannot use 1 account on 2 devices simultaneously**

---

## 📞 Need Help?

If you need help configuring your VPN servers:

1. **Outline**: Already works ✅
2. **SSTP**: Run the script above on your SSTP server
3. **V2Ray**: Set "Limit IP = 1" in X-UI panel

**All device limits are enforced by the VPN servers, not by VMaster CMS.**

---

**Last Updated**: October 9, 2025  
**Version**: 1.0

