# 🔧 SSTP & V2Ray Server Setup Guide

Complete guide for setting up and adding SSTP and V2Ray servers to VMaster.

---

## 📋 Table of Contents

1. [SSTP Server Setup](#sstp-server-setup)
2. [V2Ray Server Setup](#v2ray-server-setup)
3. [Adding Servers to VMaster](#adding-servers-to-vmaster)
4. [Testing VPN Connections](#testing-vpn-connections)
5. [Troubleshooting](#troubleshooting)

---

## 🔐 SSTP Server Setup

### What is SSTP?

**SSTP (Secure Socket Tunneling Protocol)** is a VPN protocol developed by Microsoft. It uses SSL/TLS for secure connections and works well through firewalls.

### Option 1: Using SoftEther VPN (Recommended)

SoftEther VPN is a free, open-source VPN software that supports SSTP and other protocols.

#### 1. Install SoftEther VPN Server

```bash
# On Ubuntu/Debian server
# Update system
sudo apt update && sudo apt upgrade -y

# Install dependencies
sudo apt install build-essential wget curl gcc make -y

# Download SoftEther VPN
cd /tmp
wget https://github.com/SoftEtherVPN/SoftEtherVPN_Stable/releases/download/v4.41-9787-beta/softether-vpnserver-v4.41-9787-beta-2022.11.17-linux-x64-64bit.tar.gz

# Extract
tar xzf softether-vpnserver-*.tar.gz
cd vpnserver

# Compile
make

# Install
sudo mv /tmp/vpnserver /usr/local/
cd /usr/local/vpnserver
sudo chmod 600 *
sudo chmod 700 vpnserver vpncmd
```

#### 2. Create Systemd Service

```bash
sudo nano /etc/systemd/system/vpnserver.service
```

Add this content:

```ini
[Unit]
Description=SoftEther VPN Server
After=network.target

[Service]
Type=forking
ExecStart=/usr/local/vpnserver/vpnserver start
ExecStop=/usr/local/vpnserver/vpnserver stop
Restart=on-failure

[Install]
WantedBy=multi-user.target
```

```bash
# Enable and start service
sudo systemctl enable vpnserver
sudo systemctl start vpnserver
sudo systemctl status vpnserver
```

#### 3. Configure SoftEther VPN

```bash
# Run configuration tool
/usr/local/vpnserver/vpncmd

# Select: 1 (Management of VPN Server)
# Hostname: localhost
# Port: (press Enter for default)

# Set admin password
ServerPasswordSet
# Enter new password twice

# Create Virtual Hub
HubCreate vpn_hub
# Enter password for hub

# Select the hub
Hub vpn_hub

# Enable SSTP
SstpEnable yes

# Enable SecureNAT (for internet access)
SecureNatEnable

# Create users (you can also do this via web UI)
UserCreate user1
# Set password

# Exit
exit
```

#### 4. Configure Firewall

```bash
# Allow SSTP (port 443) and VPN admin (port 5555)
sudo ufw allow 443/tcp
sudo ufw allow 5555/tcp
sudo ufw reload
```

#### 5. Get SSL Certificate (Optional but Recommended)

```bash
# Install certbot
sudo apt install certbot -y

# Get certificate
sudo certbot certonly --standalone -d vpn.yourdomain.com

# Configure SoftEther to use the certificate (via web UI or vpncmd)
```

### Option 2: Using Windows Server

If you have a Windows Server:

1. **Install RRAS (Routing and Remote Access)**
   - Server Manager → Add Roles → Network Policy and Access Services
   - Configure RRAS → Custom Configuration → VPN Access

2. **Enable SSTP**
   - RRAS Console → Server Properties → Security
   - Select SSTP, L2TP, etc.

3. **Configure Certificate**
   - Install SSL certificate on server
   - Bind to RRAS service

4. **Create VPN Users**
   - Active Directory Users and Computers
   - User Properties → Dial-in → Allow Access

---

## 🚀 V2Ray Server Setup

### What is V2Ray?

**V2Ray** is a powerful proxy tool with advanced features for bypassing censorship and network restrictions.

### Installation Steps

#### 1. Install V2Ray Server

```bash
# Official installation script
bash <(curl -L https://raw.githubusercontent.com/v2fly/fhs-install-v2ray/master/install-release.sh)

# Or manual installation
wget https://github.com/v2fly/v2ray-core/releases/latest/download/v2ray-linux-64.zip
unzip v2ray-linux-64.zip -d /usr/local/bin/

# Create directories
sudo mkdir -p /usr/local/etc/v2ray
sudo mkdir -p /var/log/v2ray
```

#### 2. Generate UUID

```bash
# Install uuidgen if not available
sudo apt install uuid-runtime -y

# Generate UUID for each user
uuidgen
# Example output: 8b7c1a2d-3e4f-5a6b-7c8d-9e0f1a2b3c4d
```

#### 3. Configure V2Ray Server

```bash
sudo nano /usr/local/etc/v2ray/config.json
```

Add this configuration:

```json
{
  "log": {
    "loglevel": "warning",
    "access": "/var/log/v2ray/access.log",
    "error": "/var/log/v2ray/error.log"
  },
  "inbounds": [
    {
      "port": 10086,
      "protocol": "vmess",
      "settings": {
        "clients": [
          {
            "id": "YOUR-UUID-HERE",
            "alterId": 64,
            "level": 1
          }
        ]
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

#### 4. Alternative: V2Ray with WebSocket + TLS

For better security and firewall bypass:

```json
{
  "inbounds": [
    {
      "port": 443,
      "protocol": "vmess",
      "settings": {
        "clients": [
          {
            "id": "YOUR-UUID-HERE",
            "alterId": 64
          }
        ]
      },
      "streamSettings": {
        "network": "ws",
        "wsSettings": {
          "path": "/v2ray"
        },
        "security": "tls",
        "tlsSettings": {
          "certificates": [
            {
              "certificateFile": "/etc/letsencrypt/live/yourdomain.com/fullchain.pem",
              "keyFile": "/etc/letsencrypt/live/yourdomain.com/privkey.pem"
            }
          ]
        }
      }
    }
  ],
  "outbounds": [
    {
      "protocol": "freedom"
    }
  ]
}
```

#### 5. Create Systemd Service

```bash
sudo nano /etc/systemd/system/v2ray.service
```

Add:

```ini
[Unit]
Description=V2Ray Service
After=network.target nss-lookup.target

[Service]
Type=simple
User=root
ExecStart=/usr/local/bin/v2ray run -config /usr/local/etc/v2ray/config.json
Restart=on-failure
RestartPreventExitStatus=23

[Install]
WantedBy=multi-user.target
```

```bash
# Enable and start
sudo systemctl enable v2ray
sudo systemctl start v2ray
sudo systemctl status v2ray
```

#### 6. Configure Firewall

```bash
# Allow V2Ray port
sudo ufw allow 10086/tcp
# Or if using WebSocket
sudo ufw allow 443/tcp
```

---

## 📝 Adding Servers to VMaster

### Adding SSTP Server

1. **Login to Admin Panel**
   ```
   http://your-vmaster.com/admin/login.php
   ```

2. **Navigate to VPN Servers → Add Server**

3. **Fill in the details:**
   ```
   Server Name: SSTP Production Server
   Server Type: sstp
   Server Host: vpn.yourdomain.com (or IP address)
   Server Port: 443
   API URL: (leave empty - not needed for SSTP)
   Max Accounts: 100
   Status: Active
   Description: SoftEther SSTP server for secure connections
   ```

4. **Click "Add Server"**

### Adding V2Ray Server

1. **Login to Admin Panel**

2. **Navigate to VPN Servers → Add Server**

3. **Fill in the details:**
   ```
   Server Name: V2Ray HK Node
   Server Type: v2ray
   Server Host: v2ray.yourdomain.com (or IP address)
   Server Port: 10086 (or your configured port)
   API URL: (leave empty - V2Ray doesn't have HTTP API by default)
   Max Accounts: 50
   Status: Active
   Description: V2Ray VMess protocol server
   ```

4. **Click "Add Server"**

### How VMaster Generates Credentials

#### For SSTP:
When you create a VPN account for SSTP, VMaster generates:
- **Username**: Unique username (e.g., `vpn_user_123`)
- **Password**: Random secure password
- **Server**: Your SSTP server address
- **Connection String**: `sstp://username:password@server:port`

**Manual Setup Required**: You must manually create the user in SoftEther:
```bash
/usr/local/vpnserver/vpncmd
Hub vpn_hub
UserCreate vpn_user_123
UserPasswordSet vpn_user_123
# Enter the password shown in VMaster
```

#### For V2Ray:
When you create a VPN account for V2Ray, VMaster generates:
- **UUID**: Random UUID for the user
- **Configuration**: VMess config JSON

**Manual Setup Required**: Add the UUID to V2Ray config:
```bash
sudo nano /usr/local/etc/v2ray/config.json
```

Add the UUID to the clients array:
```json
{
  "id": "uuid-from-vmaster",
  "alterId": 64,
  "level": 1
}
```

Then restart V2Ray:
```bash
sudo systemctl restart v2ray
```

---

## 🧪 Testing VPN Connections

### Testing SSTP

**Windows Client:**
1. Settings → Network & Internet → VPN → Add VPN
2. VPN Provider: Windows (built-in)
3. Connection name: My SSTP VPN
4. Server: vpn.yourdomain.com
5. VPN type: Secure Socket Tunneling Protocol (SSTP)
6. Username & Password: From VMaster

**Linux Client (using NetworkManager):**
```bash
sudo apt install network-manager-sstp
# Then configure via GUI
```

### Testing V2Ray

**Desktop Client:**
1. Download V2RayN (Windows) or V2RayX (Mac)
2. Import VMess configuration from VMaster
3. Connect

**Mobile:**
1. Download V2RayNG (Android) or Shadowrocket (iOS)
2. Scan QR code or import configuration
3. Connect

---

## 🔍 Troubleshooting

### SSTP Issues

**Cannot Connect:**
```bash
# Check if SoftEther is running
sudo systemctl status vpnserver

# Check logs
tail -f /usr/local/vpnserver/server_log/*.log

# Check firewall
sudo ufw status

# Test port
telnet vpn.yourdomain.com 443
```

**SSL Certificate Errors:**
- Ensure you have a valid SSL certificate
- Use Let's Encrypt for free certificates
- Configure SoftEther to use the certificate

### V2Ray Issues

**Cannot Connect:**
```bash
# Check V2Ray status
sudo systemctl status v2ray

# Check logs
tail -f /var/log/v2ray/error.log

# Test configuration
/usr/local/bin/v2ray test -config /usr/local/etc/v2ray/config.json

# Check if port is listening
netstat -tulpn | grep 10086
```

**UUID Mismatch:**
- Ensure the UUID in client matches server config
- Restart V2Ray after config changes

---

## 📊 Comparison: SSTP vs V2Ray vs Outline

| Feature | SSTP | V2Ray | Outline |
|---------|------|-------|---------|
| **Ease of Setup** | Medium | Medium-Hard | Easy |
| **Windows Support** | Native | Client App | Client App |
| **Mobile Support** | Good | Excellent | Excellent |
| **Firewall Bypass** | Good (uses 443) | Excellent | Excellent |
| **Speed** | Fast | Fast | Very Fast |
| **Management API** | Manual | Manual | Automatic |
| **Best For** | Windows users | Advanced users | Everyone |

---

## 🎯 Recommendations

### For Beginners:
1. **Start with Outline** - Easiest to set up and manage
2. Then try SSTP if you have Windows clients

### For Advanced Users:
1. **V2Ray** - Most flexible and powerful
2. Great for bypassing censorship

### For Corporate:
1. **SSTP** - Native Windows support
2. Good integration with Active Directory

---

## 🚀 Quick Start Summary

### SSTP:
```bash
# 1. Install SoftEther
wget softether && tar xzf && make && install

# 2. Configure
vpncmd → create hub → enable SSTP → create users

# 3. Add to VMaster
Admin → VPN Servers → Add SSTP Server

# 4. Create accounts and manually add users to SoftEther
```

### V2Ray:
```bash
# 1. Install V2Ray
bash <(curl -L v2ray-install.sh)

# 2. Configure
nano /usr/local/etc/v2ray/config.json

# 3. Add to VMaster
Admin → VPN Servers → Add V2Ray Server

# 4. Create accounts and manually add UUIDs to config
```

---

## 📞 Support Resources

### SSTP (SoftEther):
- Documentation: https://www.softether.org/
- Forum: https://www.vpnusers.com/

### V2Ray:
- Documentation: https://www.v2ray.com/
- GitHub: https://github.com/v2fly/v2ray-core
- Community: https://t.me/v2ray_en

---

**🎉 You're now ready to set up SSTP and V2Ray servers!**

For Outline setup, see `OUTLINE_SERVER_SETUP.md`.

