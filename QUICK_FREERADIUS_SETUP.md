# 🚀 Quick FreeRADIUS Docker Setup

Simple steps to get FreeRADIUS running in Docker.

---

## ⚡ **One-Command Setup**

SSH to your VMaster server and run:

```bash
cd /var/www/vmaster
git pull origin main
sudo bash scripts/setup-freeradius-docker.sh
```

**That's it!** The script will:
1. ✅ Pull latest code
2. ✅ Create FreeRADIUS config files
3. ✅ Ask for your SSTP server IP
4. ✅ Add SSTP server to RADIUS database
5. ✅ Start FreeRADIUS container
6. ✅ Test authentication

**Time:** ~3 minutes

---

## 📋 **What You'll Be Asked:**

```
SSTP Server IP address: [Enter your SSTP server IP]
RADIUS Shared Secret [default: testing123]: [Press Enter or type new secret]
```

---

## ✅ **After Setup:**

You'll see:
```
✅ FreeRADIUS Docker Setup Complete!

RADIUS Server IP:      YOUR_VMASTER_IP
RADIUS Auth Port:      1812
RADIUS Acct Port:      1813
Shared Secret:         testing123

Next Steps: Configure Your SSTP Server
```

---

## 🔧 **Then Configure SSTP Server:**

SSH to your SSTP server:

```bash
sudo nano /etc/accel-ppp.conf
```

Add:

```ini
[modules]
radius

[radius]
server=YOUR_VMASTER_IP,testing123,auth-port=1812,acct-port=1813
nas-identifier=sstp-server
nas-ip-address=YOUR_SSTP_SERVER_IP
```

Restart SSTP:

```bash
sudo systemctl restart accel-ppp
```

---

## ✅ **Verify It Works:**

```bash
# On VMaster server
docker ps | grep freeradius

# Should show:
# vmaster_freeradius   Up X minutes   0.0.0.0:1812-1813->1812-1813/udp
```

---

## 🎉 **Done!**

Now you can:
1. Add SSTP servers in VMaster with RADIUS checkbox
2. Create VPN accounts
3. Users authenticate via RADIUS automatically!

---

## 🆘 **If Container Keeps Restarting:**

Run the fix script:

```bash
cd /var/www/vmaster
git pull origin main
sudo bash scripts/fix-freeradius-docker.sh
```

This will diagnose and fix the issue automatically.

---

## 📚 **Full Documentation:**

- **Docker Guide:** `FREERADIUS_DOCKER_GUIDE.md`
- **Troubleshooting:** `FREERADIUS_TROUBLESHOOTING.md`
- **GUI Management:** `RADIUS_GUI_GUIDE.md`
- **Safety Info:** `SAFETY_GUARANTEE.md`
