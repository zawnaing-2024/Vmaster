# 🔧 MikroTik RADIUS Setup Guide

Complete guide to configure MikroTik to use RADIUS authentication.

---

## ✅ **Step-by-Step MikroTik Configuration:**

### **Step 1: Add RADIUS Server**

Connect to MikroTik via Winbox or SSH and run:

```
/radius
add address=52.77.246.57 secret=One@2025 service=ppp timeout=3000ms
```

**Replace:**
- `52.77.246.57` → Your VMaster public IP
- `One@2025` → Your RADIUS shared secret

---

### **Step 2: Enable RADIUS for PPP**

```
/ppp aaa
set use-radius=yes
```

---

### **Step 3: Configure SSTP Server**

```
/interface sstp-server server
set enabled=yes default-profile=default authentication=mschap2
```

---

### **Step 4: Create PPP Profile**

```
/ppp profile
add name=vpn-profile local-address=10.10.10.1 remote-address=vpn-pool use-encryption=yes
```

---

### **Step 5: Create IP Pool**

```
/ip pool
add name=vpn-pool ranges=10.10.10.10-10.10.10.100
```

---

### **Step 6: Configure Firewall**

```
/ip firewall filter
add chain=input action=accept protocol=tcp dst-port=443 comment="Allow SSTP"
add chain=input action=accept protocol=gre comment="Allow GRE"
```

---

### **Step 7: Enable NAT (if needed)**

```
/ip firewall nat
add chain=srcnat action=masquerade out-interface=ether1 comment="VPN NAT"
```

---

## ✅ **Verify Configuration:**

### **Check RADIUS settings:**

```
/radius print detail
```

**Should show:**
```
address: 52.77.246.57
secret: One@2025
service: ppp
timeout: 3s
```

### **Check PPP AAA:**

```
/ppp aaa print
```

**Should show:**
```
use-radius: yes
```

### **Check SSTP server:**

```
/interface sstp-server server print
```

**Should show:**
```
enabled: yes
authentication: mschap2
```

---

## 🔍 **Test RADIUS Connection from MikroTik:**

### **Method 1: Check RADIUS status**

```
/radius monitor 0
```

**Should show:**
```
status: ok
```

### **Method 2: Test with radclient (if available)**

On MikroTik:

```
/tool fetch url="http://52.77.246.57:1812" mode=udp
```

---

## 🐛 **Troubleshooting:**

### **Issue: "RADIUS server not responding"**

**Cause:** Firewall blocking

**Fix on VMaster:**
```bash
sudo ufw allow from 203.86.109.50 to any port 1812 proto udp
sudo ufw allow from 203.86.109.50 to any port 1813 proto udp
```

**Fix on MikroTik:**
```
/ip firewall filter
add chain=output action=accept protocol=udp dst-port=1812-1813 comment="Allow RADIUS"
```

---

### **Issue: "Authentication failed"**

**Check MikroTik logs:**
```
/log print where topics~"ppp"
```

**Check RADIUS logs on VMaster:**
```bash
sudo tail -f /var/log/freeradius/radius.log | grep 203.86.109.50
```

---

### **Issue: "No IP address assigned"**

**Fix:** Configure IP pool and profile:

```
/ip pool
add name=vpn-pool ranges=10.10.10.10-10.10.10.100

/ppp profile
set default local-address=10.10.10.1 remote-address=vpn-pool
```

---

### **Issue: "Connected but no internet"**

**Fix:** Enable NAT:

```
/ip firewall nat
add chain=srcnat action=masquerade out-interface=ether1
```

---

## 📋 **Complete MikroTik Configuration Script:**

Copy and paste this entire block into MikroTik terminal:

```
# Add RADIUS server
/radius add address=52.77.246.57 secret=One@2025 service=ppp timeout=3000ms

# Enable RADIUS for PPP
/ppp aaa set use-radius=yes

# Create IP pool
/ip pool add name=vpn-pool ranges=10.10.10.10-10.10.10.100

# Create PPP profile
/ppp profile add name=vpn-profile local-address=10.10.10.1 remote-address=vpn-pool use-encryption=yes

# Enable SSTP server
/interface sstp-server server set enabled=yes default-profile=vpn-profile authentication=mschap2 certificate=none

# Allow SSTP traffic
/ip firewall filter add chain=input action=accept protocol=tcp dst-port=443 place-before=0 comment="Allow SSTP VPN"

# Enable NAT for VPN clients
/ip firewall nat add chain=srcnat action=masquerade out-interface=ether1 comment="VPN NAT"

# Print configuration
/radius print
/ppp aaa print
/interface sstp-server server print
```

**Replace:**
- `52.77.246.57` → Your VMaster IP
- `One@2025` → Your RADIUS secret
- `ether1` → Your WAN interface name

---

## ✅ **After Configuration:**

1. **Restart PPP service:**
   ```
   /ppp restart
   ```

2. **Try connecting** from VPN client

3. **Check MikroTik logs:**
   ```
   /log print where topics~"radius"
   ```

---

## 🎯 **Expected Result:**

```
/log print where topics~"radius"
radius,info user testuser authenticated successfully
```

---

**Configure MikroTik with the script above and try connecting!** 🚀

